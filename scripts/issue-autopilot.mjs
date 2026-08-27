import { fileURLToPath, pathToFileURL } from "node:url";
import path from "node:path";

import { reportDispatchFailure, resolveDispatchFailure } from "./dispatch-failure.mjs";
import { ghClient, jsonFrom } from "./gh.mjs";
import { spawnPortable } from "./portable-spawn.mjs";

export const READY_LABEL = "ready-for-agent";
export const IN_PROGRESS_LABEL = "autopilot:in-progress";
export const BLOCKED_LABEL = "autopilot:blocked";

/**
 * Keadaan "belum ada sesi `/tdd`" dipisahkan dari `autopilot:blocked`. Label itu berarti
 * manusia harus memutuskan sesuatu -- keputusan bisnis, kredensial, migrasi destruktif.
 * Menunggu handoff TDD bukan keputusan siapa pun, hanya pekerjaan yang belum dijalankan,
 * dan mencampur keduanya membuat penyisiran label salah baca (#182).
 */
export const NEEDS_TDD_LABEL = "needs-tdd";

/**
 * Penanda yang ditinggalkan sesi `/tdd` pada komentar handoff-nya. Kontrak worker butir 2
 * mewajibkan handoff itu ada; tanpa penanda ini seleksi tidak punya cara memeriksanya, dan
 * antrean memilih issue yang gerbang worker-nya tidak mungkin diloloskan.
 */
export const HANDOFF_MARKER = "<!-- tdd-handoff -->";
export const WORKER_TITLE_PREFIX = "PMS autopilot worker";

const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");

export function commandResult(command, args, options = {}) {
    const result = spawnPortable(command, args, {
        cwd: options.cwd ?? REPO_ROOT,
        env: options.env,
        // Names the documented escape hatch: `AUTOPILOT_PASEO_BIN`.
        envPrefix: "AUTOPILOT",
    });

    if (result.error) {
        throw result.error;
    }

    const stdout = (result.stdout ?? "").trim();
    const stderr = (result.stderr ?? "").trim();

    if (result.status !== 0 && !options.allowFailure) {
        throw new Error(`${command} ${args.join(" ")} failed: ${stderr || stdout}`);
    }

    return { status: result.status ?? 1, stdout, stderr };
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

/**
 * Handoff boleh ditulis di komentar mana pun atau di body issue -- yang mengikat penandanya,
 * bukan tempatnya. Sesi `/tdd` biasanya menaruhnya di komentar; tiket yang lahir sudah
 * bersama kontrak test-nya boleh menaruhnya di body.
 */
export function hasTddHandoff(issue) {
    const bodies = [issue.body ?? "", ...(issue.comments ?? []).map((comment) => comment?.body ?? "")];

    return bodies.some((body) => String(body).includes(HANDOFF_MARKER));
}

export function isEligibleIssue(issue) {
    const labels = labelNames(issue);
    const state = String(issue.state ?? "").toUpperCase();

    return state === "OPEN"
        && labels.has(READY_LABEL)
        && !labels.has(IN_PROGRESS_LABEL)
        && !labels.has(BLOCKED_LABEL)
        && (issue.assignees ?? []).length === 0
        && hasTddHandoff(issue);
}

/**
 * Issue yang lolos semua syarat antrean kecuali handoff TDD. Dilaporkan, bukan dilabeli
 * otomatis: dispatcher tidak menulis ke tracker pada mode dry-run, dan jarak antara
 * "sudah ditriase" dan "bisa didispatch" perlu terlihat di tempat manusia sudah melihat.
 */
export function needsTddIssues(issues) {
    return [...issues]
        .filter((issue) => !hasTddHandoff(issue) && isEligibleIssue({ ...issue, comments: [{ body: HANDOFF_MARKER }] }))
        .map((issue) => Number(issue.number))
        .sort((left, right) => left - right);
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
            "number,title,body,labels,assignees,state,url,comments",
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
    const result = commandResult("paseo", ["ls", "--json"], { cwd });
    const agents = jsonFrom(result, []);

    return agents.filter((agent) => {
        const status = String(agent.status ?? "").toLowerCase();
        const title = String(agent.name ?? agent.title ?? "");
        const labels = agent.labels ?? {};
        const isWorkerLabel = Array.isArray(labels)
            ? labels.some((label) => String(label).includes("autopilot=pms"))
            : labels.autopilot === "pms";

        return ["running", "starting", "pending", "pending_init", "queued"].includes(status)
            && (title.startsWith(WORKER_TITLE_PREFIX) || isWorkerLabel);
    }).length;
}

// The prompt travels to `paseo` as one command-line argument. On Windows that
// argument passes through a `.cmd` shim, which drops literal double quotes and
// truncates at the first newline — so the prompt is built single-line and
// quote-free, and the issue title (arbitrary text from GitHub) is normalised.
export function cliSafeText(value) {
    return String(value ?? "")
        .replace(/[\r\n]+/g, " ")
        .replace(/["“”]/g, "'")
        // A caret cannot suppress %VAR% expansion during the shim's %* re-parse,
        // so a percent sign never reaches the command line at all.
        .replace(/%/g, " percent ")
        .replace(/\s+/g, " ")
        .trim();
}

export function workerPrompt(repo, issue) {
    return cliSafeText([
        `You are the single-ticket implementation worker for ${repo}.`,
        `Work only on GitHub issue #${issue.number}: ${issue.title}.`,
        "Start by reading the complete issue and comments with gh, then read AGENTS.md, CLAUDE.md, CONTEXT.md, docs/agents/autopilot.md, and the relevant ADRs.",
        "Use /implement for this issue only. Work in this fresh worktree and context; do not select or modify another issue.",
        "Follow the repository implementation contract: consume the durable TDD handoff, implement the change, run local checks, synchronize and verify on pms-dev, and run the PostgreSQL integration/full tests required by AGENTS.md.",
        "Commit the verified implementation and stop at the /code-review handoff. A fresh review/release session owns /code-review and the exact-SHA push/deployment/smoke-test rules in AGENTS.md.",
        `On success, comment on issue #${issue.number} with the changed files, tests and verification commands, the full commit SHA, and the next review step.`,
        "Leave the issue open for review.",
        "If the TDD handoff is missing, or a business decision, credential, security decision, destructive migration, or repeated verification failure blocks progress, leave the issue open, add the autopilot:blocked label, and comment with evidence and the exact decision needed.",
    ].join(" "));
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
        needsTdd: needsTddIssues(issues),
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

    if (options.mode !== "dispatch") {
        return runTick(options, repo, cwd);
    }

    // The issue tracker is the failure channel: it is reachable through `gh`
    // even when `paseo` is not, and it is a surface a human already watches.
    // A schedule run reporting `succeeded` is not evidence that dispatch worked.
    const gh = ghClient({ run: commandResult, repo, cwd });

    try {
        const code = runTick(options, repo, cwd);

        resolveDispatchFailure(gh);

        return code;
    } catch (error) {
        reportDispatchFailure(gh, error);

        throw error;
    }
}

function runTick(options, repo, cwd) {
    const issues = loadOpenIssues(repo, cwd).map((issue) => enrichBlockers(issue, repo, cwd));
    // Both modes count active workers, so `--dry-run` exercises the same `paseo`
    // call that dispatch depends on. Skipping it here (issue #144) is what let the
    // dispatcher stay broken for hours while the dry-run health check stayed green.
    const active = activeWorkerCount(cwd);
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
