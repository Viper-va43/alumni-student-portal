<?php

// Resolve the Google Maps API key from the environment first, then from a local override file.
function get_google_maps_api_key() {
    $apiKey = getenv('GOOGLE_MAPS_API_KEY');

    if (is_string($apiKey) && trim($apiKey) !== '') {
        return trim($apiKey);
    }

    // Fall back to a developer-only local file when the environment variable is missing.
    $localConfigFile = __DIR__ . '/maps.local.php';

    if (is_file($localConfigFile)) {
        $localConfig = require $localConfigFile;

        if (is_array($localConfig) && !empty($localConfig['google_maps_api_key'])) {
            return trim((string) $localConfig['google_maps_api_key']);
        }
    }

    // Return an empty string so the rest of the app can safely detect that maps are unavailable.
    return '';
}
