import test from "node:test";
import assert from "node:assert/strict";

import {
    BLOCKED_LABEL,
    IN_PROGRESS_LABEL,
    READY_LABEL,
    isEligibleIssue,
    parseBlockedBy,
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
