# Issue Autopilot

This document is the durable contract for the scheduled issue dispatcher and its
single-ticket workers. The dispatcher is implemented by
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
2. work only on the claimed issue and use `/implement`, including TDD where the
   repository workflow calls for it;
3. run local checks and the required `pms-dev` integration checks;
4. run `/code-review`, commit the verified change, and follow `AGENTS.md` for
   push, deployment, and production smoke verification;
5. comment on the issue with the changed files, checks, and full commit SHA;
6. close the issue only after every acceptance criterion and repository gate is
   green.

If a business decision, credential, security decision, destructive migration,
or repeated verification failure blocks the work, the worker leaves the issue
open, adds `autopilot:blocked`, and comments with the evidence and the exact
decision needed.

## Context hygiene

The rules are in `docs/agents/context-budget.md`; the worker follows them as written.
Autopilot-specific: a worker receives one issue, one worktree, and one fresh context,
and never depends on a previous conversation — the issue and its comments are the only
inbound state. A worker that hits the read-unit quota finishes its issue comment and
commit, then stops rather than starting another unit of work; the issue stays open with
what is left recorded, and is requeued through Recovery below.

## Recovery

An issue with `autopilot:in-progress` is intentionally excluded from the queue.
After an interrupted worker, inspect its Paseo log and issue comment before
removing the label and assignee. Requeue only after confirming that no worker is
still modifying the worktree.
