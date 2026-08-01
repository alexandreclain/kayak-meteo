<?php

function isOffshore(int $windDirection): bool
{
    list($start, $end) = OFFSHORE_WIND_ANGLES;
    return ($windDirection >= $start && $windDirection <= $end);
}

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

function findNearestTideTime(DateTime $time, ?array $highTideTimes): ?string
{
    if (empty($highTideTimes)) {
        return null;
    }

    $nearest = null;
    $smallestDiff = null;

    foreach ($highTideTimes as $tideTimestamp) {
        if ($tideTimestamp === null) continue;

        $tideTime = new DateTime($tideTimestamp);
        $diff = abs($time->getTimestamp() - $tideTime->getTimestamp());

        if ($smallestDiff === null || $diff < $smallestDiff) {
            $smallestDiff = $diff;
            $nearest = $tideTime;
        }
    }

    return $nearest?->format('H:i');
}

function buildDaylightLookup(?array $dailyData): array
{
    $sunrise = [];
    $sunset = [];

    if (isset($dailyData['time'], $dailyData['sunrise'], $dailyData['sunset'])) {
        foreach ($dailyData['time'] as $index => $dayStr) {
            $sunrise[$dayStr] = $dailyData['sunrise'][$index] ?? null;
            $sunset[$dayStr] = $dailyData['sunset'][$index] ?? null;
        }
    }

    return ['sunrise' => $sunrise, 'sunset' => $sunset];
}

function isNightTime(DateTime $time, array $daylightLookup): bool
{
    $dayStr = $time->format('Y-m-d');
    $sunrise = $daylightLookup['sunrise'][$dayStr] ?? null;
    $sunset = $daylightLookup['sunset'][$dayStr] ?? null;

    if (!$sunrise || !$sunset) {
        return false;
    }

    return ($time < new DateTime($sunrise) || $time > new DateTime($sunset));
}

function getHourlyAnalysisForSpot(array $weatherData, array $spot): array
{
    $hourlyData = $weatherData['hourly'];
    $dailyData = $weatherData['daily'] ?? null;
    $daylightLookup = buildDaylightLookup($dailyData);
    $analysis = [];

    if (!isset($hourlyData['time'], $hourlyData['wind_speed_10m'], $hourlyData['wind_direction_10m'], $hourlyData['wave_height'])) {
        return [];
    }

    for ($i = 0; $i < count($hourlyData['time']); $i++) {
        $time = new DateTime($hourlyData['time'][$i]);
        $windSpeed = $hourlyData['wind_speed_10m'][$i];
        $windDirection = $hourlyData['wind_direction_10m'][$i];
        $windGusts = $hourlyData['wind_gusts_10m'][$i] ?? null;
        $swellHeight = $hourlyData['wave_height'][$i];
        $weatherCode = $hourlyData['weathercode'][$i] ?? null;

        $seaLevel = $hourlyData['sea_level_height_msl'][$i] ?? null;
        $previousSeaLevel = $hourlyData['sea_level_height_msl'][$i - 1] ?? null;
        $tideDirection = null;
        if ($i > 0 && $seaLevel !== null && $previousSeaLevel !== null) {
            $tideDirection = match (true) {
                $seaLevel > $previousSeaLevel => 'montante',
                $seaLevel < $previousSeaLevel => 'descendante',
                default => 'étale',
            };
        }

        $hourKey = $time->format('Y-m-d\TH:i');
        $analysis[$hourKey] = [
            'sea' => ['status' => 'red', 'reasons' => ['Zone non applicable']],
            'marsh' => ['status' => 'red', 'reasons' => ['Zone non applicable']],
            'lake' => ['status' => 'red', 'reasons' => ['Zone non applicable']],
            'weather' => [
                'wind_speed' => $windSpeed,
                'wind_direction' => $windDirection,
                'wind_gusts' => $windGusts,
                'swell_height' => $swellHeight,
                'air_temperature' => $hourlyData['temperature_2m'][$i] ?? null,
                'water_temperature' => $hourlyData['sea_surface_temperature'][$i] ?? null,
                'uv_index' => $hourlyData['uv_index'][$i] ?? null,
                'weather_code' => $weatherCode,
                'tide_direction' => $tideDirection,
                'tide_next_high' => findNearestTideTime($time, $dailyData['tide_time_high'] ?? null),
            ],
        ];

        if ($weatherCode !== null && in_array($weatherCode, STORM_WEATHER_CODES)) {
            $result = ['status' => 'red', 'reasons' => ['Orage / risque de foudre']];
            if (in_array($spot['zone'], ['MER', 'MIXTE'])) $analysis[$hourKey]['sea'] = $result;
            if (in_array($spot['zone'], ['MARAIS', 'MIXTE'])) $analysis[$hourKey]['marsh'] = $result;
            if ($spot['zone'] === 'LAC') $analysis[$hourKey]['lake'] = $result;
            continue;
        }

        if (isNightTime($time, $daylightLookup)) {
            $result = ['status' => 'red', 'reasons' => ['Nuit (hors créneau de navigation)']];
            if (in_array($spot['zone'], ['MER', 'MIXTE'])) $analysis[$hourKey]['sea'] = $result;
            if (in_array($spot['zone'], ['MARAIS', 'MIXTE'])) $analysis[$hourKey]['marsh'] = $result;
            if ($spot['zone'] === 'LAC') $analysis[$hourKey]['lake'] = $result;
            continue;
        }

        $windMissing = ($windSpeed === null || $windDirection === null);

        if (in_array($spot['zone'], ['MER', 'MIXTE'])) {
            if ($windMissing || $swellHeight === null) {
                $analysis[$hourKey]['sea'] = ['status' => 'grey', 'reasons' => ['Données houle/vent indisponibles']];
            } else {
                $reasons = [];
                if ($windGusts !== null && $windGusts > SEA_WIND_ORANGE + GUST_DANGER_MARGIN) {
                    $reasons[] = 'Rafales dangereuses';
                } elseif ($windSpeed > SEA_WIND_ORANGE) {
                    $reasons[] = 'Vent fort';
                } elseif ($swellHeight > SEA_SWELL_ORANGE) {
                    $reasons[] = 'Houle forte';
                } elseif (isOffshore($windDirection) && $windSpeed > OFFSHORE_WIND_DANGER_THRESHOLD) {
                    $reasons[] = 'Vent de terre';
                }

                if (!empty($reasons)) {
                    $analysis[$hourKey]['sea'] = ['status' => 'red', 'reasons' => $reasons];
                } else {
                    if ($windSpeed <= SEA_WIND_GREEN && $swellHeight <= SEA_SWELL_GREEN) {
                        $analysis[$hourKey]['sea'] = ['status' => 'green', 'reasons' => []];
                    } else {
                        if ($windSpeed > SEA_WIND_GREEN) $reasons[] = 'Vent modéré';
                        if ($swellHeight > SEA_SWELL_GREEN) $reasons[] = 'Houle modérée';
                        $analysis[$hourKey]['sea'] = ['status' => 'orange', 'reasons' => $reasons];
                    }
                }
            }
        }

        if (in_array($spot['zone'], ['MARAIS', 'MIXTE'])) {
            $tide = getTideStatus($time, $dailyData['tide_time_high'] ?? null);

            if ($windMissing) {
                $analysis[$hourKey]['marsh'] = ['status' => 'grey', 'reasons' => ['Données vent indisponibles']];
            } elseif ($tide['status'] === 'grey') {
                $analysis[$hourKey]['marsh'] = ['status' => 'grey', 'reasons' => [$tide['reason']]];
            } elseif ($windGusts !== null && $windGusts > MARSH_WIND_ORANGE + GUST_DANGER_MARGIN) {
                $analysis[$hourKey]['marsh'] = ['status' => 'red', 'reasons' => ['Rafales dangereuses']];
            } elseif ($windSpeed > MARSH_WIND_ORANGE) {
                $analysis[$hourKey]['marsh'] = ['status' => 'red', 'reasons' => ['Vent fort']];
            } elseif ($tide['status'] === 'red') {
                $analysis[$hourKey]['marsh'] = ['status' => 'red', 'reasons' => [$tide['reason']]];
            } elseif ($windSpeed <= MARSH_WIND_GREEN && $tide['status'] === 'green') {
                $analysis[$hourKey]['marsh'] = ['status' => 'green', 'reasons' => []];
            } else {
                $reasons = [];
                if ($windSpeed > MARSH_WIND_GREEN) $reasons[] = 'Vent modéré';
                if ($tide['status'] === 'orange') $reasons[] = $tide['reason'];
                $analysis[$hourKey]['marsh'] = ['status' => 'orange', 'reasons' => $reasons];
            }
        }

        if ($spot['zone'] === 'LAC') {
            if ($windMissing) {
                $analysis[$hourKey]['lake'] = ['status' => 'grey', 'reasons' => ['Données vent indisponibles']];
            } elseif ($windGusts !== null && $windGusts > LAKE_WIND_ORANGE + GUST_DANGER_MARGIN) {
                $analysis[$hourKey]['lake'] = ['status' => 'red', 'reasons' => ['Rafales dangereuses']];
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
