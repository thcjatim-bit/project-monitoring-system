import { spawnSync } from "node:child_process";
import { appendFileSync, mkdirSync } from "node:fs";
import { fileURLToPath, pathToFileURL } from "node:url";
import path from "node:path";

export const READY_LABEL = "ready-for-agent";
export const IN_PROGRESS_LABEL = "autopilot:in-progress";
export const BLOCKED_LABEL = "autopilot:blocked";
export const WORKER_TITLE_PREFIX = "PMS autopilot worker";

const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");

function commandResult(command, args, options = {}) {
    const result = spawnSync(command, args, {
        cwd: options.cwd ?? REPO_ROOT,
        encoding: "utf8",
        windowsHide: true,
    });

    if (result.error && !options.allowFailure) {
        throw result.error;
    }

    const stdout = (result.stdout ?? "").trim();
    const stderr = (result.stderr ?? "").trim();

    if (result.status !== 0 && !options.allowFailure) {
        throw new Error(`${command} ${args.join(" ")} failed: ${stderr || stdout}`);
    }

    return { status: result.status ?? 1, stdout, stderr };
}

function jsonFrom(result, fallback) {
    if (!result.stdout) {
        return fallback;
    }

    try {
        return JSON.parse(result.stdout);
    } catch {
        return fallback;
    }
}

function labelNames(issue) {
    return new Set((issue.labels ?? []).map((label) =>
        typeof label === "string" ? label : label.name,
    ));
}

export function parseBlockedBy(body = "") {
    const text = String(body);
    const section = text.match(
        /(?:^|\n)#{1,6}\s*Blocked by\s*:?\s*\n([\s\S]*?)(?=\n#{1,6}\s|$)/i,
    )?.[1] ?? text
        .split(/\r?\n/)
        .find((line) => /^\s*Blocked by\s*:/i.test(line)) ?? "";

    return [...section.matchAll(/#(\d+)/g)]
        .map((match) => Number(match[1]))
        .filter((number, index, numbers) => Number.isInteger(number) && numbers.indexOf(number) === index);
}

export function isEligibleIssue(issue) {
    const labels = labelNames(issue);
    const state = String(issue.state ?? "").toUpperCase();

    return state === "OPEN"
        && labels.has(READY_LABEL)
        && !labels.has(IN_PROGRESS_LABEL)
        && !labels.has(BLOCKED_LABEL)
        && (issue.assignees ?? []).length === 0;
}

export function selectCandidate(issues) {
    return [...issues]
        .filter(isEligibleIssue)
        .sort((left, right) => Number(left.number) - Number(right.number))
        .find((issue) => (issue.blockedBy ?? []).every((blocker) =>
            String(blocker.state).toUpperCase() === "CLOSED",
        ));
}

function parseArgs(argv, env = process.env) {
    const options = {
        mode: "dry-run",
        maxActive: Number(env.AUTOPILOT_MAX_ACTIVE ?? 1),
    };

    for (const argument of argv) {
        if (argument === "--dispatch") {
            options.mode = "dispatch";
        } else if (argument === "--dry-run") {
            options.mode = "dry-run";
        } else if (argument.startsWith("--max-active=")) {
            options.maxActive = Number(argument.slice("--max-active=".length));
        }
    }

    if (!Number.isInteger(options.maxActive) || options.maxActive < 1) {
        throw new Error("--max-active must be a positive integer");
    }

    return options;
}

function resolveRepository(cwd) {
    return process.env.AUTOPILOT_REPO ?? commandResult(
        "gh",
        ["repo", "view", "--json", "nameWithOwner", "--jq", ".nameWithOwner"],
        { cwd },
    ).stdout;
}

function loadOpenIssues(repo, cwd) {
    return jsonFrom(commandResult(
        "gh",
        [
            "issue",
            "list",
            "--repo",
            repo,
            "--state",
            "open",
            "--label",
            READY_LABEL,
            "--limit",
            "100",
            "--json",
            "number,title,body,labels,assignees,state,url",
        ],
        { cwd },
    ), []);
}

function nativeBlockers(repo, issueNumber, cwd) {
    const result = commandResult(
        "gh",
        ["api", `repos/${repo}/issues/${issueNumber}/dependencies/blocked_by`],
        { cwd, allowFailure: true },
    );

    if (result.status !== 0) {
        return [];
    }

    return jsonFrom(result, [])
        .map((issue) => Number(issue.number))
        .filter(Number.isInteger);
}

function blockerState(repo, issueNumber, cwd) {
    const result = commandResult(
        "gh",
        ["issue", "view", String(issueNumber), "--repo", repo, "--json", "state", "--jq", ".state"],
        { cwd, allowFailure: true },
    );

    return result.status === 0 ? result.stdout : "UNKNOWN";
}

function enrichBlockers(issue, repo, cwd) {
    const written = parseBlockedBy(issue.body);
    const native = nativeBlockers(repo, issue.number, cwd);
    const numbers = [...new Set([...written, ...native])];

    return {
        ...issue,
        blockedBy: numbers.map((number) => ({
            number,
            state: blockerState(repo, number, cwd),
        })),
    };
}

function activeWorkerCount(cwd) {
    return activeWorkers(cwd).length;
}

function isActiveWorker(agent) {
    const status = String(agent.status ?? "").toLowerCase();
    const title = String(agent.name ?? agent.title ?? "");
    const labels = agent.labels ?? {};
    const isWorkerLabel = Array.isArray(labels)
        ? labels.some((label) => String(label).includes("autopilot=pms"))
        : labels.autopilot === "pms";

    return ["running", "starting", "pending", "pending_init", "queued"].includes(status)
        && (title.startsWith(WORKER_TITLE_PREFIX) || isWorkerLabel);
}

export function activeWorkers(cwd, run = commandResult) {
    const result = run("paseo", ["ls", "--json"], { cwd });
    const agents = jsonFrom(result, []);

    return agents.filter(isActiveWorker);
}

function usageFields(usage) {
    return {
        inputTokens: usage?.InputTokens ?? usage?.inputTokens ?? null,
        outputTokens: usage?.OutputTokens ?? usage?.outputTokens ?? null,
        cachedTokens: usage?.CachedTokens ?? usage?.cachedTokens ?? null,
    };
}

export function sampleActiveWorkers(cwd, options = {}) {
    const run = options.run ?? commandResult;
    const append = options.append ?? appendFileSync;
    const now = options.now ?? (() => new Date().toISOString());
    const env = options.env ?? process.env;
    const logPath = options.logPath
        ?? env.AUTOPILOT_USAGE_LOG
        ?? path.join(env.USERPROFILE ?? env.HOME ?? ".", ".pms-autopilot", "context-usage.jsonl");

    try {
        const workers = activeWorkers(cwd, run);
        if (workers.length === 0) {
            return;
        }

        mkdirSync(path.dirname(logPath), { recursive: true });
        for (const worker of workers) {
            try {
                const inspect = run(
                    "paseo",
                    ["inspect", String(worker.id), "--json"],
                    { cwd, allowFailure: true },
                );
                const details = inspect.status === 0 ? jsonFrom(inspect, {}) : {};
                const sample = {
                    ts: now(),
                    issue: Number(String(worker.name ?? worker.title ?? "").match(/#(\d+)/)?.[1]) || null,
                    agentId: worker.id,
                    status: details.status ?? worker.status,
                    ...usageFields(details.LastUsage ?? details.lastUsage),
                };

                append(logPath, `${JSON.stringify(sample)}\n`, { encoding: "utf8" });
            } catch {
                // A single worker's telemetry failure must not hide other workers.
            }
        }
    } catch {
        // Telemetry is observation-only and must never stop dispatching.
    }
}

function workerPrompt(repo, issue) {
    return `You are the single-ticket implementation worker for ${repo}. Work only on GitHub issue #${issue.number}: ${issue.title}.

Start by reading the complete issue and comments with gh, then read AGENTS.md, CLAUDE.md, CONTEXT.md, docs/agents/autopilot.md, and the relevant ADRs. Use /implement for this issue only. Work in this fresh worktree and context; do not select or modify another issue.

Follow the repository completion contract: use the agreed testing seams and TDD where applicable, run local checks, synchronize and verify on pms-dev, run PostgreSQL integration/full tests required by AGENTS.md, run /code-review, commit the verified change, and follow the exact-SHA push/deployment/smoke-test rules in AGENTS.md.

On success, comment on issue #${issue.number} with the changed files, tests and verification commands, and the full commit SHA. Close the issue only when every acceptance criterion and required gate is green. If a business decision, credential, security decision, destructive migration, or repeated verification failure blocks progress, leave the issue open, add the autopilot:blocked label, and comment with evidence and the exact decision needed.`;
}

function claimIssue(repo, issueNumber, cwd) {
    commandResult(
        "gh",
        [
            "issue",
            "edit",
            String(issueNumber),
            "--repo",
            repo,
            "--add-assignee",
            "@me",
            "--add-label",
            IN_PROGRESS_LABEL,
        ],
        { cwd },
    );
}

function unclaimAsBlocked(repo, issueNumber, cwd, reason) {
    commandResult(
        "gh",
        [
            "issue",
            "edit",
            String(issueNumber),
            "--repo",
            repo,
            "--remove-assignee",
            "@me",
            "--remove-label",
            IN_PROGRESS_LABEL,
            "--add-label",
            BLOCKED_LABEL,
        ],
        { cwd, allowFailure: true },
    );
    commandResult(
        "gh",
        [
            "issue",
            "comment",
            String(issueNumber),
            "--repo",
            repo,
            "--body",
            `Autopilot could not start a worker. The issue is blocked for review.\n\n\`${reason}\``,
        ],
        { cwd, allowFailure: true },
    );
}

function startWorker(repo, issue, cwd) {
    const stamp = new Date().toISOString().replace(/[^0-9]/g, "").slice(0, 14);
    const branch = `autopilot/issue-${issue.number}-${stamp}`;
    const slug = `pms-issue-${issue.number}-${stamp}`;
    const result = commandResult(
        "paseo",
        [
            "run",
            "--json",
            "--background",
            "--title",
            `${WORKER_TITLE_PREFIX} #${issue.number}`,
            "--provider",
            "codex/gpt-5.6-luna",
            "--mode",
            "full-access",
            "--new-workspace",
            "worktree",
            "--worktree-mode",
            "branch-off",
            "--worktree-slug",
            slug,
            "--new-branch",
            branch,
            "--base",
            "main",
            "--cwd",
            cwd,
            "--label",
            "autopilot=pms",
            "--label",
            `issue=${issue.number}`,
            workerPrompt(repo, issue),
        ],
        { cwd },
    );

    return { branch, slug, details: jsonFrom(result, { raw: result.stdout }) };
}

function report(repo, cwd, issues, candidate, active, mode) {
    const payload = {
        mode,
        repository: repo,
        activeWorkers: active,
        readyIssues: issues.length,
        candidate: candidate
            ? {
                number: candidate.number,
                title: candidate.title,
                blockedBy: candidate.blockedBy,
                url: candidate.url,
            }
            : null,
        cwd,
    };

    console.log(JSON.stringify(payload, null, 2));
}

export function main(argv = process.argv.slice(2), env = process.env) {
    const options = parseArgs(argv, env);
    const cwd = env.AUTOPILOT_CWD ?? REPO_ROOT;
    const repo = resolveRepository(cwd);
    const issues = loadOpenIssues(repo, cwd).map((issue) => enrichBlockers(issue, repo, cwd));
    sampleActiveWorkers(cwd, { env });
    const active = options.mode === "dispatch" ? activeWorkerCount(cwd) : 0;
    const inProgress = issues.some((issue) => labelNames(issue).has(IN_PROGRESS_LABEL));
    const candidate = active >= options.maxActive || inProgress ? undefined : selectCandidate(issues);

    if (options.mode === "dry-run") {
        report(repo, cwd, issues, candidate, active, options.mode);
        return 0;
    }

    if (!candidate) {
        report(repo, cwd, issues, undefined, active, options.mode);
        return 0;
    }

    try {
        claimIssue(repo, candidate.number, cwd);
        const worker = startWorker(repo, candidate, cwd);
        commandResult(
            "gh",
            [
                "issue",
                "comment",
                String(candidate.number),
                "--repo",
                repo,
                "--body",
                `PMS autopilot claimed this issue and started a fresh worker.\n\n- Worker branch: \`${worker.branch}\`\n- Worker: \`${WORKER_TITLE_PREFIX} #${candidate.number}\``,
            ],
            { cwd, allowFailure: true },
        );
        report(repo, cwd, issues, candidate, active, options.mode);
        return 0;
    } catch (error) {
        unclaimAsBlocked(repo, candidate.number, cwd, error.message);
        throw error;
    }
}

const isMainModule = process.argv[1]
    && pathToFileURL(path.resolve(process.argv[1])).href === import.meta.url;

if (isMainModule) {
    try {
        process.exitCode = main();
    } catch (error) {
        console.error(error.stack ?? error.message);
        process.exitCode = 1;
    }
}
