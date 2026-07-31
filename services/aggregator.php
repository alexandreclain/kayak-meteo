<?php
// services/aggregator.php

/**
 * Fetches weather data for every sector, keyed by sector id.
 *
 * @param array $sectors The sectors from config.
 * @return array Weather data (or error payload) per sector id.
 */
function getSectorWeatherData(array $sectors): array
{
    $sectorWeatherData = [];
    foreach ($sectors as $sectorId => $sector) {
        $sectorWeatherData[$sectorId] = getWeatherData($sector['lat'], $sector['lng']);
    }
    return $sectorWeatherData;
}

/**
 * Builds the list of relevant zone analyses (sea/marsh/lake) for a spot, based on its zone.
 *
 * @param array $hourAnalysis Analysis for one hour, as returned by getHourlyAnalysisForSpot().
 * @param string $zone The spot's zone (MER, MARAIS, LAC, MIXTE).
 * @return array List of ['status' => ..., 'reasons' => ...] applicable to this zone.
 */
function getPossibleAnalyses(array $hourAnalysis, string $zone): array
{
    $possibleAnalyses = [];
    if (in_array($zone, ['MER', 'MIXTE'])) {
        $possibleAnalyses[] = $hourAnalysis['sea'];
    }
    if (in_array($zone, ['MARAIS', 'MIXTE'])) {
        $possibleAnalyses[] = $hourAnalysis['marsh'];
    }
    if ($zone === 'LAC') {
        $possibleAnalyses[] = $hourAnalysis['lake'];
    }
    return $possibleAnalyses;
}

/**
 * Resolves the best status (green > orange > red) among a list of zone analyses.
 *
 * @param array $possibleAnalyses List of ['status' => ..., 'reasons' => ...].
 * @return array ['status' => ..., 'reasons' => ...] the best one found.
 */
function resolveBestStatus(array $possibleAnalyses): array
{
    $bestStatus = 'red';
    $reasons = [];

    foreach ($possibleAnalyses as $analysis) {
        if ($analysis['status'] === 'green') {
            return ['status' => 'green', 'reasons' => []];
        }
        if ($analysis['status'] === 'orange') {
            $bestStatus = 'orange';
            $reasons = $analysis['reasons'];
        }
    }

    if ($bestStatus === 'red' && !empty($possibleAnalyses)) {
        $reasons = $possibleAnalyses[0]['reasons'];
    }

    return ['status' => $bestStatus, 'reasons' => $reasons];
}

/**
 * Fetches weather for all spots, analyzes conditions, and aggregates valid slots.
 *
 * @param array $spots The list of spots from config.
 * @return array An array containing final slots and any API errors.
 */
function getAggregatedSlots(array $spots, array $sectors): array
{
    $aggregatedSlots = [];
    $apiErrors = [];
    $now = new DateTime();
    $threeDaysLater = (new DateTime())->modify('+3 days');

    $sectorWeatherData = getSectorWeatherData($sectors);
    foreach ($sectors as $sectorId => $sector) {
        $weatherData = $sectorWeatherData[$sectorId];
        if (isset($weatherData['error']) && $weatherData['error'] === true) {
            $apiErrors[] = "Erreur API pour le secteur " . htmlspecialchars($sector['name']) . ": " . htmlspecialchars($weatherData['reason']);
        }
    }

    // 1. Analyze each spot using the data from its sector
    foreach ($spots as $spot) {
        $weatherData = $sectorWeatherData[$spot['sector']] ?? null;
        if ($weatherData === null || isset($weatherData['error'])) {
            continue; // Skip spot if its sector data failed
        }

        if (empty($weatherData['hourly']['time'])) continue;

        $hourlyAnalysis = getHourlyAnalysisForSpot($weatherData, $spot);

        foreach ($hourlyAnalysis as $hour => $analysis) {
            $aggregatedSlots[$hour]['weather'] = $analysis['weather']; // Store weather details for the hour

            if (in_array($analysis['sea']['status'], ['green', 'orange']) && in_array($spot['zone'], ['MER', 'MIXTE'])) {
                $aggregatedSlots[$hour]['MER'][] = ['name' => $spot['name'], 'status' => $analysis['sea']['status']];
            }
            if (in_array($analysis['marsh']['status'], ['green', 'orange']) && in_array($spot['zone'], ['MARAIS', 'MIXTE'])) {
                $aggregatedSlots[$hour]['MARAIS'][] = ['name' => $spot['name'], 'status' => $analysis['marsh']['status']];
            }
            if (in_array($analysis['lake']['status'], ['green', 'orange']) && $spot['zone'] === 'LAC') {
                $aggregatedSlots[$hour]['LAC'][] = ['name' => $spot['name'], 'status' => $analysis['lake']['status']];
            }
        }
    }

    // 2. Group consecutive hours with the same possibilities
    $finalSlots = [];
    $currentSlot = null;
    $sortedHours = array_keys($aggregatedSlots);
    sort($sortedHours);

    foreach ($sortedHours as $hour) {
        $slotInfo = $aggregatedSlots[$hour];

        // La signature pour fusionner les créneaux ne doit pas inclure la météo détaillée de l'heure
        $signatureInfo = $slotInfo;
        unset($signatureInfo['weather']);

        if (isset($signatureInfo['MER'])) {
            usort($signatureInfo['MER'], fn($a, $b) => strcmp($a['name'], $b['name']));
        }
        if (isset($signatureInfo['MARAIS'])) {
            usort($signatureInfo['MARAIS'], fn($a, $b) => strcmp($a['name'], $b['name']));
        }
        if (isset($signatureInfo['LAC'])) {
            usort($signatureInfo['LAC'], fn($a, $b) => strcmp($a['name'], $b['name']));
        }

        $slotSignature = json_encode($signatureInfo);
        $time = new DateTime($hour);

        if ($time < $now) continue;

        if ($currentSlot === null || $currentSlot['signature'] !== $slotSignature) {
            if ($currentSlot !== null) $finalSlots[] = $currentSlot;
            $currentSlot = [
                'start' => $time,
                'end' => (clone $time)->modify('+1 hour'),
                'details' => $slotInfo,
                'signature' => $slotSignature,
                'indicative' => $time > $threeDaysLater,
                'weather' => $aggregatedSlots[$hour]['weather'] ?? null,
            ];
        } else {
            $currentSlot['end'] = (clone $time)->modify('+1 hour');
        }
    }
    if ($currentSlot !== null) $finalSlots[] = $currentSlot;

    return [
        'slots' => $finalSlots,
        'errors' => $apiErrors,
    ];
}

/**
 * Creates a 7-day forecast summary for all spots, including current status for a map.
 *
 * @param array $spots The list of spots from config.
 * @return array A summary of forecasts for all spots.
 */
function getSpotsForecastSummary(array $spots, array $sectors): array
{
    $spotsForecast = [];
    $now = new DateTime();
    $currentHourKey = $now->format('Y-m-d\TH:00');

    $sectorWeatherData = getSectorWeatherData($sectors);

    foreach ($spots as $spot) {
        $weatherData = $sectorWeatherData[$spot['sector']] ?? null;
        if ($weatherData === null || isset($weatherData['error'])) continue;

        $analysis = getHourlyAnalysisForSpot($weatherData, $spot);
        $daily_statuses = [];

        for ($d = 0; $d < 7; $d++) {
            $day = (new DateTime())->modify("+$d days");
            $bestStatusOfDay = 'red';
            $reasonsForDay = [];

            for ($h = 8; $h <= 20; $h++) { // Check daylight hours
                $hourKey = $day->format("Y-m-d\T") . sprintf('%02d:00', $h);
                if (!isset($analysis[$hourKey])) continue;

                $possibleAnalyses = getPossibleAnalyses($analysis[$hourKey], $spot['zone']);
                $hourResult = resolveBestStatus($possibleAnalyses);

                if ($hourResult['status'] === 'green') {
                    $bestStatusOfDay = 'green';
                    $reasonsForDay = [];
                    break; // Found a green slot, day is green, no need to check further for this day
                } elseif ($hourResult['status'] === 'orange' && $bestStatusOfDay !== 'green') {
                    $bestStatusOfDay = 'orange';
                    $reasonsForDay = $hourResult['reasons'];
                }
            }
            $daily_statuses[] = ['status' => $bestStatusOfDay, 'reasons' => $reasonsForDay];
        }

        // Get current status for the map
        $currentStatus = 'red';
        $currentReasons = [];
        if (isset($analysis[$currentHourKey])) {
            $possibleAnalyses = getPossibleAnalyses($analysis[$currentHourKey], $spot['zone']);
            $currentResult = resolveBestStatus($possibleAnalyses);
            $currentStatus = $currentResult['status'];
            $currentReasons = $currentResult['reasons'];
        }

        $spotsForecast[$spot['id']] = ['spot' => $spot, 'daily_status' => $daily_statuses, 'current_status' => ['status' => $currentStatus, 'reasons' => $currentReasons]];
    }
    return $spotsForecast;
}
