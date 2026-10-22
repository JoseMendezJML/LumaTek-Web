{{-- =========================================================
     LUMA: asistente flotante de LumaTek
     Usa POST /api/chat, igual que la app móvil.
========================================================== --}}

<button
    type="button"
    id="luma-launcher"
    class="luma-launcher"
    aria-label="Abrir a Luma, el asistente de LumaTek"
    aria-controls="luma-panel"
    aria-expanded="false"
    hidden
>
    <svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true">
        <path
            fill="currentColor"
            d="M12 3C6.48 3 2 6.92 2 11.75c0 2.4 1.1 4.57 2.9 6.15L4 21.5a.5.5 0 0 0 .72.55l4.05-2.13c1.02.27 2.11.42 3.23.42 5.52 0 10-3.92 10-8.75S17.52 3 12 3Zm4.6 5.1c-.2 2.9-2.3 5.2-5.1 5.4-.4 0-.7.3-.7.7v.6a.6.6 0 0 1-1.2 0v-.6c0-1 .7-1.8 1.7-1.9 1.9-.2 3.4-1.8 3.6-3.7-1.9.1-3.5 1.6-3.7 3.5a.6.6 0 0 1-1.2-.1c.3-2.7 2.6-4.7 5.3-4.6.7 0 1.3.1 1.3.7Z"
        />
    </svg>
</button>


<section
    id="luma-panel"
    class="luma-panel"
    role="dialog"
    aria-label="Luma, asistente de LumaTek"
    hidden
>

    {{-- ENCABEZADO --}}

    <header class="luma-header">

        <div class="luma-avatar" aria-hidden="true">L</div>

        <div class="luma-title">
            <strong>Luma</strong>
            <span>Asistente de LumaTek</span>
        </div>

        <span
            id="luma-remaining"
            class="luma-remaining"
        ></span>

        <button
            type="button"
            id="luma-reset"
            class="luma-icon-button"
            title="Nueva conversación"
            aria-label="Nueva conversación"
        >
            ↺
        </button>

        <button
            type="button"
            id="luma-close"
            class="luma-icon-button"
            title="Cerrar"
            aria-label="Cerrar a Luma"
        >
            ✕
        </button>

    </header>


    {{-- MENSAJES --}}

    <div
        id="luma-messages"
        class="luma-messages"
        aria-live="polite"
    >

        <div
            id="luma-empty"
            class="luma-empty"
        >

            <p>
                Hola, soy <strong>Luma</strong>. Conozco el estado de tus
                invernaderos, te doy consejos agronómicos y reviso fotos de
                posibles plagas.
            </p>

            <div class="luma-suggestions">

                <button
                    type="button"
                    class="luma-suggestion"
                    data-suggestion="Dame un resumen de mis invernaderos."
                >
                    Resumen de mis invernaderos
                </button>

                <button
                    type="button"
                    class="luma-suggestion"
                    data-suggestion="¿Qué alertas debo atender primero?"
                >
                    ¿Qué alertas debo atender?
                </button>

                <button
                    type="button"
                    class="luma-suggestion"
                    data-suggestion="¿Qué plaga o problema ves en esta foto?"
                    data-photo="1"
                >
                    Analizar foto de una plaga
                </button>

            </div>

        </div>

    </div>


    {{-- AVISOS --}}

    <div
        id="luma-notice"
        class="luma-notice"
        role="alert"
        hidden
    ></div>


    {{-- FORMULARIO --}}

    <form
        id="luma-form"
        class="luma-form"
    >

        <div
            id="luma-preview"
            class="luma-preview"
            hidden
        >
            <img
                id="luma-preview-image"
                alt="Foto adjunta"
            >

            <span id="luma-preview-name"></span>

            <button
                type="button"
                id="luma-preview-remove"
                class="luma-preview-remove"
                aria-label="Quitar foto"
            >
                ✕
            </button>
        </div>

        <div class="luma-input-row">

            <input
                type="file"
                id="luma-file"
                accept="image/jpeg,image/png,image/webp"
                hidden
            >

            <button
                type="button"
                id="luma-attach"
                class="luma-attach"
                aria-label="Adjuntar foto"
                title="Adjuntar foto (JPG, PNG o WEBP)"
            >
                📎
            </button>

            <textarea
                id="luma-input"
                rows="1"
                maxlength="4000"
                placeholder="Pregúntale a Luma…"
            ></textarea>

            <button
                type="submit"
                id="luma-send"
                class="luma-send"
            >
                Enviar
            </button>

        </div>

    </form>

</section>


<style>

    /* =========================================================
       BOTÓN FLOTANTE
    ========================================================== */

    .luma-launcher {
        position: fixed;
        right: 24px;
        bottom: 24px;
        z-index: 1000;

        display: flex;
        align-items: center;
        justify-content: center;

        width: 58px;
        height: 58px;

        border: 0;
        border-radius: 50%;

        background: #176136;

        color: #ffffff;

        box-shadow: 0 8px 22px rgba(23, 61, 39, .32);

        cursor: pointer;

        transition: transform .15s ease, background .15s ease;
    }


    .luma-launcher[hidden] {
        display: none;
    }


    .luma-launcher:hover {
        background: #104c2a;

        transform: translateY(-2px);
    }


    .luma-launcher:focus-visible,
    .luma-icon-button:focus-visible,
    .luma-suggestion:focus-visible,
    .luma-send:focus-visible,
    .luma-attach:focus-visible {
        outline: 3px solid #8cc9a2;
        outline-offset: 2px;
    }


    /* =========================================================
       PANEL
    ========================================================== */

    .luma-panel {
        position: fixed;
        right: 24px;
        bottom: 96px;
        z-index: 1001;

        display: flex;
        flex-direction: column;

        width: 380px;
        height: min(560px, calc(100vh - 120px));

        background: #ffffff;

        border: 1px solid #e0e8e2;
        border-radius: 16px;

        box-shadow: 0 18px 48px rgba(23, 61, 39, .22);

        overflow: hidden;
    }


    .luma-panel[hidden] {
        display: none;
    }


    .luma-header {
        display: flex;
        align-items: center;

        gap: 10px;

        padding: 12px 14px;

        background: #176136;

        color: #ffffff;
    }


    .luma-avatar {
        display: flex;
        align-items: center;
        justify-content: center;

        width: 34px;
        height: 34px;
        flex-shrink: 0;

        border-radius: 50%;

        background: #ffffff;

        color: #176136;

        font-weight: 800;
    }


    .luma-title {
        display: flex;
        flex-direction: column;

        flex: 1;

        min-width: 0;

        line-height: 1.2;
    }


    .luma-title strong {
        font-size: 14px;
    }


    .luma-title span,
    .luma-remaining {
        font-size: 10px;

        opacity: .85;
    }


    .luma-remaining {
        white-space: nowrap;
    }


    .luma-icon-button {
        width: 30px;
        height: 30px;
        flex-shrink: 0;

        border: 0;
        border-radius: 8px;

        background: rgba(255, 255, 255, .14);

        color: #ffffff;

        cursor: pointer;

        font-size: 14px;
    }


    .luma-icon-button:hover {
        background: rgba(255, 255, 255, .26);
    }


    .luma-icon-button:disabled {
        opacity: .5;

        cursor: not-allowed;
    }


    /* =========================================================
       MENSAJES
    ========================================================== */

    .luma-messages {
        flex: 1;

        display: flex;
        flex-direction: column;

        gap: 10px;

        padding: 14px;

        background: #f7faf8;

        overflow-y: auto;
    }


    .luma-empty {
        margin: auto 0;

        color: #55645b;

        font-size: 12px;
        line-height: 1.5;
        text-align: center;
    }


    .luma-suggestions {
        display: flex;
        flex-direction: column;

        gap: 6px;

        margin-top: 10px;
    }


    .luma-suggestion {
        padding: 8px 12px;

        border: 1px solid #cfdcd3;
        border-radius: 999px;

        background: #ffffff;

        color: #176136;

        cursor: pointer;

        font-size: 11px;
    }


    .luma-suggestion:hover {
        background: #eaf4ed;
    }


    .luma-bubble {
        max-width: 88%;

        padding: 9px 12px;

        border-radius: 12px;

        font-size: 12px;
        line-height: 1.5;

        overflow-wrap: anywhere;
    }


    .luma-bubble.user {
        align-self: flex-end;

        background: #176136;

        color: #ffffff;

        border-bottom-right-radius: 4px;
    }


    .luma-bubble.assistant {
        align-self: flex-start;

        background: #ffffff;

        border: 1px solid #e0e8e2;

        color: #24342a;

        border-bottom-left-radius: 4px;
    }


    .luma-bubble.pending {
        color: #78837c;

        font-style: italic;
    }


    .luma-bubble ul {
        margin: 6px 0;

        padding-left: 18px;
    }


    .luma-bubble-image {
        display: block;

        max-width: 180px;
        max-height: 180px;

        margin-bottom: 6px;

        border-radius: 8px;
    }


    .luma-bubble-tag {
        display: block;

        margin-bottom: 4px;

        font-size: 10px;

        opacity: .8;
    }


    /* =========================================================
       AVISOS Y FORMULARIO
    ========================================================== */

    .luma-notice {
        margin: 10px 14px 0;

        padding: 8px 10px;

        border: 1px solid #f0d2a8;
        border-radius: 8px;

        background: #fff7ec;

        color: #8a5a14;

        font-size: 11px;
    }


    .luma-form {
        padding: 10px 14px 14px;

        border-top: 1px solid #e0e8e2;
    }


    .luma-preview {
        display: flex;
        align-items: center;

        gap: 8px;

        margin-bottom: 8px;

        color: #55645b;

        font-size: 11px;
    }


    .luma-preview[hidden] {
        display: none;
    }


    .luma-preview img {
        width: 40px;
        height: 40px;

        border-radius: 8px;

        object-fit: cover;
    }


    .luma-preview span {
        flex: 1;

        overflow: hidden;

        text-overflow: ellipsis;
        white-space: nowrap;
    }


    .luma-preview-remove {
        border: 0;

        background: transparent;

        color: #a23b3b;

        cursor: pointer;

        font-size: 13px;
    }


    .luma-input-row {
        display: flex;
        align-items: flex-end;

        gap: 8px;
    }


    .luma-attach {
        width: 38px;
        height: 38px;
        flex-shrink: 0;

        border: 1px solid #cfdcd3;
        border-radius: 8px;

        background: #ffffff;

        cursor: pointer;

        font-size: 15px;
    }


    #luma-input {
        flex: 1;

        min-width: 0;
        min-height: 38px;
        max-height: 120px;

        padding: 9px 10px;

        border: 1px solid #cfdcd3;
        border-radius: 8px;

        font-family: inherit;
        font-size: 12px;

        resize: none;
    }


    #luma-input:focus {
        border-color: #176136;

        outline: none;
    }


    .luma-send {
        min-height: 38px;

        padding: 8px 12px;

        border: 0;
        border-radius: 8px;

        background: #176136;

        color: #ffffff;

        cursor: pointer;

        font-size: 11px;
        font-weight: 700;
    }


    .luma-send:hover {
        background: #104c2a;
    }


    .luma-send:disabled,
    .luma-attach:disabled {
        opacity: .55;

        cursor: not-allowed;
    }


    @media (max-width: 650px) {

        .luma-launcher {
            right: 16px;
            bottom: 16px;
        }

        .luma-panel {
            inset: 0;

            width: 100%;
            height: 100%;

            border: 0;
            border-radius: 0;
        }
    }

</style>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const token =
            sessionStorage.getItem(
                'lumatek_access_token'
            );

        const launcher = document.getElementById('luma-launcher');

        // Sin token no se puede llamar a la API: Luma no se muestra.
        if (!token || !launcher) {
            return;
        }

        const HISTORY_KEY = 'lumatek_chat_history';
        const OPEN_KEY = 'lumatek_luma_open';
        const MAX_MESSAGES = 20;
        const MAX_IMAGE_SIDE = 1568;

        const panel = document.getElementById('luma-panel');
        const closeButton = document.getElementById('luma-close');
        const messagesBox = document.getElementById('luma-messages');
        const emptyState = document.getElementById('luma-empty');
        const notice = document.getElementById('luma-notice');
        const remaining = document.getElementById('luma-remaining');
        const form = document.getElementById('luma-form');
        const input = document.getElementById('luma-input');
        const sendButton = document.getElementById('luma-send');
        const resetButton = document.getElementById('luma-reset');
        const attachButton = document.getElementById('luma-attach');
        const fileInput = document.getElementById('luma-file');
        const preview = document.getElementById('luma-preview');
        const previewImage = document.getElementById('luma-preview-image');
        const previewName = document.getElementById('luma-preview-name');
        const previewRemove = document.getElementById('luma-preview-remove');

        let history = loadHistory();
        let selectedImage = null;
        let sending = false;

        launcher.hidden = false;


        /*
        |--------------------------------------------------------------------------
        | Almacenamiento local (sin imágenes)
        |--------------------------------------------------------------------------
        */

        function loadHistory() {

            try {

                const stored = JSON.parse(
                    sessionStorage.getItem(HISTORY_KEY) || '[]'
                );

                return Array.isArray(stored) ? stored : [];

            } catch (error) {

                return [];
            }
        }


        function saveHistory() {

            try {

                sessionStorage.setItem(
                    HISTORY_KEY,
                    JSON.stringify(history)
                );

            } catch (error) {
                // Sin almacenamiento disponible: la conversación vive solo en esta página.
            }
        }


        function rememberOpen(value) {

            try {

                sessionStorage.setItem(OPEN_KEY, value ? '1' : '0');

            } catch (error) {
                // Opcional: solo recuerda si el panel estaba abierto.
            }
        }


        function wasOpen() {

            try {

                return sessionStorage.getItem(OPEN_KEY) === '1';

            } catch (error) {

                return false;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Abrir y cerrar
        |--------------------------------------------------------------------------
        */

        function openPanel(focus) {

            panel.hidden = false;
            launcher.setAttribute('aria-expanded', 'true');

            rememberOpen(true);
            scrollToBottom();

            if (focus) {
                input.focus();
            }
        }


        function closePanel() {

            panel.hidden = true;
            launcher.setAttribute('aria-expanded', 'false');

            rememberOpen(false);
            launcher.focus();
        }


        launcher.addEventListener('click', function () {

            panel.hidden
                ? openPanel(true)
                : closePanel();
        });


        closeButton.addEventListener('click', closePanel);


        document.addEventListener('keydown', function (event) {

            if (event.key === 'Escape' && !panel.hidden) {
                closePanel();
            }
        });


        /*
        |--------------------------------------------------------------------------
        | Markdown seguro: primero se escapa todo el HTML
        |--------------------------------------------------------------------------
        */

        function escapeHtml(text) {

            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }


        function inlineMarkdown(text) {

            return text.replace(
                /\*\*(.+?)\*\*/g,
                '<strong>$1</strong>'
            );
        }


        function renderMarkdown(text) {

            const lines = escapeHtml(text).split('\n');
            const html = [];
            let inList = false;

            lines.forEach(function (line) {

                const item = line.match(/^\s*[-*]\s+(.*)$/);

                if (item) {

                    if (!inList) {
                        html.push('<ul>');
                        inList = true;
                    }

                    html.push('<li>' + inlineMarkdown(item[1]) + '</li>');

                    return;
                }

                if (inList) {
                    html.push('</ul>');
                    inList = false;
                }

                html.push(inlineMarkdown(line) + '<br>');
            });

            if (inList) {
                html.push('</ul>');
            }

            return html
                .join('')
                .replace(/(<br>)+$/, '');
        }


        /*
        |--------------------------------------------------------------------------
        | Dibujar mensajes
        |--------------------------------------------------------------------------
        */

        function bubble(role, content, options) {

            const element = document.createElement('div');

            element.className = 'luma-bubble ' + role;

            if (options && options.pending) {
                element.classList.add('pending');
            }

            if (options && options.imageUrl) {

                const image = document.createElement('img');

                image.src = options.imageUrl;
                image.alt = 'Foto enviada';
                image.className = 'luma-bubble-image';

                element.appendChild(image);

            } else if (options && options.hadImage) {

                const tag = document.createElement('span');

                tag.className = 'luma-bubble-tag';
                tag.textContent = '📷 imagen enviada';

                element.appendChild(tag);
            }

            const body = document.createElement('div');

            body.innerHTML = role === 'assistant'
                ? renderMarkdown(content)
                : escapeHtml(content).replace(/\n/g, '<br>');

            element.appendChild(body);

            return element;
        }


        function render() {

            messagesBox
                .querySelectorAll('.luma-bubble')
                .forEach(function (element) {
                    element.remove();
                });

            emptyState.hidden = history.length > 0;

            history.forEach(function (message) {

                messagesBox.appendChild(
                    bubble(message.role, message.content, {
                        hadImage: message.hadImage,
                    })
                );
            });

            scrollToBottom();
        }


        function scrollToBottom() {

            messagesBox.scrollTop =
                messagesBox.scrollHeight;
        }


        function showNotice(message) {

            notice.textContent = message;
            notice.hidden = false;
        }


        function hideNotice() {

            notice.hidden = true;
            notice.textContent = '';
        }


        function setSending(value) {

            sending = value;

            sendButton.disabled = value;
            attachButton.disabled = value;
            input.disabled = value;
            resetButton.disabled = value;

            sendButton.textContent = value
                ? 'Enviando…'
                : 'Enviar';
        }


        /*
        |--------------------------------------------------------------------------
        | Foto: se reduce en el navegador antes de subirla
        |--------------------------------------------------------------------------
        */

        function resizeImage(file) {

            return new Promise(function (resolve, reject) {

                const url = URL.createObjectURL(file);
                const image = new Image();

                image.onload = function () {

                    const scale = Math.min(
                        1,
                        MAX_IMAGE_SIDE / Math.max(image.width, image.height)
                    );

                    const canvas = document.createElement('canvas');

                    canvas.width = Math.round(image.width * scale);
                    canvas.height = Math.round(image.height * scale);

                    canvas
                        .getContext('2d')
                        .drawImage(image, 0, 0, canvas.width, canvas.height);

                    URL.revokeObjectURL(url);

                    canvas.toBlob(
                        function (blob) {
                            blob
                                ? resolve(blob)
                                : reject(new Error('No se pudo procesar la imagen.'));
                        },
                        'image/jpeg',
                        0.85
                    );
                };

                image.onerror = function () {

                    URL.revokeObjectURL(url);

                    reject(new Error('No se pudo leer la imagen.'));
                };

                image.src = url;
            });
        }


        function clearImage() {

            if (selectedImage) {
                URL.revokeObjectURL(selectedImage.url);
            }

            selectedImage = null;
            fileInput.value = '';
            preview.hidden = true;
        }


        fileInput.addEventListener('change', async function () {

            const file = fileInput.files[0];

            if (!file) {
                return;
            }

            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {

                showNotice('Solo se aceptan fotos JPG, PNG o WEBP.');
                fileInput.value = '';

                return;
            }

            try {

                const blob = await resizeImage(file);

                clearImage();

                selectedImage = {
                    blob: blob,
                    url: URL.createObjectURL(blob),
                };

                previewImage.src = selectedImage.url;
                previewName.textContent = file.name;
                preview.hidden = false;

                hideNotice();
                input.focus();

            } catch (error) {

                showNotice(error.message);
                fileInput.value = '';
            }
        });


        attachButton.addEventListener('click', function () {
            fileInput.click();
        });


        previewRemove.addEventListener('click', clearImage);


        /*
        |--------------------------------------------------------------------------
        | Enviar
        |--------------------------------------------------------------------------
        */

        function payloadMessages(question) {

            const messages = history
                .concat([{ role: 'user', content: question }])
                .slice(-MAX_MESSAGES)
                .map(function (message) {
                    return {
                        role: message.role,
                        content: message.content,
                    };
                });

            while (messages.length && messages[0].role !== 'user') {
                messages.shift();
            }

            return messages;
        }


        async function send() {

            if (sending) {
                return;
            }

            let question = input.value.trim();

            if (!question && selectedImage) {
                question = '¿Qué plaga o problema ves en esta foto?';
            }

            if (!question) {
                input.focus();

                return;
            }

            hideNotice();
            setSending(true);

            const image = selectedImage;
            const data = new FormData();

            data.append(
                'messages',
                JSON.stringify(payloadMessages(question))
            );

            if (image) {
                data.append('image', image.blob, 'foto.jpg');
            }

            emptyState.hidden = true;

            const userBubble = bubble('user', question, {
                imageUrl: image ? image.url : null,
            });

            const pendingBubble = bubble('assistant', 'Luma está pensando…', {
                pending: true,
            });

            messagesBox.appendChild(userBubble);
            messagesBox.appendChild(pendingBubble);
            scrollToBottom();

            try {

                const response = await fetch('/api/chat', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        Authorization: `Bearer ${token}`,
                    },
                    body: data,
                });

                if (response.status === 401) {

                    sessionStorage.removeItem('lumatek_access_token');
                    sessionStorage.removeItem('lumatek_user');
                    sessionStorage.removeItem(HISTORY_KEY);
                    sessionStorage.removeItem(OPEN_KEY);

                    window.location.href = '/login';

                    return;
                }

                const result = await response
                    .json()
                    .catch(function () {
                        return {};
                    });

                if (!response.ok) {

                    userBubble.remove();
                    pendingBubble.remove();
                    emptyState.hidden = history.length > 0;

                    showNotice(
                        result.message
                        || 'No fue posible obtener respuesta. Intenta de nuevo.'
                    );

                    return;
                }

                history.push({
                    role: 'user',
                    content: question,
                    hadImage: Boolean(image),
                });

                history.push({
                    role: 'assistant',
                    content: result.reply,
                });

                saveHistory();

                pendingBubble.replaceWith(
                    bubble('assistant', result.reply)
                );

                remaining.textContent =
                    'Quedan ' + result.remaining_today + ' hoy';

                input.value = '';
                autoResize();
                clearImage();

            } catch (error) {

                userBubble.remove();
                pendingBubble.remove();
                emptyState.hidden = history.length > 0;

                showNotice('No hay conexión con el servidor. Revisa tu red e intenta de nuevo.');

            } finally {

                setSending(false);
                scrollToBottom();
                input.focus();
            }
        }


        form.addEventListener('submit', function (event) {
            event.preventDefault();
            send();
        });


        input.addEventListener('keydown', function (event) {

            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                send();
            }
        });


        function autoResize() {

            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 120) + 'px';
        }


        input.addEventListener('input', autoResize);


        /*
        |--------------------------------------------------------------------------
        | Sugerencias y nueva conversación
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll('.luma-suggestion')
            .forEach(function (button) {

                button.addEventListener('click', function () {

                    input.value = button.dataset.suggestion;
                    autoResize();

                    if (button.dataset.photo) {
                        fileInput.click();

                        return;
                    }

                    send();
                });
            });


        resetButton.addEventListener('click', function () {

            history = [];

            saveHistory();
            clearImage();
            hideNotice();
            render();

            input.value = '';
            autoResize();
            input.focus();
        });


        render();

        if (wasOpen()) {
            openPanel(false);
        }
    }
);

</script>
