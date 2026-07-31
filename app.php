<?php
// app.php - Main Application Logic

require_once 'config.php';
require_once 'services/weather.php';
require_once 'services/rules.php';
require_once 'services/aggregator.php';

// 1. Get all aggregated data for the slot list
$aggregatedData = getAggregatedSlots($spots, $sectors);
$finalSlots = $aggregatedData['slots'];
$apiErrors = $aggregatedData['errors'];

// 2. Get the 7-day summary for the table and map
$spotsForecast = getSpotsForecastSummary($spots, $sectors);

// 3. Group slots by day for display
$slotsByDay = [];
foreach ($finalSlots as $slot) {
    $day = $slot['start']->format('Y-m-d');
    if (!isset($slotsByDay[$day])) {
        $slotsByDay[$day] = [];
    }
    $slotsByDay[$day][] = $slot;
}

// 4. Prepare formatter for the view
$dateFormatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE, null, null, 'EEEE d MMMM');

// All variables are now ready for index.php