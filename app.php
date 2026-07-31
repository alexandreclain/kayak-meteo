<?php
// app.php - Main Application Logic

require_once 'config.php';
require_once 'services/weather.php';
require_once 'services/rules.php';
require_once 'services/aggregator.php';
require_once 'services/presentation.php';

// 1. Fetch weather once per sector, analyze once per spot, shared by everything below
$sectorWeatherData = getSectorWeatherData($sectors);
$apiErrors = getSectorApiErrors($sectors, $sectorWeatherData);
$spotAnalyses = computeSpotAnalyses($spots, $sectorWeatherData);

// 2. Get all aggregated slots (for the accordion)
$finalSlots = getAggregatedSlots($spotAnalyses);

// 3. Get the 7-day summary for the table and map
$spotsForecast = getSpotsForecastSummary($spotAnalyses);

// 4. Get the 0-10 navigability score per day (for the accordion header)
$dayScores = getDayScores($spotAnalyses);

// 5. Group slots by day for display
$slotsByDay = [];
foreach ($finalSlots as $slot) {
    $day = $slot['start']->format('Y-m-d');
    if (!isset($slotsByDay[$day])) {
        $slotsByDay[$day] = [];
    }
    $slotsByDay[$day][] = $slot;
}

// 6. Prepare formatter for the view
$dateFormatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE, null, null, 'EEEE d MMMM');

// All variables are now ready for index.php
