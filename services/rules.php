<?php
// services/rules.php

/**
 * Vérifie si la direction du vent est considérée comme "vent de terre" (offshore).
 *
 * @param int $windDirection Direction du vent en degrés (0-360).
 * @return bool True si le vent est offshore, false sinon.
 */
function isOffshore(int $windDirection): bool
{
    list($start, $end) = OFFSHORE_WIND_ANGLES;
    return ($windDirection >= $start && $windDirection <= $end);
}

/**
 * Evalue le statut de la marée par rapport à la pleine mer.
 *
 * @param DateTime $currentTime L'heure à vérifier.
 * @param array|null $highTideTimes Tableau des timestamps des pleines mers.
 * @return array Statut ('green', 'orange', 'red', 'grey') et raison.
 */
function getTideStatus(DateTime $currentTime, ?array $highTideTimes): array
{
    if (empty($highTideTimes)) {
        return ['status' => 'grey', 'reason' => 'Données marée indisponibles'];
    }

    foreach ($highTideTimes as $tideTimestamp) {
        if ($tideTimestamp === null) continue;

        $highTideTime = new DateTime($tideTimestamp);
        $diff = abs($currentTime->getTimestamp() - $highTideTime->getTimestamp());

        if ($diff <= TIDE_WINDOW_GREEN_SECONDS) {
            return ['status' => 'green', 'reason' => ''];
        }
        if ($diff <= TIDE_WINDOW_ORANGE_SECONDS) {
            return ['status' => 'orange', 'reason' => 'Fenêtre marée étendue'];
        }
    }
    return ['status' => 'red', 'reason' => 'Hors marée'];
}

/**
 * Analyse les données météo et génère les créneaux de navigation valides.
 *
 * @param array $weatherData Données brutes de l'API.
 * @param array $spot Le spot avec ses règles personnalisées.
 * @return array Tableau associatif heure par heure de la validité du spot.
 */
function getHourlyAnalysisForSpot(array $weatherData, array $spot): array
{
    $hourlyData = $weatherData['hourly'];
    $dailyData = $weatherData['daily'] ?? null; // Handle cases where tide data might be missing
    $analysis = [];

    // S'assurer que les données horaires minimales sont présentes
    if (!isset($hourlyData['time'], $hourlyData['wind_speed_10m'], $hourlyData['wind_direction_10m'], $hourlyData['wave_height'])) {
        return [];
    }

    for ($i = 0; $i < count($hourlyData['time']); $i++) {
        $time = new DateTime($hourlyData['time'][$i]);
        $windSpeed = $hourlyData['wind_speed_10m'][$i];
        $windDirection = $hourlyData['wind_direction_10m'][$i];
        $swellHeight = $hourlyData['wave_height'][$i];

        $hourKey = $time->format('Y-m-d\TH:i');
        $analysis[$hourKey] = [
            'sea' => ['status' => 'red', 'reasons' => ['Zone non applicable']],
            'marsh' => ['status' => 'red', 'reasons' => ['Zone non applicable']],
            'lake' => ['status' => 'red', 'reasons' => ['Zone non applicable']],
            'weather' => [
                'wind_speed' => $windSpeed,
                'wind_direction' => $windDirection,
                'swell_height' => $swellHeight,
            ],
        ];

        $windMissing = ($windSpeed === null || $windDirection === null);

        // --- Check for SEA conditions ---
        if (in_array($spot['zone'], ['MER', 'MIXTE'])) {
            if ($windMissing || $swellHeight === null) {
                $analysis[$hourKey]['sea'] = ['status' => 'grey', 'reasons' => ['Données houle/vent indisponibles']];
            } else {
                $reasons = [];
                // Conditions ROUGES impératives
                if ($windSpeed > SEA_WIND_ORANGE) {
                    $reasons[] = 'Vent fort';
                } elseif ($swellHeight > SEA_SWELL_ORANGE) {
                    $reasons[] = 'Houle forte';
                } elseif (isOffshore($windDirection) && $windSpeed > OFFSHORE_WIND_DANGER_THRESHOLD) {
                    $reasons[] = 'Vent de terre';
                }

                if (!empty($reasons)) {
                    $analysis[$hourKey]['sea'] = ['status' => 'red', 'reasons' => $reasons];
                } else {
                    // Conditions VERTES
                    if ($windSpeed <= SEA_WIND_GREEN && $swellHeight <= SEA_SWELL_GREEN) {
                        $analysis[$hourKey]['sea'] = ['status' => 'green', 'reasons' => []];
                    } else {
                        // Si ce n'est ni ROUGE ni VERT, c'est ORANGE
                        if ($windSpeed > SEA_WIND_GREEN) $reasons[] = 'Vent modéré';
                        if ($swellHeight > SEA_SWELL_GREEN) $reasons[] = 'Houle modérée';
                        $analysis[$hourKey]['sea'] = ['status' => 'orange', 'reasons' => $reasons];
                    }
                }
            }
        }

        // --- Check for MARSH conditions ---
        if (in_array($spot['zone'], ['MARAIS', 'MIXTE'])) {
            $tide = getTideStatus($time, $dailyData['tide_time_high'] ?? null);

            if ($windMissing) {
                $analysis[$hourKey]['marsh'] = ['status' => 'grey', 'reasons' => ['Données vent indisponibles']];
            } elseif ($tide['status'] === 'grey') {
                $analysis[$hourKey]['marsh'] = ['status' => 'grey', 'reasons' => [$tide['reason']]];
            // Conditions ROUGES
            } elseif ($windSpeed > MARSH_WIND_ORANGE) {
                $analysis[$hourKey]['marsh'] = ['status' => 'red', 'reasons' => ['Vent fort']];
            } elseif ($tide['status'] === 'red') {
                $analysis[$hourKey]['marsh'] = ['status' => 'red', 'reasons' => [$tide['reason']]];
            // Conditions VERTES
            } elseif ($windSpeed <= MARSH_WIND_GREEN && $tide['status'] === 'green') {
                $analysis[$hourKey]['marsh'] = ['status' => 'green', 'reasons' => []];
            // Si ce n'est ni ROUGE ni VERT, c'est ORANGE
            } else {
                $reasons = [];
                if ($windSpeed > MARSH_WIND_GREEN) $reasons[] = 'Vent modéré';
                if ($tide['status'] === 'orange') $reasons[] = $tide['reason'];
                $analysis[$hourKey]['marsh'] = ['status' => 'orange', 'reasons' => $reasons];
            }
        }

        // --- Check for LAKE conditions ---
        if ($spot['zone'] === 'LAC') {
            if ($windMissing) {
                $analysis[$hourKey]['lake'] = ['status' => 'grey', 'reasons' => ['Données vent indisponibles']];
            } elseif ($windSpeed > LAKE_WIND_ORANGE) {
                $analysis[$hourKey]['lake'] = ['status' => 'red', 'reasons' => ['Vent fort']];
            } elseif ($windSpeed <= LAKE_WIND_GREEN) {
                $analysis[$hourKey]['lake'] = ['status' => 'green', 'reasons' => []];
            } else {
                $analysis[$hourKey]['lake'] = ['status' => 'orange', 'reasons' => ['Vent modéré']];
            }
        }
    }

    return $analysis;
}
