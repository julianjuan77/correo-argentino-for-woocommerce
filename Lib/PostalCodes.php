<?php
namespace Corrarfo\Lib;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dataset local de localidades/CP de Argentina (ADR-001, docs/DECISIONS.md).
 *
 * Fuente: `option` refrescada mensualmente por WP-Cron con el resultado del endpoint de
 * Correo Argentino, o -si todavía no corrió o falló- el `Mock/postal-codes.json` bundleado
 * con el plugin. Nunca se consulta nada en vivo desde acá.
 */
class PostalCodes
{
    /** @var array|null */
    private static $dataset = null;

    public static function getDataset(): array
    {
        if (self::$dataset !== null) {
            return self::$dataset;
        }

        $cached = get_option(CORRARFO_POSTAL_CODES_CACHE_OPTION);
        if (is_array($cached) && !empty($cached)) {
            self::$dataset = $cached;
            return self::$dataset;
        }

        $file = CORRARFO_PLUGIN_DIR . 'Mock/postal-codes.json';
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- archivo local del plugin, no una URL remota.
        $decoded = file_exists($file) ? json_decode(file_get_contents($file), true) : null;
        self::$dataset = is_array($decoded) ? $decoded : [];

        return self::$dataset;
    }

    /**
     * Localidades de una provincia, agrupadas por localidad+partido con sus CPs.
     *
     * @return array<int, array{localidad: string, partido: string, cps: string[]}>
     */
    public static function getLocalidades(string $provinceLetter): array
    {
        $dataset = self::getDataset();

        if (empty($dataset[$provinceLetter]['localidades'])) {
            return [];
        }

        $grouped = [];
        foreach ($dataset[$provinceLetter]['localidades'] as $entry) {
            if (empty($entry['localidad']) || empty($entry['cp'])) {
                continue;
            }
            $partido = $entry['partido'] ?? '';
            $key = $entry['localidad'] . '|' . $partido;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'localidad' => $entry['localidad'],
                    'partido' => $partido,
                    'cps' => [],
                ];
            }
            $grouped[$key]['cps'][] = $entry['cp'];
        }

        return array_values($grouped);
    }

    /**
     * True si la combinación provincia+localidad+CP existe en el dataset. Si la provincia
     * no está cubierta por el dataset, no bloquea (devuelve true) en vez de asumir un error
     * que puede no serlo.
     */
    public static function isValid(string $provinceLetter, string $localidad, string $cp): bool
    {
        $dataset = self::getDataset();

        if (empty($dataset[$provinceLetter]['localidades'])) {
            return true;
        }

        $localidad = trim($localidad);

        foreach ($dataset[$provinceLetter]['localidades'] as $entry) {
            if (
                isset($entry['cp'], $entry['localidad'])
                && $entry['cp'] === $cp
                && strcasecmp(trim($entry['localidad']), $localidad) === 0
            ) {
                return true;
            }
        }

        return false;
    }
}
