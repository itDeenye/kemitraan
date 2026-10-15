import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import { compile } from '@vue/compiler-dom';
import { parse } from '@vue/compiler-sfc';
import ts from 'typescript';

const require = createRequire(import.meta.url);
const Vue = require('vue');
const { renderToString } = createRequire(require.resolve('vue'))('@vue/server-renderer');
const pageUrl = new URL('../../resources/js/shared/pages/VersionPage.vue', import.meta.url);

async function versionPage(result, { unavailable = false } = {}) {
    const { descriptor } = parse(readFileSync(pageUrl, 'utf8'));
    const source = ts.createSourceFile('VersionPage.ts', descriptor.scriptSetup.content, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const statements = source.statements.filter(statement => !ts.isImportDeclaration(statement));
    const bindings = statements.flatMap(statement => {
        if (ts.isVariableStatement(statement)) {
            return statement.declarationList.declarations.map(declaration => declaration.name.getText(source));
        }
        return ts.isFunctionDeclaration(statement) ? [statement.name.text] : [];
    });
    const code = ts.transpileModule(statements.map(statement => statement.getText(source)).join('\n'), {
        compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
    }).outputText;
    const dependencies = {
        computed: Vue.computed,
        ref: Vue.ref,
        onMounted: () => {},
        fetch: async () => {
            if (unavailable) throw new Error('API unavailable');
            return { ok: true, json: async () => result };
        },
        console: { error: () => {} },
    };
    const page = new Function(...Object.keys(dependencies), `${code}\nreturn { ${bindings.join(',')} };`)(...Object.values(dependencies));
    const render = new Function('Vue', compile(descriptor.template.content, { mode: 'function', prefixIdentifiers: true }).code)(Vue);
    const component = { setup: () => page, render };
    const renderPage = () => {
        const app = Vue.createSSRApp(component);
        for (const tag of ['v-app', 'v-container', 'v-row', 'v-col', 'v-card', 'v-chip', 'v-icon', 'v-btn', 'v-progress-circular', 'v-snackbar']) {
            app.component(tag, { setup: (_, { slots }) => () => Vue.h('div', slots.default?.()) });
        }
        app.directive('tooltip', {});
        return renderToString(app);
    };
    await page.loadVersions();
    return {
        html: await renderPage(),
        reloadUnavailable: async () => {
            unavailable = true;
            await page.loadVersions();
            return renderPage();
        },
    };
}

test('version cards display their own release date from the API', async () => {
    const { html } = await versionPage({
        success: true,
        message: 'Versi aplikasi berhasil diambil.',
        data: { backend: 'v1.2.0', frontend: 'v1.1.3', released_at: { backend: '2026-10-13', frontend: '2026-10-12' } },
    });
    assert.match(html, /Frontend App[\s\S]*v1\.1\.3[\s\S]*datetime="2026-10-12"[\s\S]*12 Oktober 2026/);
    assert.match(html, /Backend API[\s\S]*v1\.2\.0[\s\S]*datetime="2026-10-13"[\s\S]*13 Oktober 2026/);
    assert.doesNotMatch(html, /WIB/);
});

test('unknown and legacy release dates are shown honestly without using today', async () => {
    for (const released_at of [undefined, { backend: null, frontend: null }]) {
        const { html } = await versionPage({ success: true, data: { backend: 'v1.0.0', frontend: 'v1.0.0', released_at } });
        assert.equal((html.match(/Belum tercatat/g) || []).length, 2);
        assert.doesNotMatch(html, /datetime=/);
    }
});

test('legacy release timestamps display their WIB hour and minute without seconds', async () => {
    for (const timestamp of ['2026-10-15T09:13:27+07:00', '2026-10-15T02:13:27Z']) {
        const { html } = await versionPage({ success: true, data: {
            backend: 'v1.0.0', frontend: 'v1.0.0',
            released_at: { backend: timestamp, frontend: timestamp },
        } });
        assert.equal((html.match(/15 Oktober 2026, 09:13 WIB/g) || []).length, 2);
        assert.doesNotMatch(html.replace(/<[^>]*>/g, ' '), /09:13:27|09\.13\.27/);
        assert.doesNotMatch(html, /Belum tercatat/);
    }
});

test('new release timestamps take precedence over the date-only compatibility fields', async () => {
    const { html } = await versionPage({ success: true, data: {
        backend: 'v1.0.0', frontend: 'v1.0.0',
        released_at: { backend: '2026-10-15', frontend: '2026-10-14' },
        released_at_datetime: { backend: '2026-10-15T09:37:51+07:00', frontend: '2026-10-14T17:00:59Z' },
    } });
    assert.match(html, /Frontend App[\s\S]*15 Oktober 2026, 00:00 WIB/);
    assert.match(html, /Backend API[\s\S]*15 Oktober 2026, 09:37 WIB/);
    assert.doesNotMatch(html.replace(/<[^>]*>/g, ' '), /09:37:51|00:00:59/);
});

test('unknown time falls back to the component date without inventing a time', async () => {
    const { html } = await versionPage({ success: true, data: {
        backend: 'v1.0.0', frontend: 'v1.0.0',
        released_at: { backend: '2026-10-15', frontend: '2026-10-14' },
        released_at_datetime: { backend: null, frontend: null },
    } });
    assert.match(html, /15 Oktober 2026/);
    assert.match(html, /14 Oktober 2026/);
    assert.doesNotMatch(html, /WIB|Belum tercatat/);
});

test('invalid release dates do not crash rendering or hide the versions', async () => {
    const { html } = await versionPage({ success: true, data: {
        backend: 'v1.0.0', frontend: 'v1.0.0',
        released_at: { backend: 'invalid-date', frontend: '2026-10-15T09:13:27+07:00T00:00:00+07:00' },
    } });
    assert.equal((html.match(/v1\.0\.0/g) || []).length, 2);
    assert.equal((html.match(/Belum tercatat/g) || []).length, 2);
});

test('failed reload clears stale release dates along with the fallback versions', async () => {
    const page = await versionPage({ success: true, data: { backend: 'v1.2.0', frontend: 'v1.1.3', released_at: { backend: '2026-10-13', frontend: '2026-10-12' } } });
    const html = await page.reloadUnavailable();
    assert.equal((html.match(/Belum tercatat/g) || []).length, 2);
    assert.doesNotMatch(html, /datetime=/);
});
