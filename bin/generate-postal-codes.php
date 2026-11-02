<?php
/**
 * Regenera Mock/postal-codes.json consultando, provincia por provincia, el endpoint
 * público (no documentado) que usa el formulario de "Consulta de Código Postal" de
 * Correo Argentino. Ver docs/DECISIONS.md ADR-001.
 *
 * Uso: php bin/generate-postal-codes.php
 *
 * Script plano: no requiere wp-load.php ni WP-CLI. No se ejecuta en producción ni en
 * cada request; es un paso manual antes de cada release (y lo que corre, con la misma
 * lógica, el refresh mensual por WP-Cron del plugin).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script sólo se ejecuta por línea de comandos.');
}

const CA_ENDPOINT = 'https://www.correoargentino.com.ar/sites/all/modules/custom/ca_forms/api/wsFacade.php';
const CA_REFERER = 'https://www.correoargentino.com.ar/formularios/cpa';
const REQUEST_DELAY_MICROSECONDS = 300000; // 300ms entre provincias, por cortesía con el endpoint.
const CABA_LETTER = 'C';

$pluginDir = dirname(__DIR__);
$provincesFile = $pluginDir . '/Mock/provinces.json';
$outputFile = $pluginDir . '/Mock/postal-codes.json';

$provinces = json_decode(file_get_contents($provincesFile), true);
if (!is_array($provinces) || empty($provinces['provincias'])) {
    fwrite(STDERR, "No se pudo leer {$provincesFile}\n");
    exit(1);
}

/**
 * @return array|null Decoded JSON on success, null on failure.
 */
function fetchLocalidades(string $provinceLetter): ?array
{
    $postFields = http_build_query([
        'action' => 'localidades',
        'localidad' => 'none',
        'calle' => '',
        'altura' => '',
        'provincia' => $provinceLetter,
    ]);

    $headers = [
        'User-Agent: Mozilla/5.0 (compatible; correo-argentino-for-woocommerce dataset generator)',
        'Accept: application/json, text/javascript, */*; q=0.01',
        'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With: XMLHttpRequest',
        'Referer: ' . CA_REFERER,
    ];

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $postFields,
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);

    $body = @file_get_contents(CA_ENDPOINT, false, $context);
    $statusLine = $http_response_header[0] ?? '';
    $httpCode = 0;
    if (preg_match('#HTTP/\S+\s+(\d+)#', $statusLine, $m)) {
        $httpCode = (int) $m[1];
    }

    if ($body === false || $httpCode !== 200) {
        fwrite(STDERR, "  Error HTTP ({$httpCode}) para provincia {$provinceLetter}\n");
        return null;
    }

    // El endpoint devuelve el JSON con BOM UTF-8 adelante.
    $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);
    $decoded = json_decode($body, true);

    if (!is_array($decoded)) {
        fwrite(STDERR, "  Respuesta inválida para provincia {$provinceLetter}\n");
        return null;
    }

    return $decoded;
}

$dataset = [];
$failures = [];

foreach ($provinces['provincias'] as $provincia) {
    $letter = $provincia['iso_id'];
    $nombre = $provincia['nombre'];

    echo "Consultando {$nombre} ({$letter})...\n";

    if ($letter === CABA_LETTER) {
        // El endpoint de "localidades" no da CP para CABA (se asigna por calle/altura).
        // Ver bin/caba-barrios-cp.php.
        $barrios = require __DIR__ . '/caba-barrios-cp.php';
        $entries = [];
        foreach ($barrios as $barrio => $codigosPostales) {
            foreach (array_unique($codigosPostales) as $cp) {
                $entries[] = [
                    'localidad' => $barrio,
                    'partido' => 'CAPITAL FEDERAL',
                    'cp' => $cp,
                ];
            }
        }
        echo "  {$nombre}: " . count($entries) . " entradas (tabla curada de barrios)\n";
        $dataset[$letter] = [
            'nombre' => $nombre,
            'localidades' => $entries,
        ];
        usleep(REQUEST_DELAY_MICROSECONDS);
        continue;
    }

    $localidades = fetchLocalidades($letter);

    if ($localidades === null) {
        $failures[] = $letter;
        usleep(REQUEST_DELAY_MICROSECONDS);
        continue;
    }

    $seen = [];
    $entries = [];
    foreach ($localidades as $item) {
        if (empty($item['nombre']) || empty($item['cp'])) {
            continue;
        }
        $partido = $item['partido'] ?? '';
        $key = $item['nombre'] . '|' . $partido . '|' . $item['cp'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $entries[] = [
            'localidad' => $item['nombre'],
            'partido' => $partido,
            'cp' => $item['cp'],
        ];
    }

    echo "  {$nombre}: " . count($entries) . " localidades\n";

    $dataset[$letter] = [
        'nombre' => $nombre,
        'localidades' => $entries,
    ];

    usleep(REQUEST_DELAY_MICROSECONDS);
}

if (!empty($failures)) {
    fwrite(STDERR, "\nFallaron " . count($failures) . " provincia(s): " . implode(', ', $failures) . "\n");
    fwrite(STDERR, "No se escribe {$outputFile} para no generar un dataset incompleto. Reintentá.\n");
    exit(1);
}

$json = json_encode($dataset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
file_put_contents($outputFile, $json . "\n");

$totalLocalidades = array_sum(array_map(fn($p) => count($p['localidades']), $dataset));
echo "\nListo: {$outputFile} (" . count($dataset) . " provincias, {$totalLocalidades} localidades)\n";
