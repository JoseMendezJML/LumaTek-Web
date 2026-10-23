import { IotSerial, supportError } from './iot-serial.js';

const panel = document.querySelector('#iot-serial');
if (panel) {
    const find = id => panel.querySelector(`#${id}`);
    const status = find('serial-status');
    const connect = find('serial-connect'), close = find('serial-close');
    const wifi = find('serial-wifi'), pair = find('serial-pair');
    const device = find('serial-device'), confirmation = find('serial-confirm');
    let client, busy = false, connected = false, paired = false;
    const show = message => { status.textContent = message; };
    const update = () => {
        connect.disabled = busy || connected || Boolean(supportError());
        close.disabled = busy || !connected;
        wifi.querySelector('button').disabled = busy || !connected;
        find('serial-ssid').disabled = busy;
        find('serial-password').disabled = busy;
        pair.disabled = busy || !connected || paired || !device.value || !confirmation.checked;
        device.disabled = busy; confirmation.disabled = busy;
    };
    const run = async action => {
        busy = true; update();
        try { await action(); }
        catch (error) { show(error?.name === 'NotFoundError' ? 'No se seleccionó un puerto USB.' : error?.name === 'SecurityError' ? 'El navegador bloqueó el puerto USB. Revisa los permisos y utiliza HTTPS.' : error.message || 'No se pudo completar la operación USB.'); }
        finally { busy = false; update(); }
    };
    const unsupported = supportError();
    show(unsupported || 'Conecta un ESP32 con el firmware experimental ya instalado. Reinícialo físicamente y selecciona el puerto dentro de tres minutos.');
    connect.addEventListener('click', () => run(async () => {
        const previous = client;
        client = new IotSerial({ serial: navigator.serial, onDisconnect: () => {
            connected = false; find('serial-password').value = ''; show('USB desconectado. Vuelve a conectar y seleccionar el puerto.'); update();
        } });
        const hello = await client.connect(() => previous?.close(false));
        connected = true; paired = hello.paired || hello.pairing_recovery_required;
        show(paired ? 'El equipo ya está vinculado o requiere recuperación. Revoca la vinculación anterior desde Zonas y sensores antes de repetir.' :
            `Puerto conectado. Configura Wi-Fi o vincula si la red y hora ya están listas. ${hello.persistent_storage ? 'Almacenamiento cifrado disponible.' : 'Solo RAM: Wi-Fi e identidad se perderán al cortar la alimentación.'}`);
    }));
    wifi.addEventListener('submit', event => {
        event.preventDefault();
        if (busy || !connected) return;
        const ssid = find('serial-ssid').value, password = find('serial-password').value;
        find('serial-password').value = '';
        run(async () => {
            show('Validando Wi-Fi, hora y acceso HTTPS desde el ESP32…');
            const result = await client.wifi(ssid, password);
            show(result === 'ready_persisted' ? 'Wi-Fi validado y guardado en el ESP32.' : 'Wi-Fi validado solo en RAM. Se perderá al cortar la alimentación.');
        });
    });
    const approve = async (id, proof) => {
        const token = sessionStorage.getItem('lumatek_access_token');
        if (!token) throw new Error('La sesión administrativa caducó. Inicia sesión nuevamente.');
        let response;
        try {
            response = await fetch(`/api/devices/${encodeURIComponent(id)}/pairing`, {
                method: 'POST', mode: 'same-origin', credentials: 'same-origin', redirect: 'error', cache: 'no-store',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
                body: JSON.stringify(proof), signal: AbortSignal.timeout(15000),
            });
        } catch { throw new Error('No se confirmó la aprobación. Revisa la vinculación pendiente en Zonas y sensores antes de repetir.'); }
        // Never display or log arbitrary response bodies.
        if (!response.ok) throw new Error(response.status === 422 ? 'Desafío inválido o caducado; vinculación rechazada.' :
            response.status === 403 ? 'Vinculación rechazada: necesitas permisos de administrador y un dispositivo activo de tu empresa.' :
            response.status === 401 ? 'La sesión administrativa caducó. Inicia sesión nuevamente.' :
            response.status === 409 ? 'Vinculación rechazada: revoca primero la vinculación anterior o pendiente en Zonas y sensores.' :
            response.status === 429 ? 'Demasiados intentos. Espera antes de iniciar otra vinculación.' : 'Vinculación rechazada por el servidor. Revisa la vinculación pendiente antes de repetir.');
    };
    pair.addEventListener('click', () => {
        if (busy || !connected || !confirmation.checked || !device.value) return;
        const id = device.value;
        run(async () => {
            show('Solicitando un desafío al ESP32 y autorizando el dispositivo seleccionado…');
            const result = await client.pair(id, approve, show);
            paired = true; confirmation.checked = false;
            show(result === 'paired_persisted' ? 'Vinculación completada y guardada en el ESP32. Verifica configuración y transmisión en Zonas y sensores.' :
                'Vinculación completada solo en RAM. La identidad se perderá al cortar la alimentación; revoca la vinculación antes de repetir. Verifica configuración y transmisión.');
        });
    });
    close.addEventListener('click', () => run(async () => {
        await client.close(); connected = false; confirmation.checked = false;
        find('serial-password').value = ''; find('serial-ssid').value = '';
        show('Sesión USB cerrada. Para abrir otra, reinicia físicamente el ESP32. Una aprobación abandonada puede requerir revocación en Zonas y sensores.');
    }));
    device.addEventListener('change', () => { confirmation.checked = false; update(); });
    confirmation.addEventListener('change', update);
    window.addEventListener('pagehide', () => {
        find('serial-password').value = ''; find('serial-ssid').value = '';
        connected = false; client?.close(); update();
    });
    update();
}
