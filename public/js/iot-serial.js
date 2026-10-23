// Application provisioning only. Never flashes firmware or exchanges device secrets.
const encoder = new TextEncoder();
const messages = {
    closed_or_wrong_session: 'La sesión USB caducó. Reinicia físicamente el ESP32 y vuelve a seleccionar el puerto.',
    challenge_required: 'Desafío caducado. Inicia una nueva sesión y vinculación.',
    not_approved_or_rejected: 'Vinculación rechazada o desafío caducado. Revisa la autorización y revoca la vinculación pendiente antes de repetir.',
    revoke_existing_binding_first: 'Revoca primero la vinculación anterior desde Zonas y sensores.',
    revoke_and_pair_again: 'Resultado del canje incierto. Revoca la vinculación en Zonas y sensores antes de repetir.',
    retry_later: 'El ESP32 exige esperar 11 segundos entre intentos. Vuelve a iniciar la sesión si es necesario.',
    candidate_failed_previous_record_retained: 'No se validó Wi-Fi, hora o HTTPS. Se conservó el registro anterior.',
    production_profile_or_ram_storage_required: 'El firmware necesita un perfil HTTPS válido y almacenamiento Wi-Fi en RAM.',
    network_time_or_profile_unavailable: 'El ESP32 no tiene red, hora o perfil HTTPS disponible.',
    challenge_failed: 'No se pudo obtener el desafío por HTTPS. Revisa el perfil, la red y los límites del servidor.',
    invalid_parameters: 'SSID o contraseña Wi-Fi inválidos.',
};
const failure = status => new Error(messages[status] || 'El ESP32 rechazó el comando o devolvió una respuesta incompatible.');

export function supportError(environment = globalThis) {
    if (environment.location?.protocol !== 'https:' || !environment.isSecureContext) return 'Abre esta página por HTTPS para configurar el ESP32.';
    if (!environment.navigator?.serial) return 'Navegador incompatible con Web Serial. Utiliza un navegador de escritorio compatible, como Chrome o Edge.';
    return '';
}

export class IotSerial {
    constructor({ serial, now = () => performance.now(), sleep = ms => new Promise(resolve => setTimeout(resolve, ms)), onDisconnect = () => {} }) {
        Object.assign(this, { serial, now, sleep, onDisconnect });
        this.disconnected = event => { if (event.target === this.port || event.port === this.port) this.lost(); };
    }
    async connect(beforeOpen = async () => {}) {
        // Must be invoked directly from the user's click; no getPorts/autoconnect.
        this.port = await this.serial.requestPort();
        try {
            this.usbDisconnected = false;
            await beforeOpen();
            await this.port.open({ baudRate: 115200 });
            this.serial.addEventListener('disconnect', this.disconnected);
            this.reader = this.port.readable.getReader();
            this.writer = this.port.writable.getWriter();
            this.readTask = this.read();
            const hello = await this.request('hello', { cmd: 'hello' }, 10000);
            if (hello.board_model !== 'esp32_devkit_v1' || hello.version !== '0.1.0-experimental') throw failure();
            if (!hello.window_open || typeof hello.nonce !== 'string' || !hello.nonce || hello.nonce.length > 100) throw failure('closed_or_wrong_session');
            if (!hello.profile_ready) throw failure('production_profile_or_ram_storage_required');
            this.nonce = hello.nonce;
            this.deadline = this.now() + 180000;
            return hello;
        } catch (error) { await this.close(false); throw error; }
    }
    lost() {
        this.usbDisconnected = true;
        this.nonce = null;
        this.challenge = null;
        this.pending?.reject(new Error('USB desconectado. Conecta el equipo y abre una nueva sesión.'));
        this.onDisconnect();
    }
    async read() {
        const decoder = new TextDecoder();
        let line = '', discard = false;
        try {
            while (true) {
                const { value, done } = await this.reader.read();
                if (done) break;
                for (const char of decoder.decode(value, { stream: true })) {
                    if (char === '\n') {
                        if (!discard) {
                            let frame;
                            try { frame = JSON.parse(line); } catch { /* ROM boot text is not protocol. */ }
                            if (frame?.protocol === 1 && this.pending && (frame.event === this.pending.event || frame.event === 'protocol' || (this.pending.event === 'pairing.challenge' && frame.event === 'pairing'))) {
                                this.pending.resolve(frame);
                            }
                        }
                        line = ''; discard = false;
                    } else if (char !== '\r' && !discard) {
                        line += char;
                        if (line.length > 2048) { line = ''; discard = true; }
                    }
                }
            }
        } catch { /* No raw serial data or browser exception is logged. */ }
        finally { if (!this.closing) this.lost(); }
    }
    async request(event, command, timeout = 15000, accept = () => true) {
        if (this.usbDisconnected) throw new Error('USB desconectado. Conecta el equipo y abre una nueva sesión.');
        if (this.pending) throw new Error('Ya hay una operación USB en curso.');
        if (command.cmd !== 'hello') {
            if (!this.nonce || this.now() >= this.deadline) throw failure('closed_or_wrong_session');
            command = { ...command, nonce: this.nonce };
        }
        const bytes = encoder.encode(JSON.stringify(command) + '\n');
        if (bytes.length > 1025) throw failure();
        let timer;
        const response = new Promise((resolve, reject) => {
            this.pending = { event, reject, resolve: frame => {
                if (accept(frame)) resolve(frame);
            } };
            timer = setTimeout(() => reject(new Error('El ESP32 no respondió a tiempo. Revisa USB y reinicia la sesión; si hubo aprobación, revisa o revoca la vinculación pendiente.')), timeout);
        });
        // Observe rejection even if a physical disconnect occurs during write.
        response.catch(() => {});
        try {
            await this.writer.write(bytes);
            return await response;
        } catch (error) {
            // A late reply cannot be matched safely after an uncertain operation.
            this.lost();
            await this.close(false);
            throw new Error(error?.message?.startsWith('El ESP32') || error?.message?.startsWith('USB desconectado') ? error.message : 'No se pudo comunicar por USB. Desconecta y vuelve a seleccionar el puerto.');
        } finally { bytes.fill(0); clearTimeout(timer); this.pending = null; }
    }
    async wifi(ssid, password) {
        const ssidBytes = encoder.encode(ssid), passwordBytes = encoder.encode(password);
        const valid = ssidBytes.length >= 1 && ssidBytes.length <= 32 && passwordBytes.length >= 8 && passwordBytes.length <= 63 && !ssid.includes('\0') && !password.includes('\0');
        ssidBytes.fill(0); passwordBytes.fill(0);
        if (!valid) throw failure('invalid_parameters');
        const frame = await this.request('wifi', { cmd: 'wifi.set', ssid, password }, 75000, frame => frame.status !== 'validating_candidate');
        if (!['ready_persisted', 'ready_ram_only'].includes(frame.status)) throw failure(frame.status);
        return frame.status;
    }
    async pair(deviceId, approve, progress = () => {}) {
        this.challenge = null;
        const frame = await this.request('pairing.challenge', { cmd: 'pair.start' });
        if (frame.event !== 'pairing.challenge') throw failure(frame.status);
        if (!/^[0-9a-f-]{36}$/i.test(frame.pairing_id) || !/^[0-9a-f]{48}$/i.test(frame.operator_proof) || frame.expires_in !== 600) throw failure();
        const received = this.now();
        const expires = received + frame.expires_in * 1000;
        this.challenge = { pairing_id: frame.pairing_id, operator_proof: frame.operator_proof };
        try {
            await approve(deviceId, this.challenge);
            progress('Autorizado por la empresa. Esperando el intervalo de seguridad del ESP32…');
            // Start's HTTP call can complete later than its timestamp; wait from receipt.
            await this.sleep(Math.max(0, 11000 - (this.now() - received)));
            if (this.now() >= expires) throw failure('challenge_required');
            const result = await this.request('pairing', { cmd: 'pair.exchange' });
            if (!['paired_persisted', 'paired_ram_only'].includes(result.status)) throw failure(result.status);
            return result.status;
        } finally { this.challenge = null; }
    }
    async close(notify = true) {
        if (this.closing) return;
        this.closing = true;
        try {
            if (notify && this.nonce && !this.pending) {
                try { await this.request('session', { cmd: 'session.close' }, 1500); } catch { /* Best effort; physical window still closes. */ }
            }
            this.pending?.reject(new Error('Sesión USB cerrada.'));
            await this.reader?.cancel().catch(() => {});
            await this.readTask;
            this.reader?.releaseLock();
            this.writer?.releaseLock();
            await this.port?.close().catch(() => {});
        } finally {
            this.serial.removeEventListener('disconnect', this.disconnected);
            this.nonce = null; this.challenge = null; this.port = null;
            this.reader = null; this.writer = null; this.closing = false;
        }
    }
}
