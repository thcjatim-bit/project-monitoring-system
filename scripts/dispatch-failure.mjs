import { createHash } from "node:crypto";

import { jsonFrom } from "./gh.mjs";

/**
 * The autopilot dispatcher's failure channel.
 *
 * A dispatch tick that throws has to become visible somewhere a human already
 * looks. The issue tracker is that surface: `gh` still works in the environments
 * where `paseo` does not, and a schedule run reporting `succeeded` only means
 * the agent finished, not that dispatch worked (issue #144).
 *
 * The channel is sticky — one open issue per incident, closed by the first tick
 * that gets through — so an open `autopilot:dispatch-failed` issue means
 * dispatch is broken right now.
 */

export const DISPATCH_FAILURE_LABEL = "autopilot:dispatch-failed";
export const DISPATCH_FAILURE_TITLE = "Autopilot dispatch is failing";
export const FAILURE_REPEAT_MS = 6 * 60 * 60 * 1000;
const FAILURE_MARKER = "autopilot-dispatch-failure";

/**
 * A stable, human-readable identity for a dispatch failure, so repeated ticks of
 * the same breakage are recognised as one incident instead of 45 of them.
 * Volatile detail (absolute paths, PIDs, timestamps) is collapsed.
 */
export function failureSignature(error) {
    return String(error?.message ?? error ?? "unknown failure")
        .split(/\r?\n/)[0]
        .replace(/[A-Za-z]:\\[^\s"']+|\/(?:[\w.-]+\/)+[\w.-]+/g, "<path>")
        .replace(/\b\d{3,}\b/g, "<n>")
        .replace(/\s+/g, " ")
        .trim()
        .slice(0, 200);
}

function failureFingerprint(signature) {
    return createHash("sha1").update(signature).digest("hex").slice(0, 12);
}

export function failureCommentBody(signature, occurredAt) {
    return [
        `<!-- ${FAILURE_MARKER}:${failureFingerprint(signature)} -->`,
        "The scheduled autopilot dispatcher could not complete a tick.",
        "",
        `- First seen this report: ${occurredAt}`,
        "- Health check: `npm run autopilot:dry-run` (exercises the same `paseo` call as dispatch)",
        "",
        "```text",
        signature,
        "```",
        "",
        "A schedule run reporting `succeeded` only means the agent finished, not that dispatch worked.",
    ].join("\n");
}

/**
 * Reports the first occurrence, a change of signature, or a repeat that is at
 * least `FAILURE_REPEAT_MS` old. Everything else is the same incident still
 * burning, and stays quiet.
 */
export function shouldReportFailure(lastComment, signature, now = Date.now()) {
    if (!lastComment) {
        return true;
    }

    if (!String(lastComment.body ?? "").includes(`${FAILURE_MARKER}:${failureFingerprint(signature)}`)) {
        return true;
    }

    const age = now - Date.parse(lastComment.createdAt ?? "");

    return !Number.isFinite(age) || age >= FAILURE_REPEAT_MS;
}

/**
 * Says on stderr that the tracker could not be reached, so the one case this
 * channel cannot cover — `gh` broken as well — is at least stated rather than
 * looking like a quiet success.
 */
function trackerUnreachable(attempt) {
    console.error(
        `Could not ${attempt}: the issue tracker is unreachable. `
        + "The dispatch failure is on stderr and the exit code is non-zero.",
    );
}

function announce(result, attempt) {
    if (result.status !== 0) {
        trackerUnreachable(attempt);
    }
}

function ensureFailureLabel(gh) {
    // No `--force`: the label may already exist with a description this repo
    // owns, and re-creating it would silently rewrite that.
    gh.call([
        "label", "create", DISPATCH_FAILURE_LABEL,
        "--color", "B60205",
        "--description", "Autopilot dispatcher could not complete a tick",
    ]);
}

/**
 * Looks for the open sticky issue.
 *
 * A query that fails tells us nothing about whether an incident is already
 * open, so "no issue" and "could not ask" are different answers: reading the
 * second as the first would open a fresh issue on every tick that a transient
 * `gh` failure coincides with a dispatch failure.
 */
function findFailureIssue(gh) {
    const result = gh.call([
        "issue", "list",
        "--label", DISPATCH_FAILURE_LABEL,
        "--state", "open",
        "--limit", "1",
        "--json", "number",
    ]);

    if (result.status !== 0) {
        return { known: false, number: null };
    }

    return { known: true, number: jsonFrom(result, [])[0]?.number ?? null };
}

/**
 * The most recent report on the sticky issue, if it can be read.
 *
 * Same rule as `findFailureIssue`: a query that failed is not "there is no
 * earlier report". Reading it that way would post a duplicate comment on every
 * tick, because an absent report always warrants one.
 */
function lastFailureComment(gh, issueNumber) {
    const result = gh.call([
        "issue", "view", String(issueNumber),
        "--json", "comments",
        "--jq", `[.comments[] | select(.body | contains("${FAILURE_MARKER}"))] | last | {createdAt, body}`,
    ]);

    if (result.status !== 0) {
        return { known: false, comment: null };
    }

    return { known: true, comment: jsonFrom(result, null) };
}

/**
 * Makes a dispatch failure visible in the issue tracker. Never throws: the
 * caller is already carrying the real error.
 */
export function reportDispatchFailure(gh, error, options = {}) {
    const now = options.now ?? new Date();

    try {
        const signature = failureSignature(error);
        const body = failureCommentBody(signature, now.toISOString());
        const existing = findFailureIssue(gh);

        if (!existing.known) {
            trackerUnreachable("check for an open dispatch failure issue");

            return;
        }

        if (existing.number === null) {
            ensureFailureLabel(gh);
            announce(gh.call([
                "issue", "create",
                "--title", DISPATCH_FAILURE_TITLE,
                "--label", DISPATCH_FAILURE_LABEL,
                "--body", body,
            ]), "open the dispatch failure issue");

            return;
        }

        const last = lastFailureComment(gh, existing.number);

        if (!last.known) {
            trackerUnreachable("read the open dispatch failure issue");

            return;
        }

        if (shouldReportFailure(last.comment, signature, now.getTime())) {
            announce(
                gh.call(["issue", "comment", String(existing.number), "--body", body]),
                "comment on the dispatch failure issue",
            );
        }
    } catch (reportingError) {
        console.error(`Could not report the dispatch failure: ${reportingError.message}`);
    }
}

/** Closes the sticky failure issue once a tick completes, so an open issue means "still broken". */
export function resolveDispatchFailure(gh) {
    try {
        const existing = findFailureIssue(gh);

        if (!existing.known) {
            trackerUnreachable("check whether a dispatch failure issue is still open");

            return;
        }

        if (existing.number === null) {
            return;
        }

        announce(gh.call([
            "issue", "close", String(existing.number),
            "--comment", "Autopilot dispatch completed a tick again. Closing this incident.",
        ]), "close the dispatch failure issue");
    } catch (reportingError) {
        console.error(`Could not close the dispatch failure issue: ${reportingError.message}`);
    }
}
