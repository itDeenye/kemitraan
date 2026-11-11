import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import { parse } from '@vue/compiler-sfc';
import ts from 'typescript';

const require = createRequire(import.meta.url);
const Vue = require('vue');

function downgradeForm() {
    const source = readFileSync(new URL('../../resources/js/admin/components/partnership/DowngradeForm.vue', import.meta.url), 'utf8');
    const script = parse(source).descriptor.scriptSetup.content;
    const file = ts.createSourceFile('form.ts', script, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const code = file.statements.filter(item => !ts.isImportDeclaration(item)).map(item => item.getText(file)).join('\n');
    const compiled = ts.transpileModule(code, {
        compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
    }).outputText;
    const timers = new Map();
    const requests = [], messages = [];
    let nextTimer = 0;
    const dependencies = {
        ref: Vue.ref, reactive: Vue.reactive, computed: Vue.computed,
        defineEmits: () => () => {}, defineExpose: () => {},
        useSnackbarStore: () => ({ showMessage: (...args) => messages.push(args) }),
        api: { get: (url, { params }) => new Promise((resolve, reject) => requests.push({ url, params, resolve, reject })) },
        setTimeout: callback => { timers.set(++nextTimer, callback); return nextTimer; },
        clearTimeout: id => timers.delete(id),
        console: { error: () => {} },
    };
    const form = new Function(...Object.keys(dependencies), `${compiled}\nreturn { searchMembers, searchParents, members, parents, form, selectedMember, onMemberChange, loadingMembers, close };`)(...Object.values(dependencies));
    return {
        ...form, requests, messages,
        flushTimer() {
            const [id, callback] = timers.entries().next().value;
            timers.delete(id);
            return callback();
        },
    };
}

const response = (id, name) => ({ data: { data: { results: [{ id, name, code: `MITRA-${id}` }] } } });

test('a slow previous member search cannot overwrite the newest downgrade search', async () => {
    const form = downgradeForm();
    form.searchMembers('');
    const previous = form.flushTimer();
    form.searchMembers('eko');
    const latest = form.flushTimer();
    form.requests[1].resolve(response(2, 'Eko'));
    await latest;
    form.requests[0].resolve(response(1, 'Other'));
    await previous;
    assert.deepEqual(form.members.value.map(member => member.name), ['Eko']);
});

test('a slow sponsor search cannot overwrite the newest downgrade sponsor search', async () => {
    const form = downgradeForm();
    Object.assign(form.form, { member_id: 10, to_level_id: 3 });
    form.searchParents('');
    const previous = form.flushTimer();
    form.searchParents('ayu');
    const latest = form.flushTimer();
    form.requests[1].resolve(response(2, 'Ayu'));
    await latest;
    form.requests[0].resolve(response(1, 'Other'));
    await previous;
    assert.deepEqual(form.parents.value.map(member => member.name), ['Ayu']);
});

test('changing the downgrade member discards a previous members sponsor options', async () => {
    const form = downgradeForm();
    Object.assign(form.form, { member_id: 10, to_level_id: 3 });
    form.searchParents('ayu');
    const pending = form.flushTimer();
    form.selectedMember.value = { id: 20 };
    form.onMemberChange();
    form.requests[0].resolve(response(2, 'Ayu'));
    await pending;
    assert.deepEqual(form.parents.value, []);
});

test('closing a downgrade form discards its pending member search', async () => {
    const form = downgradeForm();
    form.searchMembers('eko');
    const pending = form.flushTimer();
    form.close();
    form.requests[0].resolve(response(2, 'Eko'));
    await pending;
    assert.deepEqual(form.members.value, []);
});
