<?php
namespace Corrarfo\Lib;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Regenera el dataset de localidades/CP en segundo plano (WP-Cron mensual, ADR-001) y lo
 * guarda en una `option` propia. Nunca toca Mock/postal-codes.json: ese archivo es el
 * dataset bundleado con el plugin y se regenera a mano con bin/generate-postal-codes.php
 * antes de cada release.
 */
class PostalCodesRefresher
{
    const CA_ENDPOINT = 'https://www.correoargentino.com.ar/sites/all/modules/custom/ca_forms/api/wsFacade.php';
    const CA_REFERER = 'https://www.correoargentino.com.ar/formularios/cpa';
    const CABA_LETTER = 'C';

    /**
     * Si falla cualquier provincia, no se escribe nada: se mantiene el último dataset
     * bueno (la `option` si ya existía, o el Mock/postal-codes.json bundleado si no).
     */
    public static function refresh(): void
    {
        $provincesFile = CORRARFO_PLUGIN_DIR . 'Mock/provinces.json';
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- archivo local del plugin, no una URL remota.
        $provinces = file_exists($provincesFile) ? json_decode(file_get_contents($provincesFile), true) : null;

        if (!is_array($provinces) || empty($provinces['provincias'])) {
            return;
        }

        $dataset = [];

        foreach ($provinces['provincias'] as $provincia) {
            $letter = $provincia['iso_id'];
            $nombre = $provincia['nombre'];

            if ($letter === self::CABA_LETTER) {
                $dataset[$letter] = [
                    'nombre' => $nombre,
                    'localidades' => self::cabaLocalidades(),
                ];
                continue;
            }

            $localidades = self::fetchLocalidades($letter);

            if ($localidades === null) {
                return;
            }

            $dataset[$letter] = [
                'nombre' => $nombre,
                'localidades' => $localidades,
            ];
        }

        update_option(CORRARFO_POSTAL_CODES_CACHE_OPTION, $dataset, false);
    }

    private static function cabaLocalidades(): array
    {
        $file = CORRARFO_PLUGIN_DIR . 'bin/caba-barrios-cp.php';
        $barrios = file_exists($file) ? require $file : [];

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

        return $entries;
    }

    /**
     * @return array|null null si falló la consulta a Correo Argentino.
     */
    private static function fetchLocalidades(string $provinceLetter): ?array
    {
        $request = wp_remote_post(self::CA_ENDPOINT, [
            'body' => [
                'action' => 'localidades',
                'localidad' => 'none',
                'calle' => '',
                'altura' => '',
                'provincia' => $provinceLetter,
            ],
            'headers' => [
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => self::CA_REFERER,
            ],
            'timeout' => 20,
        ]);

        if (is_wp_error($request) || wp_remote_retrieve_response_code($request) !== 200) {
            return null;
        }

        $body = preg_replace('/^\xEF\xBB\xBF/', '', wp_remote_retrieve_body($request));
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            return null;
        }

        $seen = [];
        $entries = [];
        foreach ($decoded as $item) {
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

        return $entries;
    }
}
