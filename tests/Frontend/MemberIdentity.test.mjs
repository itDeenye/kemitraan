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
    'admin/components/partnership/MemberEditDialog.vue',
    'member/pages/network/registrations/index.vue',
    'member/pages/profile/edit.vue',
];
const profilePath = 'member/pages/profile/edit.vue';

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

function setup(path, identity = { type: 'KTP', number: '' }, overrides = {}) {
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
        ...overrides,
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
    if (context.loading?.value !== undefined) context.loading.value = false;
    const renderTemplate = new Function('Vue', compile(descriptor.template.content, { mode: 'function', prefixIdentifiers: true }).code)(Vue);
    const app = Vue.createSSRApp({ render: () => renderTemplate(Vue.proxyRefs(context), []) });
    const tags = new Set([...descriptor.template.content.matchAll(/<([A-Z][\w-]*|v-[\w-]*)/g)].map(match => match[1]));
    for (const tag of tags) app.component(tag, {
        inheritAttrs: false,
        setup: (_, { attrs, slots }) => () => {
            if (['v-text-field', 'v-select', 'MediaUpload'].includes(tag)) result.push({ ...attrs, tag });
            return Vue.h('div', null, slots.default?.());
        },
    });
    await renderToString(app);
    return {
        customer: result.find(item => item.tag === 'v-text-field' && /Nomor Customer/.test(item.label)),
        number: result.find(item => item.tag === 'v-text-field' && /Nomor (Identitas|Paspor)/.test(item.label)),
        type: result.find(item => item.tag === 'v-select' && /Jenis Identitas/.test(item.label)),
        uploads: result.filter(item => item.tag === 'MediaUpload'),
    };
}

const isValid = (field, value) => field.rules.every(rule => rule(value) === true);

test('admin customer input loads the current number and saves a trimmed manual correction', async () => {
    let saved;
    const page = setup(paths[1], undefined, {
        referenceService: { getProvinces: async () => [], getBanks: async () => [] },
        memberService: { getMember: async () => ({ customer_number: 'OLD-123' }),
            updateMember: async (id, payload) => { saved = structuredClone({ id, payload }); } },
    });
    await page.context.open(7);
    const { customer } = await fields(page);
    assert.ok(customer, 'The manual customer input must be rendered');
    assert.equal(customer.modelValue, 'OLD-123');
    assert.notEqual(customer.disabled, true);
    assert.equal(Number(customer.maxlength), 30);
    assert.equal(isValid(customer, ''), false);
    assert.equal(isValid(customer, '   '), false);
    assert.equal(isValid(customer, '1'.repeat(31)), false);
    customer['onUpdate:modelValue']('  0010030900  ');
    page.context.formRef.value = { validate: async () => ({ valid: true }), resetValidation() {} };
    await page.context.submit();
    assert.equal(saved.id, 7);
    assert.equal(saved.payload.customer_number, '0010030900');
    assert.equal(page.context.form.value.customer_number, '');
});

test('editing an unsynchronized profile does not send a blank customer number', async () => {
    let saved;
    const page = setup(paths[1], undefined, {
        referenceService: { getProvinces: async () => [], getBanks: async () => [] },
        memberService: { getMember: async () => ({}), updateMember: async (id, payload) => { saved = payload; } },
    });
    await page.context.open(7);
    const { customer } = await fields(page);
    assert.ok(customer);
    assert.equal(isValid(customer, ''), true);
    page.context.formRef.value = { validate: async () => ({ valid: true }), resetValidation() {} };
    await page.context.submit();
    assert.equal('customer_number' in saved, false);
});

for (const path of paths) {
    test(`${path}: health insurance enforces 13 numeric digits`, async () => {
        const page = setup(path);
        const form = (page.context.form ?? page.context.formProfile)?.value
            ?? (page.context.form ?? page.context.formProfile);
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
        const form = (page.context.form ?? page.context.formProfile)?.value
            ?? (page.context.form ?? page.context.formProfile);
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
        const form = (page.context.form ?? page.context.formProfile)?.value
            ?? (page.context.form ?? page.context.formProfile);
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
    const page = setup(profilePath, { type: 'SIM', number: '001234567890' });
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

test('admin edit keeps all documents and bank details on a text-only save and excludes network fields', async () => {
    let saved;
    const member = {
        id: 7, code: '0001/0001/0001', username: 'member.seven', name: 'Member Seven',
        image: 'https://example.test/profile.webp',
        identity: { type: 'SIM', number: '001234567890', image: 'https://example.test/identity.webp' },
        payment_proof_image_url: 'https://example.test/payment.webp', can_update_payment_proof: true,
        bank_accounts: [{ id: 9, bank_id: 1, account_name: 'Member Seven', account_number: '001234',
            city: 'Bandung', branch: 'Dago', is_active: true, is_default: true }],
    };
    const page = setup(paths[1], undefined, {
        referenceService: { getProvinces: async () => [], getBanks: async () => [] },
        memberService: { getMember: async () => member, updateMember: async (id, payload) => {
            saved = structuredClone({ id, payload });
        } },
    });
    await page.context.open(7);
    const rendered = await fields(page);
    assert.equal(isValid(rendered.number, member.identity.number), true);
    assert.deepEqual(rendered.uploads.map(upload => upload.collection), ['profile', 'general', 'payment_proof']);
    page.context.form.value.name = 'Member Seven Updated';
    page.context.formRef.value = { validate: async () => ({ valid: true }), resetValidation() {} };
    await page.context.submit();
    assert.equal(saved.id, 7);
    assert.equal(saved.payload.image_url, member.image);
    assert.equal(saved.payload.identity_image_url, member.identity.image);
    assert.equal(saved.payload.payment_proof_image_url, member.payment_proof_image_url);
    assert.equal(saved.payload.bank_accounts[0].city, 'Bandung');
    assert.equal(saved.payload.bank_accounts[0].branch, 'Dago');
    assert.equal(saved.payload.bank_accounts[0].account_number, '001234');
    for (const field of ['level', 'upline', 'code', 'username']) assert.equal(field in saved.payload, false);
});

test('member edits identity document only after upload and waits for uploads before saving', async () => {
    const saved = [];
    const page = setup(profilePath, undefined, {
        profileService: {
            getProfile: async () => ({ success: true, data: { member: {
                name: 'Member', identity: { type: 'KTP', number: '', image: 'https://example.test/old-identity.webp' },
            } } }),
            updateProfile: async payload => {
                saved.push(structuredClone(payload));
                return { success: true };
            },
        },
    });
    await page.context.loadProfile();
    assert.equal(page.context.identityImage.value, 'https://example.test/old-identity.webp');
    const rendered = await fields(page);
    assert.deepEqual(rendered.uploads.map(upload => upload.collection), ['profile', 'general']);
    page.context.profileFormRef.value = { validate: async () => ({ valid: true }) };
    await page.context.saveProfile();
    assert.equal('identity_image_url' in saved[0], false);
    assert.equal('payment_proof_image_url' in saved[0], false);
    page.context.identityImage.value = 'https://example.test/new-identity.webp';
    page.context.identityImageChanged.value = true;
    page.context.mediaUploads.identity = true;
    await page.context.saveProfile();
    assert.equal(saved.length, 1);
    page.context.mediaUploads.identity = false;
    await page.context.saveProfile();
    assert.equal(saved[1].identity_image_url, 'https://example.test/new-identity.webp');
});
