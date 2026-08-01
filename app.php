<?php

require_once 'config.php';
require_once 'services/weather.php';
require_once 'services/rules.php';
require_once 'services/aggregator.php';
require_once 'services/presentation.php';

$sectorWeatherData = getSectorWeatherData($sectors);
$apiErrors = getSectorApiErrors($sectors, $sectorWeatherData);
$spotAnalyses = computeSpotAnalyses($spots, $sectorWeatherData);

$finalSlots = getAggregatedSlots($spotAnalyses);

$idealDepartureHours = getIdealDepartureHours($spotAnalyses);

$spotsForecast = getSpotsForecastSummary($spotAnalyses, $idealDepartureHours);
$groupedForecasts = groupIdenticalForecasts($spotsForecast);

$dayScores = getDayScores($groupedForecasts);

$currentWeather = getCurrentWeatherSnapshot($spotAnalyses);

$slotsByDay = [];
foreach ($finalSlots as $slot) {
    $day = $slot['start']->format('Y-m-d');
    if (!isset($slotsByDay[$day])) {
        $slotsByDay[$day] = [];
    }
    $slotsByDay[$day][] = $slot;
}

$dateFormatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE, null, null, 'EEEE d MMMM');
