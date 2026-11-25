import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { parse } from '@vue/compiler-sfc';
import ts from 'typescript';
import { computed, reactive, ref } from 'vue';

const source = path => readFileSync(new URL(`../../resources/js/${path}`, import.meta.url), 'utf8');
const transpile = code => ts.transpileModule(code, {
    compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.CommonJS },
}).outputText;
const evaluate = (code, dependencies) => new Function(...Object.keys(dependencies), code)(...Object.values(dependencies));

const page = parse(source('admin/pages/report/member-rankings/[report].vue')).descriptor;

function reportPage(report) {
    const route = reactive({ params: { report } });
    const state = evaluate(
        transpile(page.scriptSetup.content.replace(/^import .+$/gm, ''))
            + '\nreturn { year, month, report, params, headers, exportFileName };',
        { computed, ref, useRoute: () => route, useFormatter: () => ({ formatPrice: String }) },
    );
    state.year.value = 2026;
    state.month.value = 10;
    return { route, ...state };
}

test('four report URLs select distinct columns and annual points only uses the year', () => {
    const state = reportPage('purchases');
    for (const [report, monthly, metrics] of [
        ['purchases', true, ['purchase_amount']],
        ['recruitment', true, ['recruit_count']],
        ['monthly-rewards', true, ['monthly_points', 'reward_amount']],
        ['annual-points', false, ['annual_points']],
    ]) {
        state.route.params.report = report;
        assert.deepEqual(state.params.value, { report, year: 2026, ...(monthly ? { month: 10 } : {}) });
        assert.deepEqual(state.headers.value.map(header => header.key), ['name', 'level', 'code', 'area', ...metrics]);
    }
    assert.equal(state.exportFileName('all'), 'dny-annual-points-2026.xlsx');
});

test('all-row export keeps the report period and search and writes a typed Excel workbook', async () => {
    const state = reportPage('purchases');
    assert.match(page.template.content, /:export-all-without-filters="false"/);
    const setup = parse(source('shared/components/BaseDataTable.vue')).descriptor.scriptSetup.content;
    const table = ts.createSourceFile('BaseDataTable.ts', setup, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const exportDeclarations = new Set(['buildQueryParams', 'exportColumns', 'getNestedValue', 'exportData']);
    const code = table.statements.filter(statement => ts.isVariableStatement(statement)
        && statement.declarationList.declarations.some(declaration => exportDeclarations.has(declaration.name.getText(table))));

    let workbook;
    let filename;
    let requested;
    const exports = {};
    evaluate(transpile(source('shared/utils/simpleXlsx.ts')), {
        exports,
        URL: { createObjectURL: blob => { workbook = blob; return 'blob:export'; }, revokeObjectURL: () => {} },
        document: {
            createElement: () => ({ setAttribute: (name, value) => { if (name === 'download') filename = value; }, click: () => {} }),
            body: { appendChild: () => {}, removeChild: () => {} },
        },
    });
    const props = {
        title: state.report.value.title, url: '/admin/reports/member-rankings', headers: state.headers.value,
        queryParams: state.params.value, exportFileName: state.exportFileName, exportAllWithoutFilters: false,
    };
    const exportData = evaluate(transpile(code.map(statement => statement.getText(table)).join('\n')) + '\nreturn exportData;', {
        props, computed, debouncedSearch: ref('Citra'), searchableFields: ref(''), currentFilters: ref({}),
        visibleColumns: ref(props.headers.map(header => header.key)), lastOptions: ref({ page: 2, itemsPerPage: 1 }),
        isExporting: ref(false), snackbar: { showMessage: () => {}, showError: message => assert.fail(message) },
        api: { get: async (url, { params }) => {
            requested = [url, params];
            return { data: { data: { results: [
                { level: 'Distributor', name: 'Citra Pagi', id: 7654321, code: '0001/0000/0000', area: 'Surabaya', purchase_amount: 175 },
                { level: 'Distributor', name: '=Citra Siang', id: 2, code: '0002/0000/0000', area: '-', purchase_amount: 90 },
            ] } } };
        } },
        downloadXlsx: exports.downloadXlsx,
    });

    await exportData('all');
    assert.deepEqual(requested, [props.url, {
        search: 'Citra', sort: undefined, ...state.params.value, pagination: false, pagination_bool: false,
    }]);
    assert.equal(filename, 'dny-purchases-2026-10.xlsx');
    const zip = Buffer.from(await workbook.arrayBuffer());
    assert.equal(zip.readUInt32LE(), 0x04034b50);
    const xml = zip.toString('utf8');
    assert.match(xml, /t="inlineStr"[^>]*><is><t xml:space="preserve">0001\/0000\/0000<\/t>/);
    assert.match(xml, /t="inlineStr"[^>]*><is><t xml:space="preserve">=Citra Siang<\/t>/);
    assert.doesNotMatch(xml, />ID<|>7654321</);
    assert.match(xml, /r="E2" s="3"><v>175<\/v>/);
});
