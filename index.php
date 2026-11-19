<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sorteos Pitaya</title>
    <link rel="icon" type="image/png" href="img/icon12.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css?v=<?php echo mt_rand(1,9999); ?>">
</head>
<body>

    <!-- Tabs de navegación (siempre visibles) -->
    <nav class="tabs-nav">
        <button class="tab-btn active" onclick="showTab('registro', event)">Registro</button>
        <button class="tab-btn"        onclick="showTab('como-participar', event)">Cómo Participar</button>
        <button class="tab-btn"        onclick="showTab('premios', event)">Premios</button>
    </nav>

    <!-- ─── TAB 1: REGISTRO ─────────────────────────────────────────── -->
    <section id="registro" class="tab-content active">

        <!-- Hero: Logo + Evento sobre la imagen de fondo -->
        <div class="registro-hero">
            <img src="img/Logo.svg" alt="Batidos Pitaya" class="hero-logo"
                 onerror="this.onerror=null; this.style.display='none'; document.getElementById('heroLogoFallback').style.display='block'">
            <div id="heroLogoFallback" style="display:none; font-size:2rem; font-weight:900; color:white; letter-spacing:0.1em; margin-bottom:14px;">PITAYA</div>
            <p class="hero-te-lleva">te lleva al concierto de</p>
            <h1 class="hero-artist">MAR<span class="plus-sign">+</span>IN GARRIX</h1>
            <p class="hero-event-info">28 NOV | ESTADIO NACIONAL SOBERANÍA</p>
        </div>

        <!-- Formulario sobre fondo oscuro -->
        <div class="registro-content">
            <div class="form-dark-card">

                <!-- Mensaje de acceso bloqueado (default: visible hasta que QR válido) -->
                <div id="qrRequiredMessage" class="lock-message">
                    <div class="lock-icon">🔒</div>
                    <h2>Acceso Restringido</h2>
                    <p>Para registrar tu participación en el sorteo,<br>
                       escanea el código QR disponible en tu sucursal Pitaya.</p>
                </div>

                <!-- Formulario (oculto hasta QR válido) -->
                <form id="registrationForm" enctype="multipart/form-data" style="display:none;">

                    <h3 class="form-dark-title">📋 Registro de Participación</h3>

                    <!-- Paso 1: Foto del ticket -->
                    <div style="margin-bottom:24px;">
                        <label class="dark-label">Foto del Ticket *</label>
                        <div class="camera-container" style="max-width:100%;">
                            <button type="button" class="camera-btn" id="cameraOpenBtn" onclick="openCamera()">
                                📷 Tomar foto del ticket
                            </button>
                            <div id="ticketPreviewContainer" style="margin-top:10px;"></div>
                            <div id="ticketValidationStatus" style="display:none;" class="ticket-validation-status info"></div>
                        </div>
                    </div>

                    <!-- Código de participación (auto-llenado, no editable) -->
                    <div class="form-group full-width" style="margin-bottom:16px;">
                        <label for="codigo_participacion">Código de Participación *</label>
                        <input type="text" id="codigo_participacion" name="codigo_participacion"
                               placeholder="Se leerá automáticamente de tu ticket"
                               readonly required>
                    </div>

                    <!-- Puntos asignados (calculados del monto) -->
                    <div id="puntosContainer" style="display:none; margin-bottom:20px;">
                        <label class="dark-label">Puntos de Sorteo</label>
                        <div class="puntos-display-box">
                            <span class="puntos-valor" id="puntosValor">0</span>
                            <span class="puntos-label">punto(s) acumulado(s)</span>
                            <span class="puntos-tipo" id="puntosTipo" style="display:none;">Cliente Regular</span>
                        </div>
                    </div>

                    <!-- Datos del participante -->
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="nombre_completo">Nombre Completo *</label>
                            <input type="text" id="nombre_completo" name="nombre_completo"
                                   placeholder="Ej: Juan Pérez" required onblur="capitalizeName(this)">
                        </div>
                        <div class="form-group">
                            <label for="numero_contacto">No. Contacto *</label>
                            <input type="tel" id="numero_contacto" name="numero_contacto"
                                   placeholder="Ej: 88888888" required maxlength="8" pattern="[0-9]{8}"
                                   oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                        </div>
                        <div class="form-group">
                            <label for="numero_cedula">No. Cédula</label>
                            <input type="text" id="numero_cedula" name="numero_cedula"
                                   placeholder="000-000000-0000X">
                        </div>
                        <div class="form-group full-width">
                            <label for="correo_electronico">Correo Electrónico (opcional)</label>
                            <input type="email" id="correo_electronico" name="correo_electronico"
                                   placeholder="usuario@gmail.com">
                        </div>
                    </div>

                    <!-- Campos ocultos (datos extraídos + sesión) -->
                    <input type="hidden" id="h_token_sesion"       name="token_sesion">
                    <input type="hidden" id="h_es_club"            name="es_club" value="0">
                    <input type="hidden" id="h_ia_fecha"           name="ia_fecha">
                    <input type="hidden" id="h_ia_hora"            name="ia_hora">
                    <input type="hidden" id="h_ia_sucursal_nombre" name="ia_sucursal_nombre">
                    <input type="hidden" id="h_ia_monto_total"     name="ia_monto_total">
                    <input type="hidden" id="h_puntos_sorteo"      name="puntos_sorteo" value="0">
                    <input type="hidden" id="h_sucursal_id"        name="sucursal_id">
                    <input type="hidden" id="h_sucursal_nombre"    name="sucursal_nombre">
                    <input type="hidden" id="h_validado_imagen"    name="validado_imagen" value="0">

                    <button type="submit" class="btn-submit" id="submitBtn" disabled>
                        ENVIAR REGISTRO
                    </button>
                    <div id="formMessage" style="margin-top:14px; text-align:center; font-weight:600;"></div>
                </form>
            </div>
        </div>
    </section>

    <!-- ─── TAB 2: CÓMO PARTICIPAR ──────────────────────────────────── -->
    <section id="como-participar" class="tab-content">
        <div class="content-container">
            <div class="info-card">

                <!-- SEGMENTO 1: Pasos -->
                <div class="tab-segment seg-white">
                    <div class="card-title-wrap">
                        <h2 class="card-title-highlighted">¿Cómo Participar?</h2>
                    </div>

                    <p class="participation-intro" style="margin-top:24px;">
                        La participación se realizará mediante la acumulación de puntos, los cuales se
                        convertirán automáticamente en acciones para el sorteo.
                    </p>

                    <!-- Pasos con imágenes -->
                    <div class="icon-steps-container">

                        <!-- Paso 1: Escanear QR -->
                        <div class="icon-step">
                            <div class="icon-step-icon">
                                <img src="img/escanearcodigo.png" alt="Escanear código QR" class="step-svg-icon">
                            </div>
                            <h4>Escanear el código QR</h4>
                            <p>Ubicado en sucursales Pitaya<br>y redes sociales.</p>
                        </div>

                        <div class="icon-step-divider"></div>

                        <!-- Paso 2: Formulario -->
                        <div class="icon-step">
                            <div class="icon-step-icon">
                                <img src="img/completarformulario.png" alt="Completar formulario Digital" class="step-svg-icon">
                            </div>
                            <h4>Completar el formulario Digital</h4>
                            <p>Datos personales, Fotografía de la factura*<br>Conservar la factura original.</p>
                        </div>

                    </div>

                    <p class="note-footer" style="margin-top:4px;">
                        *Facturas ilegibles, incompletas, alteradas, duplicadas, fuera de fecha<br>
                        o no registradas vía QR serán automáticamente invalidadas.
                    </p>
                </div>

                <!-- SEGMENTO 2: Acumulación de puntos -->
                <div class="tab-segment seg-gray">
                    <div class="card-title-wrap">
                        <h3 class="card-title-highlighted card-title-dark">ACUMULACIÓN DE PUNTOS POR MONTO DE COMPRA</h3>
                    </div>
                    <div class="puntos-section" style="margin-top:20px;">
                        <div class="section-divider-label">• CLIENTE REGULAR •</div>
                        <div class="puntos-centered-row">
                            Por cada <span class="puntos-amount">C$250.00</span> en compras realizadas <span class="puntos-arrow">→ <span class="puntos-num">1</span> punto</span>
                        </div>
                        <div class="section-divider-label" style="margin-top:18px;">• CLUB PITAYA •</div>
                        <div class="puntos-centered-row" style="border-bottom:none;">
                            Por cada <span class="puntos-amount">C$250.00</span> en compras realizadas <span class="puntos-arrow">→ <span class="puntos-num">2</span> puntos</span>
                        </div>
                        <p class="note-footer" style="margin-top:14px;">
                            *El cálculo de puntos se realizará únicamente sobre compras efectivamente
                            facturadas durante la vigencia del sorteo.
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ─── TAB 3: PREMIOS ───────────────────────────────────────────── -->
    <section id="premios" class="tab-content">
        <div class="content-container">
            <div class="info-card">

                <!-- SEGMENTO 1: Premio -->
                <div class="tab-segment seg-white">
                    <div class="card-title-wrap">
                        <h2 class="card-title-highlighted">PREMIO</h2>
                    </div>
                    <div class="vip-prize-content" style="margin-top:20px;">
                        <div class="vip-horizontal">
                            <img src="img/ticketdoble.png" alt="Ticket Doble VIP" class="vip-ticket-img">
                            <span class="vip-name">PASE DOBLE VIP</span>
                        </div>
                        <p class="vip-note">El premio no es transferible ni canjeable por dinero en efectivo.</p>
                    </div>
                </div>

                <!-- SEGMENTO 2: Términos y Condiciones -->
                <div class="tab-segment seg-gray">
                    <div class="card-title-wrap">
                        <h3 class="card-title-highlighted card-title-dark">TÉRMINOS Y CONDICIONES</h3>
                    </div>
                    <ul class="terminos-list" style="margin-top:20px;">
                        <li>* Participan únicamente personas mayores de <strong>18 años</strong>.</li>
                        <li>* No podrán participar colaboradores de Batidos Pitaya.</li>
                        <li>* Para reclamar el premio, el ganador deberá presentar su cédula de identidad vigente.</li>
                        <li>* Los datos registrados deberán coincidir con los del documento de identidad.</li>
                        <li>* Las facturas duplicadas, alteradas o fuera de fecha serán invalidadas.</li>
                        <li>* El ganador será seleccionado mediante sorteo entre las participaciones válidas.</li>
                        <li>* El premio no es transferible ni canjeable por dinero en efectivo.</li>
                        <li>* Participar en la promoción implica la aceptación de estos términos y condiciones.</li>
                        <li>* Solo serán válidas las facturas originales, legibles, completas y registradas durante la vigencia de la promoción.</li>
                    </ul>
                </div>

            </div>
        </div>
    </section>

    <!-- ─── Camera Modal ─────────────────────────────────────────────── -->
    <div id="cameraModal">
        <div class="camera-content">
            <div class="camera-container">
                <video id="video" autoplay playsinline></video>
                <canvas id="canvas"></canvas>
                <div class="camera-overlay">
                    <div class="camera-overlay-corner corner-tl"></div>
                    <div class="camera-overlay-corner corner-tr"></div>
                    <div class="camera-overlay-corner corner-bl"></div>
                    <div class="camera-overlay-corner corner-br"></div>
                    <div class="area-guide"><span>Código del ticket</span></div>
                </div>
            </div>
            <div class="camera-controls">
                <button id="captureBtn" onclick="capturePhoto()">📸 Capturar</button>
                <button id="closeCameraBtn" onclick="closeCamera()">✖ Cerrar</button>
            </div>
        </div>
    </div>

    <!-- ─── Success Modal ────────────────────────────────────────────── -->
    <div id="successModal">
        <div class="success-content">
            <div class="success-icon">🎉</div>
            <h3 class="success-title">¡Listo!</h3>
            <p class="success-message">
                ¡Ya estás participando en el sorteo!<br>
                <strong id="pointsDisplay"></strong><br>
                <span style="font-size:0.9rem; color:#666;">Para registrar otro ticket, escanea el QR nuevamente.</span>
            </p>
            <button class="success-btn" onclick="closeSuccessModal()">Entendido</button>
        </div>
    </div>

    <script>
    // ─── Constantes ───────────────────────────────────────────────────────────
    const SESSION_KEY = 'sorteos_session_data';
    let stream            = null;
    let capturedPhotoBlob = null;
    let sessionData       = null;

    // ─── Inicialización ───────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        initializeQRValidation();
    });

    function initializeQRValidation() {
        // ¿Hay sesión activa guardada?
        const stored = sessionStorage.getItem(SESSION_KEY);
        if (stored) {
            try {
                const data = JSON.parse(stored);
                // Verificar que no haya expirado localmente
                if (data.expires_at && Date.now() < data.expires_at) {
                    activateForm(data);
                    return;
                } else {
                    sessionStorage.removeItem(SESSION_KEY);
                }
            } catch(e) { sessionStorage.removeItem(SESSION_KEY); }
        }

        // ¿Viene un ?k= en la URL?
        const params = new URLSearchParams(window.location.search);
        const k = params.get('k');
        if (!k) {
            showLockMessage('Para participar en el sorteo, escanea el código QR disponible en tu sucursal Pitaya.');
            return;
        }

        // Limpiar la URL
        window.history.replaceState({}, document.title, window.location.pathname);

        // Pedir geolocalización — mostrar espera ANTES del diálogo del navegador
        // No mostramos "Acceso Restringido" hasta saber qué respondió el usuario
        showLocationWaiting();

        if (!navigator.geolocation) {
            showLockMessage('Tu navegador no soporta geolocalización. Usa un navegador actualizado.');
            return;
        }

        navigator.geolocation.getCurrentPosition(
            pos => callValidateAccess(k, pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy),
            err => {
                if (err.code === 1) {
                    // Permiso denegado → mensaje específico para activar ubicación
                    showLocationDenied();
                } else {
                    const msgs = {
                        2: 'No se pudo obtener tu ubicación. Verifica que el GPS esté activo y reintenta.',
                        3: 'El tiempo de espera de ubicación expiró. Recarga la página e intenta nuevamente.',
                    };
                    showLockMessage(msgs[err.code] || 'Error al obtener ubicación.');
                }
            },
            { timeout: 15000, enableHighAccuracy: true }
        );
    }

    async function callValidateAccess(k, lat, lng, accuracy) {
        try {
            const resp = await fetch('validate_access.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ k, lat, lng, accuracy })
            });
            const data = await resp.json();

            if (data.success) {
                // Guardar sesión
                const session = {
                    token:            data.token,
                    sucursal_id:      data.sucursal_id,
                    sucursal_nombre:  data.sucursal_nombre,
                    expires_at:       Date.now() + (30 * 60 * 1000), // 30 min
                };
                sessionStorage.setItem(SESSION_KEY, JSON.stringify(session));
                activateForm(session);
            } else {
                showLockMessage(data.message || 'No fue posible validar el acceso.');
            }
        } catch(e) {
            showLockMessage('Error de conexión al verificar el acceso. Reintenta.');
        }
    }

    function activateForm(session) {
        sessionData = session;
        document.getElementById('qrRequiredMessage').style.display = 'none';
        document.getElementById('registrationForm').style.display  = 'block';
        document.getElementById('h_token_sesion').value      = session.token;
        document.getElementById('h_sucursal_id').value       = session.sucursal_id  || '';
        document.getElementById('h_sucursal_nombre').value   = session.sucursal_nombre || '';
    }

    function showLockMessage(msg) {
        const el = document.getElementById('qrRequiredMessage');
        el.innerHTML = `
            <div class="lock-icon">🔒</div>
            <h2>Acceso Restringido</h2>
            <p>${escHtml(msg)}</p>
        `;
        el.style.display = 'block';
        document.getElementById('registrationForm').style.display = 'none';
    }

    function showLocationWaiting() {
        // Mensaje neutral mientras el navegador pregunta por la ubicación
        const el = document.getElementById('qrRequiredMessage');
        el.innerHTML = `
            <div class="lock-icon" style="font-size:2.2rem">📍</div>
            <h2>Verificando ubicación</h2>
            <p>Necesitamos confirmar que estás en una sucursal Pitaya.<br>
               <strong>Por favor acepta el permiso de ubicación</strong> que aparece en tu pantalla.</p>
        `;
        el.style.display = 'block';
        document.getElementById('registrationForm').style.display = 'none';
    }

    function showLocationDenied() {
        // Permiso explícitamente denegado → instrucciones claras para activarlo
        const el = document.getElementById('qrRequiredMessage');
        el.innerHTML = `
            <div class="lock-icon" style="font-size:2.2rem">📍</div>
            <h2>Activa tu ubicación</h2>
            <p>Para validar tu participación necesitamos verificar que estás en una sucursal Pitaya.</p>
            <p><strong>¿Cómo activar la ubicación?</strong><br>
               Busca el ícono de candado 🔒 o de información ℹ️ junto a la dirección del sitio,
               cambia el permiso de <em>Ubicación</em> a <strong>Permitir</strong> y recarga la página.</p>
        `;
        el.style.display = 'block';
        document.getElementById('registrationForm').style.display = 'none';
    }

    function showValidationStatus(msg, type) {
        let el = document.getElementById('ticketValidationStatus');
        el.textContent  = msg;
        el.className    = `ticket-validation-status ${type}`;
        el.style.display = 'block';
    }

    // ─── Cámara ───────────────────────────────────────────────────────────────
    async function openCamera() {
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            document.getElementById('video').srcObject = stream;
            document.getElementById('cameraModal').classList.add('active');
        } catch(e) {
            alert('No se pudo acceder a la cámara. Verifica los permisos del navegador.');
        }
    }

    function closeCamera() {
        if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
        document.getElementById('video').srcObject = null;
        document.getElementById('cameraModal').classList.remove('active');
    }

    async function capturePhoto() {
        const video  = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const ctx    = canvas.getContext('2d');
        canvas.width  = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0);

        canvas.toBlob(async blob => {
            capturedPhotoBlob = blob;

            // Preview
            const url = URL.createObjectURL(blob);
            document.getElementById('ticketPreviewContainer').innerHTML =
                `<img src="${url}" class="ticket-preview" alt="Vista previa del ticket">`;

            closeCamera();
            await processTicketImage(blob);
        }, 'image/jpeg', 0.92);
    }

    async function processTicketImage(blob) {
        showValidationStatus('Verificando ticket...', 'info');
        document.getElementById('submitBtn').disabled    = true;
        document.getElementById('codigo_participacion').value = '';
        document.getElementById('puntosContainer').style.display = 'none';
        document.getElementById('h_validado_imagen').value = '0';

        const fd = new FormData();
        fd.append('ticket_image', blob, 'ticket.jpg');
        // Enviar el token de sesión para que el servidor valide antes de procesar
        const tokenEl = document.getElementById('h_token_sesion');
        if (tokenEl && tokenEl.value) fd.append('token_sesion', tokenEl.value);

        try {
            const resp = await fetch('detect_invoice.php', { method: 'POST', body: fd });
            const data = await resp.json();

            if (data.success) {
                // Llenar código (no editable)
                document.getElementById('codigo_participacion').value = data.codigo_participacion;

                // Llenar puntos
                document.getElementById('puntosValor').textContent = data.puntos_sorteo;
                const tipoEl = document.getElementById('puntosTipo');
                tipoEl.textContent = data.es_club ? 'Club Pitaya' : 'Cliente Regular';
                tipoEl.style.display = 'inline-block';
                document.getElementById('puntosContainer').style.display = 'block';

                // Campos ocultos
                document.getElementById('h_es_club').value            = data.es_club ? '1' : '0';
                document.getElementById('h_ia_fecha').value           = data.ia_fecha || '';
                document.getElementById('h_ia_hora').value            = data.ia_hora  || '';
                document.getElementById('h_ia_sucursal_nombre').value = data.ia_sucursal_nombre || '';
                document.getElementById('h_ia_monto_total').value     = data.ia_monto_total ?? '';
                document.getElementById('h_puntos_sorteo').value      = data.puntos_sorteo;
                document.getElementById('h_validado_imagen').value    = '1';

                showValidationStatus('✅ Ticket verificado. Código: ' + data.codigo_participacion, 'success');
                document.getElementById('submitBtn').disabled = false;
                document.getElementById('cameraOpenBtn').textContent = '📷 Retomar foto';
            } else {
                showValidationStatus('⚠️ ' + (data.mensaje || 'No se pudo verificar el ticket. Reintente la foto.'), 'warning');
                document.getElementById('submitBtn').disabled = true;
            }
        } catch(e) {
            showValidationStatus('⚠️ Error al verificar el ticket. Reintente la foto.', 'warning');
            document.getElementById('submitBtn').disabled = true;
        }
    }

    // ─── Formulario ───────────────────────────────────────────────────────────
    function capitalizeName(input) {
        const words = input.value.trim().toLowerCase().split(/\s+/);
        if (words.length < 2) {
            input.setCustomValidity('Por favor ingresa nombre y apellido');
            return;
        }
        input.setCustomValidity('');
        input.value = words.map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
    }

    document.getElementById('registrationForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        if (!capturedPhotoBlob) {
            showMsg('Debes tomar una foto del ticket.', 'red');
            return;
        }
        if (!document.getElementById('codigo_participacion').value) {
            showMsg('El código de participación no fue leído. Reintenta la foto del ticket.', '#ff6b6b');
            return;
        }
        // ia_sucursal_nombre obligatorio para prevenir duplicados
        if (!document.getElementById('h_ia_sucursal_nombre').value) {
            showMsg('No se pudo identificar la sucursal del ticket. Reintenta la foto.', '#ff6b6b');
            return;
        }

        document.getElementById('submitBtn').disabled = true;
        showMsg('Enviando registro...', 'var(--primary-teal)');

        const fd = new FormData(this);
        fd.append('foto_ticket', capturedPhotoBlob, 'ticket.jpg');

        try {
            const resp = await fetch('save_registration.php', { method: 'POST', body: fd });
            const data = await resp.json();

            if (data.success) {
                const pts = data.puntos_sorteo ?? document.getElementById('h_puntos_sorteo').value;
                document.getElementById('pointsDisplay').textContent =
                    `Participas con ${pts} punto${pts != 1 ? 's' : ''}`;
                document.getElementById('successModal').classList.add('active');
                this.reset();
                capturedPhotoBlob = null;
                document.getElementById('ticketPreviewContainer').innerHTML = '';
                document.getElementById('ticketValidationStatus').style.display = 'none';
                document.getElementById('puntosContainer').style.display = 'none';
                showMsg('', '');
            } else if (data.limit_reached) {
                // Límite de 20 tickets por dispositivo alcanzado
                document.getElementById('registrationForm').style.display = 'none';
                const el = document.getElementById('qrRequiredMessage');
                el.innerHTML = `
                    <div class="lock-icon" style="font-size:2.2rem">🎟️</div>
                    <h2>Límite de participaciones alcanzado</h2>
                    <p>Has registrado el máximo de <strong>20 tickets</strong> permitidos por participante en este sorteo.</p>
                    <p>¡Mucha suerte en el sorteo del Concierto Martin Garrix! 🎶</p>
                `;
                el.style.display = 'block';
            } else {
                showMsg('Error: ' + (data.message || 'Intenta nuevamente.'), '#ff6b6b');
                document.getElementById('submitBtn').disabled = false;
            }
        } catch(e) {
            showMsg('Error de conexión. Intenta nuevamente.', '#ff6b6b');
            document.getElementById('submitBtn').disabled = false;
        }
    });

    function closeSuccessModal() {
        document.getElementById('successModal').classList.remove('active');
        sessionStorage.removeItem(SESSION_KEY);
        sessionData = null;
        // Bloquear formulario — deben escanear QR de nuevo
        document.getElementById('registrationForm').style.display = 'none';
        showLockMessage('Para registrar otro ticket, escanea el código QR en tu sucursal Pitaya nuevamente.');
    }

    // ─── Tabs ─────────────────────────────────────────────────────────────────
    function showTab(tabId, event) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        event.currentTarget.classList.add('active');
        document.getElementById(tabId).classList.add('active');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────
    function showMsg(msg, color) {
        const el = document.getElementById('formMessage');
        el.textContent   = msg;
        el.style.color   = color;
    }

    function escHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }
    </script>
</body>
</html>
