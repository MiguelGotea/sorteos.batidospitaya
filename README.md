# sorteos.batidospitaya.com
## Plataforma de Registro de Puntos — Sorteo Concierto Martin Garrix 2026

> **Identificador interno:** `concierto_martingarrix`  
> **Herramienta ERP:** `gestion_sorteos` → módulo `gestion_sorteos_conciertomartin.php`

---

## 1. Visión General

Plataforma web para que clientes de Batidos Pitaya registren sus tickets de compra y acumulen puntos para participar en el sorteo del Concierto Martin Garrix 2026. Fusiona dos sistemas previos:

| Sistema fuente | Qué se tomó |
|---|---|
| `pitayalove.batidospitaya.com` | Estructura visual: fondo único, 3 pestañas, estilos |
| `descubre.batidospitaya.com` | Mecanismo de acceso: QR físico + geolocalización + sesiones |

---

## 2. Arquitectura de Proyectos

```
sorteos.batidospitaya.com/              ← Frontend (PHP relay, sin lógica sensible)
api.batidospitaya.com/api/sorteos/      ← Backend API (procesamiento real, AIService)
erp.batidospitaya.com/modulos/marketing/ ← Módulo de gestión interna
descubre.batidospitaya.com/             ← Solo .htaccess de redirección del QR físico
```

---

## 3. Estrategia de Acceso (QR + Geo)

### Flujo completo

```
QR físico en sucursal
    └── URL: descubre.batidospitaya.com/?k=bP9x2Qm7vN4kRj3sT8wLf6Yh
         └── .htaccess redirige → sorteos.batidospitaya.com/?k=SORTEOS_2026_CONCIERTO_MARTINGARRIX_X9K7M
              └── validate_access.php
                   ├── Verifica ?k= (QR_SECRET)
                   ├── Pide geolocalización del dispositivo
                   │    ├── Esperando → "Verificando ubicación / acepta el permiso..."
                   │    ├── Denegado → "Activa tu ubicación" + instrucciones (no muestra Restringido)
                   │    └── Error GPS → "Acceso Restringido" con motivo
                   ├── Haversine contra las 16 sucursales activas (radio 150 m)
                   └── Si OK → crea sesión en DB (token UUID, expira 30 min)
                        └── Formulario activo para 1 ticket
```

### Restricciones de acceso
- El QR físico en descubre redirige al secreto de sorteos. **Nunca cambiar el QR impreso.**
- Cada escaneo = 1 sesión. La sesión se marca `usado=1` al guardar el registro.
- Un cliente **puede** registrar múltiples tickets siempre que escanee el QR por cada uno.
- La verificación de ubicación cubre **todas las sucursales activas** (`activa=1`), radio 150 m.
- Si el usuario deniega ubicación → instrucciones para activarla (no muestra "Acceso Restringido").
- Pestañas **"Cómo Participar"** y **"Premios"** son siempre visibles sin QR ni ubicación.
- Solo la pestaña **Registro** requiere QR + ubicación válida.

### Secretos
| Clave | Valor |
|---|---|
| QR físico (descubre) | `bP9x2Qm7vN4kRj3sT8wLf6Yh` |
| QR secret en sorteos | `SORTEOS_2026_CONCIERTO_MARTINGARRIX_X9K7M` |
| API Token (Header `X-Sorteos-Token`) | `s0rt30s_c0nc13rt0_m4rt1n_g4rr1x_2026_s3cr3t` |

---

## 4. Procesamiento de Tickets (Extracción de Datos)

### Motor de procesamiento
Se usa `AIService.php` ubicado en `api.batidospitaya.com/core/ai/AIService.php`. Este servicio usa **cascada de proveedores**: google → openai → groq. Las API keys **nunca están en código** — viven en la tabla `ia_proveedores_api` de la DB.

> ⚠️ **NUNCA mencionar "IA", "inteligencia artificial", "Vision API"** al usuario. Usar términos como "verificación del ticket" o "procesamiento de imagen".

### Relay de imagen (token protegido)

```
index.php (usuario sube foto + token_sesion)
    └── detect_invoice.php  (RELAY — sorteos frontend)
         ├── [FIX A] Valida que token_sesion no sea vacío
         ├── [FIX B] Valida tamaño ≤ 8 MB
         └── POST → api.batidospitaya.com/api/sorteos/procesar_ticket.php
              ├── [FIX A] Verifica sesión en DB (existe, no usada, no expirada)
              └── AIService.php ← accede a ia_proveedores_api (keys en DB)
                   └── Responde JSON limpio (sin keys, sin raw text)
```

El token de procesamiento **nunca sale del servidor API**. El frontend solo envía la imagen y recibe datos extraídos.

### Datos extraídos del ticket

| Campo | Obligatorio | Se muestra al usuario |
|---|---|---|
| `codigo_participacion` | ✅ Sí | ✅ Sí (Código de participación) |
| `ia_sucursal_nombre` | ✅ Sí | ❌ No (uso interno para anti-duplicado) |
| `ia_monto_total` | No (puede ser nulo) | ✅ Sí (traducido a puntos) |
| `ia_fecha` | No | ❌ No |
| `ia_hora` | No | ❌ No |
| `es_club` | Detectado del título | ❌ No (afecta cálculo de puntos) |

### Detección Club vs Regular
- Ticket normal: `"Código para sorteo: XXXXX"` → `es_club = 0`
- Ticket Club Pitaya: `"Código Club para sorteo: XXXXX"` → `es_club = 1`

### Política de puntos
| Tipo | Puntos |
|---|---|
| Regular | 1 punto por cada C$ 250 |
| Club Pitaya | 2 puntos por cada C$ 250 |

---

## 5. Anti-Duplicados

La restricción de duplicados se basa en:

```sql
UNIQUE KEY uk_codigo_suc_nombre_sorteo (codigo_participacion, ia_sucursal_nombre(50), nombre_sorteo)
```

**Por qué `ia_sucursal_nombre` y NO `sucursal_id`:**
- `sucursal_id` viene del GPS (donde estaba el cliente al escanear). Un cliente puede escanear desde otra ubicación — eso es válido.
- `ia_sucursal_nombre` viene del **texto del ticket** extraído por el motor de procesamiento. Es el dato real de la tienda que emitió el ticket.
- Los correlativos de ticket se repiten entre tiendas (secuencial por tienda). Necesitamos el nombre de la tienda para garantizar unicidad real.

**Triple barrera anti-duplicado:**
1. **JS frontend:** bloquea submit si `ia_sucursal_nombre` está vacío
2. **`save_registration.php`:** valida que no sea vacío antes de llamar a la API
3. **`guardar_registro.php` (API):** SELECT antes del INSERT + try/catch PDOException (error 23000)

---

## 6. Validación Diferida de Tickets

No se valida en tiempo real si el ticket existe en ventas porque el sistema puede tener latencia. Se guarda el registro y se valida después desde el ERP.

**Tabla de validación:** `VentasGlobalesAccessCSV`  
**Campos a triangular:** `CodPedido` + `Fecha` + `Sucursal_Nombre` + `Hora`

**En el ERP:** botón "Validar contra sistema" en cada fila del módulo → llama a `api/sorteos/validar_registro.php` → actualiza `validado_sistema`:
- `NULL` = pendiente de validar
- `1` = ticket real confirmado
- `0` = ticket inválido / no encontrado

---

## 7. Estructura de Tablas

### `sorteos_sesiones_qr`
Registra cada escaneo de QR válido. Una sesión = permiso para un registro.

| Campo | Descripción |
|---|---|
| `token` | UUID único de la sesión |
| `geo_lat / geo_lng` | Coordenadas donde escaneó |
| `distancia_metros` | Distancia a la sucursal detectada |
| `sucursal_id / nombre` | Sucursal detectada por GPS |
| `ip_origen` | IP del cliente (usada para rate limiting) |
| `expira_en` | 30 minutos desde creación |
| `usado` | 1 = ya registró un ticket con esta sesión |

### `sorteos_registros`
Registro de cada participación.

| Campo | Descripción |
|---|---|
| `nombre_sorteo` | Permite reutilizar tabla en sorteos futuros (`concierto_martingarrix`) |
| `codigo_participacion` | Código del ticket (extraído, mostrado al usuario) |
| `ia_sucursal_nombre` | Nombre sucursal del ticket (extraído, uso interno anti-duplicado) |
| `ia_monto_total` | Monto extraído del ticket |
| `es_club` | 0 = regular, 1 = Club Pitaya |
| `puntos_sorteo` | Puntos calculados según política |
| `validado_sistema` | NULL = pendiente, 1 = válido, 0 = inválido |
| `foto_ticket` | Ruta en `uploads/tickets/` dentro de public_html del API |

---

## 8. API Endpoints (`api.batidospitaya.com/api/sorteos/`)

Todos requieren header `X-Sorteos-Token`.

| Archivo | Método | Descripción |
|---|---|---|
| `crear_sesion.php` | POST JSON | Crea sesión QR válida, retorna token |
| `procesar_ticket.php` | POST multipart | Recibe imagen + token_sesion, verifica sesión, extrae datos vía AIService |
| `guardar_registro.php` | POST multipart | Verifica límite IP, guarda participación + foto |
| `listar_registros.php` | GET | Lista registros con filtros y paginación |
| `validar_registro.php` | POST JSON | Actualiza `validado_sistema` de un registro |

---

## 9. Módulo ERP

**Archivo:** `erp.batidospitaya.com/modulos/marketing/gestion_sorteos_conciertomartin.php`  
**Tool ERP:** `nombre='gestion_sorteos'` (id=80 en `tools_erp`)

**Acciones configuradas:**
| Acción | id | Cargo 49 |
|---|---|---|
| `vista` | 166 | allow |
| `edicion` | 167 | allow |
| `invalidacion_masiva` | 216 | allow |

**Cargo con acceso:** 49 — Gerente de Procesos y Automatización

---

## 10. Deploy y CI/CD

| Componente | Detalle |
|---|---|
| Script push | `.scripts/gitpush_sorteos.ps1` — Script Tanque v7 Anti-Choque |
| Workflow sorteos | `.github/workflows/deploy-sorteos.yml` — webfactory/ssh-agent pattern |
| Workflow API | `.github/workflows/deploy-api.yml` — webfactory/ssh-agent pattern |
| Secrets GitHub | `HOSTINGER_SSH_KEY`, `HOSTINGER_HOST`, `HOSTINGER_USER`, `HOSTINGER_PATH_SORTEOS`, `HOSTINGER_PATH_API` |
| Logo | `img/Logo.svg` (SVG, L mayúscula) — NO usar `logo.png` |

### Exclusiones rsync — sorteos
```
.git/  .github/  .scripts/  uploads/  img_bosquejos/  *.md  *.sql
```

### Exclusiones rsync — API
```
.git/  .github/  .scripts/  .agent/  .gitignore
uploads/          ← carpeta raíz de public_html (creada/gestionada manualmente)
api/uploads/      ← dentro de api/ (por si acaso)
core/database/env.php
core/database/env.production.php
```

> ⚠️ **El `.htaccess` del API no está en el repo (gitignore). Se sube/modifica manualmente en servidor.**  
> ⚠️ **La carpeta `uploads/tickets/` se crea manualmente en el servidor. El deploy nunca la toca.**

---

## 11. Estructura de `uploads/` en el servidor API

```
public_html/            ← raíz del sitio api.batidospitaya.com
├── api/
│   └── sorteos/
│       └── guardar_registro.php  → guarda en ../../uploads/tickets/
├── uploads/            ← gestionada manualmente, excluida del deploy
│   └── tickets/
│       ├── .htaccess   ← Deny from all (subido manualmente)
│       └── ticket_*.jpg
└── .htaccess           ← gestionado manualmente, excluido del repo
```

**Path PHP desde `guardar_registro.php`:**
```php
$uploadDir = __DIR__ . '/../../uploads/tickets/';
// __DIR__ = public_html/api/sorteos/
// /../../  = public_html/
// resultado = public_html/uploads/tickets/
```

---

## 12. Seguridad — Vulnerabilidades Resueltas

### Fix A — Sesión requerida antes de procesar imagen
**Sin este fix:** cualquiera podía llamar a `detect_invoice.php` repetidamente sin QR, consumiendo créditos de procesamiento.

**Implementación (triple validación):**
1. `index.php` envía `token_sesion` al llamar `detect_invoice.php`
2. `detect_invoice.php` rechaza si `token_sesion` está vacío
3. `procesar_ticket.php` consulta DB: sesión debe existir, `usado=0`, no expirada

### Fix B — Límite de tamaño de imagen
En `detect_invoice.php`, antes de reenviar a la API:
```php
$MAX_BYTES = 8 * 1024 * 1024; // 8 MB
if ($image['size'] > $MAX_BYTES) → error limpio, sin curl
```

### Fix C — Límite de 20 tickets por IP
En `guardar_registro.php`, tras validar la sesión:
```sql
SELECT COUNT(*) FROM sorteos_registros r
JOIN sorteos_sesiones_qr s ON s.token = r.token_sesion
WHERE s.ip_origen = ? AND r.nombre_sorteo = 'concierto_martingarrix'
```
- Si ≥ 20 → HTTP 429 con `limit_reached: true`
- `save_registration.php` propaga `limit_reached`
- `index.php` reemplaza formulario con mensaje amigable:  
  *"🎟️ Límite de participaciones alcanzado — Has registrado el máximo de 20 tickets"*

### Fix F — Fotos de tickets no accesibles por URL
`uploads/tickets/.htaccess` (subido manualmente al servidor):
```apache
Options -Indexes
Require all denied
```

### Protección de archivos sensibles en sorteos — `.htaccess`
Bloquea acceso HTTP a extensiones que nunca deben ser públicas:
```apache
<FilesMatch "\.(md|sql|log|sh|ps1|env|yml|yaml|json|txt|bak|old)$">
    Deny from all
</FilesMatch>
```
También bloquea carpetas: `.git/`, `.scripts/`, `img_bosquejos/`

### Tabla resumen de seguridad

| Riesgo | Mitigación |
|---|---|
| Exposición de API key de procesamiento | Keys solo en DB (`ia_proveedores_api`). `detect_invoice.php` es relay puro. |
| Consumo de créditos IA sin QR | Fix A: sesión validada en DB antes de cualquier procesamiento |
| Imagen gigante como DoS | Fix B: límite 8 MB en relay |
| Spam de registros por IP | Fix C: máximo 20 tickets por IP por sorteo |
| Ticket duplicado | UNIQUE KEY en DB + SELECT previo + PDOException catch (triple barrera) |
| QR robado / compartido | Sesión expira en 30 min + `usado=1` al registrar |
| Acceso sin QR | `?k=` obligatorio y validado con constante `QR_SECRET` |
| Ticket falso | Validación diferida contra `VentasGlobalesAccessCSV` desde ERP |
| Acceso fuera de sucursal | Haversine 150 m contra todas las sucursales activas |
| Fotos de clientes accesibles por URL | Fix F: `Deny from all` en `uploads/tickets/.htaccess` |
| README/SQL accesibles por URL | `.htaccess` sorteos bloquea `.md`, `.sql`, `.yml`, etc. |
| Listado de directorios | `Options -Indexes` en todos los `.htaccess` |
| Repositorio público | Repo en privado (D) |

---

## 13. UX de Geolocalización

| Estado | Lo que ve el usuario |
|---|---|
| Esperando respuesta del diálogo | 📍 "Verificando ubicación / Por favor acepta el permiso..." |
| Permiso denegado (error code 1) | 📍 "Activa tu ubicación" + instrucciones (candado → Permitir) |
| GPS no disponible o timeout | 🔒 "Acceso Restringido" con motivo técnico |
| Ubicación ok pero fuera de rango | 🔒 "Acceso Restringido" (mensaje del backend) |
| Límite de 20 tickets alcanzado | 🎟️ "Límite de participaciones alcanzado" (reemplaza el formulario) |

---

## 14. Sucursales Activas (hardcoded en `core/config.php`)

| # | Nombre | Lat | Lng |
|---|---|---|---|
| 1 | León | 12.4360159 | -86.8785915 |
| 2 | Matagalpa | 12.9226542 | -85.9199127 |
| 3 | Estelí | 13.0935830 | -86.3557330 |
| 4 | Altamira | 12.1214362 | -86.2524664 |
| 5 | Villa Fontana | 12.1029806 | -86.2626317 |
| 6 | Granada | 11.9301629 | -85.9512482 |
| 7 | Las Colinas | 12.0989821 | -86.2397280 |
| 8 | Masaya | 11.9760658 | -86.0944643 |
| 9 | Natura | 12.1127630 | -86.2757494 |
| 10 | Las Brisas | 12.1520307 | -86.3080606 |
| 11 | Rivas | 11.4384092 | -85.8289844 |
| 12 | Única | 12.0828984 | -86.2299587 |
| 13 | Ticuantepe | 12.0502313 | -86.2024189 |

> Para agregar sucursales: actualizar el array `SUCURSALES_ACTIVAS` en `core/config.php`.

---

## 15. Checklist de Implementación

- [ ] Ejecutar `sorteos_tablas.sql` (o ALTER — ver sección 16)
- [ ] Ejecutar `erp/modulos/marketing/sql/permisos_gestion_sorteos_conciertomartin.sql`
- [ ] Crear carpeta `uploads/tickets/` manualmente en `public_html/` del servidor API
- [ ] Subir manualmente `uploads/tickets/.htaccess` con `Deny from all`
- [ ] Verificar que `ia_proveedores_api` tenga keys activas para google / openai / groq
- [ ] Push a rama main de sorteos → GitHub Actions despliega automáticamente
- [ ] Push en descubre → activa `.htaccess` de redirección del QR físico
- [ ] Verificar acceso vía QR en al menos una sucursal real

---

## 16. ALTER TABLE (Si las tablas ya existen)

Si ya ejecutaste una versión anterior del SQL, aplica solo los cambios:

```sql
-- Paso 1: eliminar restricción anterior si existe (tenía sucursal_id)
ALTER TABLE `sorteos_registros`
  DROP INDEX IF EXISTS `uk_codigo_sucursal_sorteo`;

-- Paso 2: agregar la restricción correcta con ia_sucursal_nombre
ALTER TABLE `sorteos_registros`
  ADD UNIQUE KEY `uk_codigo_suc_nombre_sorteo`
      (`codigo_participacion`, `ia_sucursal_nombre`(50), `nombre_sorteo`);
```

> Verificar índices actuales:
> ```sql
> SHOW INDEX FROM sorteos_registros WHERE Key_name LIKE 'uk_%';
> ```

---

## 17. Reutilización para Sorteos Futuros

La tabla `sorteos_registros` tiene `nombre_sorteo` para soportar múltiples sorteos. Para un sorteo nuevo:

1. Cambiar `NOMBRE_SORTEO` en `core/config.php`
2. Cambiar `QR_SECRET` en `core/config.php`
3. Actualizar artes en `img/fondo.JPEG`, texto de pestañas en `index.php`
4. Crear nuevo módulo ERP con nombre descriptivo (ej. `gestion_sorteos_navidad2026.php`)
5. Actualizar `titulo` y `url_real` en `tools_erp` WHERE `nombre='gestion_sorteos'`
6. El QR físico puede ser nuevo o el mismo (actualizar redirect en `.htaccess` de descubre)
