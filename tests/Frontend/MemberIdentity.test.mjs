import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import { compile } from '@vue/compiler-dom';
import { parse } from '@vue/compiler-sfc';
import ts from 'typescript';

const require = createRequire(import.meta.url);
const Vue = require('vue');
const { renderToString } = createRequire(require.resolve('vue'))('@vue/server-renderer');
const sourceFile = path => new URL(`../../resources/js/${path}`, import.meta.url);
const types = ['KTP', 'KK', 'SIM', 'BPJS', 'BPJS_KETENAGAKERJAAN', 'PASPOR'];
const paths = [
    'admin/components/partnership/DistributorForm.vue',
    'member/pages/network/registrations/index.vue',
    'member/pages/profile/edit.vue',
];

function identityDependencies() {
    const url = sourceFile('shared/utils/member-identity.ts');
    if (!existsSync(url)) return {};
    const code = ts.transpileModule(readFileSync(url, 'utf8'), {
        compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
    }).outputText;
    const exports = {};
    new Function('exports', code)(exports);
    return exports;
}

function setup(path, identity = { type: 'KTP', number: '' }) {
    const { descriptor } = parse(readFileSync(sourceFile(path), 'utf8'));
    const source = ts.createSourceFile(path, descriptor.scriptSetup.content, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const statements = source.statements.filter(statement => !ts.isImportDeclaration(statement));
    const bindings = statements.flatMap(statement => ts.isVariableStatement(statement)
        ? statement.declarationList.declarations.flatMap(declaration => ts.isIdentifier(declaration.name)
            ? [declaration.name.text]
            : declaration.name.elements.map(element => element.name.getText(source)))
        : ts.isFunctionDeclaration(statement) ? [statement.name.text] : []);
    const code = ts.transpileModule(statements.map(statement => statement.getText(source)).join('\n'), {
        compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
    }).outputText;
    const dependencies = {
        ...identityDependencies(), ref: Vue.ref, reactive: Vue.reactive, computed: Vue.computed, watch: () => {},
        onMounted: () => {}, defineExpose: () => {}, defineEmits: () => () => {},
        defineProps: () => ({}), withDefaults: (props, defaults) => ({ ...defaults, ...props }),
        useRouter: () => ({ push: () => {} }), useRoute: () => ({ query: {} }),
        useAuthStore: () => ({ user: { member: { level: { code: 'DST' } } }, fetchUser: async () => {} }),
        useSnackbarStore: () => ({ showMessage: () => {} }), useFormatter: () => ({ formatDate: String }),
        useRegionCascade: () => Object.fromEntries(['provinces', 'cities', 'districts', 'subdistricts'].map(key => [key, Vue.ref([])])),
        profileService: { getProfile: async () => ({ success: true, data: { member: { name: 'Member', identity } } }) },
    };
    const context = new Function(...Object.keys(dependencies), `${code}\nreturn { ${bindings.join(',')} };`)(...Object.values(dependencies));
    if (context.options) context.options.value = {
        identity_types: types, sponsor: { name: 'Distributor', code: '0001', level_code: 'DST' },
        levels: [], agents: [], genders: ['Laki-laki', 'Perempuan'],
    };
    return { context: { ...context.props, ...context }, descriptor };
}

async function fields({ context, descriptor }) {
    const result = [];
    const renderTemplate = new Function('Vue', compile(descriptor.template.content, { mode: 'function', prefixIdentifiers: true }).code)(Vue);
    const app = Vue.createSSRApp({ render: () => renderTemplate(Vue.proxyRefs(context), []) });
    const tags = new Set([...descriptor.template.content.matchAll(/<([A-Z][\w-]*|v-[\w-]*)/g)].map(match => match[1]));
    for (const tag of tags) app.component(tag, {
        inheritAttrs: false,
        setup: (_, { attrs, slots }) => () => {
            if (['v-text-field', 'v-select'].includes(tag)) result.push({ ...attrs, tag });
            return Vue.h('div', null, slots.default?.());
        },
    });
    await renderToString(app);
    return {
        number: result.find(item => item.tag === 'v-text-field' && /Nomor (Identitas|Paspor)/.test(item.label)),
        type: result.find(item => item.tag === 'v-select' && /Jenis Identitas/.test(item.label)),
    };
}

const isValid = (field, value) => field.rules.every(rule => rule(value) === true);

for (const path of paths) {
    test(`${path}: health insurance enforces 13 numeric digits`, async () => {
        const page = setup(path);
        const form = page.context.form ?? page.context.formProfile;
        form.identity_type = 'BPJS';
        const { number } = await fields(page);
        assert.equal(isValid(number, '0001234567890'), true);
        assert.equal(isValid(number, '000123456789'), false);
        assert.equal(isValid(number, 'A001234567890'), false);
        assert.equal(Number(number.maxlength), 13);
        assert.match(number.hint ?? number.placeholder, /13/);
    });

    test(`${path}: passport accepts eight alphanumeric characters`, async () => {
        const page = setup(path);
        const form = page.context.form ?? page.context.formProfile;
        form.identity_type = 'PASPOR';
        const { number } = await fields(page);
        assert.equal(isValid(number, 'A0123456'), true);
        assert.equal(isValid(number, 'A012345'), false);
        assert.equal(isValid(number, 'A012-456'), false);
        assert.equal(Number(number.maxlength), 8);
        assert.equal(number.inputmode, 'text');
    });

    test(`${path}: employment insurance accepts only 11 or 16 digits`, async () => {
        const page = setup(path);
        const form = page.context.form ?? page.context.formProfile;
        form.identity_type = 'BPJS_KETENAGAKERJAAN';
        const { number } = await fields(page);
        assert.equal(isValid(number, '00012345678'), true);
        assert.equal(isValid(number, '0001234567890123'), true);
        assert.equal(isValid(number, '0001234567890'), false);
        assert.equal(Number(number.maxlength), 16);
    });

    test(`${path}: identity options distinguish both BPJS types and include KK`, async () => {
        const { type } = await fields(setup(path));
        const items = type.items.map(item => typeof item === 'string' ? { value: item, title: item } : item);
        assert.deepEqual(items.map(item => item.value), types);
        assert.equal(items.find(item => item.value === 'BPJS').title, 'BPJS Kesehatan');
        assert.equal(items.find(item => item.value === 'BPJS_KETENAGAKERJAAN').title, 'BPJS Ketenagakerjaan');
    });
}

test('profile permits legacy identity unchanged but validates changed number or type', async () => {
    const page = setup(paths[2], { type: 'SIM', number: '001234567890' });
    await page.context.loadProfile();
    let { number } = await fields(page);
    assert.equal(isValid(number, '001234567890'), true);
    assert.equal(page.context.formProfile.identity_no, '001234567890');
    page.context.formProfile.identity_no = '001234567891';
    ({ number } = await fields(page));
    assert.equal(isValid(number, '001234567891'), false);
    page.context.formProfile.identity_type = 'KTP';
    ({ number } = await fields(page));
    assert.equal(isValid(number, '001234567890'), false);
});
