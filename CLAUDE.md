# project-monitoring-system

## Communication

Be concise. Lead with the result, blocker, or decision.

When I need to make a decision:
- Give me the viable options.
- Briefly state the trade-off of each.
- Recommend one option and why.
- Then stop and let me decide.

Do not give long summaries of routine work.
Do not invent options when there is no meaningful decision.

## Session context budget

Every session in this repo — interactive, autopilot worker, or wayfinder — is bound by
`docs/agents/context-budget.md`. Read it before starting work. It defines the phase
boundary rule (one session crosses at most one phase boundary), the read-unit quota that
backs it up, tool output discipline, and what a session must make durable before it ends.

## Agent skills

### Issue tracker

Issues live in GitHub Issues for `thcjatim-bit/project-monitoring-system`, managed via the `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels

The default five-role vocabulary — each label string equal to its role name. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: `CONTEXT.md` and `docs/adr/` at the repo root. See `docs/agents/domain.md`.

### Issue autopilot

When dispatching or working on a scheduled GitHub issue, read `docs/agents/autopilot.md`; it defines eligibility, context boundaries, worker gates, and recovery.
