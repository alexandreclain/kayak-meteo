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
 * Fetches wave height and sea level (tide signal) from Open-Meteo's free Marine API.
 * This is the primary source for houle/marée data (same provider as the main forecast,
 * no API key required).
 *
 * @param float $lat
 * @param float $lng
 * @return array Raw Open-Meteo marine payload, or [] on failure.
 */
function _fetchMarineData(float $lat, float $lng): array
{
    $apiUrl = sprintf(
        "https://marine-api.open-meteo.com/v1/marine?latitude=%s&longitude=%s&hourly=wave_height,sea_level_height_msl&timezone=auto&forecast_days=7",
        $lat,
        $lng
    );

    $apiResult = _callApi($apiUrl);
    if ($apiResult['http_code'] !== 200 || $apiResult['response'] === false) {
        return [];
    }

    $data = json_decode($apiResult['response'], true);
    if (!isset($data['hourly']['time'])) {
        return [];
    }

    return $data;
}

/**
 * Derives high-tide timestamps from a continuous sea-level-height series, by finding
 * local maxima. Used because Open-Meteo does not expose tide extremes directly.
 *
 * @param array $times ISO8601 hourly timestamps.
 * @param array $seaLevels Sea level height (m) for each timestamp, same length as $times.
 * @return array List of ISO8601 timestamps at high tide.
 */
function _extractHighTideTimes(array $times, array $seaLevels): array
{
    $highTideTimes = [];
    $count = count($seaLevels);

    for ($i = 1; $i < $count - 1; $i++) {
        $previous = $seaLevels[$i - 1];
        $current = $seaLevels[$i];
        $next = $seaLevels[$i + 1];

        if ($previous === null || $current === null || $next === null) {
            continue;
        }

        if ($current > $previous && $current >= $next) {
            $highTideTimes[] = $times[$i];
        }
    }

    return $highTideTimes;
}

/**
 * Fetches fallback wave data from Stormglass.io API. Used only as a last resort if
 * the Marine API above is unavailable, since Stormglass's free tier has a very low
 * daily quota.
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
        ]
    ];

    foreach ($data['hours'] as $hourData) {
        // Stormglass gives values in a nested array, we take the first available source (e.g., 'sg')
        $waveHeight = $hourData['waveHeight']['sg'] ?? null;

        $formattedData['hourly']['time'][] = (new DateTime($hourData['time']))->format('Y-m-d\TH:i');
        $formattedData['hourly']['wave_height'][] = $waveHeight;
    }

    return $formattedData;
}

/**
 * Fills null values of $field in $primaryData['hourly'] using $secondaryData['hourly'],
 * matching entries by timestamp.
 *
 * @param array $primaryData
 * @param array $secondaryData
 * @param string $field
 * @return array The merged data.
 */
function _mergeByTime(array $primaryData, array $secondaryData, string $field): array
{
    if (empty($secondaryData['hourly']['time']) || empty($secondaryData['hourly'][$field])) {
        return $primaryData;
    }

    $secondaryMap = array_flip($secondaryData['hourly']['time']);

    foreach ($primaryData['hourly']['time'] as $index => $time) {
        if (!isset($secondaryMap[$time])) {
            continue;
        }
        $secondaryIndex = $secondaryMap[$time];
        $secondaryValue = $secondaryData['hourly'][$field][$secondaryIndex] ?? null;

        if (($primaryData['hourly'][$field][$index] ?? null) === null && $secondaryValue !== null) {
            $primaryData['hourly'][$field][$index] = $secondaryValue;
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

    // Construction de l'URL de l'API Open-Meteo (vent, température de l'eau)
    $forecastApiUrl = sprintf(
        "https://api.open-meteo.com/v1/forecast?latitude=%s&longitude=%s&hourly=wind_speed_10m,wind_direction_10m,sea_surface_temperature&timezone=auto&wind_speed_unit=kmh&forecast_days=7",
        $lat,
        $lng
    );

    // --- 1. Appel à l'API Météo (critique) ---
    $forecastApiResult = _callApi($forecastApiUrl);

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

    $weatherData['hourly']['wave_height'] = array_fill(0, count($weatherData['hourly']['time']), null);

    // --- 2. Houle + marée : API Marine d'Open-Meteo (gratuite, même fournisseur) ---
    // Le "tide_time_high" est déduit des maxima locaux du niveau de la mer, car
    // Open-Meteo n'expose pas directement les heures de pleine mer.
    $marineData = _fetchMarineData($lat, $lng);
    if (!empty($marineData['hourly']['time'])) {
        $weatherData = _mergeByTime($weatherData, $marineData, 'wave_height');
        $weatherData['daily']['tide_time_high'] = _extractHighTideTimes(
            $marineData['hourly']['time'],
            $marineData['hourly']['sea_level_height_msl'] ?? []
        );
    }

    // --- 3. Repli : si la houle est toujours manquante, Stormglass en dernier recours ---
    $isSwellMissing = true;
    foreach ($weatherData['hourly']['wave_height'] as $value) {
        if ($value !== null) {
            $isSwellMissing = false;
            break;
        }
    }

    if ($isSwellMissing) {
        $fallbackData = _fetchStormglassData($lat, $lng);
        if (!empty($fallbackData)) {
            $weatherData = _mergeByTime($weatherData, $fallbackData, 'wave_height');
        }
    }

    // --- 4. Mise en cache et retour ---
    if (!is_dir(dirname($cacheFile))) {
        mkdir(dirname($cacheFile), 0755, true);
    }
    file_put_contents($cacheFile, json_encode($weatherData));

    return $weatherData;
}
