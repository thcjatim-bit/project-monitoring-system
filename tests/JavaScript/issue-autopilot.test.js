import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";

import {
    BLOCKED_LABEL,
    IN_PROGRESS_LABEL,
    READY_LABEL,
    commandResult,
    HANDOFF_MARKER,
    hasTddHandoff,
    isEligibleIssue,
    parseBlockedBy,
    selectCandidate,
    workerPrompt,
} from "../../scripts/issue-autopilot.mjs";
import {
    DISPATCH_FAILURE_LABEL,
    DISPATCH_FAILURE_TITLE,
    FAILURE_REPEAT_MS,
    failureCommentBody,
    failureSignature,
    reportDispatchFailure,
    resolveDispatchFailure,
    shouldReportFailure,
} from "../../scripts/dispatch-failure.mjs";
import { ghClient } from "../../scripts/gh.mjs";
import { resolveExecutable, windowsShimCommandLine } from "../../scripts/portable-spawn.mjs";

const issue = (overrides = {}) => ({
    number: 10,
    title: "Example issue",
    state: "OPEN",
    labels: [{ name: READY_LABEL }],
    assignees: [],
    body: "",
    comments: [{ body: "<!-- tdd-handoff --> Handoff." }],
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

// --- #182: seleksi antrean menagih handoff TDD yang kontrak worker wajibkan ---
// Kontrak antrean dan kontrak worker sempat tidak sinkron: seleksi memilih issue
// yang gerbang worker butir 2 tidak mungkin diloloskan, jadi tiap tick membakar
// satu worker dan satu worktree untuk berhenti di baris yang sama (#167, #170).
test("a ready issue without a TDD handoff is not eligible, however clean its labels are", () => {
    assert.equal(isEligibleIssue(issue({ comments: [] })), false);
    assert.equal(isEligibleIssue(issue({ comments: undefined })), false);
    assert.equal(
        isEligibleIssue(issue({ comments: [{ body: "Sudah saya triase, silakan dikerjakan." }] })),
        false,
    );
});

test("the handoff marker counts wherever it is written, comment or body", () => {
    assert.equal(hasTddHandoff(issue({ comments: [{ body: `${HANDOFF_MARKER}\nSeam: ...` }] })), true);
    assert.equal(hasTddHandoff(issue({ comments: [], body: `${HANDOFF_MARKER}\nSeam: ...` })), true);
    assert.equal(hasTddHandoff(issue({ comments: [{ body: "Handoff TDD menyusul." }] })), false);
});

test("selection skips an otherwise-lowest issue that has no handoff", () => {
    const withHandoff = { comments: [{ body: HANDOFF_MARKER }] };
    const selected = selectCandidate([
        issue({ number: 11, comments: [] }),
        issue({ number: 12, ...withHandoff }),
    ]);

    assert.equal(selected.number, 12);
});

// Penanda yang dicocokkan di mana saja membuat komentar yang sekadar *menyebut* penandanya --
// termasuk komentar yang menjelaskan aturan ini -- terbaca sebagai handoff. Ditemukan saat
// komentar koreksi label di #167 membuat tiket itu tampak sudah punya handoff.
test("merely mentioning the marker in prose is not a handoff", () => {
    const mention = "Seleksi kini menagih penanda `<!-- tdd-handoff -->` sebagai syarat kelima.";

    assert.equal(hasTddHandoff(issue({ comments: [{ body: mention }] })), false);
    assert.equal(isEligibleIssue(issue({ comments: [{ body: mention }] })), false);
});

test("a handoff is a comment that opens with the marker", () => {
    assert.equal(hasTddHandoff(issue({ comments: [{ body: `${HANDOFF_MARKER}\n## Handoff TDD` }] })), true);
    assert.equal(hasTddHandoff(issue({ comments: [{ body: `\n  ${HANDOFF_MARKER}\nSeam: ...` }] })), true);
});

// --- Regression: the dispatcher must be able to execute `paseo` (issue #144) ---
// The scheduled agent died on `spawnSync paseo ENOENT` every tick: on Windows a
// CLI installed as a `.cmd` shim is unreachable through a bare spawn, and the
// scheduled agent's PATH does not match the interactive one.

const tempDir = (t, prefix) => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), prefix));

    t.after(() => fs.rmSync(directory, { recursive: true, force: true }));

    return directory;
};

const shimDir = (t) => {
    const directory = tempDir(t, "pms-autopilot-shim-");
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

const emptyHome = (t) => tempDir(t, "pms-autopilot-home-");

// The failure was Windows-only, so this pins the resolution rule itself rather
// than the host platform: on a non-Windows runner the branch that broke would
// otherwise never be entered.
test("resolves a .cmd shim through PATHEXT, which a bare spawn does not", (t) => {
    const directory = tempDir(t, "pms-autopilot-pathext-");
    // PATHEXT is upper-case, and so is the name written here: on Windows the
    // two match case-insensitively, and a case-sensitive runner can still run
    // this test with the platform injected.
    const shim = path.join(directory, "pms-fake-paseo.CMD");

    fs.writeFileSync(shim, "@echo off\r\n");

    const env = { PATH: directory, Path: directory, PATHEXT: ".COM;.EXE;.BAT;.CMD" };

    assert.equal(
        resolveExecutable("pms-fake-paseo", { env, platform: "win32", envPrefix: "AUTOPILOT" }),
        path.resolve(shim),
    );
    // Same PATH, no PATHEXT expansion: this is the ENOENT the dispatcher hit.
    assert.equal(resolveExecutable("pms-fake-paseo", { env, platform: "linux", envPrefix: "AUTOPILOT" }), null);
});

// Paseo's installer can land per-machine in Program Files instead of per-user
// under LOCALAPPDATA. Only the per-user path was searched, so a per-machine
// install was reported as "is Paseo installed?" while Paseo was installed.
test("finds a per-machine Paseo install under Program Files", (t) => {
    const programFiles = tempDir(t, "pms-autopilot-programfiles-");
    const home = emptyHome(t);
    const bin = path.join(programFiles, "Paseo", "resources", "bin");

    fs.mkdirSync(bin, { recursive: true });

    const shim = path.join(bin, "paseo.cmd");

    fs.writeFileSync(shim, "@echo off\r\n");

    const env = {
        PATH: "",
        Path: "",
        PATHEXT: ".COM;.EXE;.BAT;.CMD",
        USERPROFILE: home,
        LOCALAPPDATA: home,
        PROGRAMFILES: programFiles,
    };

    // PATHEXT is upper-case, so the resolved candidate carries the extension in
    // that case while the installed file is lower-case. On Windows the two are
    // the same file; the comparison, not the resolution, is what must be loose.
    assert.equal(
        resolveExecutable("paseo", { env, platform: "win32", envPrefix: "AUTOPILOT" }).toLowerCase(),
        path.resolve(shim).toLowerCase(),
    );
});

test("an override pointing at a directory is not mistaken for an executable", (t) => {
    const directory = tempDir(t, "pms-autopilot-override-");
    const env = { PATH: "", Path: "", AUTOPILOT_PMS_FAKE_PASEO_BIN: directory };

    assert.equal(resolveExecutable("pms-fake-paseo", { env, envPrefix: "AUTOPILOT" }), null);
});

test("executes a CLI installed as a Windows .cmd shim", (t) => {
    const { directory } = shimDir(t);
    const env = { ...process.env, PATH: directory, Path: directory };

    const result = commandResult("pms-fake-paseo", ["ls", "--json"], { env });

    assert.equal(result.status, 0);
    assert.deepEqual(JSON.parse(result.stdout), [{ status: "running" }]);
});

test("finds the CLI through AUTOPILOT_<CMD>_BIN when it is absent from PATH", (t) => {
    const { file } = shimDir(t);
    const home = emptyHome(t);
    const env = {
        ...process.env,
        PATH: "",
        Path: "",
        USERPROFILE: home,
        LOCALAPPDATA: home,
        AUTOPILOT_PMS_FAKE_PASEO_BIN: file,
    };

    assert.equal(
        resolveExecutable("pms-fake-paseo", { env, envPrefix: "AUTOPILOT" }),
        path.resolve(file),
    );
    assert.equal(commandResult("pms-fake-paseo", ["ls"], { env }).status, 0);
});

test("fails with a diagnosable message, not ENOENT, when paseo is not executable", (t) => {
    const home = emptyHome(t);
    // PROGRAMFILES is neutralised alongside the per-user paths: this host may
    // genuinely have Paseo installed per-machine, and the point here is the
    // message when it is installed nowhere the search looks.
    const env = {
        ...process.env,
        PATH: "",
        Path: "",
        USERPROFILE: home,
        LOCALAPPDATA: home,
        PROGRAMFILES: home,
    };

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

    // cmd.exe expands %VAR% before the shim ever sees the argument, and a
    // caret cannot suppress that, so the prompt carries no percent sign.
    assert.doesNotMatch(prompt, /%/);
    assert.match(prompt, /issue #144/);
    assert.doesNotThrow(() => windowsShimCommandLine("paseo.cmd", ["run", "--title", "PMS autopilot worker #144", prompt]));
});

// --- Regression: a failing dispatch must be visible without reading paseo logs (issue #144) ---

test("collapses volatile detail so repeated ticks of one breakage share a signature", () => {
    const windows = failureSignature(new Error("spawnSync paseo ENOENT\n" + String.raw`    at C:\repo\scripts\issue-autopilot.mjs:13:20`));
    const posix = failureSignature(new Error("spawnSync paseo ENOENT\n    at /home/pms/scripts/issue-autopilot.mjs:13:20"));

    assert.equal(windows, "spawnSync paseo ENOENT");
    assert.equal(posix, "spawnSync paseo ENOENT");
    assert.equal(
        failureSignature(new Error("worker 12345 died")),
        failureSignature(new Error("worker 98765 died")),
    );

    // A signature keeps only the first line, so a path *there* is what would
    // otherwise split one breakage into one incident per tick. Windows paths
    // count: that is the platform the dispatcher fails on.
    assert.equal(
        failureSignature(new Error(String.raw`gh failed: C:\Users\a\repo\x.mjs is missing`)),
        "gh failed: <path> is missing",
    );
    assert.equal(
        failureSignature(new Error("gh failed: /home/a/repo/x.mjs is missing")),
        "gh failed: <path> is missing",
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

// `responses` maps the first two `gh` arguments to either stdout or a
// `{ status, stdout }` pair, so a test can make one call fail.
const recordingGh = (responses = {}) => {
    const calls = [];
    const run = (command, args) => {
        calls.push({ command, args });
        const key = args.slice(0, 2).join(" ");
        const response = responses[key] ?? "";
        const { status = 0, stdout = "" } = typeof response === "string" ? { stdout: response } : response;

        return { status, stdout, stderr: "" };
    };

    return {
        calls,
        client: ghClient({ run, repo: "owner/repo", cwd: "." }),
        argsFor: (key) => calls.find((call) => call.args.slice(0, 2).join(" ") === key)?.args ?? null,
    };
};

test("a dispatch failure opens one sticky issue in the tracker", () => {
    const gh = recordingGh({ "issue list": "[]" });

    reportDispatchFailure(gh.client, new Error("spawnSync paseo ENOENT"));

    const created = gh.argsFor("issue create");

    assert.ok(created, "expected the dispatcher to create a sticky failure issue");
    assert.equal(created[created.indexOf("--title") + 1], DISPATCH_FAILURE_TITLE);
    assert.equal(created[created.indexOf("--label") + 1], DISPATCH_FAILURE_LABEL);
    assert.match(created[created.indexOf("--body") + 1], /spawnSync paseo ENOENT/);
    assert.equal(gh.argsFor("issue comment"), null);
});

test("a repeated failure comments on the existing issue instead of opening another", () => {
    const gh = recordingGh({ "issue list": '[{"number":200}]', "issue view": "null" });

    reportDispatchFailure(gh.client, new Error("spawnSync paseo ENOENT"));

    assert.equal(gh.argsFor("issue create"), null);
    assert.deepEqual(gh.argsFor("issue comment")?.slice(0, 3), ["issue", "comment", "200"]);
});

// A tracker query that fails says nothing about whether an incident is already
// open. Treating it as "none open" is what would turn 45 identical broken ticks
// into 45 issues — the exact shape of the breakage in #144.
test("a failed tracker query neither opens a duplicate issue nor closes an open one", () => {
    const unreachable = { status: 1, stdout: "" };
    const reporting = recordingGh({ "issue list": unreachable });

    reportDispatchFailure(reporting.client, new Error("spawnSync paseo ENOENT"));

    assert.equal(reporting.argsFor("issue create"), null);
    assert.equal(reporting.argsFor("issue comment"), null);

    const resolving = recordingGh({ "issue list": unreachable });

    resolveDispatchFailure(resolving.client);

    assert.equal(resolving.argsFor("issue close"), null);
});

// Same rule one level down: "cannot read the issue" is not "the issue carries
// no earlier report", and an absent report always warrants a comment.
test("a failed read of the sticky issue does not post a duplicate comment", () => {
    const gh = recordingGh({
        "issue list": '[{"number":200}]',
        "issue view": { status: 1, stdout: "" },
    });

    reportDispatchFailure(gh.client, new Error("spawnSync paseo ENOENT"));

    assert.equal(gh.argsFor("issue comment"), null);
    assert.equal(gh.argsFor("issue create"), null);
});

test("the sticky label is created without overwriting an existing one", () => {
    const gh = recordingGh({ "issue list": "[]" });

    reportDispatchFailure(gh.client, new Error("spawnSync paseo ENOENT"));

    const label = gh.argsFor("label create");

    assert.ok(label, "expected the dispatcher to ensure the label exists");
    assert.ok(!label.includes("--force"), "--force rewrites a label the repo already owns");
});

test("a recovered tick closes the sticky issue, so an open one means still broken", () => {
    const gh = recordingGh({ "issue list": '[{"number":200}]' });

    resolveDispatchFailure(gh.client);

    assert.deepEqual(gh.argsFor("issue close")?.slice(0, 3), ["issue", "close", "200"]);
});

test("reporting never masks the failure it is reporting", () => {
    const exploding = ghClient({
        run: () => {
            throw new Error("gh is gone too");
        },
        repo: "owner/repo",
        cwd: ".",
    });

    assert.doesNotThrow(() => reportDispatchFailure(exploding, new Error("spawnSync paseo ENOENT")));
    assert.doesNotThrow(() => resolveDispatchFailure(exploding));
});
