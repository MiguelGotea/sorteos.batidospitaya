<?php
/**
 * sorteos.batidospitaya.com/detect_invoice.php
 *
 * Relay seguro: recibe la imagen del cliente y la reenvía
 * a api.batidospitaya.com/api/sorteos/procesar_ticket.php
 *
 * El token de procesamiento de imágenes NUNCA toca este servidor
 * ni el navegador del cliente. Solo se usa el token de sorteos
 * para autenticar la llamada servidor-a-servidor.
 */
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

// ─── Reenviar la imagen al endpoint seguro de la API ─────────────────────────
// El API key de procesamiento SOLO existe en api.batidospitaya.com
$apiEndpoint = API_SORTEOS_BASE . '/procesar_ticket.php';

$curlFile = new CURLFile(
    $image['tmp_name'],
    $image['type'] ?: 'image/jpeg',
    $image['name'] ?: 'ticket.jpg'
);

$ch = curl_init($apiEndpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => ['ticket_image' => $curlFile],
    CURLOPT_HTTPHEADER     => [
        'X-Sorteos-Token: ' . API_SORTEOS_TOKEN,
        // Sin Content-Type: lo pone curl automáticamente para multipart
    ],
    CURLOPT_TIMEOUT        => 25,
]);

$result   = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr || !$result) {
    $response['mensaje'] = 'No se pudo procesar la imagen en este momento. Reintente.';
    echo json_encode($response);
    exit;
}

$apiResponse = json_decode($result, true);

if (!$apiResponse) {
    $response['mensaje'] = 'Respuesta inválida del servicio de procesamiento.';
    echo json_encode($response);
    exit;
}

// Propagar respuesta de la API al cliente (sin exponer nada interno)
echo json_encode($apiResponse);
