import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { IotSerial, supportError } from '../../public/js/iot-serial.js';

function simulated(overrides = {}) {
    let controller, clock = 0;
    const commands = [], sleeps = [];
    const emit = frame => controller.enqueue(new TextEncoder().encode(JSON.stringify({ protocol: 1, ...frame }) + '\n'));
    const hello = { event: 'hello', board_model: 'esp32_devkit_v1', version: '0.1.0-experimental', window_open: true, profile_ready: true, persistent_storage: false, nonce: 'usb-local-nonce', ...overrides.hello };
    const port = {
        readable: new ReadableStream({ start(value) { controller = value; } }),
        writable: new WritableStream({ write(bytes) {
            const command = JSON.parse(new TextDecoder().decode(bytes));
            commands.push(command);
            if (command.cmd === 'hello') {
                controller.enqueue(new TextEncoder().encode('ROM boot text\r\n' + 'x'.repeat(3000) + '\n'));
                // Fragmentation, CRLF and UTF-8 parsing are exercised by all handshakes.
                const data = new TextEncoder().encode(JSON.stringify({ protocol: 1, ...hello }) + '\r\n');
                controller.enqueue(data.slice(0, 7)); controller.enqueue(data.slice(7));
            } else if (command.cmd === 'wifi.set') {
                emit({ event: 'wifi', status: 'validating_candidate' });
                emit({ event: 'wifi', status: overrides.wifi || 'ready_ram_only' });
            } else if (command.cmd === 'pair.start') {
                emit(overrides.start || { event: 'pairing.challenge', pairing_id: '12345678-1234-1234-1234-123456789abc', operator_proof: 'a'.repeat(48), expires_in: 600 });
            } else if (command.cmd === 'pair.exchange') emit({ event: 'pairing', status: overrides.exchange || 'paired_ram_only' });
            else if (command.cmd === 'session.close') emit({ event: 'session', status: 'closed' });
        } }),
        async open(options) { assert.equal(options.baudRate, 115200); },
        async close() { port.closed = true; },
    };
    const events = new Map();
    const serial = { async requestPort() { serial.requests = (serial.requests || 0) + 1; return port; }, addEventListener: (event, fn) => events.set(event, fn), removeEventListener: event => events.delete(event) };
    const client = new IotSerial({ serial, now: () => clock, sleep: async ms => { sleeps.push(ms); clock += ms; } });
    return { client, commands, sleeps, port, serial, emit, advance: ms => { clock += ms; }, disconnect: () => { events.get('disconnect')?.({ target: port }); controller.close(); } };
}

test('requires HTTPS even on localhost, secure context and Web Serial', () => {
    assert.match(supportError({ location: { protocol: 'http:' }, isSecureContext: true, navigator: { serial: {} } }), /HTTPS/);
    assert.match(supportError({ location: { protocol: 'https:' }, isSecureContext: true, navigator: {} }), /incompatible/);
    assert.equal(supportError({ location: { protocol: 'https:' }, isSecureContext: true, navigator: { serial: {} } }), '');
});

test('simulated USB: explicit port, NDJSON hello, Wi-Fi, operator-only approval, private exchange and close', async () => {
    const f = simulated();
    assert.equal(f.serial.requests, undefined);
    const hello = await f.client.connect();
    assert.equal(hello.persistent_storage, false);
    assert.equal(await f.client.wifi('Red México', 'clave-prueba'), 'ready_ram_only');
    const approvals = [];
    assert.equal(await f.client.pair('12', async (id, proof) => { approvals.push({ id, proof }); f.advance(2000); }), 'paired_ram_only');
    assert.equal(approvals[0].id, '12');
    assert.deepEqual(Object.keys(approvals[0].proof).sort(), ['operator_proof', 'pairing_id']);
    assert.deepEqual(f.sleeps, [9000]);
    assert.deepEqual(f.commands.at(-1), { cmd: 'pair.exchange', nonce: 'usb-local-nonce' });
    assert.equal(f.client.challenge, null);
    await f.client.close();
    assert.equal(f.commands.at(-1).cmd, 'session.close');
    assert.equal(f.port.closed, true);
    assert.equal(f.client.nonce, null);
    assert.equal(f.serial.requests, 1);
});

test('Wi-Fi uses byte lengths, rejects NUL, never truncates or writes invalid credentials', async () => {
    const f = simulated(); await f.client.connect();
    for (const [ssid, password] of [['é'.repeat(17), 'password'], ['red', 'é'.repeat(32)], ['red\0evil', 'password'], ['red', 'short']]) {
        await assert.rejects(f.client.wifi(ssid, password), /inválidos/);
    }
    assert.equal(f.commands.length, 1);
    await f.client.close();
});

test('blocks wrong firmware, closed window and missing HTTPS profile', async () => {
    for (const hello of [{ board_model: 'other' }, { version: 'other' }, { window_open: false }, { profile_ready: false }]) {
        const f = simulated({ hello });
        await assert.rejects(f.client.connect());
        assert.equal(f.port.closed, true);
        assert.equal(f.client.nonce, null);
    }
});

test('expired local session never writes another command', async () => {
    const f = simulated(); await f.client.connect(); f.advance(180000);
    await assert.rejects(f.client.wifi('red', 'password'), /caducó/);
    assert.equal(f.commands.length, 1); await f.client.close(false);
});

test('challenge expiration and rejected approval never exchange', async () => {
    for (const expired of [true, false]) {
        const f = simulated(); await f.client.connect();
        await assert.rejects(f.client.pair('1', async () => {
            if (expired) f.advance(600000); else throw new Error('Vinculación rechazada');
        }), expired ? /caducado/ : /rechazada/);
        assert.equal(f.commands.some(c => c.cmd === 'pair.exchange'), false);
        assert.equal(f.client.challenge, null); await f.client.close(false);
    }
});

test('device statuses clearly report rejection, expiration, network and recovery without automatic retry', async () => {
    for (const [status, message] of [['not_approved_or_rejected', /rechazada/], ['challenge_required', /caducado/], ['revoke_and_pair_again', /incierto/], ['retry_later', /11 segundos/]]) {
        const f = simulated({ exchange: status }); await f.client.connect();
        await assert.rejects(f.client.pair('1', async () => {}), message);
        assert.equal(f.commands.filter(c => c.cmd === 'pair.exchange').length, 1);
        await f.client.close();
    }
    const f = simulated({ wifi: 'candidate_failed_previous_record_retained' }); await f.client.connect();
    await assert.rejects(f.client.wifi('red', 'password'), /No se validó/); await f.client.close();
});

test('unplugging rejects the active request and clears local session', async () => {
    const f = simulated(); await f.client.connect();
    const waiting = f.client.request('never', { cmd: 'hello' });
    f.disconnect();
    await assert.rejects(waiting, /USB desconectado/);
    assert.equal(f.client.nonce, null); await f.client.close(false);
});

test('timeout closes port and prevents late responses being reused', async () => {
    const f = simulated(); await f.client.connect();
    await assert.rejects(f.client.request('never', { cmd: 'hello' }, 5), /no respondió a tiempo/);
    assert.equal(f.port.closed, true);
    assert.equal(f.client.nonce, null);
    await assert.rejects(f.client.wifi('red', 'password'), /USB desconectado/);
});

test('disconnect during approval never writes exchange after the wait', async () => {
    const f = simulated(); await f.client.connect();
    await assert.rejects(f.client.pair('1', async () => f.disconnect()), /USB desconectado/);
    assert.equal(f.commands.some(c => c.cmd === 'pair.exchange'), false);
    await f.client.close(false);
});

test('UI has no device-secret HTTP endpoints, persistence, logging or automatic port enumeration', () => {
    const ui = readFileSync(new URL('../../public/js/iot-serial-ui.js', import.meta.url), 'utf8');
    assert.doesNotMatch(ui, /exchange_secret|\/api\/iot\/pairings|localStorage|sessionStorage\.setItem|console\.|getPorts\(/);
    assert.match(ui, /serial-connect/); assert.match(ui, /password'\)\.value = ''/);
    assert.match(ui, /redirect: 'error'/);
});

test('UI simulated device: user click selects port, clears Wi-Fi password and sends only operator proof to authenticated approval', async () => {
    const f = simulated();
    const elements = new Map();
    const element = id => {
        if (!elements.has(id)) elements.set(id, {
            value: '', checked: false, disabled: true, textContent: '', listeners: {},
            addEventListener(event, handler) { this.listeners[event] = handler; },
            querySelector(selector) { return element(`${id}:${selector}`); },
        });
        return elements.get(id);
    };
    const saved = new Map(['document', 'window', 'location', 'isSecureContext', 'navigator', 'sessionStorage', 'fetch'].map(key => [key, Object.getOwnPropertyDescriptor(globalThis, key)]));
    const install = (key, value) => Object.defineProperty(globalThis, key, { value, configurable: true });
    const calls = [];
    install('document', { querySelector: () => ({ querySelector: selector => element(selector.slice(1)) }) });
    install('window', { addEventListener() {} });
    install('location', { protocol: 'https:' }); install('isSecureContext', true);
    install('navigator', { serial: f.serial });
    install('sessionStorage', { getItem: () => 'operator-jwt-test-only' });
    install('fetch', async (url, options) => { calls.push({ url, options }); return { ok: false, status: 422 }; });
    const settle = async predicate => {
        for (let i = 0; i < 100 && !predicate(); i++) await new Promise(resolve => setImmediate(resolve));
        assert.ok(predicate(), 'UI operation must settle');
    };
    try {
        await import('../../public/js/iot-serial-ui.js?ui-simulation');
        assert.equal(f.serial.requests, undefined);
        assert.equal(element('serial-connect').disabled, false);
        await element('serial-connect').listeners.click();
        assert.equal(f.serial.requests, 1);
        element('serial-ssid').value = 'red-local'; element('serial-password').value = 'password-test';
        element('serial-wifi').listeners.submit({ preventDefault() {} });
        assert.equal(element('serial-password').value, '');
        await settle(() => element('serial-status').textContent.includes('Wi-Fi validado'));
        element('serial-device').value = '42'; element('serial-confirm').checked = true;
        element('serial-confirm').listeners.change();
        assert.equal(element('serial-pair').disabled, false);
        element('serial-pair').listeners.click();
        await settle(() => element('serial-status').textContent.includes('caducado'));
        assert.equal(calls.length, 1);
        assert.equal(calls[0].url, '/api/devices/42/pairing');
        assert.equal(calls[0].options.headers.Authorization, 'Bearer operator-jwt-test-only');
        assert.deepEqual(Object.keys(JSON.parse(calls[0].options.body)).sort(), ['operator_proof', 'pairing_id']);
        assert.equal(f.commands.some(c => c.cmd === 'pair.exchange'), false);
        await element('serial-close').listeners.click();
        assert.equal(f.port.closed, true);
        assert.equal(element('serial-ssid').value, '');
    } finally {
        for (const [key, descriptor] of saved) {
            if (descriptor) Object.defineProperty(globalThis, key, descriptor); else delete globalThis[key];
        }
    }
});
