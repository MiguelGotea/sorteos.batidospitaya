<?php
/**
 * config.php — Configuración central de sorteos.batidospitaya.com
 * Sorteo: Concierto Martin Garrix 2026
 *
 * IMPORTANTE: Las coordenadas de sucursales están hardcodeadas aquí
 * para evitar exponer credenciales de BD. Actualizar manualmente
 * cuando cambien las sucursales activas (activa=1 en tabla sucursales).
 */

// Llave secreta del QR de sorteos
// El .htaccess de descubre.batidospitaya.com redirige al escanear su QR
// pasando esta misma clave como ?k=
define('QR_SECRET', 'SORTEOS_2026_CONCIERTO_MARTINGARRIX_X9K7M');

// ─── SWITCH: Restricción de Geolocalización ──────────────────────────────────
// true  → el sistema verifica que el usuario esté cerca de una sucursal Pitaya
//          (pide GPS, valida radio, guarda sucursal cercana en la sesión).
// false → la restricción está DESACTIVADA: no se pide GPS, no se valida radio
//          y la sesión se crea sin datos de sucursal (sucursal_id = null).
// ─────────────────────────────────────────────────────────────────────────────
define('GEO_RESTRICCION_ACTIVA', false);

// Radio permitido en metros (mismo que descubre)
define('RADIO_METROS', 2000);

// Expiración de sesión en minutos
define('EXPIRACION_SESION_MINUTOS', 30);

// Identificador del sorteo (para reutilizar tablas en futuros sorteos)
define('NOMBRE_SORTEO', 'concierto_martingarrix');

// Política de acumulación de puntos
define('PUNTOS_POR_MONTO', 250);       // C$250 = 1 punto base
define('MULTIPLICADOR_CLUB', 2);       // Club Pitaya: puntos x2

// Token de autenticación para endpoints en api.batidospitaya.com
define('API_SORTEOS_TOKEN', 's0rt30s_c0nc13rt0_m4rt1n_g4rr1x_2026_s3cr3t');
define('API_SORTEOS_BASE',  'https://api.batidospitaya.com/api/sorteos');

// Google Vision API (misma clave que pitayalove)
define('GOOGLE_VISION_API_KEY', 'AIzaSyC4e4r67sp9LTDsMQE4tQCw6orLvKtJQ5M');

// -----------------------------------------------------------------------------
// Sucursales activas hardcodeadas (fuente: tabla sucursales, activa=1)
// Última actualización: 2026-11-19
// -----------------------------------------------------------------------------
define('SUCURSALES_ACTIVAS', [
    ['id' => 1,  'nombre' => 'León',          'lat' => 12.4360158900268,   'lng' => -86.8785914721781],
    ['id' => 2,  'nombre' => 'Matagalpa',     'lat' => 12.9226542017308,   'lng' => -85.9199126929161],
    ['id' => 3,  'nombre' => 'Estelí',        'lat' => 13.093583,          'lng' => -86.355733],
    ['id' => 4,  'nombre' => 'Altamira',      'lat' => 12.1214361971434,   'lng' => -86.2524664284708],
    ['id' => 5,  'nombre' => 'Villa Fontana', 'lat' => 12.1029805528255,   'lng' => -86.2626316680716],
    ['id' => 6,  'nombre' => 'Granada',       'lat' => 11.9301629214584,   'lng' => -85.9512481763033],
    ['id' => 7,  'nombre' => 'Las Colinas',   'lat' => 12.0989821291169,   'lng' => -86.2397280119443],
    ['id' => 8,  'nombre' => 'Masaya',        'lat' => 11.9760657848697,   'lng' => -86.0944643220183],
    ['id' => 9,  'nombre' => 'Natura',        'lat' => 12.1127630492795,   'lng' => -86.2757494203965],
    ['id' => 12, 'nombre' => 'Las Brisas',    'lat' => 12.152030668624,    'lng' => -86.3080605727177],
    ['id' => 13, 'nombre' => 'Rivas',         'lat' => 11.4384092104362,   'lng' => -85.8289843976444],
    ['id' => 19, 'nombre' => 'Única',         'lat' => 12.082898446555852, 'lng' => -86.22995873579353],
    ['id' => 20, 'nombre' => 'Ticuantepe',    'lat' => 12.050231308474618, 'lng' => -86.20241886951335],
    ['id' => 23, 'nombre' => 'Calli',         'lat' => 12.154528924761436, 'lng' => -86.3489311910773],
    ['id' => 25, 'nombre' => 'Bolonia',       'lat' => 12.12299607439811,  'lng' => -86.25234063643902],
    ['id' => 26, 'nombre' => 'Las Américas',  'lat' => 12.139437166695028, 'lng' => -86.22888316647938],
]);
