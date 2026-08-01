<?php
// services/presentation.php - Textes d'affichage (séparés de la logique métier)

/**
 * Explique concrètement le risque derrière chaque raison retournée par rules.php,
 * pour que l'utilisateur comprenne pourquoi un créneau est déconseillé et pas seulement "quoi".
 */
const REASON_DESCRIPTIONS = [
    'Vent fort' => "Le vent dépasse le seuil de sécurité de la zone : risque de perte de contrôle du kayak et de dérive rapide.",
    'Vent modéré' => "Vent proche du seuil de vigilance : praticable pour un pagayeur à l'aise, restez prudent et surveillez l'évolution.",
    'Rafales dangereuses' => "Les rafales dépassent largement le vent moyen : risque de déséquilibre soudain, souvent la vraie cause de dessalage (chavirage).",
    'Houle forte' => "Houle trop importante pour la zone : risque de prise d'eau et de dessalage, navigation difficile pour rejoindre la côte.",
    'Houle modérée' => "Houle un peu formée : conditions pour pagayeurs à l'aise, restez vigilant.",
    'Vent de terre' => "Le vent souffle de la côte vers le large : il vous éloigne du rivage, avec un risque de ne plus pouvoir revenir à la pagaie en cas de fatigue ou de panne.",
    'Hors marée' => "En dehors de la fenêtre de pleine mer : risque d'échouage sur la vase ou impossibilité de rejoindre la cale à basse mer.",
    'Fenêtre marée étendue' => "Un peu éloigné de l'heure de pleine mer : praticable mais surveillez la hauteur d'eau, risque d'échouage en fin de créneau.",
    'Orage / risque de foudre' => "Risque d'orage : la foudre est un danger mortel sur l'eau, où vous êtes le point le plus haut. Ne sortez pas.",
    'Nuit (hors créneau de navigation)' => "En dehors des heures de jour (lever/coucher du soleil) : visibilité insuffisante, navigation nocturne déconseillée.",
    'Données houle/vent indisponibles' => "Les données de houle ou de vent n'ont pas pu être récupérées pour cet horaire : vérifiez les conditions par un autre moyen avant de partir.",
    'Données marée indisponibles' => "Les horaires de marée n'ont pas pu être calculés pour cet horaire : vérifiez une table de marée officielle avant de partir.",
    'Données vent indisponibles' => "Les données de vent n'ont pas pu être récupérées pour cet horaire : vérifiez les conditions par un autre moyen avant de partir.",
    'Données indisponibles pour ce jour' => "Aucune donnée exploitable pour cette journée : vérifiez la météo par un autre moyen avant de partir.",
];

/**
 * Classes Tailwind pour le badge de score /5 d'un jour, selon sa navigabilité.
 *
 * @param int|null $score
 * @return string
 */
function dayScoreBadgeClasses(?int $score): string
{
    if ($score === null) return 'bg-slate-100 text-slate-500 border border-slate-200';
    if ($score >= 4) return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
    if ($score >= 2) return 'bg-amber-50 text-amber-700 border border-amber-200';
    return 'bg-red-50 text-red-700 border border-red-200';
}

/**
 * Nom de fichier image (assets/) correspondant à un statut vert/orange/rouge/gris.
 *
 * @param string $status
 * @return string
 */
function statusIconFile(string $status): string
{
    return match ($status) {
        'green' => 'green.jpg',
        'orange' => 'orange.jpg',
        'grey' => 'grey.jpg',
        default => 'red.jpg',
    };
}

/**
 * Même logique de palier que dayScoreBadgeClasses(), mais retourne l'image de statut
 * correspondante (pour l'icône "en moyenne vert/orange/rouge" d'un jour).
 *
 * @param int|null $score
 * @return string
 */
function dayScoreIconFile(?int $score): string
{
    if ($score === null) return 'grey.jpg';
    if ($score >= 4) return 'green.jpg';
    if ($score >= 2) return 'orange.jpg';
    return 'red.jpg';
}

/**
 * Sépare un nom de spot en partie principale + précision entre parenthèses, pour afficher
 * cette dernière en plus discret (ex: "Port Olona (Cale publique)" → "Port Olona" + "Cale publique").
 *
 * @param string $name
 * @return array ['main' => string, 'detail' => string|null]
 */
function splitSpotName(string $name): array
{
    if (preg_match('/^(.*?)\s*\((.*)\)\s*$/', $name, $matches)) {
        return ['main' => $matches[1], 'detail' => $matches[2]];
    }
    return ['main' => $name, 'detail' => null];
}

/**
 * Opacité dégressive selon l'éloignement dans les prévisions : J+0 pleine opacité, J+6
 * plus estompé, pour traduire visuellement la fiabilité décroissante des prévisions.
 *
 * @param int $dayIndex 0 (aujourd'hui) à 6 (dans une semaine).
 * @return float
 */
function dayFadeOpacity(int $dayIndex): float
{
    return max(0.55, round(1 - ($dayIndex * 0.075), 2));
}

/**
 * Met en forme un tableau météo (celui produit par rules.php) en une liste prête à afficher
 * (icône + libellé + valeur), pour le panneau d'info déclenché en JS comme pour d'éventuels
 * autres affichages. Ignore les champs absents plutôt que d'afficher des valeurs vides.
 *
 * @param array|null $weather
 * @return array Liste de ['icon' => string, 'label' => string, 'value' => string]
 */
function formatWeatherForDisplay(?array $weather): array
{
    if (!$weather) {
        return [];
    }

    $items = [];
    $wx = weatherCodeToLabel($weather['weather_code'] ?? null);
    $items[] = ['icon' => $wx['icon'], 'label' => 'Météo', 'value' => $wx['label']];

    if ($weather['air_temperature'] !== null) {
        $items[] = ['icon' => '🌡️', 'label' => "Température de l'air", 'value' => round($weather['air_temperature']) . '°C'];
    }
    if ($weather['water_temperature'] !== null) {
        $items[] = ['icon' => '🌊', 'label' => "Température de l'eau", 'value' => round($weather['water_temperature']) . '°C'];
    }
    if ($weather['uv_index'] !== null) {
        $items[] = ['icon' => '☀️', 'label' => 'Indice UV', 'value' => 'UV ' . round($weather['uv_index'], 1)];
    }
    if ($weather['wind_speed'] !== null) {
        $gusts = $weather['wind_gusts'] !== null ? ' (rafales ' . round($weather['wind_gusts']) . ' km/h)' : '';
        $direction = '';
        if ($weather['wind_direction'] !== null) {
            $arrow = '<svg xmlns="http://www.w3.org/2000/svg" class="inline-block h-3.5 w-3.5 align-[-2px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="transform: rotate(' . (int) $weather['wind_direction'] . 'deg)"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 14l-4-4m4 4l4-4" /></svg>';
            $direction = ' — ' . $arrow . ' ' . $weather['wind_direction'] . '°';
        }
        $items[] = ['icon' => '💨', 'label' => 'Vent', 'value' => round($weather['wind_speed']) . ' km/h' . $gusts . $direction];
    }
    if ($weather['swell_height'] !== null) {
        $items[] = ['icon' => '🌊', 'label' => 'Houle', 'value' => $weather['swell_height'] . ' m'];
    }
    if (($weather['tide_direction'] ?? null) !== null) {
        $nextHigh = $weather['tide_next_high'] ? ' — pleine mer ' . $weather['tide_next_high'] : '';
        $items[] = ['icon' => tideDirectionIcon($weather['tide_direction']), 'label' => 'Marée', 'value' => ucfirst($weather['tide_direction']) . $nextHigh];
    }

    return $items;
}

/**
 * Icône représentant le sens de la marée.
 *
 * @param string|null $direction 'montante', 'descendante', 'étale' ou null.
 * @return string
 */
function tideDirectionIcon(?string $direction): string
{
    return match ($direction) {
        'montante' => '⬆️',
        'descendante' => '⬇️',
        default => '➡️',
    };
}

/**
 * Traduit un code météo OMM (weathercode Open-Meteo) en icône + libellé lisible.
 *
 * @param int|null $code
 * @return array ['icon' => string, 'label' => string]
 */
function weatherCodeToLabel(?int $code): array
{
    if ($code === null) {
        return ['icon' => '❔', 'label' => 'Inconnu'];
    }

    return match (true) {
        $code === 0 => ['icon' => '☀️', 'label' => 'Ciel dégagé'],
        in_array($code, [1, 2]) => ['icon' => '🌤️', 'label' => 'Peu nuageux'],
        $code === 3 => ['icon' => '☁️', 'label' => 'Couvert'],
        in_array($code, [45, 48]) => ['icon' => '🌫️', 'label' => 'Brouillard'],
        in_array($code, [51, 53, 55, 56, 57]) => ['icon' => '🌦️', 'label' => 'Bruine'],
        in_array($code, [61, 63, 65, 66, 67]) => ['icon' => '🌧️', 'label' => 'Pluie'],
        in_array($code, [71, 73, 75, 77]) => ['icon' => '🌨️', 'label' => 'Neige'],
        in_array($code, [80, 81, 82]) => ['icon' => '🌦️', 'label' => 'Averses'],
        in_array($code, STORM_WEATHER_CODES) => ['icon' => '⛈️', 'label' => 'Orage'],
        default => ['icon' => '❔', 'label' => 'Inconnu'],
    };
}
