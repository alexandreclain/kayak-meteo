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
];

/**
 * Retourne l'explication associée à une raison, ou un texte générique si elle est inconnue.
 */
function describeReason(string $reason): string
{
    return REASON_DESCRIPTIONS[$reason] ?? "Condition à vérifier sur place avant de partir.";
}

/**
 * Classes Tailwind pour le badge de score /10 d'un jour, selon sa navigabilité.
 *
 * @param int|null $score
 * @return string
 */
function dayScoreBadgeClasses(?int $score): string
{
    if ($score === null) return 'bg-slate-100 text-slate-500 border border-slate-200';
    if ($score >= 7) return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
    if ($score >= 4) return 'bg-amber-50 text-amber-700 border border-amber-200';
    return 'bg-red-50 text-red-700 border border-red-200';
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
