# Issue Autopilot

This document is the durable contract for the scheduled issue dispatcher and its
single-ticket implementation workers. The dispatcher is implemented by
`scripts/issue-autopilot.mjs`.

## Queue contract

An issue is eligible only when all of these are true:

- it is open and has the `ready-for-agent` label;
- it has no assignee;
- it is not labelled `autopilot:in-progress` or `autopilot:blocked`;
- every issue in its native or written `Blocked by` edges is closed.

The dispatcher claims one issue before starting a worker. It processes the lowest
issue number first and uses one fresh Paseo worktree per issue.

## Worker contract

The worker must:

1. read the complete issue and comments, then read `AGENTS.md`, `CLAUDE.md`,
   `CONTEXT.md`, and the relevant ADRs;
2. verify that the issue has a durable TDD handoff, then work only on the
   claimed issue with `/implement`;
3. run local checks and the required `pms-dev` integration checks;
4. commit the verified implementation and comment on the issue with the changed
   files, checks, full commit SHA, and the next `/code-review` step;
5. stop after the implementation handoff. A fresh review/release session owns
   `/code-review`, push, deployment, and production smoke verification;
6. leave the issue open until the review/release session confirms every
   acceptance criterion and repository gate is green.

If a business decision, credential, security decision, destructive migration,
or repeated verification failure blocks the work, the worker leaves the issue
open, adds `autopilot:blocked`, and comments with the evidence and the exact
decision needed.

## Failure channel

A dispatch tick that throws is reported into the issue tracker, because `gh`
still works in the environments where `paseo` does not, and the tracker is a
surface a human already watches.

- One sticky issue titled `Autopilot dispatch is failing`, labelled
  `autopilot:dispatch-failed`. It has no `ready-for-agent` label, so the queue
  can never select it.
- Repeated ticks of the same breakage comment on that issue at most once every
  six hours; a changed failure signature comments immediately.
- The first tick that completes closes the issue. So an **open**
  `autopilot:dispatch-failed` issue means dispatch is broken right now, and no
  such issue means the last tick got through.
- Reporting is best-effort and never masks the original error: the dispatcher
  still exits non-zero with the failure on stderr.

## Health check

`npm run autopilot:dry-run` is the health check. It selects a candidate without
claiming or dispatching anything, but it does make the same `paseo ls --json`
call that dispatch depends on, so an environment that cannot execute `paseo`
fails the dry run instead of passing it (issue #144).

`paseo` is resolved by `scripts/portable-spawn.mjs`, not by a bare spawn: it
searches PATH, then `~/.local/bin` and the bundled Paseo CLI directory, and it
runs a `.cmd`/`.bat` shim through `cmd.exe`. Set `AUTOPILOT_PASEO_BIN` to an
absolute path to override the search. Because a `.cmd` shim silently drops
double quotes and truncates at a newline, every argument handed to `paseo` must
be single-line and quote-free; the worker prompt is normalised by `cliSafeText`
and the shim layer throws rather than shipping a mangled argument.

A scheduled run reporting `succeeded` only means the agent finished, not that
dispatch worked. Check the dispatcher's own output, not the schedule status.

## Context hygiene

The rules are in `docs/agents/context-budget.md`; the worker follows them as written.
Autopilot-specific: a worker receives one issue, one worktree, and one fresh context,
and never depends on a previous conversation — the issue and its comments are the only
inbound state. A worker that hits the read-unit quota finishes its issue comment and
implementation commit, then stops rather than starting another unit of work; the issue
stays open with the review handoff recorded, and the next session continues from that
state.

## Recovery

An issue with `autopilot:in-progress` is intentionally excluded from the queue.
After an interrupted worker, inspect its Paseo log and issue comment before
removing the label and assignee. Requeue only after confirming that no worker is
still modifying the worktree.
