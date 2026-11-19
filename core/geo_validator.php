<?php
require_once __DIR__ . '/config.php';

/**
 * Calcula la distancia en metros entre dos puntos usando la fórmula de Haversine
 */
function calcularDistanciaMetros(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $R    = 6371000; // Radio de la Tierra en metros
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a    = sin($dLat / 2) ** 2
          + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    $c    = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return round($R * $c, 2);
}

/**
 * Valida si el usuario está dentro del radio de CUALQUIER sucursal activa.
 * Retorna la sucursal más cercana dentro del rango.
 *
 * @return array {
 *   valido: bool,
 *   sucursal_id: int|null,
 *   sucursal_nombre: string|null,
 *   distancia: int|null,
 *   mensaje: string
 * }
 */
function validarUbicacionMultiSucursal($lat, $lng): array
{
    if (!is_numeric($lat) || !is_numeric($lng)) {
        return [
            'valido'           => false,
            'sucursal_id'      => null,
            'sucursal_nombre'  => null,
            'distancia'        => null,
            'mensaje'          => 'Coordenadas de ubicación no válidas.',
        ];
    }

    $lat = (float) $lat;
    $lng = (float) $lng;

    $mejorSucursal  = null;
    $menorDistancia = PHP_INT_MAX;

    $sucursales = getSucursalesActivas(); // Obtiene desde API (con caché en memoria)

    if (empty($sucursales)) {
        return [
            'valido'           => false,
            'sucursal_id'      => null,
            'sucursal_nombre'  => null,
            'distancia'        => null,
            'mensaje'          => 'No se pudo obtener la lista de sucursales. Intenta nuevamente.',
        ];
    }

    foreach ($sucursales as $sucursal) {
        $dist = calcularDistanciaMetros($sucursal['lat'], $sucursal['lng'], $lat, $lng);
        if ($dist < $menorDistancia) {
            $menorDistancia = $dist;
            $mejorSucursal  = $sucursal;
        }
    }

    if ($menorDistancia <= RADIO_METROS) {
        return [
            'valido'          => true,
            'sucursal_id'     => $mejorSucursal['id'],
            'sucursal_nombre' => $mejorSucursal['nombre'],
            'distancia'       => (int) $menorDistancia,
            'mensaje'         => 'Ubicación verificada en ' . $mejorSucursal['nombre'] . '.',
        ];
    }

    return [
        'valido'          => false,
        'sucursal_id'     => null,
        'sucursal_nombre' => null,
        'distancia'       => (int) $menorDistancia,
        'mensaje'         => 'No se detectó ninguna sucursal Pitaya participante cerca de tu ubicación. '
                           . 'La más cercana es ' . $mejorSucursal['nombre']
                           . ' (' . (int) $menorDistancia . ' metros).',
    ];
}
