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

// ─── SWITCH: Restricción de Geolocalización (GPS) ─────────────────────────────
// true  → el sistema verifica que el usuario esté físicamente cerca de una sucursal Pitaya
//          (solicita permisos de GPS en el navegador, valida radio de 2km).
// false → la restricción de GPS está DESACTIVADA (no pide permisos de ubicación al usuario).
// ─────────────────────────────────────────────────────────────────────────────
define('GEO_RESTRICCION_ACTIVA', false);

// ─── SWITCH: Validación de País (Nicaragua) sin requerir permisos ────────────
// true  → comprueba que el participante esté en Nicaragua mediante IP (Cloudflare / GeoIP)
//          y zona horaria, sin solicitar ningún permiso al usuario.
// false → no valida país.
// ─────────────────────────────────────────────────────────────────────────────
define('VALIDAR_PAIS_NICARAGUA', true);

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
// Sucursales activas — se obtienen en tiempo real desde la API
// (tabla sucursales WHERE activa=1 AND sucursal=1, campos: codigo, nombre, lat, lng)
//
// El campo 'id' de cada entrada devuelta por la API = sucursales.codigo
// (NO el id de fila). Es el código de sucursal usado en registros y reportes.
//
// La función getSucursalesActivas() hace la llamada una sola vez por request
// y cachea el resultado en $GLOBALS para evitar dobles llamadas.
// -----------------------------------------------------------------------------
function getSucursalesActivas(): array
{
    if (isset($GLOBALS['__sucursales_cache'])) {
        return $GLOBALS['__sucursales_cache'];
    }

    $ch = curl_init(API_SORTEOS_BASE . '/sucursales_activas.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET        => true,
        CURLOPT_HTTPHEADER     => ['X-Sorteos-Token: ' . API_SORTEOS_TOKEN],
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $sucursales = [];
    if ($httpCode === 200 && $result) {
        $data = json_decode($result, true);
        if (!empty($data['success']) && is_array($data['sucursales'])) {
            $sucursales = $data['sucursales'];
        }
    }

    $GLOBALS['__sucursales_cache'] = $sucursales;
    return $sucursales;
}
