<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/core/config.php';

$response = [
    'success'              => false,
    'codigo_participacion' => null,
    'puntos_sorteo'        => 0,
    'es_club'              => false,
    'ia_fecha'             => null,
    'ia_hora'              => null,
    'ia_sucursal_nombre'   => null,
    'ia_monto_total'       => null,
    'mensaje'              => '',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['ticket_image'])) {
    $response['mensaje'] = 'No se recibió la imagen del ticket.';
    echo json_encode($response);
    exit;
}

$image = $_FILES['ticket_image'];
if ($image['error'] !== UPLOAD_ERR_OK) {
    $response['mensaje'] = 'Error al recibir la imagen.';
    echo json_encode($response);
    exit;
}

// ─── Llamada a Google Vision API ──────────────────────────────────────────────
$imageContent = base64_encode(file_get_contents($image['tmp_name']));
$requestBody  = json_encode([
    'requests' => [[
        'image'    => ['content' => $imageContent],
        'features' => [['type' => 'TEXT_DETECTION', 'maxResults' => 1]],
    ]],
]);

$apiUrl = 'https://vision.googleapis.com/v1/images:annotate?key=' . GOOGLE_VISION_API_KEY;

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $requestBody,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 15,
]);
$result    = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$result) {
    $response['mensaje'] = 'No se pudo procesar la imagen del ticket. Reintente la foto.';
    echo json_encode($response);
    exit;
}

$visionData = json_decode($result, true);
$fullText   = $visionData['responses'][0]['textAnnotations'][0]['description'] ?? '';

if (empty($fullText)) {
    $response['mensaje'] = 'No se detectó texto en la imagen. Asegúrese de que el ticket sea legible y reintente.';
    echo json_encode($response);
    exit;
}

// ─── EXTRACCIÓN OBLIGATORIA: Código de participación + Club ───────────────────
$codigoParticipacion = null;
$esClub              = false;

// Patrón "Código Club para sorteo: XXXXX" → club
if (preg_match('/C[oó]digo\s+Club\s+para\s+sorteo\s*[:\-]?\s*(\d{4,20})/i', $fullText, $m)) {
    $codigoParticipacion = $m[1];
    $esClub              = true;
}
// Patrón "Código para sorteo: XXXXX" → regular
elseif (preg_match('/C[oó]digo\s+para\s+sorteo\s*[:\-]?\s*(\d{4,20})/i', $fullText, $m)) {
    $codigoParticipacion = $m[1];
    $esClub              = false;
}
// Fallback: "CODIGO.*SORTEO.*XXXXX"
elseif (preg_match('/(?:CODIGO|SORTEO|PARTICIPAR)(?:\s+\w+)?\s*[:\-]?\s*(\d{5,20})/i', $fullText, $m)) {
    $codigoParticipacion = $m[1];
}
// Fallback: "No. XXXXX" o "#XXXXX"
elseif (preg_match('/(?:No\.?|#)\s*(\d{4,20})/i', $fullText, $m)) {
    $codigoParticipacion = $m[1];
}

// Si no se pudo extraer el código: falla crítica
if ($codigoParticipacion === null) {
    $response['mensaje'] = 'No se pudo leer el código del ticket. Por favor reintente la foto asegurándose de que el código sea visible y esté bien iluminado.';
    echo json_encode($response);
    exit;
}

// ─── EXTRACCIÓN OBLIGATORIA: Monto total → calcula puntos ────────────────────
$montoTotal = null;

if (preg_match('/Total\s+a\s+Pagar\s*[:\-]?\s*C?\$?\s*([\d,]+\.?\d*)/i', $fullText, $m)) {
    $montoTotal = (float) str_replace(',', '', $m[1]);
} elseif (preg_match('/TOTAL\s*[:\-]?\s*C?\$?\s*([\d,]+\.?\d*)/i', $fullText, $m)) {
    $montoTotal = (float) str_replace(',', '', $m[1]);
}

// Calcular puntos
$puntos = 0;
if ($montoTotal !== null && $montoTotal > 0) {
    $puntos = (int) floor($montoTotal / PUNTOS_POR_MONTO);
    if ($esClub) {
        $puntos = $puntos * MULTIPLICADOR_CLUB;
    }
}

// ─── EXTRACCIÓN OPCIONAL: Fecha ───────────────────────────────────────────────
$iaFecha = null;
if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $fullText, $m)) {
    // dd/mm/yyyy → yyyy-mm-dd
    $iaFecha = $m[3] . '-' . $m[2] . '-' . $m[1];
} elseif (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $fullText, $m)) {
    $iaFecha = $m[1] . '-' . $m[2] . '-' . $m[3];
}

// ─── EXTRACCIÓN OPCIONAL: Hora ────────────────────────────────────────────────
$iaHora = null;
if (preg_match('/\b(\d{1,2}):(\d{2})(?::(\d{2}))?\b/', $fullText, $m)) {
    $h = str_pad($m[1], 2, '0', STR_PAD_LEFT);
    $i = $m[2];
    $s = isset($m[3]) && $m[3] !== '' ? $m[3] : '00';
    $iaHora = "$h:$i:$s";
}

// ─── EXTRACCIÓN OPCIONAL: Sucursal ───────────────────────────────────────────
$iaSucursal = null;
if (preg_match('/Sucursal\s*[:\-]?\s*([^\n\r]+)/i', $fullText, $m)) {
    $iaSucursal = trim($m[1]);
} else {
    // Buscar nombre de sucursal conocida en el texto
    $sucursalesConocidas = ['León','Matagalpa','Estelí','Altamira','Villa Fontana','Granada',
                            'Las Colinas','Masaya','Natura','Las Brisas','Rivas','Única',
                            'Ticuantepe','Calli','Bolonia','Las Américas'];
    foreach ($sucursalesConocidas as $nombre) {
        if (stripos($fullText, $nombre) !== false) {
            $iaSucursal = $nombre;
            break;
        }
    }
}

// ─── Respuesta exitosa ────────────────────────────────────────────────────────
$response['success']              = true;
$response['codigo_participacion'] = $codigoParticipacion;
$response['puntos_sorteo']        = $puntos;
$response['es_club']              = $esClub;
$response['ia_fecha']             = $iaFecha;
$response['ia_hora']              = $iaHora;
$response['ia_sucursal_nombre']   = $iaSucursal;
$response['ia_monto_total']       = $montoTotal;
$response['mensaje']              = 'Ticket procesado correctamente.';

echo json_encode($response);
