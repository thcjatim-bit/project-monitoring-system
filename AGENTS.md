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

`docs/agents/context-budget.md` is the binding contract for how much work one session takes on. The implementation workflow below sits in phase 3 and phase 4 of that contract: a session may close phase 3 and carry the same issue through review and deployment, but it does not also plan or specify new work, and it takes one issue at a time. Before a session ends, its decisions, commit SHAs, and next step belong in the issue.

## Implementation workflow

For every `/implement` ticket:

1. Read the ticket, specification, ADRs, `CONTEXT.md`, and applicable repository instructions.
2. Identify required testing seams and implement using the repository TDD workflow.
3. Run available local checks.
4. Synchronize and verify the implementation on `pms-dev`.
5. Run PostgreSQL integration tests on `pms-dev`, fix failures, and repeat until required checks are green.
6. Run code review and commit the completed work.

Do not complete a backend or database ticket while required remote integration tests are failing.

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

A ticket affecting the running application is complete only when implementation, required tests, development integration verification, code review, and a commit all exist; when deployment is requested, production deployment and health verification must also succeed. For `/implement` tickets where production deployment is expected, complete the full workflow without pausing between implementation, testing, and deployment.

The permanent `/implement` workflow is:

```text
implement
→ TDD
→ pms-dev verification
→ full tests
→ code review
→ commit
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
