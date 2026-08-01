<?php

function getSectorWeatherData(array $sectors): array
{
    $sectorWeatherData = [];
    foreach ($sectors as $sectorId => $sector) {
        $sectorWeatherData[$sectorId] = getWeatherData($sector['lat'], $sector['lng']);
    }
    return $sectorWeatherData;
}

function getSectorApiErrors(array $sectors, array $sectorWeatherData): array
{
    $apiErrors = [];
    foreach ($sectors as $sectorId => $sector) {
        $weatherData = $sectorWeatherData[$sectorId] ?? null;
        if ($weatherData !== null && isset($weatherData['error']) && $weatherData['error'] === true) {
            $apiErrors[] = "Erreur API pour le secteur " . htmlspecialchars($sector['name']) . ": " . htmlspecialchars($weatherData['reason']);
        }
    }
    return $apiErrors;
}

function computeSpotAnalyses(array $spots, array $sectorWeatherData): array
{
    $spotAnalyses = [];
    foreach ($spots as $spot) {
        $weatherData = $sectorWeatherData[$spot['sector']] ?? null;
        if ($weatherData === null || isset($weatherData['error']) || empty($weatherData['hourly']['time'])) {
            continue;
        }
        $spotAnalyses[$spot['id']] = [
            'spot' => $spot,
            'analysis' => getHourlyAnalysisForSpot($weatherData, $spot),
        ];
    }
    return $spotAnalyses;
}

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

function statusRank(string $status): int
{
    return match ($status) {
        'green' => 3,
        'orange' => 2,
        'grey' => 1,
        default => 0,
    };
}

function resolveBestStatus(array $possibleAnalyses): array
{
    $best = ['status' => 'red', 'reasons' => []];

    foreach ($possibleAnalyses as $analysis) {
        if ($analysis['status'] === 'green') {
            return ['status' => 'green', 'reasons' => []];
        }
        if (statusRank($analysis['status']) > statusRank($best['status'])) {
            $best = $analysis;
        }
    }

    if ($best['status'] === 'red' && !empty($possibleAnalyses)) {
        $best['reasons'] = $possibleAnalyses[0]['reasons'];
    }

    return $best;
}

function getAggregatedSlots(array $spotAnalyses): array
{
    $aggregatedSlots = [];
    $now = new DateTime();

    foreach ($spotAnalyses as $entry) {
        $spot = $entry['spot'];
        foreach ($entry['analysis'] as $hour => $analysis) {
            $aggregatedSlots[$hour]['weather'] = $analysis['weather'];

            if (in_array($analysis['sea']['status'], ['green', 'orange', 'grey']) && in_array($spot['zone'], ['MER', 'MIXTE'])) {
                $aggregatedSlots[$hour]['MER'][] = ['name' => $spot['name'], 'status' => $analysis['sea']['status']];
            }
            if (in_array($analysis['marsh']['status'], ['green', 'orange', 'grey']) && in_array($spot['zone'], ['MARAIS', 'MIXTE'])) {
                $aggregatedSlots[$hour]['MARAIS'][] = ['name' => $spot['name'], 'status' => $analysis['marsh']['status']];
            }
            if (in_array($analysis['lake']['status'], ['green', 'orange', 'grey']) && $spot['zone'] === 'LAC') {
                $aggregatedSlots[$hour]['LAC'][] = ['name' => $spot['name'], 'status' => $analysis['lake']['status']];
            }
        }
    }

    $finalSlots = [];
    $currentSlot = null;
    $sortedHours = array_keys($aggregatedSlots);
    sort($sortedHours);

    foreach ($sortedHours as $hour) {
        $slotInfo = $aggregatedSlots[$hour];
        $hasAnyZone = isset($slotInfo['MER']) || isset($slotInfo['MARAIS']) || isset($slotInfo['LAC']);

        if (!$hasAnyZone) {
            if ($currentSlot !== null) {
                $finalSlots[] = $currentSlot;
                $currentSlot = null;
            }
            continue;
        }

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
                'weather' => $aggregatedSlots[$hour]['weather'] ?? null,
            ];
        } else {
            $currentSlot['end'] = (clone $time)->modify('+1 hour');
        }
    }
    if ($currentSlot !== null) $finalSlots[] = $currentSlot;

    return $finalSlots;
}

function getSpotsForecastSummary(array $spotAnalyses, array $idealDepartureHours): array
{
    $spotsForecast = [];
    $upcomingHourKey = (new DateTime())->modify('+1 hour')->format('Y-m-d\TH:00');

    foreach ($spotAnalyses as $entry) {
        $spot = $entry['spot'];
        $analysis = $entry['analysis'];
        $daily_statuses = [];

        for ($d = 0; $d < 7; $d++) {
            $day = (new DateTime())->modify("+$d days");
            $dayKey = $day->format('Y-m-d');
            $idealHour = $idealDepartureHours[$dayKey]['hour'] ?? null;
            $hourKey = $idealHour !== null ? ($day->format('Y-m-d\T') . $idealHour) : null;

            if ($hourKey !== null && isset($analysis[$hourKey])) {
                $possibleAnalyses = getPossibleAnalyses($analysis[$hourKey], $spot['zone']);
                $result = resolveBestStatus($possibleAnalyses);
                $daily_statuses[] = [
                    'status' => $result['status'],
                    'reasons' => $result['reasons'],
                    'weather' => $analysis[$hourKey]['weather'] ?? null,
                ];
            } else {
                $daily_statuses[] = ['status' => 'grey', 'reasons' => ['Données indisponibles pour ce jour'], 'weather' => null];
            }
        }

        $currentStatus = 'red';
        $currentReasons = [];
        if (isset($analysis[$upcomingHourKey])) {
            $possibleAnalyses = getPossibleAnalyses($analysis[$upcomingHourKey], $spot['zone']);
            $currentResult = resolveBestStatus($possibleAnalyses);
            $currentStatus = $currentResult['status'];
            $currentReasons = $currentResult['reasons'];
        }

        $spotsForecast[$spot['id']] = ['spot' => $spot, 'daily_status' => $daily_statuses, 'current_status' => ['status' => $currentStatus, 'reasons' => $currentReasons]];
    }
    return $spotsForecast;
}

function buildHourlyGlobalStatus(array $spotAnalyses): array
{
    $hourlyPossible = [];
    foreach ($spotAnalyses as $entry) {
        $spot = $entry['spot'];
        foreach ($entry['analysis'] as $hourKey => $hourAnalysis) {
            $hourlyPossible[$hourKey] = array_merge(
                $hourlyPossible[$hourKey] ?? [],
                getPossibleAnalyses($hourAnalysis, $spot['zone'])
            );
        }
    }

    $hourlyStatus = [];
    foreach ($hourlyPossible as $hourKey => $possibleAnalyses) {
        $hourlyStatus[$hourKey] = resolveBestStatus($possibleAnalyses);
    }
    return $hourlyStatus;
}

function getDayScores(array $groupedForecasts): array
{
    $scoreWeights = ['green' => 1.0, 'orange' => 0.6, 'red' => 0.0];

    $dayScores = [];
    for ($d = 0; $d < 7; $d++) {
        $dayKey = (new DateTime())->modify("+$d days")->format('Y-m-d');

        $credits = [];
        foreach ($groupedForecasts as $group) {
            $status = $group['daily_status'][$d]['status'] ?? null;
            if ($status === null || $status === 'grey') continue;
            $credits[] = $scoreWeights[$status];
        }

        $dayScores[$dayKey] = [
            'score' => !empty($credits) ? (int) round((array_sum($credits) / count($credits)) * 5) : null,
            'counted_hours' => count($credits),
        ];
    }

    return $dayScores;
}

function getIdealDepartureHours(array $spotAnalyses): array
{
    $hourlyStatus = buildHourlyGlobalStatus($spotAnalyses);
    $ideal = [];

    for ($d = 0; $d < 7; $d++) {
        $day = (new DateTime())->modify("+$d days");
        $dayKey = $day->format('Y-m-d');
        $bestHourKey = null;
        $bestStatus = 'red';

        for ($h = 0; $h < 24; $h++) {
            $hourKey = $day->format('Y-m-d\T') . sprintf('%02d:00', $h);
            if (!isset($hourlyStatus[$hourKey])) continue;

            $status = $hourlyStatus[$hourKey]['status'];

            if ($status === 'green') {
                $bestHourKey = $hourKey;
                $bestStatus = 'green';
                break;
            }

            if ($bestHourKey === null || statusRank($status) > statusRank($bestStatus)) {
                $bestHourKey = $hourKey;
                $bestStatus = $status;
            }
        }

        $weather = null;
        if ($bestHourKey !== null) {
            foreach ($spotAnalyses as $entry) {
                if (isset($entry['analysis'][$bestHourKey]['weather'])) {
                    $weather = $entry['analysis'][$bestHourKey]['weather'];
                    break;
                }
            }
        }

        $ideal[$dayKey] = [
            'hour' => $bestHourKey ? (new DateTime($bestHourKey))->format('H:i') : null,
            'status' => $bestStatus,
            'weather' => $weather,
        ];
    }

    return $ideal;
}

function groupIdenticalForecasts(array $spotsForecast): array
{
    $groups = [];
    foreach ($spotsForecast as $forecast) {
        $signatureBasis = array_map(
            fn($day) => ['status' => $day['status'], 'reasons' => $day['reasons']],
            $forecast['daily_status']
        );
        $signature = json_encode($signatureBasis);

        if (!isset($groups[$signature])) {
            $groups[$signature] = [
                'spots' => [],
                'daily_status' => $forecast['daily_status'],
            ];
        }
        $groups[$signature]['spots'][] = $forecast['spot'];
    }
    return array_values($groups);
}

function getCurrentWeatherSnapshot(array $spotAnalyses): ?array
{
    $currentHourKey = (new DateTime())->format('Y-m-d\TH:00');

    foreach ($spotAnalyses as $entry) {
        if (isset($entry['analysis'][$currentHourKey]['weather'])) {
            return $entry['analysis'][$currentHourKey]['weather'];
        }
    }

    return null;
}
