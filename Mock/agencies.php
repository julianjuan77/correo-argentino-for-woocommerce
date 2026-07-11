<?php

if (!defined('ABSPATH')) {
    exit;
}

class Corrarfo_Agencies
{
    public function __construct()
    {
    }

    public static function getAll()
    {
        $agencies = json_decode(file_get_contents(__DIR__ . '/agencies.json'), true);
        $results = [];

        foreach ($agencies as $value) {
            $location = $value['location'];
            $text = implode(', ', [
                $location['state_name'],
                $location['city_name'],
                $location['neighborhood_name'],
                $location['street_name'],
                $location['street_number'],
                '(' . $location['zip_code'] . ')',
            ]);

            if ($value['status'] === 'active') {
                $results[] = [
                    'id' => $value['agency_id'],
                    'text' => $text,
                    'agency_name' => $value['agency_name'],
                ];
            }
        }

        wp_send_json($results);
    }
}

// La clase se llama Corrarfo_Agencies. El alias 'Agencies' se eliminará en el reemplazo global.


if (defined('DOING_AJAX') && DOING_AJAX) {
    Corrarfo_Agencies::getAll();
}