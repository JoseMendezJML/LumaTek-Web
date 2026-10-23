import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const view = (name) => readFileSync(new URL(`../../resources/views/${name}/index.blade.php`, import.meta.url), 'utf8');
const dashboard = view('dashboard');
const greenhouses = view('greenhouses');
const metricFunction = dashboard.slice(dashboard.indexOf('    function metricHtml('), dashboard.indexOf('    function renderRecentAlerts('));
const soilFunction = greenhouses.slice(greenhouses.indexOf('    async function loadSoilMoisture('), greenhouses.indexOf('    async function loadSoilHistory('));

function metricHtml(metric) {
    const context = vm.createContext({ escapeHtml: (value) => String(value ?? '') });
    vm.runInContext(metricFunction, context);
    return context.metricHtml('', 'Humedad del suelo', metric);
}

async function soilCard(data, reject = false) {
    const elements = {};
    for (const selector of ['.soil-value', '.soil-connection', '.soil-detail', '.soil-level', '.soil-alert']) {
        const classes = new Set(['show']);
        elements[selector] = { textContent: '', className: '', classList: {
            add: (name) => classes.add(name), remove: (name) => classes.delete(name), contains: (name) => classes.has(name),
        } };
    }
    const context = vm.createContext({
        token: 'test-only', logoutIfUnauthorized: () => false,
        lastReadingText: (minutes) => `Hace ${minutes} minutos`,
        fetch: async () => {
            if (reject) throw new Error('offline');
            return { ok: true, json: async () => ({ data }) };
        },
    });
    vm.runInContext(soilFunction, context);
    await context.loadSoilMoisture(123, { querySelector: (selector) => elements[selector] });
    return elements;
}

test('dashboard renders integer RAW, calibration label and physical source without percent', () => {
    const html = metricHtml({ value: 3185, unit: 'raw', status: 'pending_calibration', status_label: 'Pendiente de calibración', source_label: 'IoT físico', connection_status: 'connected', connection_label: 'Conectado' });
    assert.ok(html.includes('3185 raw'));
    assert.ok(html.includes('Pendiente de calibración'));
    assert.ok(html.includes('IoT físico'));
    assert.ok(!html.includes('%'));
    assert.ok(!html.includes('metric-status-high'));
});

test('greenhouse RAW is neutral, displays source, and hides percentage warning', async () => {
    const card = await soilCard({ humidity: 3185, unit: 'raw', level: 'pending_calibration', level_label: 'Pendiente de calibración', source_label: 'IoT físico', connection_status: 'connected', connection_label: 'Conectado', minutes_since_last_reading: 0 });
    assert.equal(card['.soil-value'].textContent, '3185 raw');
    assert.equal(card['.soil-level'].textContent, 'Pendiente de calibración');
    assert.equal(card['.soil-level'].className, 'soil-level soil-level-neutral');
    assert.equal(card['.soil-alert'].classList.contains('show'), false);
    assert.ok(card['.soil-detail'].textContent.includes('IoT físico'));
});

test('percentage keeps its unit and low indicator while identifying simulation', async () => {
    const card = await soilCard({ humidity: 25, unit: '%', level: 'low', level_label: 'Baja', source_label: 'Simulación / manual', connection_status: 'simulated', connection_label: 'Simulación / manual', minutes_since_last_reading: 1 });
    assert.equal(card['.soil-value'].textContent, '25.0 %');
    assert.equal(card['.soil-level'].className, 'soil-level soil-level-low');
    assert.equal(card['.soil-alert'].classList.contains('show'), true);
    assert.equal(card['.soil-connection'].textContent, 'Simulación / manual');
    assert.ok(!card['.soil-connection'].className.includes('connection-connected'));
});

test('RAW without readings keeps RAW placeholder and calibration label', async () => {
    const card = await soilCard({ humidity: null, unit: 'raw', level_label: 'Pendiente de calibración', connection_status: 'no_data' });
    assert.equal(card['.soil-value'].textContent, '-- raw');
    assert.equal(card['.soil-level'].textContent, 'Pendiente de calibración');
    assert.equal(card['.soil-alert'].classList.contains('show'), false);
});

test('network failure does not invent a percentage or reference unavailable response data', async () => {
    const card = await soilCard(null, true);
    assert.equal(card['.soil-value'].textContent, '--');
    assert.equal(card['.soil-level'].textContent, 'Error');
});

for (const [name, prefix, next] of [['loadTemperature', 'temperature', 'loadSoilMoisture'], ['loadAmbientHumidity', 'ambient', 'loadGreenhouses']]) {
    test(`${prefix} does not present simulation as a connected physical sensor`, async () => {
        const source = greenhouses.slice(greenhouses.indexOf(`    async function ${name}(`), greenhouses.indexOf(`    async function ${next}(`));
        const elements = {};
        const context = vm.createContext({
            token: 'test-only', logoutIfUnauthorized: () => false, lastReadingText: () => 'Última lectura',
            fetch: async () => ({ json: async () => ({ data: {
                temperature: 24.1, humidity: 75, level: 'normal', status: 'normal', minutes_since_last_reading: 0,
                connection_status: 'simulated', connection_label: 'Simulación / manual', source_label: 'Simulación / manual',
            } }) }),
        });
        vm.runInContext(source, context);
        const card = { querySelector: (selector) => elements[selector] ??= {
            textContent: '', className: '', classList: { add() {}, remove() {} },
        } };
        await context[name](123, card);
        assert.equal(elements[`.${prefix}-connection`].textContent, 'Simulación / manual');
        assert.ok(!elements[`.${prefix}-connection`].className.includes('connection-connected'));
        assert.ok(elements[`.${prefix}-detail`].textContent.includes('Simulación / manual'));
    });
}

test('history labels require server-derived provenance instead of trusting source=iot', () => {
    const history = view('history');
    const source = history.slice(history.indexOf('        function formatSource('), history.indexOf('        function escapeHtml('));
    const context = vm.createContext({});
    vm.runInContext(source, context);
    assert.equal(context.formatSource({ source: 'iot' }), 'Origen no verificado');
    assert.equal(context.formatSource({ source: 'iot', source_label: 'IoT no verificado' }), 'IoT no verificado');
    assert.equal(context.formatSource({ source: 'iot', source_label: 'IoT físico' }), 'IoT físico');
});


test('dashboard retains stale physical values and shows their original measurement date', () => {
    const html = metricHtml({ value: 3184, unit: 'raw', connection_status: 'disconnected', connection_label: 'Desconectado',
        measurement_label: 'Medicion antigua', recorded_at: '2026-10-23 12:00:00', source_label: 'IoT fisico' });
    for (const text of ['3184 raw', 'Desconectado', 'Medicion antigua', '2026-10-23 12:00:00']) assert.ok(html.includes(text));
});

test('greenhouse keeps readings visible during missing communication', async () => {
    const card = await soilCard({ humidity: 3184, unit: 'raw', level_label: 'Pendiente de calibracion',
        connection_status: 'stale', connection_label: 'Sin comunicacion reciente', measurement_label: 'Medicion antigua',
        recorded_at: '2026-10-23 12:00:00', minutes_since_last_reading: 7 });
    assert.equal(card['.soil-value'].textContent, '3184 raw');
    assert.equal(card['.soil-connection'].textContent, 'Sin comunicacion reciente');
    assert.ok(card['.soil-detail'].textContent.includes('2026-10-23 12:00:00'));
    assert.ok(card['.soil-detail'].textContent.includes('Medicion antigua'));
});
