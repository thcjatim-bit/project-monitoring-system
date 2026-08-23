import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";

import {
    BLOCKED_LABEL,
    DISPATCH_FAILURE_LABEL,
    DISPATCH_FAILURE_TITLE,
    FAILURE_REPEAT_MS,
    IN_PROGRESS_LABEL,
    READY_LABEL,
    commandResult,
    failureCommentBody,
    failureSignature,
    isEligibleIssue,
    parseBlockedBy,
    reportDispatchFailure,
    resolveDispatchFailure,
    selectCandidate,
    shouldReportFailure,
    workerPrompt,
} from "../../scripts/issue-autopilot.mjs";
import { resolveExecutable, windowsShimCommandLine } from "../../scripts/portable-spawn.mjs";

const issue = (overrides = {}) => ({
    number: 10,
    title: "Example issue",
    state: "OPEN",
    labels: [{ name: READY_LABEL }],
    assignees: [],
    body: "",
    ...overrides,
});

test("parses written blocker edges without treating unrelated issue references as blockers", () => {
    const body = [
        "## Parent",
        "Part of #99.",
        "",
        "## Blocked by",
        "- #12",
        "- #13",
        "",
        "## Acceptance criteria",
        "- The link to #99 remains visible",
    ].join("\n");

    assert.deepEqual(parseBlockedBy(body), [12, 13]);
});

test("only an open, unassigned, ready issue is eligible", () => {
    assert.equal(isEligibleIssue(issue()), true);
    assert.equal(isEligibleIssue(issue({ assignees: [{ login: "someone" }] })), false);
    assert.equal(isEligibleIssue(issue({ labels: [{ name: IN_PROGRESS_LABEL }] })), false);
    assert.equal(isEligibleIssue(issue({ labels: [{ name: BLOCKED_LABEL }] })), false);
    assert.equal(isEligibleIssue(issue({ state: "CLOSED" })), false);
});

test("selects the lowest unblocked issue and fails closed for unknown blocker state", () => {
    const selected = selectCandidate([
        issue({ number: 11, blockedBy: [{ number: 3, state: "OPEN" }] }),
        issue({ number: 12, blockedBy: [{ number: 4, state: "UNKNOWN" }] }),
        issue({ number: 13, blockedBy: [{ number: 5, state: "CLOSED" }] }),
    ]);

    assert.equal(selected.number, 13);
});

// --- Regression: the dispatcher must be able to execute `paseo` (issue #144) ---
// The scheduled agent died on `spawnSync paseo ENOENT` every tick: on Windows a
// CLI installed as a `.cmd` shim is unreachable through a bare spawn, and the
// scheduled agent's PATH does not match the interactive one.

const shimDir = () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), "pms-autopilot-shim-"));
    const isWindows = process.platform === "win32";
    const file = path.join(directory, isWindows ? "pms-fake-paseo.cmd" : "pms-fake-paseo");

    fs.writeFileSync(
        file,
        isWindows
            ? "@echo off\r\necho [{\"status\":\"running\"}]\r\n"
            : "#!/bin/sh\necho '[{\"status\":\"running\"}]'\n",
        { mode: 0o755 },
    );

    return { directory, file };
};

const emptyHome = () => fs.mkdtempSync(path.join(os.tmpdir(), "pms-autopilot-home-"));

test("executes a CLI installed as a Windows .cmd shim", () => {
    const { directory } = shimDir();
    const env = { ...process.env, PATH: directory, Path: directory };

    const result = commandResult("pms-fake-paseo", ["ls", "--json"], { env });

    assert.equal(result.status, 0);
    assert.deepEqual(JSON.parse(result.stdout), [{ status: "running" }]);
});

test("finds the CLI through AUTOPILOT_<CMD>_BIN when it is absent from PATH", () => {
    const { file } = shimDir();
    const home = emptyHome();
    const env = {
        ...process.env,
        PATH: "",
        Path: "",
        USERPROFILE: home,
        LOCALAPPDATA: home,
        AUTOPILOT_PMS_FAKE_PASEO_BIN: file,
    };

    assert.equal(resolveExecutable("pms-fake-paseo", { env }), path.resolve(file));
    assert.equal(commandResult("pms-fake-paseo", ["ls"], { env }).status, 0);
});

test("fails with a diagnosable message, not ENOENT, when paseo is not executable", () => {
    const home = emptyHome();
    const env = { ...process.env, PATH: "", Path: "", USERPROFILE: home, LOCALAPPDATA: home };

    assert.throws(
        () => commandResult("paseo", ["ls", "--json"], { env }),
        (error) => {
            assert.doesNotMatch(error.message, /ENOENT/);
            assert.match(error.message, /paseo is not executable/);
            assert.match(error.message, /AUTOPILOT_PASEO_BIN/);

            return true;
        },
    );
});

test("refuses to ship an argument the Windows shim would silently mangle", () => {
    assert.throws(
        () => windowsShimCommandLine("paseo.cmd", ["run", "line one\nline two"]),
        /multi-line argument/,
    );
    assert.throws(
        () => windowsShimCommandLine("paseo.cmd", ["run", 'a "quoted" word']),
        /double quote/,
    );
    assert.doesNotThrow(
        () => windowsShimCommandLine("paseo.cmd", ["run", "--label", "autopilot=pms", "100% & <fine>"]),
    );
});

test("the worker prompt survives the Windows shim even with a hostile issue title", () => {
    const prompt = workerPrompt("owner/repo", {
        number: 144,
        title: 'Dispatcher "autopilot" gagal\ntiap tik & 100% of ticks',
    });

    assert.doesNotMatch(prompt, /["\r\n]/);
    assert.match(prompt, /issue #144/);
    assert.doesNotThrow(() => windowsShimCommandLine("paseo.cmd", ["run", "--title", "PMS autopilot worker #144", prompt]));
});

// --- Regression: a failing dispatch must be visible without reading paseo logs (issue #144) ---

test("collapses volatile detail so repeated ticks of one breakage share a signature", () => {
    const windows = failureSignature(new Error("spawnSync paseo ENOENT\n    at C:\repo\scripts\issue-autopilot.mjs:13:20"));
    const posix = failureSignature(new Error("spawnSync paseo ENOENT\n    at /home/pms/scripts/issue-autopilot.mjs:13:20"));

    assert.equal(windows, "spawnSync paseo ENOENT");
    assert.equal(posix, "spawnSync paseo ENOENT");
    assert.equal(
        failureSignature(new Error("worker 12345 died")),
        failureSignature(new Error("worker 98765 died")),
    );
});

test("reports a new incident once, then stays quiet until the repeat window elapses", () => {
    const signature = failureSignature(new Error("spawnSync paseo ENOENT"));
    const now = Date.parse("2026-08-23T12:00:00Z");
    const comment = (agoMs, body) => ({
        createdAt: new Date(now - agoMs).toISOString(),
        body,
    });
    const sameIncident = failureCommentBody(signature, "2026-08-23T00:00:00Z");

    assert.equal(shouldReportFailure(null, signature, now), true);
    assert.equal(shouldReportFailure(comment(60_000, sameIncident), signature, now), false);
    assert.equal(shouldReportFailure(comment(FAILURE_REPEAT_MS + 1, sameIncident), signature, now), true);
    assert.equal(
        shouldReportFailure(comment(60_000, failureCommentBody("gh: not found", "2026-08-23T00:00:00Z")), signature, now),
        true,
    );
});

test("the failure report names the health check that catches this class of failure", () => {
    const body = failureCommentBody(failureSignature(new Error("spawnSync paseo ENOENT")), "2026-08-23T12:00:00Z");

    assert.match(body, /autopilot:dry-run/);
    assert.match(body, /spawnSync paseo ENOENT/);
    assert.doesNotMatch(body, /-->[\s\S]*-->/);
});

const recordingGh = (responses = {}) => {
    const calls = [];
    const run = (command, args) => {
        calls.push({ command, args });
        const key = args.slice(0, 2).join(" ");
        const stdout = responses[key] ?? "";

        return { status: 0, stdout, stderr: "" };
    };

    return { calls, run, argsFor: (key) => calls.find((call) => call.args.slice(0, 2).join(" ") === key)?.args ?? null };
};

test("a dispatch failure opens one sticky issue in the tracker", () => {
    const gh = recordingGh({ "issue list": "[]" });

    reportDispatchFailure("owner/repo", ".", new Error("spawnSync paseo ENOENT"), { run: gh.run });

    const created = gh.argsFor("issue create");

    assert.ok(created, "expected the dispatcher to create a sticky failure issue");
    assert.equal(created[created.indexOf("--title") + 1], DISPATCH_FAILURE_TITLE);
    assert.equal(created[created.indexOf("--label") + 1], DISPATCH_FAILURE_LABEL);
    assert.match(created[created.indexOf("--body") + 1], /spawnSync paseo ENOENT/);
    assert.equal(gh.argsFor("issue comment"), null);
});

test("a repeated failure comments on the existing issue instead of opening another", () => {
    const gh = recordingGh({ "issue list": '[{"number":200}]', "issue view": "null" });

    reportDispatchFailure("owner/repo", ".", new Error("spawnSync paseo ENOENT"), { run: gh.run });

    assert.equal(gh.argsFor("issue create"), null);
    assert.deepEqual(gh.argsFor("issue comment")?.slice(0, 3), ["issue", "comment", "200"]);
});

test("a recovered tick closes the sticky issue, so an open one means still broken", () => {
    const gh = recordingGh({ "issue list": '[{"number":200}]' });

    resolveDispatchFailure("owner/repo", ".", { run: gh.run });

    assert.deepEqual(gh.argsFor("issue close")?.slice(0, 3), ["issue", "close", "200"]);
});

test("reporting never masks the failure it is reporting", () => {
    const exploding = () => {
        throw new Error("gh is gone too");
    };

    assert.doesNotThrow(() => reportDispatchFailure("owner/repo", ".", new Error("spawnSync paseo ENOENT"), { run: exploding }));
    assert.doesNotThrow(() => resolveDispatchFailure("owner/repo", ".", { run: exploding }));
});
