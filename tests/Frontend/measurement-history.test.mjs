import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const context = vm.createContext({});
vm.runInContext(readFileSync(new URL('../../public/js/measurement-history.js', import.meta.url), 'utf8'), context);
const history = context.LumaMeasurementHistory;
const row = (unit, value, minute, extra = {}) => ({ sensor_id: 7, unit, value, provenance: 'physical_iot', source_label: 'IoT físico',
    recorded_at_iso: `2026-10-22T12:${String(minute).padStart(2, '0')}:00Z`, interval_snapshot: 60, ...extra });

test('RAW and percentages use separate series without conversion', () => {
    const groups = history.series([row('%', 25, 0), row('raw', 3184, 1)]);
    assert.equal(groups.length, 2);
    assert.equal(groups[1].unit, 'raw');
    assert.equal(groups[1].points[0].value, 3184);
});

test('gaps, origin, sensor and calibration epochs cannot be joined silently', () => {
    const groups = history.series([row('raw', 3184, 0), row('raw', 3180, 1), row('raw', 3170, 20),
        row('raw', 3000, 21, { provenance: 'manual_or_simulated' }), row('raw', 3100, 22, { sensor_id: 8 }),
        row('%', 50, 23, { calibration_snapshot: { dry: 3500, wet: 1000 } }), row('%', 60, 24, { calibration_snapshot: { dry: 3300, wet: 1000 } })]);
    assert.equal(groups.length, 5);
    assert.equal(groups[0].segments.length, 2);
    assert.equal(groups[0].segments[0].length, 2);
});

test('canvas labels each unit and never connects across units', () => {
    const labels = [];
    const canvas = { style: {}, getContext: () => ({ setTransform() {}, clearRect() {}, beginPath() {}, moveTo() {}, lineTo() {}, stroke() {}, arc() {}, fill() {}, fillText: text => labels.push(text) }) };
    const groups = history.draw(canvas, [row('raw', 3184, 0), row('%', 25, 1)], 600, 300);
    assert.equal(groups.length, 2);
    assert.ok(labels.some(label => label.includes('Unidad: raw')));
    assert.ok(labels.some(label => label.includes('Unidad: %')));
    assert.ok(labels.some(label => label.includes('IoT físico')));
});

test('report metrics separate units and handle empty histories', () => {
    assert.equal(history.metricText({ by_unit: [{ unit: '%', average: 25 }, { unit: 'raw', average: 3184 }] }, 'average'), '25.00 % | 3184.00 raw');
    assert.equal(history.metricText({ by_unit: [] }, 'average'), '—');
});


test('history filters share the form and pagination follows the results', () => {
    const view = readFileSync(new URL('../../resources/views/history/index.blade.php', import.meta.url), 'utf8');
    const form = view.slice(view.indexOf('<form id="history-filter-form">'), view.indexOf('</form>'));
    for (const id of ['greenhouse', 'zone', 'metric', 'sensor', 'start-date', 'end-date', 'unit']) {
        assert.ok(form.includes(`id="history-${id}"`), id);
    }
    assert.ok(!form.includes('id="history-previous"'));
    assert.ok(view.indexOf('id="history-previous"') > view.indexOf('id="history-table-body"'));
    assert.ok(form.includes('id="history-search-button"'));
    for (const id of ['total', 'minimum', 'maximum', 'average']) assert.ok(view.includes(`id="summary-${id}"`));
});

test('mobile legends wrap long sensor names and stay clear of the axes', () => {
    const labels = [], lines = [];
    const canvas = { style: {}, getContext: () => ({
        setTransform() {}, clearRect() {}, beginPath() {}, moveTo: (x, y) => lines.push([x, y]), lineTo() {}, stroke() {}, arc() {}, fill() {},
        measureText: text => ({ width: text.length * 7 }),
        fillText: (text, x, y) => labels.push({ text, x, y }),
    }) };
    history.draw(canvas, [row('raw', 3184, 0, { sensor: { name: 'SensorExtremadamenteLargo'.repeat(5) } }),
        row('%', 25, 1, { provenance: 'manual_or_simulated', source_label: 'Simulacion / manual' })], 280, 300);
    assert.ok(labels.some(label => label.text.startsWith('Desde:')));
    assert.ok(labels.some(label => label.text.startsWith('Hasta:')));
    assert.ok(labels.filter(label => label.x === 34).length > 4);
    assert.ok(labels.filter(label => label.x === 34).every(label => label.text.length * 7 <= 232));
    assert.ok(parseInt(canvas.style.height) > 600);
    const titleY = labels.filter(label => label.text.startsWith('Unidad:')).map(label => label.y);
    assert.ok(titleY[1] > titleY[0] + 285);
    assert.ok(lines.length > 0);
});


test('pagination describes all four pages and empty results', () => {
    for (let page = 1; page <= 4; page++) {
        const text = history.paginationText({ page, last_page: 4, total: 82, from: (page - 1) * 25 + 1, to: Math.min(page * 25, 82) });
        assert.ok(text.includes(`${page} de 4`));
        assert.ok(text.includes(`de 82`));
        assert.ok(text.includes(String(Math.min(page * 25, 82))));
    }
    assert.ok(history.paginationText({ page: 1, last_page: 1, total: 0, from: null, to: null }).includes('Sin registros'));
});

test('page requests keep every selected filter and request 25 rows', () => {
    const view = readFileSync(new URL('../../resources/views/history/index.blade.php', import.meta.url), 'utf8');
    const start = view.indexOf('const params =');
    const requestCode = view.slice(start, view.indexOf('try {', start));
    const selected = { 'history-sensor': '7', 'history-unit': 'raw' };
    const sandbox = vm.createContext({ URLSearchParams,
        metricSelect: { value: 'soil_humidity' }, greenhouseSelect: { value: '2' }, zoneSelect: { value: '3' },
        startDateInput: { value: '2026-10-01' }, endDateInput: { value: '2026-10-23' },
        document: { getElementById: id => ({ value: selected[id] }) }, historyPage: 1 });
    const first = new URLSearchParams(vm.runInContext(`(() => { ${requestCode} return params.toString(); })()`, sandbox));
    sandbox.historyPage = 2;
    const second = new URLSearchParams(vm.runInContext(`(() => { ${requestCode} return params.toString(); })()`, sandbox));
    assert.equal(second.get('page'), '2');
    assert.equal(second.get('per_page'), '25');
    for (const key of ['metric', 'greenhouse_id', 'zone_id', 'sensor_id', 'unit', 'start_date', 'end_date']) {
        assert.ok(first.has(key), key);
        assert.equal(first.get(key), second.get(key), key);
    }
});


test('navigation buttons follow first middle and last page boundaries', () => {
    const view = readFileSync(new URL('../../resources/views/history/index.blade.php', import.meta.url), 'utf8');
    const start = view.indexOf('historyLastPage = result.pagination?.last_page');
    const end = view.indexOf('currentMetric =', start);
    for (const page of [1, 2, 4]) {
        const nodes = {};
        const sandbox = vm.createContext({ result: { pagination: { page, last_page: 4, total: 82 } },
            historyPage: page, historyLastPage: 1, window: { LumaMeasurementHistory: history },
            document: { getElementById: id => nodes[id] ??= {} } });
        vm.runInContext(view.slice(start, end), sandbox);
        assert.equal(nodes['history-previous'].disabled, page === 1);
        assert.equal(nodes['history-next'].disabled, page === 4);
    }
});


test('reduced real points stay disconnected across omitted readings', () => {
    const groups = history.series([row('raw', 3100, 0, { break_before: true }), row('raw', 3900, 1, { break_before: true }), row('raw', 3000, 20, { break_before: true })]);
    assert.equal(groups[0].segments.length, 3);
    assert.ok(groups[0].segments.every(segment => segment.length === 1));
});

test('history graph and resize use full period data independently of table data', () => {
    const view = readFileSync(new URL('../../resources/views/history/index.blade.php', import.meta.url), 'utf8');
    assert.ok(view.includes('currentChartData = result.chart?.data ?? []'));
    assert.ok(view.includes('drawChart(currentChartData)'));
    assert.ok(!/drawChart\(\s*currentHistoryData/.test(view));
    assert.ok(view.includes('currentChartPeriod = result.chart ?? {}'));
});
