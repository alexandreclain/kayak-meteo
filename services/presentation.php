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
 * Rend le détail météo complet d'une heure donnée (grille 3 colonnes), utilisé à la fois pour
 * le détail d'un créneau et pour le panneau d'info de la synthèse : les deux affichent ainsi
 * exactement les mêmes données, dans le même ordre, avec la même apparence — plus de risque
 * de divergence entre les deux vues.
 *
 * @param array|null $weather Tableau météo produit par rules.php.
 * @param bool $showTide La marée n'a de sens que si un marais est concerné par ce contexte.
 * @return string HTML (chaîne vide si $weather est absent).
 */
function renderWeatherDetailGrid(?array $weather, bool $showTide): string
{
    if (!$weather) {
        return '';
    }

    $wx = weatherCodeToLabel($weather['weather_code'] ?? null);

    ob_start(); ?>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs text-slate-600 text-left">
        <!-- Ligne 1 : météo, UV, température de l'air -->
        <div class="flex items-center gap-1.5" title="Conditions générales">
            <span><?= $wx['icon'] ?></span>
            <span><?= $wx['label'] ?></span>
        </div>
        <?php if ($weather['uv_index'] !== null): ?>
        <div class="flex items-center gap-1.5" title="Indice UV">
            <span>☀️</span>
            <span>UV <?= round($weather['uv_index'], 1) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($weather['air_temperature'] !== null): ?>
        <div class="flex items-center gap-1.5" title="Température de l'air">
            <span>🌡️</span>
            <span><?= round($weather['air_temperature']) ?>°C</span>
        </div>
        <?php endif; ?>

        <!-- Ligne 2 : vent (+ direction), houle, température de l'eau (juste sous celle de l'air) -->
        <?php if ($weather['wind_speed'] !== null): ?>
        <div class="flex items-center gap-1.5" title="Vent moyen (rafales) et direction">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
            <span>
                <?= round($weather['wind_speed']) ?> km/h<?php if ($weather['wind_gusts'] !== null): ?> <span class="text-slate-400">(raf. <?= round($weather['wind_gusts']) ?>)</span><?php endif; ?>
            </span>
            <?php if ($weather['wind_direction'] !== null): ?>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="transform: rotate(<?= (int) $weather['wind_direction'] ?>deg);"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 14l-4-4m4 4l4-4" /></svg>
            <span><?= $weather['wind_direction'] ?>°</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($weather['swell_height'] !== null): ?>
        <div class="flex items-center gap-1.5" title="Houle">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12h2l2-9 2 18 2-9 2 12 2-15 2 10h2"/></svg>
            <span><?= $weather['swell_height'] ?> m</span>
        </div>
        <?php endif; ?>
        <?php if ($weather['water_temperature'] !== null): ?>
        <div class="flex items-center gap-1.5" title="Température de l'eau">
            <span>🌊</span>
            <span><?= round($weather['water_temperature']) ?>°C</span>
        </div>
        <?php endif; ?>

        <?php if ($showTide && ($weather['tide_direction'] ?? null) !== null): ?>
        <div class="col-span-2 sm:col-span-3 flex items-center gap-1.5 pt-2 mt-1 border-t border-slate-100" title="Marée">
            <span><?= tideDirectionIcon($weather['tide_direction']) ?></span>
            <span>
                Marée <?= $weather['tide_direction'] ?>
                <?php if ($weather['tide_next_high']): ?><span class="text-slate-400">· pleine mer <?= $weather['tide_next_high'] ?></span><?php endif; ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
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
