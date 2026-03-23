<?php

function get_google_maps_api_key() {
    $apiKey = getenv('GOOGLE_MAPS_API_KEY');

    if (is_string($apiKey) && trim($apiKey) !== '') {
        return trim($apiKey);
    }

    $localConfigFile = __DIR__ . '/maps.local.php';

    if (is_file($localConfigFile)) {
        $localConfig = require $localConfigFile;

        if (is_array($localConfig) && !empty($localConfig['google_maps_api_key'])) {
            return trim((string) $localConfig['google_maps_api_key']);
        }
    }

    return '';
}
