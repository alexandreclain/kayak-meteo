<?php

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

    $formattedData = [
        'hourly' => [
            'time' => [],
            'wave_height' => [],
        ]
    ];

    foreach ($data['hours'] as $hourData) {
        $waveHeight = $hourData['waveHeight']['sg'] ?? null;

        $formattedData['hourly']['time'][] = (new DateTime($hourData['time']))->format('Y-m-d\TH:i');
        $formattedData['hourly']['wave_height'][] = $waveHeight;
    }

    return $formattedData;
}

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

function getWeatherData(float $lat, float $lng): array
{
    $cacheFile = sprintf(__DIR__ . '/../cache/weather_data_%s.json', md5($lat . '_' . $lng));

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < CACHE_DURATION) {
        $jsonData = file_get_contents($cacheFile);
        $cachedData = json_decode($jsonData, true);
        if ($cachedData !== null && !isset($cachedData['error'])) {
            return $cachedData;
        }
    }

    $forecastApiUrl = sprintf(
        "https://api.open-meteo.com/v1/forecast?latitude=%s&longitude=%s&hourly=wind_speed_10m,wind_direction_10m,wind_gusts_10m,temperature_2m,weathercode,uv_index,sea_surface_temperature&daily=sunrise,sunset&timezone=auto&wind_speed_unit=kmh&forecast_days=7",
        $lat,
        $lng
    );

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
    $weatherData['hourly']['sea_level_height_msl'] = array_fill(0, count($weatherData['hourly']['time']), null);

    $marineData = _fetchMarineData($lat, $lng);
    if (!empty($marineData['hourly']['time'])) {
        $weatherData = _mergeByTime($weatherData, $marineData, 'wave_height');
        $weatherData = _mergeByTime($weatherData, $marineData, 'sea_level_height_msl');
        $weatherData['daily']['tide_time_high'] = _extractHighTideTimes(
            $marineData['hourly']['time'],
            $marineData['hourly']['sea_level_height_msl'] ?? []
        );
    }

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

    if (!is_dir(dirname($cacheFile))) {
        mkdir(dirname($cacheFile), 0755, true);
    }
    file_put_contents($cacheFile, json_encode($weatherData));

    return $weatherData;
}
