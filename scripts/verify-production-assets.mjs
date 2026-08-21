import { spawnSync } from 'node:child_process';

const trackedDiffCheck = spawnSync('git', ['diff', '--quiet', '--', 'public/build']);
const untrackedAssetsCheck = spawnSync(
    'git',
    ['ls-files', '--others', '--exclude-standard', '--', 'public/build'],
    { encoding: 'utf8' },
);

if (
    trackedDiffCheck.status !== 0 ||
    untrackedAssetsCheck.status !== 0 ||
    untrackedAssetsCheck.stdout.trim() !== ''
) {
    const assetStatusCheck = spawnSync('git', ['status', '--short', '--', 'public/build'], {
        encoding: 'utf8',
    });

    process.stderr.write('Production build does not match the committed asset index.\n');
    process.stderr.write(assetStatusCheck.stdout);
    process.exit(1);
}

process.stdout.write('Production assets are reproducible and match the committed manifest.\n');
