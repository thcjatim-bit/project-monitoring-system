/**
 * The `gh` calls in this repo all carry the same three things: how to run a
 * command, which repository to run it against, and from which directory. This
 * bundles them once so callers pass a client instead of that trio.
 */

/** Parses the stdout of a `--json` call, falling back when it is empty or not JSON. */
export function jsonFrom(result, fallback) {
    if (!result.stdout) {
        return fallback;
    }

    try {
        return JSON.parse(result.stdout);
    } catch {
        return fallback;
    }
}

/**
 * `run` follows the `commandResult` contract: it returns `{ status, stdout, stderr }`
 * and, with `allowFailure`, reports a non-zero exit instead of throwing.
 */
export function ghClient({ run, repo, cwd }) {
    return {
        repo,
        cwd,
        /** Runs `gh <args> --repo <repo>`. Never throws on a non-zero exit; inspect `status`. */
        call(args) {
            return run("gh", [...args, "--repo", repo], { cwd, allowFailure: true });
        },
    };
}
