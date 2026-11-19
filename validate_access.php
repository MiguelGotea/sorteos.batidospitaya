<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/geo_validator.php';

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

// ─── CAPA 1: Llave QR ────────────────────────────────────────────────────────
if (empty($k) || $k !== QR_SECRET) {
    $response['message']    = 'Para participar en el sorteo, escanea el código QR disponible en tu sucursal Pitaya.';
    $response['error_type'] = 'INVALID_KEY';
    echo json_encode($response);
    exit;
}

// ─── CAPA 2: Geolocalización ──────────────────────────────────────────────────
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
    'distancia_metros'      => $geoCheck['distancia'],
    'geo_precision_metros'  => $precisionMetros,
    'sucursal_id'           => $geoCheck['sucursal_id'],
    'sucursal_nombre'       => $geoCheck['sucursal_nombre'],
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
$response['sucursal_id']      = $geoCheck['sucursal_id'];
$response['sucursal_nombre']  = $geoCheck['sucursal_nombre'];

echo json_encode($response);
