@extends('layouts.app')

@section('title', 'Asistente | LumaTek')

@section('page-title', 'Asistente')

@section(
    'page-subtitle',
    'Resúmenes de tus invernaderos, consejos agronómicos y detección de plagas'
)

@section('content')

<div class="assistant-page">

    <section class="assistant-card">

        {{-- =========================================================
             ENCABEZADO
        ========================================================== --}}

        <div class="assistant-header">

            <div>
                <h2>Asistente LumaTek</h2>

                <p>
                    Las respuestas usan los datos actuales de tus invernaderos.
                </p>
            </div>

            <div class="assistant-header-actions">

                <span
                    id="assistant-remaining"
                    class="assistant-remaining"
                ></span>

                <button
                    type="button"
                    id="assistant-reset"
                    class="secondary-button"
                >
                    Nueva conversación
                </button>

            </div>

        </div>


        {{-- =========================================================
             MENSAJES
        ========================================================== --}}

        <div
            id="assistant-messages"
            class="assistant-messages"
            aria-live="polite"
        >

            <div
                id="assistant-empty"
                class="assistant-empty"
            >

                <p>
                    ¿En qué te ayudo hoy? Prueba con una sugerencia:
                </p>

                <div class="assistant-suggestions">

                    <button
                        type="button"
                        class="suggestion-button"
                        data-suggestion="Dame un resumen de mis invernaderos."
                    >
                        Resumen de mis invernaderos
                    </button>

                    <button
                        type="button"
                        class="suggestion-button"
                        data-suggestion="¿Qué alertas debo atender primero?"
                    >
                        ¿Qué alertas debo atender?
                    </button>

                    <button
                        type="button"
                        class="suggestion-button"
                        data-suggestion="¿Qué plaga o problema ves en esta foto?"
                        data-photo="1"
                    >
                        Analizar foto de una plaga
                    </button>

                </div>

            </div>

        </div>


        {{-- =========================================================
             AVISOS
        ========================================================== --}}

        <div
            id="assistant-notice"
            class="assistant-notice"
            role="alert"
            hidden
        ></div>


        {{-- =========================================================
             FORMULARIO
        ========================================================== --}}

        <form
            id="assistant-form"
            class="assistant-form"
        >

            <div
                id="assistant-preview"
                class="assistant-preview"
                hidden
            >
                <img
                    id="assistant-preview-image"
                    alt="Foto adjunta"
                >

                <span id="assistant-preview-name"></span>

                <button
                    type="button"
                    id="assistant-preview-remove"
                    class="preview-remove"
                    aria-label="Quitar foto"
                >
                    ✕
                </button>
            </div>

            <div class="assistant-input-row">

                <input
                    type="file"
                    id="assistant-file"
                    accept="image/jpeg,image/png,image/webp"
                    hidden
                >

                <button
                    type="button"
                    id="assistant-attach"
                    class="attach-button"
                    aria-label="Adjuntar foto"
                    title="Adjuntar foto (JPG, PNG o WEBP)"
                >
                    📎
                </button>

                <textarea
                    id="assistant-input"
                    rows="1"
                    maxlength="4000"
                    placeholder="Escribe tu mensaje…"
                ></textarea>

                <button
                    type="submit"
                    id="assistant-send"
                    class="primary-button"
                >
                    Enviar
                </button>

            </div>

        </form>

    </section>

</div>

@endsection


@push('styles')

<style>

    .assistant-page {
        width: 100%;
    }


    .assistant-card {
        display: flex;
        flex-direction: column;

        height: calc(100vh - 170px);
        min-height: 480px;

        background: #ffffff;

        border: 1px solid #e0e8e2;
        border-radius: 14px;

        overflow: hidden;
    }


    /* =========================================================
       ENCABEZADO
    ========================================================== */

    .assistant-header {
        display: flex;
        align-items: center;
        justify-content: space-between;

        gap: 15px;

        padding: 16px 20px;

        border-bottom: 1px solid #e0e8e2;
    }


    .assistant-header h2 {
        margin: 0;

        color: #173d27;

        font-size: 17px;
    }


    .assistant-header p {
        margin: 5px 0 0;

        color: #78837c;

        font-size: 10px;
    }


    .assistant-header-actions {
        display: flex;
        align-items: center;

        gap: 12px;
    }


    .assistant-remaining {
        color: #55645b;

        font-size: 10px;
        white-space: nowrap;
    }


    .primary-button,
    .secondary-button {
        min-height: 38px;

        padding: 9px 15px;

        border-radius: 8px;

        cursor: pointer;

        font-size: 11px;
        font-weight: 700;
    }


    .primary-button {
        border: 0;

        background: #176136;

        color: #ffffff;
    }


    .primary-button:hover {
        background: #104c2a;
    }


    .secondary-button {
        border: 1px solid #cfdcd3;

        background: #ffffff;

        color: #176136;
    }


    .secondary-button:hover {
        background: #f1f7f3;
    }


    .primary-button:disabled,
    .secondary-button:disabled,
    .attach-button:disabled {
        opacity: .55;

        cursor: not-allowed;
    }


    /* =========================================================
       MENSAJES
    ========================================================== */

    .assistant-messages {
        flex: 1;

        display: flex;
        flex-direction: column;

        gap: 12px;

        padding: 20px;

        background: #f7faf8;

        overflow-y: auto;
    }


    .assistant-empty {
        margin: auto;

        max-width: 520px;

        color: #55645b;

        font-size: 12px;
        text-align: center;
    }


    .assistant-suggestions {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;

        gap: 8px;

        margin-top: 12px;
    }


    .suggestion-button {
        padding: 9px 13px;

        border: 1px solid #cfdcd3;
        border-radius: 999px;

        background: #ffffff;

        color: #176136;

        cursor: pointer;

        font-size: 11px;
    }


    .suggestion-button:hover {
        background: #eaf4ed;
    }


    .chat-bubble {
        max-width: min(680px, 85%);

        padding: 11px 14px;

        border-radius: 12px;

        font-size: 12.5px;
        line-height: 1.55;

        overflow-wrap: anywhere;
    }


    .chat-bubble.user {
        align-self: flex-end;

        background: #176136;

        color: #ffffff;

        border-bottom-right-radius: 4px;
    }


    .chat-bubble.assistant {
        align-self: flex-start;

        background: #ffffff;

        border: 1px solid #e0e8e2;

        color: #24342a;

        border-bottom-left-radius: 4px;
    }


    .chat-bubble.pending {
        color: #78837c;

        font-style: italic;
    }


    .chat-bubble ul {
        margin: 6px 0;

        padding-left: 18px;
    }


    .chat-bubble-image {
        display: block;

        max-width: 220px;
        max-height: 220px;

        margin-bottom: 8px;

        border-radius: 8px;
    }


    .chat-bubble-tag {
        display: block;

        margin-bottom: 4px;

        font-size: 10px;

        opacity: .8;
    }


    /* =========================================================
       AVISOS Y FORMULARIO
    ========================================================== */

    .assistant-notice {
        margin: 0 20px;
        margin-top: 12px;

        padding: 10px 12px;

        border: 1px solid #f0d2a8;
        border-radius: 8px;

        background: #fff7ec;

        color: #8a5a14;

        font-size: 11px;
    }


    .assistant-form {
        padding: 14px 20px 18px;

        border-top: 1px solid #e0e8e2;
    }


    .assistant-preview {
        display: flex;
        align-items: center;

        gap: 10px;

        margin-bottom: 10px;

        color: #55645b;

        font-size: 11px;
    }


    .assistant-preview img {
        width: 48px;
        height: 48px;

        border-radius: 8px;

        object-fit: cover;
    }


    .preview-remove {
        border: 0;

        background: transparent;

        color: #a23b3b;

        cursor: pointer;

        font-size: 13px;
    }


    .assistant-input-row {
        display: flex;
        align-items: flex-end;

        gap: 10px;
    }


    .attach-button {
        width: 40px;
        height: 40px;

        flex-shrink: 0;

        border: 1px solid #cfdcd3;
        border-radius: 8px;

        background: #ffffff;

        cursor: pointer;

        font-size: 16px;
    }


    #assistant-input {
        flex: 1;

        min-height: 40px;
        max-height: 140px;

        padding: 10px 12px;

        border: 1px solid #cfdcd3;
        border-radius: 8px;

        font-family: inherit;
        font-size: 12.5px;

        resize: none;
    }


    #assistant-input:focus {
        border-color: #176136;

        outline: none;
    }


    @media (max-width: 650px) {

        .assistant-card {
            height: calc(100vh - 140px);
        }

        .assistant-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .assistant-messages,
        .assistant-form {
            padding-left: 12px;
            padding-right: 12px;
        }

        .chat-bubble {
            max-width: 92%;
        }
    }

</style>

@endpush


@push('scripts')

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const token =
            sessionStorage.getItem(
                'lumatek_access_token'
            );


        if (!token) {

            window.location.href =
                '/login';

            return;
        }


        const HISTORY_KEY = 'lumatek_chat_history';
        const MAX_MESSAGES = 20;
        const MAX_IMAGE_SIDE = 1568;

        const messagesBox = document.getElementById('assistant-messages');
        const emptyState = document.getElementById('assistant-empty');
        const notice = document.getElementById('assistant-notice');
        const remaining = document.getElementById('assistant-remaining');
        const form = document.getElementById('assistant-form');
        const input = document.getElementById('assistant-input');
        const sendButton = document.getElementById('assistant-send');
        const resetButton = document.getElementById('assistant-reset');
        const attachButton = document.getElementById('assistant-attach');
        const fileInput = document.getElementById('assistant-file');
        const preview = document.getElementById('assistant-preview');
        const previewImage = document.getElementById('assistant-preview-image');
        const previewName = document.getElementById('assistant-preview-name');
        const previewRemove = document.getElementById('assistant-preview-remove');

        let history = loadHistory();
        let selectedImage = null;
        let sending = false;


        /*
        |--------------------------------------------------------------------------
        | Historial local (sin imágenes)
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
                // Sin almacenamiento disponible: la conversación vive solo en esta pestaña.
            }
        }


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

            element.className = 'chat-bubble ' + role;

            if (options && options.pending) {
                element.classList.add('pending');
            }

            if (options && options.imageUrl) {

                const image = document.createElement('img');

                image.src = options.imageUrl;
                image.alt = 'Foto enviada';
                image.className = 'chat-bubble-image';

                element.appendChild(image);

            } else if (options && options.hadImage) {

                const tag = document.createElement('span');

                tag.className = 'chat-bubble-tag';
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
                .querySelectorAll('.chat-bubble')
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

            const pendingBubble = bubble('assistant', 'Analizando…', {
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
            input.style.height = Math.min(input.scrollHeight, 140) + 'px';
        }


        input.addEventListener('input', autoResize);


        /*
        |--------------------------------------------------------------------------
        | Sugerencias y nueva conversación
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll('.suggestion-button')
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
    }
);

</script>

@endpush
