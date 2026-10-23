@extends('layouts.app')
@section('title', 'Instalación IoT | LumaTek')
@section('page-title', 'Instalación IoT')
@section('page-subtitle', 'Guía de instalación y vinculación de dispositivos compatibles')

@section('content')
<div class="iot-guide">
    <section class="iot-card iot-intro">
        <div><span class="iot-eyebrow">MANUAL DE DISPOSITIVOS</span><h2>Conecta tu invernadero a LumaTek</h2>
            <p>Sigue estos seis pasos con un ESP32 compatible. El registro en la web y la instalación del equipo son procesos distintos.</p></div>
        <a class="iot-button" href="{{ route('zones.index') }}">Volver a Zonas y sensores</a>
    </section>

    <nav class="iot-steps" aria-label="Pasos de instalación">
        @foreach(['Registrar dispositivo', 'Instalar firmware', 'Configurar Wi-Fi', 'Vincular dispositivo', 'Registrar sensores', 'Verificar transmisión'] as $step)
            <a href="#step-{{ $loop->iteration }}"><span>{{ $loop->iteration }}</span>{{ $step }}</a>
        @endforeach
    </nav>

    <section class="iot-card" aria-labelledby="hardware-title">
        <h2 id="hardware-title">Equipo compatible probado</h2>
        <p><strong>ESP32 DevKit V1</strong> · modelo de configuración <code>esp32_devkit_v1</code> · conexión Wi-Fi. Confirma el modelo y la serigrafía de tu placa antes de cablear; esta guía no acredita otras variantes DevKit.</p>
        <div class="iot-table"><table><caption>Sensores y pines probados</caption><thead><tr><th>Medición</th><th>Sensor</th><th>Entrada</th><th>Unidad</th></tr></thead><tbody>
            <tr><td>Temperatura</td><td>DHT22</td><td>GPIO4</td><td>°C</td></tr>
            <tr><td>Humedad ambiental</td><td>DHT22</td><td>GPIO4, compartido con temperatura</td><td>%</td></tr>
            <tr><td>Humedad del suelo</td><td>Analógico · analog_soil</td><td>GPIO34</td><td>raw, pendiente de calibración</td></tr>
        </tbody></table></div>
        <p>El mismo DHT22 se registra como dos sensores lógicos. GPIO34 recibe la señal analógica del sensor de suelo. Verifica alimentación, tierra común y compatibilidad eléctrica de cada módulo antes de energizarlo; no conectes una señal de 5 V a una entrada de 3,3 V.</p>
    </section>

    <div class="iot-step-grid">
        <section id="step-1" class="iot-card iot-step"><span class="iot-number">1</span><h2>Registrar dispositivo</h2>
            <p>En <a href="{{ route('zones.index') }}">Zonas y sensores</a>, selecciona un invernadero y una zona de tu empresa. Registra el dispositivo con modelo ESP32 DevKit V1, código único y conexión Wi-Fi.</p>
            <p>Comprueba que invernadero, zona y dispositivo estén activos y disponibles en tu plan. «Activo» permite administrarlo; no significa que esté conectado.</p>
        </section>
        <section id="step-2" class="iot-card iot-step"><span class="iot-number">2</span><h2>Instalar firmware</h2>
            <p>Utiliza únicamente una versión publicada y aprobada para tu modelo. El archivo <code>.ino</code> es código fuente para Arduino IDE; el <code>.bin</code> es una imagen compilada y requiere las instrucciones y parámetros de su versión.</p>
            <p class="iot-notice">Actualmente no hay firmware de distribución aprobado. No uses el firmware de laboratorio como instalador de producción. Consulta el catálogo antes de continuar.</p>
            <a href="#firmware-catalog">Consultar versiones y disponibilidad</a>
        </section>
        <section id="step-3" class="iot-card iot-step"><span class="iot-number">3</span><h2>Configurar Wi-Fi</h2>
            <p>Usa el asistente USB experimental de esta página con el firmware de distribución ya instalado. La contraseña se envía únicamente al ESP32 por USB; no se envía a Laravel ni se guarda en el navegador.</p>
            <a href="#iot-serial">Abrir asistente de configuración USB</a>
        </section>
        <section id="step-4" class="iot-card iot-step"><span class="iot-number">4</span><h2>Vincular dispositivo</h2>
            <p>En el asistente USB, selecciona un dispositivo registrado de tu empresa y confirma su vinculación. El ESP32 obtiene el desafío por HTTPS; la web aprueba únicamente su prueba temporal. El equipo realiza el canje privado y recibe su credencial sin pasarla al navegador.</p>
            <p>No necesitas el monitor serial. El desafío caduca y la ventana USB termina tres minutos después del reinicio físico. Si abandonas una aprobación o pierdes el resultado del canje, revisa y revoca la vinculación pendiente desde Zonas y sensores antes de repetir.</p>
            <a href="#iot-serial">Abrir asistente de vinculación</a>
        </section>
        <section id="step-5" class="iot-card iot-step"><span class="iot-number">5</span><h2>Registrar sensores</h2>
            <p>En el dispositivo correspondiente, registra temperatura y humedad ambiental como DHT22 en GPIO4. Añade humedad del suelo como analog_soil en GPIO34 con unidad <code>raw</code> mientras no exista calibración.</p>
            <p>Los identificadores los asigna LumaTek; no copies IDs de otro equipo. Verifica tipo, unidad, pin y estado. Los límites de sensores de tu plan siguen aplicándose.</p>
            <p>RAW no es porcentaje. Se mostrará «Pendiente de calibración» y no debe interpretarse como una alerta porcentual ni como una orden de riego.</p>
        </section>
        <section id="step-6" class="iot-card iot-step"><span class="iot-number">6</span><h2>Verificar transmisión</h2>
            <p>Revisa en Zonas y sensores la última comunicación autenticada. En el resumen del invernadero y en Historial, comprueba los valores, unidades, fechas y la procedencia «IoT físico».</p>
            <p>Una simulación o un registro «IoT no verificado» no acredita transmisión física. Conserva la última lectura como referencia si el equipo pierde conexión.</p>
            <p>Con intervalo de 300 segundos: conectado antes de 6 minutos, sin comunicación reciente entre 6 y menos de 11, y desconectado desde 11 minutos sin comunicación autenticada. Recarga la página para actualizar el estado.</p>
            <a href="{{ route('history.index') }}">Consultar Historial</a>
        </section>
    </div>

    <section class="iot-card" id="iot-serial" aria-labelledby="serial-title">
        <h2 id="serial-title">Asistente USB experimental · configuración y vinculación</h2>
        <p>Requiere HTTPS, un navegador de escritorio compatible con Web Serial y el firmware experimental 0.1.0 ya instalado en un ESP32 DevKit V1. Seleccionar el puerto requiere tu interacción. Este asistente configura el equipo; no instala firmware.</p>
        <p>La ventana USB dura tres minutos desde el reinicio físico. Sin almacenamiento cifrado, Wi-Fi e identidad quedan solo en RAM. La contraseña Wi-Fi se utiliza en memoria durante el envío y no se conserva en esta página.</p>
        <div class="iot-downloads">
            <button id="serial-connect" type="button" disabled>Seleccionar puerto USB</button>
            <button id="serial-close" type="button" disabled>Cerrar sesión USB</button>
        </div>
        <form id="serial-wifi" autocomplete="off">
            <p><label for="serial-ssid">Red Wi-Fi (SSID, 1–32 bytes)</label><br><input id="serial-ssid" type="text" required autocomplete="off" spellcheck="false"></p>
            <p><label for="serial-password">Contraseña WPA/WPA2 (8–63 bytes)</label><br><input id="serial-password" type="password" required autocomplete="off"></p>
            <button type="submit" disabled>Configurar Wi-Fi por USB</button>
        </form>
        <p><label for="serial-device">Dispositivo registrado de tu empresa</label><br>
            <select id="serial-device">
                <option value="">Selecciona un dispositivo sin vincular</option>
                @foreach($devices as $device)
                    <option value="{{ $device->id }}">{{ $device->name }} · {{ $device->device_code }}</option>
                @endforeach
            </select>
        </p>
        <p><label><input id="serial-confirm" type="checkbox"> Confirmo que este ESP32 corresponde al dispositivo seleccionado y autorizo vincularlo con mi empresa.</label></p>
        <button id="serial-pair" type="button" disabled>Vincular con mi empresa</button>
        <p id="serial-status" role="status" aria-live="polite" aria-atomic="true">Se necesita JavaScript y HTTPS para usar el asistente USB.</p>
    </section>

    <section class="iot-card" id="remote-config"><h2>Configuración remota e intervalo</h2>
        <p>El ESP32 vinculado descarga por HTTPS la configuración de sus sensores y el intervalo de medición mediante <code>GET /api/iot/config</code>, usando su credencial individual. Envía sus lotes a <code>POST /api/iot/readings</code>; no utiliza el JWT de un usuario.</p>
        <p>El intervalo se administra en la tarjeta del dispositivo. Un cambio se aplica cuando el firmware vuelve a consultar la configuración: no implica una actualización instantánea por parte de la web. Se ha comprobado el cambio de 60 a 300 segundos, aproximadamente una medición cada cinco minutos.</p>
        <p>El asistente permite configurar Wi-Fi y vincular; los sensores e intervalos se administran desde Zonas y sensores. Una comunicación reciente puede coexistir con una medición antigua; comprueba ambas fechas.</p>
    </section>

    <section class="iot-card" id="firmware-catalog"><h2>Catálogo de firmware</h2>
        <p>Solo las versiones publicadas, revisadas y aprobadas con archivos íntegros permiten descargas. Las versiones de laboratorio quedan fuera de distribución.</p>
        @forelse($releases as $release)
            <article class="iot-release"><h3>{{ $release['label'] ?? $release['model'] }}</h3>
                <dl><div><dt>Versión</dt><dd>{{ $release['version'] ?? 'Sin versión distribuible' }}</dd></div>
                    <div><dt>Fecha de versión</dt><dd>{{ $release['date'] ?? 'Sin fecha de publicación' }}</dd></div>
                    <div><dt>Estado</dt><dd>{{ ['planned' => 'En preparación', 'draft' => 'Borrador', 'published' => 'Publicada', 'withdrawn' => 'Retirada'][$release['status'] ?? ''] ?? 'No publicada' }}</dd></div></dl>
                <p>{{ $release['instructions'] ?? 'Instrucciones pendientes de aprobación.' }}</p>
                <div class="iot-downloads">
                    @foreach(['ino', 'bin'] as $format)
                        @if(in_array($format, $release['downloads'], true))
                            <a class="iot-button" href="{{ route('iot.firmware.download', ['release' => $release['id'], 'format' => $format]) }}">Descargar .{{ $format }}</a>
                        @else
                            <button type="button" disabled>Descarga .{{ $format }} no disponible</button>
                        @endif
                    @endforeach
                </div>
            </article>
        @empty
            <p>No hay versiones disponibles para tu empresa.</p>
        @endforelse
    </section>

    <section class="iot-card"><h2>Instalación USB desde el navegador</h2>
        <p>El futuro instalador utilizará ESP Web Tools. Requiere una imagen aprobada y un manifiesto válido que identifique el modelo y las imágenes con sus parámetros de escritura.</p>
        <button type="button" disabled aria-describedby="usb-pending">Instalar por USB · próximamente</button>
        <p id="usb-pending">Instalador desactivado: no hay firmware y manifiesto aprobados. El flasheo sigue desactivado. El asistente de configuración solicita el puerto solo al pulsar su botón; no carga ESP Web Tools.</p>
    </section>

    <section class="iot-card"><h2>Solución de problemas</h2>
        @foreach([
            'No conecta a Wi-Fi' => 'Comprueba cobertura, alimentación y los datos de la red configurada en el equipo. Introduce la contraseña únicamente en el formulario USB local; no la compartas en capturas de diagnóstico.',
            'Error de certificado HTTPS' => 'Comprueba fecha y hora del equipo, nombre del servidor y cadena de certificados de confianza del firmware. No desactives la validación del certificado. Solicita una revisión del firmware si la cadena de confianza está desactualizada.',
            'Dispositivo desconectado' => 'Comprueba USB o alimentación, Wi-Fi y acceso HTTPS. Confirma el intervalo configurado y recarga la página. «Activo» no equivale a «Conectado»; compara la última comunicación con la fecha de la última medición.',
            'Credencial revocada' => 'La credencial anterior deja de autenticar al equipo. Inicia un desafío nuevo y repite la vinculación desde su tarjeta; no reutilices secretos ni intercambies credenciales entre dispositivos.',
            'Sensores sin lectura' => 'Verifica estados del invernadero, zona, dispositivo y sensores; límites del plan, tipo, unidad, pin y cableado. Temperatura y humedad DHT22 comparten GPIO4; el suelo analógico usa GPIO34. Revisa si hay comunicaciones nuevas pero mediciones antiguas.',
        ] as $title => $answer)
            <details><summary>{{ $title }}</summary><p>{{ $answer }}</p></details>
        @endforeach
    </section>
</div>
@endsection

@push('styles')
<style>
    .iot-guide { display: grid; gap: 20px; color: #314239; }
    .iot-card { padding: 24px; background: #fff; border: 1px solid #dce5de; border-radius: 12px; min-width: 0; scroll-margin-top: 24px; }
    .iot-card h2 { margin: 0 0 12px; font-size: 19px; color: #176136; }
    .iot-card h3 { font-size: 16px; margin-top: 0; }
    .iot-card p, .iot-card li { font-size: 14px; line-height: 1.75; }
    .iot-card a:not(.iot-button) { color: #176136; text-underline-offset: 3px; }
    .iot-intro { display: flex; align-items: center; justify-content: space-between; gap: 24px; }
    .iot-intro p { max-width: 700px; margin-bottom: 0; }
    .iot-eyebrow { display: block; font-size: 11px; letter-spacing: .08em; color: #637269; margin-bottom: 10px; }
    .iot-button, .iot-guide button { display: inline-block; padding: 12px 16px; border: 1px solid #176136; border-radius: 8px; font-size: 13px; text-decoration: none; min-height: 44px; }
    .iot-button { color: #fff; background: #176136; text-align: center; }
    .iot-guide button:disabled { color: #637269; background: #f3f6f4; border-color: #dce5de; cursor: not-allowed; }
    .iot-steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    .iot-steps a { display: flex; align-items: center; gap: 10px; padding: 14px; color: #176136; background: #edf5ef; border-radius: 8px; text-decoration: none; font-size: 13px; }
    .iot-steps span, .iot-number { display: inline-grid; place-items: center; width: 30px; height: 30px; border-radius: 50%; background: #176136; color: #fff; flex-shrink: 0; }
    .iot-step-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
    .iot-number { margin-bottom: 12px; }
    .iot-notice { padding: 12px 14px; border-left: 3px solid #a38331; background: #fbf8ee; border-radius: 4px; }
    .iot-table { overflow-x: auto; }
    .iot-table table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
    .iot-table caption { text-align: left; color: #637269; margin: 8px 0; }
    .iot-table th, .iot-table td { padding: 12px; border-bottom: 1px solid #e5ece7; }
    .iot-table th { background: #f3f6f4; }
    .iot-release { padding: 18px; border: 1px solid #e5ece7; border-radius: 8px; margin-top: 16px; }
    .iot-release dl { display: flex; flex-wrap: wrap; gap: 20px; font-size: 13px; }
    .iot-release dt { color: #637269; margin-bottom: 4px; }
    .iot-release dd { margin: 0; }
    .iot-downloads { display: flex; gap: 12px; flex-wrap: wrap; }
    .iot-guide details { border-top: 1px solid #e5ece7; padding: 14px 0; }
    .iot-guide summary { cursor: pointer; font-weight: 600; font-size: 14px; line-height: 1.6; }
    .iot-guide a:focus-visible, .iot-guide summary:focus-visible { outline: 3px solid #8ebd9c; outline-offset: 4px; }
    #iot-serial input:not([type="checkbox"]), #iot-serial select { padding: 10px; max-width: 100%; border: 1px solid #9cad9e; border-radius: 6px; }
    #serial-status { padding: 12px; background: #edf5ef; }
    .iot-guide code { overflow-wrap: anywhere; }
    @media(max-width: 1050px) { .iot-intro { align-items: flex-start; flex-direction: column; } .iot-steps { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media(max-width: 650px) { .iot-card { padding: 18px; } .iot-step-grid, .iot-steps { grid-template-columns: 1fr; } .iot-downloads > * { width: 100%; text-align: center; } }
</style>
@endpush

@push('scripts')
<script type="module" src="{{ asset('js/iot-serial-ui.js') }}"></script>
@endpush
