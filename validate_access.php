<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/core/config.php';
if (GEO_RESTRICCION_ACTIVA) {
    require_once __DIR__ . '/core/geo_validator.php';
}

$response = ['success' => false, 'message' => '', 'token' => null, 'sucursal_nombre' => null];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

$input       = json_decode(file_get_contents('php://input'), true) ?? [];
$k           = trim($input['k'] ?? '');
$lat         = $input['lat'] ?? null;
$lng         = $input['lng'] ?? null;
$accuracy    = $input['accuracy'] ?? null;
$timezone    = trim($input['timezone'] ?? '');

// ─── IP del cliente ──────────────────────────────────────────────────────────
$ip = $_SERVER['HTTP_CF_CONNECTING_IP']
     ?? $_SERVER['HTTP_X_FORWARDED_FOR']
     ?? $_SERVER['REMOTE_ADDR']
     ?? '';
if (strpos($ip, ',') !== false) {
    $ip = trim(explode(',', $ip)[0]);
}

// ─── CAPA 1: Llave QR ────────────────────────────────────────────────────────
if (empty($k) || $k !== QR_SECRET) {
    $response['message']    = 'Para participar en el sorteo, escanea el código QR disponible en tu sucursal Pitaya.';
    $response['error_type'] = 'INVALID_KEY';
    echo json_encode($response);
    exit;
}

// ─── CAPA 1.5: Verificación de País (Nicaragua, sin permisos de GPS) ─────────
if (defined('VALIDAR_PAIS_NICARAGUA') && VALIDAR_PAIS_NICARAGUA) {
    if (!validarPaisNicaragua($ip, $timezone)) {
        $response['message']    = 'Esta promoción está disponible únicamente para participantes ubicados en Nicaragua.';
        $response['error_type'] = 'COUNTRY_RESTRICTED';
        echo json_encode($response);
        exit;
    }
}

// ─── CAPA 2: Geolocalización (controlada por GEO_RESTRICCION_ACTIVA) ─────────
if (GEO_RESTRICCION_ACTIVA) {
    if ($lat === null || $lng === null) {
        $response['message']    = 'Es necesario permitir el acceso a tu ubicación para verificar que estás en una sucursal Pitaya participante.';
        $response['error_type'] = 'GEO_REQUIRED';
        echo json_encode($response);
        exit;
    }

    $geoCheck = validarUbicacionMultiSucursal($lat, $lng);

    if (!$geoCheck['valido']) {
        $response['message']    = $geoCheck['mensaje'];
        $response['error_type'] = 'OUT_OF_RANGE';
        $response['distancia']  = $geoCheck['distancia'];
        echo json_encode($response);
        exit;
    }

    $sucursalId     = $geoCheck['sucursal_id'];
    $sucursalNombre = $geoCheck['sucursal_nombre'];
    $distancia      = $geoCheck['distancia'];
} else {
    // Geo desactivada: sin sucursal asociada al acceso
    $sucursalId     = null;
    $sucursalNombre = null;
    $distancia      = null;
}

// ─── CAPA 3: Crear sesión vía API ─────────────────────────────────────────────
$token    = bin2hex(random_bytes(32));
$expiraEn = date('Y-m-d H:i:s', time() + (EXPIRACION_SESION_MINUTOS * 60));
$ip       = $_SERVER['HTTP_CF_CONNECTING_IP']
         ?? $_SERVER['HTTP_X_FORWARDED_FOR']
         ?? $_SERVER['REMOTE_ADDR']
         ?? '';

$precisionMetros = ($accuracy !== null && is_numeric($accuracy)) ? (int) round((float) $accuracy) : null;

$payload = json_encode([
    'token'                 => $token,
    'ip'                    => $ip,
    'geo_lat'               => $lat,
    'geo_lng'               => $lng,
    'distancia_metros'      => $distancia,
    'geo_precision_metros'  => $precisionMetros,
    'sucursal_id'           => $sucursalId,
    'sucursal_nombre'       => $sucursalNombre,
    'expira_en'             => $expiraEn,
]);

$ch = curl_init(API_SORTEOS_BASE . '/crear_sesion.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-Sorteos-Token: ' . API_SORTEOS_TOKEN,
    ],
    CURLOPT_TIMEOUT        => 10,
]);
$apiResult  = curl_exec($ch);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    $response['message'] = 'No se pudo iniciar la sesión de registro. Intenta nuevamente.';
    echo json_encode($response);
    exit;
}

$apiData = json_decode($apiResult, true);
if (!($apiData['success'] ?? false)) {
    $response['message'] = 'Error al registrar la sesión: ' . ($apiData['error'] ?? 'desconocido');
    echo json_encode($response);
    exit;
}

$response['success']          = true;
$response['message']          = 'Verificación exitosa. Acceso concedido.';
$response['token']            = $token;
$response['sucursal_id']      = $sucursalId;
$response['sucursal_nombre']  = $sucursalNombre;

echo json_encode($response);

/**
 * Valida si la conexión proviene de Nicaragua sin solicitar permisos de GPS al usuario.
 * Métodos:
 * 1. IPs locales o de desarrollo (127.0.0.1, privadas) -> permitido
 * 2. Header de país de Cloudflare (HTTP_CF_IPCOUNTRY) -> evalúa 'NI'
 * 3. Servicio GeoIP público rápido por IP (timeout 1.5s)
 * 4. Fallback por zona horaria reportada por el navegador (America/Managua)
 */
function validarPaisNicaragua(string $ip, string $timezone = ''): bool
{
    // 1. IPs locales o de desarrollo
    if (in_array($ip, ['127.0.0.1', '::1', '']) || 
        filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return true;
    }

    // 2. Cloudflare Country Header
    $cfCountry = strtoupper(trim($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''));
    if (!empty($cfCountry) && $cfCountry !== 'XX' && $cfCountry !== 'T1') {
        return ($cfCountry === 'NI');
    }

    // 3. Consulta GeoIP ligera (timeout estricto)
    $ch = curl_init("http://ip-api.com/json/{$ip}?fields=status,countryCode");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 2,
        CURLOPT_CONNECTTIMEOUT => 1,
    ]);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $res) {
        $data = json_decode($res, true);
        if (($data['status'] ?? '') === 'success' && !empty($data['countryCode'])) {
            return (strtoupper($data['countryCode']) === 'NI');
        }
    }

    // 4. Fallback por zona horaria del dispositivo
    if (!empty($timezone) && $timezone === 'America/Managua') {
        return true;
    }

    // Si los servicios externos fallan, se permite para no perjudicar a usuarios legítimos
    return true;
}
