import { spawnSync } from "node:child_process";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";

/**
 * Executes a command the way a scheduled agent needs it to be executed.
 *
 * Two Windows facts make a plain `spawnSync("paseo", args)` fail:
 *
 * 1. Node refuses to spawn `.cmd`/`.bat` without a shell (CVE-2024-27980), and it
 *    no longer resolves those extensions through PATH either — so a CLI installed
 *    as a `.cmd` shim is reported as ENOENT even when PATH is correct.
 * 2. A scheduled agent does not inherit the interactive PATH, so the shim may
 *    genuinely be missing from PATH as well.
 *
 * This module resolves the executable itself (PATH plus known install
 * directories plus an env override) and, for `.cmd`/`.bat` shims, runs them
 * through `cmd.exe` with a hand-built, escaped command line.
 */

const WINDOWS_SHIM = /\.(cmd|bat)$/i;
const DEFAULT_ENV_PREFIX = "PORTABLE";

/**
 * The escape hatch for an environment this module cannot search its way out of:
 * `<prefix>_<COMMAND>_BIN`. The prefix belongs to the caller, not here — the
 * autopilot dispatcher passes `AUTOPILOT`, giving `AUTOPILOT_PASEO_BIN`.
 */
function envOverrideName(command, prefix = DEFAULT_ENV_PREFIX) {
    return `${prefix}_${command.replace(/[^a-z0-9]+/gi, "_").toUpperCase()}_BIN`;
}

function isExecutableFile(candidate) {
    return fs.existsSync(candidate) && fs.statSync(candidate).isFile();
}

function candidateNames(command, env, platform) {
    if (platform !== "win32") {
        return [command];
    }

    if (path.extname(command)) {
        return [command];
    }

    const extensions = (env.PATHEXT ?? ".COM;.EXE;.BAT;.CMD")
        .split(";")
        .map((extension) => extension.trim())
        .filter(Boolean);

    return [command, ...extensions.map((extension) => command + extension)];
}

function searchDirectories(env, platform) {
    const fromPath = (env.PATH ?? env.Path ?? "").split(path.delimiter).filter(Boolean);

    if (platform !== "win32") {
        return fromPath;
    }

    const home = env.USERPROFILE ?? os.homedir();
    const localAppData = env.LOCALAPPDATA ?? path.join(home, "AppData", "Local");

    return [
        ...fromPath,
        path.join(home, ".local", "bin"),
        path.join(localAppData, "Programs", "Paseo", "resources", "bin"),
    ];
}

/**
 * Returns the absolute path of `command`, or null when it cannot be found.
 * Unlike Node's own lookup, this finds `.cmd`/`.bat` shims on Windows.
 */
export function resolveExecutable(command, options = {}) {
    const env = options.env ?? process.env;
    const platform = options.platform ?? process.platform;
    const override = env[envOverrideName(command, options.envPrefix)];

    if (override) {
        return isExecutableFile(override) ? path.resolve(override) : null;
    }

    if (command.includes("/") || command.includes("\\")) {
        return isExecutableFile(command) ? path.resolve(command) : null;
    }

    const names = candidateNames(command, env, platform);

    for (const directory of searchDirectories(env, platform)) {
        for (const name of names) {
            const candidate = path.join(directory, name);

            if (isExecutableFile(candidate)) {
                return path.resolve(candidate);
            }
        }
    }

    return null;
}

function escapeForCmd(value) {
    return `"${String(value)}"`.replace(/[()[\]{}%!^"<>&|;, ]/g, "^$&");
}

/**
 * Builds the command line for `cmd.exe /d /s /c`, to be passed with
 * `windowsVerbatimArguments: true`.
 *
 * `.cmd` shims forward their arguments with `%*`, which re-parses the command
 * line: a literal double quote is silently dropped and a newline silently
 * truncates everything after it. Both losses are invisible at runtime, so this
 * throws instead of shipping a mangled argument.
 */
export function windowsShimCommandLine(file, args) {
    for (const argument of args) {
        const text = String(argument);

        if (text.includes("\"")) {
            throw new Error(
                `Cannot pass an argument containing a double quote to the Windows shim ${path.basename(file)}: ${text.slice(0, 80)}`,
            );
        }

        if (/[\r\n]/.test(text)) {
            throw new Error(
                `Cannot pass a multi-line argument to the Windows shim ${path.basename(file)}: ${text.slice(0, 80)}`,
            );
        }
    }

    return [file, ...args].map(escapeForCmd).join(" ");
}

/**
 * `spawnSync` that can actually reach a `.cmd`/`.bat` shim on Windows.
 * Throws a diagnosable error when the command cannot be found at all.
 */
export function spawnPortable(command, args = [], options = {}) {
    const env = options.env ?? process.env;
    const platform = options.platform ?? process.platform;
    const file = resolveExecutable(command, { env, platform, envPrefix: options.envPrefix });

    if (!file) {
        throw new Error(
            `${command} is not executable from this environment. `
            + `Set ${envOverrideName(command, options.envPrefix)} to its absolute path, or add it to PATH.`,
        );
    }

    const spawnOptions = {
        cwd: options.cwd,
        encoding: "utf8",
        windowsHide: true,
        env,
    };

    if (platform === "win32" && WINDOWS_SHIM.test(file)) {
        return spawnSync(
            env.ComSpec ?? env.COMSPEC ?? "cmd.exe",
            ["/d", "/s", "/c", windowsShimCommandLine(file, args)],
            { ...spawnOptions, windowsVerbatimArguments: true },
        );
    }

    return spawnSync(file, args, spawnOptions);
}
