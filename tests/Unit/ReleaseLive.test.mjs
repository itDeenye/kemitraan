import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { copyFileSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import test from 'node:test';

const bash = process.env.DNY_TEST_BASH || (process.platform === 'win32' ? join(process.env.ProgramFiles, 'Git', 'bin', 'bash.exe') : 'bash');
const scriptUrl = new URL('../../.agents/release-live.sh', import.meta.url);

function run(command, args, cwd, input) {
    const result = spawnSync(command, args, {
        cwd,
        input,
        encoding: 'utf8',
        timeout: 60000,
        env: { ...process.env, GIT_TERMINAL_PROMPT: '0' },
    });
    assert.equal(result.status, 0, `${command} ${args.join(' ')}\n${result.error || ''}\n${result.stdout}\n${result.stderr}`);
    return result.stdout.trim();
}

function releaseFixture(t, changedFiles, releasedAt = { backend: '2026-01-02', frontend: '2026-01-03' }) {
    const root = mkdtempSync(join(tmpdir(), 'dny-release-test-'));
    t.after(() => {
        assert.equal(dirname(resolve(root)), resolve(tmpdir()));
        assert.match(root.split(/[\\/]/).at(-1), /^dny-release-test-/);
        rmSync(root, { recursive: true, force: true });
    });
    const repo = join(root, 'source');
    const origin = join(root, 'origin.git');
    const live = join(root, 'live.git');
    mkdirSync(repo);
    const git = (...args) => run('git', args, repo);
    const put = (path, contents) => {
        const target = join(repo, path);
        mkdirSync(dirname(target), { recursive: true });
        writeFileSync(target, contents);
    };
    git('init', '--initial-branch=dev');
    git('config', 'user.name', 'Release Test');
    git('config', 'user.email', 'release-test@example.invalid');
    git('config', 'core.autocrlf', 'false');
    git('config', 'core.hooksPath', join(root, 'no-hooks'));
    git('config', 'commit.gpgsign', 'false');
    git('config', 'tag.gpgsign', 'false');
    const dateLiteral = date => date === null ? 'null' : JSON.stringify(date);
    put('config/api.php', `<?php\nreturn ['version' => ['backend' => 'v1.0.0', 'frontend' => 'v1.0.0'], 'released_at' => ['backend' => ${dateLiteral(releasedAt.backend)}, 'frontend' => ${dateLiteral(releasedAt.frontend)}]];\n`);
    put('app/Example.php', '<?php\n');
    put('resources/js/example.ts', 'export {};\n');
    git('add', '.');
    git('commit', '-m', 'Initial live application');
    git('init', '--bare', origin);
    git('init', '--bare', live);
    git('remote', 'add', 'origin', origin);
    git('remote', 'add', 'live', live);
    git('push', 'origin', 'dev');
    git('push', 'live', 'dev:dev', 'dev:main');
    mkdirSync(join(repo, '.agents'));
    copyFileSync(scriptUrl, join(repo, '.agents/release-live.sh'));
    for (const path of changedFiles) put(path, 'Changed application fixture\n');
    git('add', '.');
    git('commit', '-m', 'Changes on dev');
    git('push', 'origin', 'dev');
    return {
        repo,
        git,
        release: (confirmation, components = 1) => run(bash, ['.agents/release-live.sh'], repo, `${'\n'.repeat(components)}${confirmation}\n`),
        config: () => JSON.parse(run('php', ['-r', 'echo json_encode(require "config/api.php");'], repo)),
        liveConfig: () => git('show', 'refs/remotes/live/dev:config/api.php'),
    };
}

function assertReleaseTimestamp(value, before) {
    assert.match(value, /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+07:00$/);
    const timestamp = Date.parse(value);
    assert.ok(timestamp >= before - 1000 && timestamp <= Date.now(), `Expected current release time, received ${value}`);
}

for (const [component, path, expectedUnchanged] of [
    ['backend', 'app/Example.php', { frontend: '2026-01-03' }],
    ['frontend', 'resources/js/example.ts', { backend: '2026-01-02' }],
]) {
    test(`confirmed ${component}-only live release stamps its time and preserves the other component`, t => {
        const fixture = releaseFixture(t, [path]);
        const before = Date.now();
        fixture.release('y');
        const config = fixture.config();
        assertReleaseTimestamp(config.released_at?.[component], before);
        for (const [unchanged, date] of Object.entries(expectedUnchanged)) assert.equal(config.released_at[unchanged], date);
        assert.deepEqual(config.version, { backend: 'v1.0.0', frontend: 'v1.0.0' });
        fixture.git('fetch', 'live', 'dev', 'main');
        assert.equal(fixture.git('rev-parse', 'live/dev'), fixture.git('rev-parse', 'live/main'));
        assert.equal(fixture.liveConfig(), readFileSync(join(fixture.repo, 'config/api.php'), 'utf8').trim());
    });
}

test('a release changing both components records the same Jakarta timestamp for both', t => {
    const fixture = releaseFixture(t, ['app/Example.php', 'resources/js/example.ts']);
    const before = Date.now();
    fixture.release('y', 2);
    const config = fixture.config();
    assertReleaseTimestamp(config.released_at.backend, before);
    assert.equal(config.released_at.frontend, config.released_at.backend);
});

test('releasing one component does not invent an unknown date for the other component', t => {
    const fixture = releaseFixture(t, ['app/Example.php'], { backend: null, frontend: null });
    const before = Date.now();
    fixture.release('y');
    const config = fixture.config();
    assertReleaseTimestamp(config.released_at.backend, before);
    assert.equal(config.released_at.frontend, null);
});

test('cancelled live confirmation changes no dates, commits, tags or live branches', t => {
    const fixture = releaseFixture(t, ['app/Example.php']);
    const config = fixture.config();
    const head = fixture.git('rev-parse', 'HEAD');
    const live = fixture.git('ls-remote', 'live');
    fixture.release('n');
    assert.deepEqual(fixture.config(), config);
    assert.equal(fixture.git('rev-parse', 'HEAD'), head);
    assert.equal(fixture.git('tag', '--list'), '');
    assert.equal(fixture.git('ls-remote', 'live'), live);
});

test('ordinary dev push and documentation-only changes keep the release dates unchanged', t => {
    const fixture = releaseFixture(t, ['docs/example.md']);
    const config = fixture.config();
    assert.deepEqual(config.released_at, { backend: '2026-01-02', frontend: '2026-01-03' });
    fixture.release('y');
    assert.deepEqual(fixture.config(), config);
    assert.equal(fixture.git('tag', '--list'), '');
});
