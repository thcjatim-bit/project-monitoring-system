import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";

import {
    BLOCKED_LABEL,
    IN_PROGRESS_LABEL,
    READY_LABEL,
    isEligibleIssue,
    parseBlockedBy,
    sampleActiveWorkers,
    selectCandidate,
} from "../../scripts/issue-autopilot.mjs";

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

test("samples active workers into JSONL with their latest usage", () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), "autopilot-usage-"));
    const logPath = path.join(directory, "context-usage.jsonl");

    sampleActiveWorkers("/repo", {
        logPath,
        now: () => "2026-08-23T01:30:00.000Z",
        run: (command, args) => {
            if (command === "paseo" && args[0] === "ls") {
                return {
                    status: 0,
                    stdout: JSON.stringify([{
                        id: "agent-143",
                        name: "PMS autopilot worker #143",
                        status: "running",
                    }]),
                };
            }

            return {
                status: 0,
                stdout: JSON.stringify({
                    status: "running",
                    LastUsage: {
                        InputTokens: 17209,
                        OutputTokens: 186,
                        CachedTokens: 9984,
                    },
                }),
            };
        },
    });

    assert.deepEqual(fs.readFileSync(logPath, "utf8").trim().split("\n").map(JSON.parse), [{
        ts: "2026-08-23T01:30:00.000Z",
        issue: 143,
        agentId: "agent-143",
        status: "running",
        inputTokens: 17209,
        outputTokens: 186,
        cachedTokens: 9984,
    }]);
});

test("records null usage before the worker's first model call", () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), "autopilot-usage-"));
    const logPath = path.join(directory, "context-usage.jsonl");

    sampleActiveWorkers("/repo", {
        logPath,
        run: (command, args) => args[0] === "ls"
            ? {
                status: 0,
                stdout: JSON.stringify([{
                    id: "agent-143",
                    name: "PMS autopilot worker #143",
                    status: "starting",
                }]),
            }
            : { status: 0, stdout: JSON.stringify({ status: "starting" }) },
    });

    const [sample] = fs.readFileSync(logPath, "utf8").trim().split("\n").map(JSON.parse);
    assert.equal(sample.inputTokens, null);
    assert.equal(sample.outputTokens, null);
    assert.equal(sample.cachedTokens, null);
});

test("does not fail when inspecting a worker fails", () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), "autopilot-usage-"));
    const logPath = path.join(directory, "context-usage.jsonl");

    assert.doesNotThrow(() => sampleActiveWorkers("/repo", {
        logPath,
        run: (command, args) => args[0] === "ls"
            ? {
                status: 0,
                stdout: JSON.stringify([{
                    id: "agent-143",
                    name: "PMS autopilot worker #143",
                    status: "running",
                }]),
            }
            : { status: 1, stdout: "", stderr: "paseo unavailable" },
    }));
});

test("continues sampling other workers after one inspection fails", () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), "autopilot-usage-"));
    const logPath = path.join(directory, "context-usage.jsonl");

    sampleActiveWorkers("/repo", {
        logPath,
        run: (command, args) => {
            if (args[0] === "ls") {
                return {
                    status: 0,
                    stdout: JSON.stringify([
                        { id: "agent-143", name: "PMS autopilot worker #143", status: "running" },
                        { id: "agent-144", name: "PMS autopilot worker #144", status: "running" },
                    ]),
                };
            }

            if (args[1] === "agent-143") {
                throw new Error("worker disappeared");
            }

            return {
                status: 0,
                stdout: JSON.stringify({ status: "running", LastUsage: { InputTokens: 1 } }),
            };
        },
    });

    const [sample] = fs.readFileSync(logPath, "utf8").trim().split("\n").map(JSON.parse);
    assert.equal(sample.agentId, "agent-144");
});

test("does not write a sample when no workers are active", () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), "autopilot-usage-"));
    const logPath = path.join(directory, "context-usage.jsonl");

    sampleActiveWorkers("/repo", {
        logPath,
        run: () => ({ status: 0, stdout: "[]" }),
    });

    assert.equal(fs.existsSync(logPath), false);
});
