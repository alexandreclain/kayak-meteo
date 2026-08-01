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
 * Builds the list of human-readable API error messages, one per sector that failed.
 *
 * @param array $sectors The sectors from config.
 * @param array $sectorWeatherData Result of getSectorWeatherData().
 * @return array List of error strings.
 */
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

/**
 * Computes the hourly analysis for every spot once, so downstream consumers
 * (slots, 7-day summary, day scores) don't each re-fetch/re-analyze the same data.
 *
 * @param array $spots The list of spots from config.
 * @param array $sectorWeatherData Result of getSectorWeatherData().
 * @return array [$spotId => ['spot' => ..., 'analysis' => getHourlyAnalysisForSpot() result]]
 */
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
 * Ranks a status so the "best" one can be picked among several zone analyses.
 * 'grey' (donnée manquante) est volontairement mieux classé que 'red' (danger confirmé) :
 * on ne veut pas afficher un créneau comme dangereux simplement parce qu'une donnée manque.
 *
 * @param string $status
 * @return int
 */
function statusRank(string $status): int
{
    return match ($status) {
        'green' => 3,
        'orange' => 2,
        'grey' => 1,
        default => 0, // red
    };
}

/**
 * Resolves the best status (green > orange > grey > red) among a list of zone analyses.
 *
 * @param array $possibleAnalyses List of ['status' => ..., 'reasons' => ...].
 * @return array ['status' => ..., 'reasons' => ...] the best one found.
 */
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

/**
 * Analyzes conditions for every spot and aggregates them into contiguous favorable slots.
 *
 * @param array $spotAnalyses Result of computeSpotAnalyses().
 * @return array The final list of slots, sorted chronologically.
 */
function getAggregatedSlots(array $spotAnalyses): array
{
    $aggregatedSlots = [];
    $now = new DateTime();
    $indicativeCutoff = (new DateTime())->modify('+' . RELIABLE_FORECAST_DAYS . ' days');

    // 1. Merge every spot's hourly analysis into per-hour zone buckets
    foreach ($spotAnalyses as $entry) {
        $spot = $entry['spot'];
        foreach ($entry['analysis'] as $hour => $analysis) {
            $aggregatedSlots[$hour]['weather'] = $analysis['weather']; // Store weather details for the hour

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

    // 2. Group consecutive hours with the same possibilities
    $finalSlots = [];
    $currentSlot = null;
    $sortedHours = array_keys($aggregatedSlots);
    sort($sortedHours);

    foreach ($sortedHours as $hour) {
        $slotInfo = $aggregatedSlots[$hour];
        $hasAnyZone = isset($slotInfo['MER']) || isset($slotInfo['MARAIS']) || isset($slotInfo['LAC']);

        // Heure sans aucune option nulle part (nuit, orage général...) : on referme le créneau
        // en cours sans créer de bloc vide, et sans fusionner à tort à travers ce trou.
        if (!$hasAnyZone) {
            if ($currentSlot !== null) {
                $finalSlots[] = $currentSlot;
                $currentSlot = null;
            }
            continue;
        }

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
                'indicative' => $time > $indicativeCutoff,
                'weather' => $aggregatedSlots[$hour]['weather'] ?? null,
            ];
        } else {
            $currentSlot['end'] = (clone $time)->modify('+1 hour');
        }
    }
    if ($currentSlot !== null) $finalSlots[] = $currentSlot;

    return $finalSlots;
}

/**
 * Creates a 7-day forecast summary for all spots, including current status for a map.
 *
 * @param array $spotAnalyses Result of computeSpotAnalyses().
 * @return array A summary of forecasts for all spots.
 */
function getSpotsForecastSummary(array $spotAnalyses): array
{
    $spotsForecast = [];
    // La carte affiche l'heure à venir plutôt que l'heure en cours : l'heure courante est
    // déjà entamée (jusqu'à 59 min dans le passé), l'heure suivante est plus utile en pratique.
    $upcomingHourKey = (new DateTime())->modify('+1 hour')->format('Y-m-d\TH:00');

    foreach ($spotAnalyses as $entry) {
        $spot = $entry['spot'];
        $analysis = $entry['analysis'];
        $daily_statuses = [];

        for ($d = 0; $d < 7; $d++) {
            $day = (new DateTime())->modify("+$d days");
            $bestStatusOfDay = 'red';
            $reasonsForDay = [];
            $hasAnyHourData = false;

            // 0-23h : la nuit est déjà marquée rouge par getHourlyAnalysisForSpot() via le
            // lever/coucher réel du soleil, plus fiable qu'une fenêtre fixe.
            for ($h = 0; $h < 24; $h++) {
                $hourKey = $day->format("Y-m-d\T") . sprintf('%02d:00', $h);
                if (!isset($analysis[$hourKey])) continue;
                $hasAnyHourData = true;

                $possibleAnalyses = getPossibleAnalyses($analysis[$hourKey], $spot['zone']);
                $hourResult = resolveBestStatus($possibleAnalyses);

                if ($hourResult['status'] === 'green') {
                    $bestStatusOfDay = 'green';
                    $reasonsForDay = [];
                    break; // Found a green slot, day is green, no need to check further for this day
                } elseif (
                    statusRank($hourResult['status']) > statusRank($bestStatusOfDay)
                    || ($hourResult['status'] === $bestStatusOfDay && empty($reasonsForDay))
                ) {
                    // Le 2e cas capture la raison la première fois qu'on reste au même rang
                    // (ex: rouge sur rouge) : sans lui, un jour resté rouge du début à la fin
                    // affichait une icône rouge sans aucune raison associée.
                    $bestStatusOfDay = $hourResult['status'];
                    $reasonsForDay = $hourResult['reasons'];
                }
            }

            if (!$hasAnyHourData) {
                $bestStatusOfDay = 'grey';
                $reasonsForDay = ['Données indisponibles pour ce jour'];
            }

            $daily_statuses[] = ['status' => $bestStatusOfDay, 'reasons' => $reasonsForDay];
        }

        // Get the upcoming-hour status for the map
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

/**
 * Merges every spot's hourly analysis into a single "best status anywhere" per hour,
 * shared by getDayScores() and getIdealDepartureHours() so both work off the same data.
 *
 * @param array $spotAnalyses Result of computeSpotAnalyses().
 * @return array [$hourKey => ['status' => ..., 'reasons' => ...]]
 */
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

/**
 * Computes a 0-10 navigability score per day, based on the best status available across
 * ALL spots combined for each hour (i.e. "is there at least one good place to go").
 * Green hours count fully, orange hours partially, red hours count as zero; hours with
 * missing data are simply excluded from the average rather than penalizing the score.
 *
 * @param array $spotAnalyses Result of computeSpotAnalyses().
 * @return array [$dayKey ('Y-m-d') => ['score' => int|null, 'counted_hours' => int]]
 */
function getDayScores(array $spotAnalyses): array
{
    $hourlyStatus = buildHourlyGlobalStatus($spotAnalyses);
    $scoreWeights = ['green' => 1.0, 'orange' => 0.6, 'red' => 0.0];

    $dayScores = [];
    for ($d = 0; $d < 7; $d++) {
        $day = (new DateTime())->modify("+$d days");
        $dayKey = $day->format('Y-m-d');
        $creditSum = 0.0;
        $countedHours = 0;

        for ($h = 0; $h < 24; $h++) {
            $hourKey = $day->format('Y-m-d\T') . sprintf('%02d:00', $h);
            if (!isset($hourlyStatus[$hourKey])) continue;

            $best = $hourlyStatus[$hourKey];
            if ($best['status'] === 'grey') continue;

            $countedHours++;
            $creditSum += $scoreWeights[$best['status']];
        }

        $dayScores[$dayKey] = [
            'score' => $countedHours > 0 ? (int) round(($creditSum / $countedHours) * 10) : null,
            'counted_hours' => $countedHours,
        ];
    }

    return $dayScores;
}

/**
 * For each of the next 7 days, finds the best hour to head out (first green hour of the
 * day, or the best available status if there's no green), and returns that hour along
 * with a weather snapshot taken at that exact hour — so the 7-day summary shows the
 * conditions that actually matter (the ideal window) instead of an arbitrary fixed time.
 *
 * @param array $spotAnalyses Result of computeSpotAnalyses().
 * @return array [$dayKey ('Y-m-d') => ['hour' => 'H:i'|null, 'status' => string, 'weather' => array|null]]
 */
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
                break; // premier créneau vert de la journée = heure de départ idéale
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

/**
 * Groups spots that share the exact same 7-day forecast (status + reasons for every day)
 * into a single row, so the summary table doesn't repeat identical lines for spots that
 * behave the same way all week.
 *
 * @param array $spotsForecast Result of getSpotsForecastSummary().
 * @return array List of ['spots' => [spot, ...], 'daily_status' => [...]].
 */
function groupIdenticalForecasts(array $spotsForecast): array
{
    $groups = [];
    foreach ($spotsForecast as $forecast) {
        $signature = json_encode($forecast['daily_status']);
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

/**
 * Récupère un aperçu météo "maintenant" (heure en cours, pas l'heure à venir utilisée pour
 * la carte de sécurité) : température, vent, UV, marée... pour le bloc de présentation.
 *
 * @param array $spotAnalyses Result of computeSpotAnalyses().
 * @return array|null
 */
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
