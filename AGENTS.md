# Autonomous Development and Production Operations

## Environments

The project has two logical environments:

- Development and integration: `pms-dev`
- Production: `pms-prod`

Use SSH directly. The Windows workstation is the primary source-editing environment. PostgreSQL does not need to run locally on Windows.

## Development server

`pms-dev` is authoritative for PHP runtime verification, PostgreSQL integration testing, Laravel integration tests, migrations against development/testing databases, service integration, and infrastructure verification.

Run required server commands directly, for example:

```sh
ssh pms-dev "<command>"
```

Do not ask the user to execute ordinary development-server commands.

## Infrastructure management

The Linux user has restricted, non-interactive sudo through:

```sh
sudo -n /usr/local/sbin/pms-install <profile>
```

Inspect available profiles with:

```sh
ssh pms-dev "sudo -n /usr/local/sbin/pms-install"
```

When required project infrastructure is missing, inspect the current state, identify an approved `pms-install` profile, install it when appropriate, verify the version, restart or reload the relevant service when necessary, and verify its health. Do not use arbitrary sudo commands or modify sudoers, SSH authentication, firewall rules, users, or OS networking.

## Database safety

Development, testing, and production databases are separate. Tests must use the dedicated testing database; never run automated tests with production credentials.

Never run `migrate:fresh` or `db:wipe` against production. Never intentionally use `RefreshDatabase` with production credentials or automatically roll back a production schema. Production migrations are forward migrations through the approved deployment workflow; prefer backward-compatible, additive migrations.

## Session context budget

`docs/agents/context-budget.md` is the binding contract for how much work one session takes on. TDD, implementation, and review/release are separate phases and separate sessions. A session takes one issue at a time and ends by recording its decisions, evidence, commit SHA, and next step in the issue.

## TDD workflow

For every `/tdd` ticket:

1. Read the ticket, specification, ADRs, `CONTEXT.md`, and applicable repository instructions.
2. Identify the required testing seams and write the focused tests or test contract before production implementation.
3. Run the focused tests and record whether they are red because the requested behavior is not implemented, or green because the behavior already exists.
4. Record the test files, commands, result, and the next `/implement` step in the issue. Commit the test-first change when it is useful as the handoff fixed point.

End the TDD session after the test handoff is durable. Do not implement production behavior or run `/code-review` in the same session.

## Implementation workflow

For every `/implement` ticket:

1. Read the ticket and comments, including the durable TDD handoff, then read the specification, ADRs, `CONTEXT.md`, and applicable repository instructions.
2. Implement the requested behavior against the agreed test seams.
3. Run available local checks and the focused tests.
4. Synchronize and verify the implementation on `pms-dev`.
5. Run PostgreSQL integration tests and the required full checks on `pms-dev`, fix failures, and repeat until the required checks are green.
6. Commit the verified implementation and record the changed files, checks, full Git SHA, and next `/code-review` step in the issue.

Do not complete a backend or database ticket while required remote integration tests are failing.

End the implementation session after the verified implementation commit and durable issue handoff. Do not run `/tdd` or `/code-review` in the same session.

## Code review and release workflow

For every `/code-review` handoff:

1. Read the issue, TDD handoff, implementation evidence, ADRs, and the exact commit or merge-base being reviewed.
2. Review the change against the specification and repository standards, then run the review checks required by the repository.
3. If findings require code changes, record them in the issue and return to a fresh `/implement` session. Do not silently fix implementation findings inside the review session.
4. If the review is clean, record the review result and proceed with the push and production deployment gates below when deployment is requested.

The review session is the release gate; it must not review an uncommitted working tree when a verified implementation commit is available.

## Production deployment

Deploy autonomously once required verification succeeds. Production source must come from a Git commit already verified in development; never edit application source manually in the production directory.

Before deployment, ensure the implementation is committed, the working tree is clean, required tests and PostgreSQL integration tests are green, code review is complete, the commit is pushed to the configured upstream branch, and the exact full Git SHA is known.

Deploy the verified SHA only:

```sh
ssh pms-prod "sudo -n /usr/local/sbin/pms-deploy deploy <FULL_GIT_SHA>"
```

Do not deploy a branch name.

## Production verification and rollback

After every deployment, run:

```sh
ssh pms-prod "sudo -n /usr/local/sbin/pms-deploy status"
```

Verify the deployed commit, Laravel application, queue worker, scheduler, health endpoint, and relevant logs if a problem is detected. Investigate failures autonomously and correct application or configuration issues through source control, development verification, a new commit, and a new deployment.

Code rollback is available through:

```sh
ssh pms-prod "sudo -n /usr/local/sbin/pms-deploy rollback-code"
```

This does not roll back database migrations. Where database changes have reached production, prefer a forward-fix migration.

## Production rules

Production is never an experimentation environment. Do not run tests with production credentials, run `migrate:fresh` or `db:wipe`, manually alter production source code, modify SSH or sudo policy, disable security controls, or store passwords, private keys, tokens, or production secrets in Git.

Perform application infrastructure changes only through approved automation such as `sudo -n /usr/local/sbin/pms-install <profile>`.

## Autonomous behavior and completion

For ordinary development, testing, deployment, service restart, and server-verification work, act directly rather than asking the user to run commands. On command failure, inspect the error and environment, make an appropriate non-destructive correction, retest, and continue until success or a genuine external blocker.

A ticket affecting the running application is complete only when the TDD handoff, implementation, required tests, development integration verification, verified implementation commit, and clean code review all exist; when deployment is requested, production deployment and health verification must also succeed. `/implement` ends at its commit and review handoff. `/code-review` is the next session and owns the review/release gate.

The permanent `/implement` workflow is:

```text
/tdd (fresh session)
→ test contract / focused tests
→ issue handoff
→ /implement (fresh session)
→ implementation
→ pms-dev verification
→ full tests
→ verified commit
→ issue handoff
→ /code-review (fresh session)
→ review
→ push
→ pms-prod exact-SHA deploy
→ deploythc.web.id smoke test
```

## Testing environment drift recovery

If PostgreSQL integration or the full Laravel suite fails because the dedicated
testing environment has drifted, a fresh worker must repair it autonomously.
Do not ask the user to restore the testing database manually. From the
development server, run `bash scripts/bootstrap-testing.sh`, verify the
`pms_app` database and role fidelity, rerun the focused test, then the
PostgreSQL integration/security tests and full suite.

The bootstrap reaches PostgreSQL two ways. `PMS_TESTING_PROVIDER=docker` is the
default and the `pms-dev` path. On a workstation that runs PostgreSQL directly
on the host, set `PMS_TESTING_PROVIDER=native` and supply
`PMS_SUPERUSER_PASSWORD` (plus `PMS_PSQL` when `psql` is not on PATH); the
native path only ever targets a loopback server. Refusals for both providers
are covered offline by `bash scripts/bootstrap-testing.test.sh`.

The bootstrap is allowed to recreate only the explicitly named disposable
testing database `project_monitoring_system_testing`. It must refuse production
environments and production-looking targets. Human escalation is appropriate
only when SSH, the approved sudo/infrastructure authority, or database
authority is genuinely unavailable.
