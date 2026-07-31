<?php
// services/weather.php

/**
 * Helper function to make cURL API calls.
 * @param string $url
 * @param array $headers
 * @return array ['response' => string, 'http_code' => int]
 */
function _callApi(string $url, array $headers = []): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'KayakWeatherApp/1.0');
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['response' => $response, 'http_code' => $httpCode];
}

/**
 * Fetches fallback data from Stormglass.io API.
 * @param float $lat
 * @param float $lng
 * @return array Formatted data compatible with Open-Meteo structure.
 */
function _fetchStormglassData(float $lat, float $lng): array
{
    if (!defined('STORMGLASS_API_KEY') || STORMGLASS_API_KEY === '') {
        return [];
    }

    $params = 'waveHeight,waveDirection,windSpeed,windDirection,swellHeight';
    $apiUrl = "https://api.stormglass.io/v2/weather/point?lat={$lat}&lng={$lng}&params={$params}";
    
    $apiResult = _callApi($apiUrl, ['Authorization: ' . STORMGLASS_API_KEY]);

    if ($apiResult['http_code'] !== 200) {
        return [];
    }

    $data = json_decode($apiResult['response'], true);
    if (!isset($data['hours'])) {
        return [];
    }

    // Reformat Stormglass data to match Open-Meteo structure for easy merging
    $formattedData = [
        'hourly' => [
            'time' => [],
            'wave_height' => [],
            'wind_speed_10m' => [],
            'wind_direction_10m' => [],
        ]
    ];

    foreach ($data['hours'] as $hourData) {
        // Stormglass gives values in a nested array, we take the first available source (e.g., 'sg')
        $waveHeight = $hourData['waveHeight']['sg'] ?? null;
        $windSpeed = $hourData['windSpeed']['sg'] ?? null; // in m/s
        $windDirection = $hourData['windDirection']['sg'] ?? null;

        $formattedData['hourly']['time'][] = (new DateTime($hourData['time']))->format('Y-m-d\TH:i');
        $formattedData['hourly']['wave_height'][] = $waveHeight;
        // Convert wind speed from m/s to km/h
        $formattedData['hourly']['wind_speed_10m'][] = $windSpeed !== null ? round($windSpeed * 3.6) : null;
        $formattedData['hourly']['wind_direction_10m'][] = $windDirection;
    }

    return $formattedData;
}

/**
 * Merges fallback data into the primary data source, filling null values.
 * @param array $primaryData
 * @param array $fallbackData
 * @return array The merged data.
 */
function _mergeData(array $primaryData, array $fallbackData): array
{
    if (empty($fallbackData) || !isset($fallbackData['hourly']['time'])) {
        return $primaryData;
    }

    $fallbackMap = array_flip($fallbackData['hourly']['time']);

    foreach ($primaryData['hourly']['time'] as $index => $time) {
        if (isset($fallbackMap[$time])) {
            $fallbackIndex = $fallbackMap[$time];
            // If primary data for swell is null, use fallback
            if ($primaryData['hourly']['wave_height'][$index] === null && $fallbackData['hourly']['wave_height'][$fallbackIndex] !== null) {
                $primaryData['hourly']['wave_height'][$index] = $fallbackData['hourly']['wave_height'][$fallbackIndex];
            }
            // You could add more fallbacks here (e.g., for wind) if needed
        }
    }
    return $primaryData;
}

/**
 * Récupère les données météo depuis l'API Open-Meteo ou le cache local.
 *
 * @param float $lat Latitude du spot.
 * @param float $lng Longitude du spot.
 * @return array Les données météo décodées ou un tableau d'erreur.
 */
function getWeatherData(float $lat, float $lng): array
{
    $cacheFile = sprintf(__DIR__ . '/../cache/weather_data_%s.json', md5($lat . '_' . $lng));

    // Vérification du cache
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < CACHE_DURATION) {
        $jsonData = file_get_contents($cacheFile);
        $cachedData = json_decode($jsonData, true);
        // Si le cache est valide et n'est pas un ancien message d'erreur
        if ($cachedData !== null && !isset($cachedData['error'])) {
            return $cachedData;
        }
    }

    // Construction de l'URL de l'API Open-Meteo Marine
    $forecastApiUrl = sprintf(
        "https://api.open-meteo.com/v1/forecast?latitude=%s&longitude=%s&hourly=wind_speed_10m,wind_direction_10m,wave_height,sea_surface_temperature&timezone=auto&wind_speed_unit=kmh&forecast_days=7",
        $lat,
        $lng
    );
    // NOUVEAU: URL dédiée pour les marées
    $tideApiUrl = sprintf(
        "https://api.open-meteo.com/v1/tide?latitude=%s&longitude=%s&daily=tide_time_high,tide_time_low&timezone=auto&forecast_days=7",
        $lat,
        $lng
    );

    // --- 1. Appel à l'API Météo ---
    $forecastApiResult = _callApi($forecastApiUrl);

    // Gestion des erreurs de l'API Météo (critique)
    if ($forecastApiResult['http_code'] !== 200 || $forecastApiResult['response'] === false) {
        if (file_exists($cacheFile)) {
            $jsonData = file_get_contents($cacheFile);
            $cachedData = json_decode($jsonData, true);
            if ($cachedData !== null) return $cachedData;
        }
        if ($forecastApiResult['response']) {
            $errorDetails = json_decode($forecastApiResult['response'], true);
            if (isset($errorDetails['reason'])) {
                return ['error' => true, 'reason' => $errorDetails['reason']];
            }
        }
        return ['error' => true, 'reason' => 'La connexion à l\'API météo a échoué (HTTP code: ' . $forecastApiResult['http_code'] . ').'];
    }

    $weatherData = json_decode($forecastApiResult['response'], true);
    if ($weatherData === null || isset($weatherData['error'])) {
        return ['error' => true, 'reason' => $weatherData['reason'] ?? 'Impossible de décoder la réponse météo JSON.'];
    }

    // --- 2. Appel à l'API Marées ---
    $tideApiResult = _callApi($tideApiUrl);

    // --- 3. Fusion des données ---
    if ($tideApiResult['http_code'] === 200 && $tideApiResult['response']) {
        $tideData = json_decode($tideApiResult['response'], true);
        if ($tideData && !isset($tideData['error']) && isset($tideData['daily'])) {
            $weatherData['daily'] = $tideData['daily']; // Remplacer le 'daily' par celui des marées
        }
    }

    // --- 4. FALLBACK LOGIC ---
    // Check if swell data is missing (all values are null)
    $isSwellMissing = (isset($weatherData['hourly']['wave_height']) && count(array_filter($weatherData['hourly']['wave_height'])) === 0);

    if ($isSwellMissing) {
        $fallbackData = _fetchStormglassData($lat, $lng);
        if (!empty($fallbackData)) {
            $weatherData = _mergeData($weatherData, $fallbackData);
        }
    }

    // --- 5. Mise en cache et retour ---
    if (!is_dir(dirname($cacheFile))) {
        mkdir(dirname($cacheFile), 0755, true);
    }
    file_put_contents($cacheFile, json_encode($weatherData));

    return $weatherData;
}
