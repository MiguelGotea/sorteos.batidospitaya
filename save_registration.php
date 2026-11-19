<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/core/config.php';

$response = ['success' => false, 'message' => '', 'puntos_sorteo' => 0];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

// ─── Leer campos ──────────────────────────────────────────────────────────────
$tokenSesion         = trim($_POST['token_sesion']          ?? '');
$nombre              = trim($_POST['nombre_completo']        ?? '');
$contacto            = trim($_POST['numero_contacto']        ?? '');
$cedula              = trim($_POST['numero_cedula']          ?? '');
$correo              = trim($_POST['correo_electronico']      ?? '');
$codigoParticipacion = trim($_POST['codigo_participacion']   ?? '');
$puntosStr           = $_POST['puntos_sorteo']              ?? 0;
$esClubStr           = $_POST['es_club']                    ?? '0';
$iaFecha             = $_POST['ia_fecha']                   ?? null;
$iaHora              = $_POST['ia_hora']                    ?? null;
$iaSucursalNombre    = $_POST['ia_sucursal_nombre']         ?? null;
$iaMonto             = $_POST['ia_monto_total']             ?? null;
$sucursalId          = $_POST['sucursal_id']                ?? null;
$sucursalNombre      = $_POST['sucursal_nombre']            ?? null;
$validadoImagen      = (int) ($_POST['validado_imagen']     ?? 0);

// ─── Validaciones ─────────────────────────────────────────────────────────────
if (empty($tokenSesion)) {
    $response['message'] = 'Sesión no válida. Escanea el QR nuevamente.';
    echo json_encode($response);
    exit;
}

if (empty($nombre)) {
    $response['message'] = 'El nombre completo es obligatorio.';
    echo json_encode($response);
    exit;
}
$palabrasNombre = preg_split('/\s+/', trim($nombre));
if (count($palabrasNombre) < 2) {
    $response['message'] = 'Por favor ingresa nombre y apellido completo.';
    echo json_encode($response);
    exit;
}
// Capitalizar nombre
$nombre = implode(' ', array_map(fn($p) => ucfirst(strtolower($p)), $palabrasNombre));

if (!preg_match('/^[0-9]{8}$/', $contacto)) {
    $response['message'] = 'El número de contacto debe tener exactamente 8 dígitos.';
    echo json_encode($response);
    exit;
}

if (empty($codigoParticipacion)) {
    $response['message'] = 'El código de participación es obligatorio. Reintente la foto del ticket.';
    echo json_encode($response);
    exit;
}

// ia_sucursal_nombre es OBLIGATORIO: si la extracción falló el sistema no puede
// verificar duplicados correctamente. Pedir reintentar la foto.
if (empty(trim($iaSucursalNombre ?? ''))) {
    $response['message'] = 'No se pudo identificar la sucursal del ticket. Por favor reintente la fotografía.';
    echo json_encode($response);
    exit;
}

if (!empty($correo) && !preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $correo)) {
    $response['message'] = 'El correo electrónico no es válido.';
    echo json_encode($response);
    exit;
}

if (!isset($_FILES['foto_ticket']) || $_FILES['foto_ticket']['error'] !== UPLOAD_ERR_OK) {
    $response['message'] = 'La foto del ticket es obligatoria.';
    echo json_encode($response);
    exit;
}

// ─── Preparar payload multipart para la API ───────────────────────────────────
$puntos   = max(0, (int) $puntosStr);
$esClub   = ($esClubStr === '1' || $esClubStr === 'true') ? 1 : 0;
$iaMonto  = ($iaMonto !== null && is_numeric($iaMonto)) ? (float) $iaMonto : null;
$sucId    = ($sucursalId !== null && is_numeric($sucursalId)) ? (int) $sucursalId : null;

$postFields = [
    'token_sesion'          => $tokenSesion,
    'nombre_completo'       => $nombre,
    'numero_contacto'       => $contacto,
    'numero_cedula'         => $cedula,
    'correo_electronico'    => $correo,
    'codigo_participacion'  => $codigoParticipacion,
    'puntos_sorteo'         => $puntos,
    'es_club'               => $esClub,
    'ia_fecha'              => $iaFecha ?? '',
    'ia_hora'               => $iaHora ?? '',
    'ia_sucursal_nombre'    => $iaSucursalNombre ?? '',
    'ia_monto_total'        => $iaMonto ?? '',
    'sucursal_id'           => $sucId ?? '',
    'sucursal_nombre'       => $sucursalNombre ?? '',
    'validado_imagen'       => $validadoImagen,
    'foto_ticket'           => new CURLFile(
        $_FILES['foto_ticket']['tmp_name'],
        $_FILES['foto_ticket']['type'] ?: 'image/jpeg',
        'ticket.jpg'
    ),
];

// ─── Llamar a API ─────────────────────────────────────────────────────────────
$ch = curl_init(API_SORTEOS_BASE . '/guardar_registro.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postFields,
    CURLOPT_HTTPHEADER     => ['X-Sorteos-Token: ' . API_SORTEOS_TOKEN],
    CURLOPT_TIMEOUT        => 20,
]);
$apiResult = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$apiResult) {
    $response['message'] = 'Error al guardar el registro. Intenta nuevamente.';
    echo json_encode($response);
    exit;
}

$apiData = json_decode($apiResult, true);

if ($apiData['success'] ?? false) {
    $response['success']       = true;
    $response['message']       = 'Registro guardado exitosamente.';
    $response['puntos_sorteo'] = $apiData['puntos_sorteo'] ?? $puntos;
} else {
    $response['message'] = $apiData['error'] ?? 'Error al procesar el registro.';
}

echo json_encode($response);
