<?php
// config.php

require_once __DIR__ . '/services/env.php';
loadEnv(__DIR__ . '/.env');

// --- SECTEURS GÉOGRAPHIQUES POUR LES APPELS API ---
$sectors = [
    'sables_coast' => [
        'name' => 'Côte des Sables',
        'lat' => 46.4960,
        'lng' => -1.7830,
    ],
    'talmont_estuary' => [
        'name' => 'Estuaire du Payré (Talmont)',
        'lat' => 46.4400,
        'lng' => -1.6700,
    ],
    'brem_north_marais' => [
        'name' => 'Marais Nord (Brem/Olonne)',
        'lat' => 46.5300,
        'lng' => -1.7800,
    ]
];

// --- LISTE DES SPOTS DE KAYAK CORRIGÉS ---
$spots = [
    // --- SPOTS MER ---
    [
        'id' => 'paracou',
        'name' => 'Plage de La Paracou',
        'sector' => 'sables_coast',
        'zone' => 'MER',
        'lat' => 46.5135,
        'lng' => -1.7982,
        'rando' => 'Parcours côtier rasant les rochers du Cap d\'Olonne par mer calme.',
    ],
    [
        'id' => 'grande_plage',
        'name' => 'La Grande Plage (Chenal traversier)',
        'sector' => 'sables_coast',
        'zone' => 'MER',
        'lat' => 46.4948,
        'lng' => -1.7885,
        'rando' => 'Navigation abritée dans la baie des Sables (sortie obligatoire par le chenal traversier en été).',
    ],
    [
        'id' => 'armandeche',
        'name' => 'Cale de l\'Armandèche (La Chaume)',
        'sector' => 'sables_coast',
        'zone' => 'MER',
        'lat' => 46.4962,
        'lng' => -1.7961,
        'rando' => 'Cale de mise à l\'eau autorisée sur la côte sauvage la Chaumoise.',
    ],
    [
        'id' => 'plage_tanchet',
        'name' => 'Plage de Tanchet (Chenal nautique)',
        'sector' => 'sables_coast',
        'zone' => 'MER',
        'lat' => 46.4832,
        'lng' => -1.7705,
        'rando' => 'Mise à l\'eau via le chenal sud de la baie vers l\'anse de Cayola.',
    ],

    // --- SPOTS PORT / MIXTE ---
    [
        'id' => 'port_olona',
        'name' => 'Port Olona (Cale publique)',
        'sector' => 'sables_coast',
        'zone' => 'MIXTE',
        'lat' => 46.5022,
        'lng' => -1.7868,
        'rando' => 'Cale protégée pour rejoindre le chenal ou remonter vers la rivière Vertonne.',
    ],

    // --- SPOTS MARAIS & ESTUAIRES ---
    [
        'id' => 'salines',
        'name' => 'Embarcadère des Salines (L\'Aubraie)',
        'sector' => 'brem_north_marais',
        'zone' => 'MARAIS',
        'lat' => 46.5182,
        'lng' => -1.7825,
        'rando' => 'Boucle des marais salants (6-8 km), 100% abritée du vent.',
    ],
    [
        'id' => 'riviere_loirs',
        'name' => 'Rivière des Loirs (La Gachère)',
        'sector' => 'brem_north_marais',
        'zone' => 'MARAIS',
        'lat' => 46.5412,
        'lng' => -1.7981,
        'rando' => 'Descente calme vers le havre de la Gachère.',
    ],
    [
        'id' => 'gabelous',
        'name' => 'Cale des Gabelous (Brem-sur-Mer)',
        'sector' => 'brem_north_marais',
        'zone' => 'MARAIS',
        'lat' => 46.5510,
        'lng' => -1.8021,
        'rando' => 'Randonnée le long de l\'Auzance.',
    ],
    [
        'id' => 'guittiere',
        'name' => 'Port de la Guittière (Talmont)',
        'sector' => 'talmont_estuary',
        'zone' => 'MARAIS',
        'lat' => 46.4461,
        'lng' => -1.6578,
        'rando' => 'Découverte de l\'estuaire du Payré et des parcs ostréicoles.',
    ],
    [
        'id' => 'veillon',
        'name' => 'Cale du Havre du Veillon (Payré)',
        'sector' => 'talmont_estuary',
        'zone' => 'MARAIS',
        'lat' => 46.4385,
        'lng' => -1.6782,
        'rando' => 'Remontée du chenal du Payré à marée montante.',
    ],

    // --- PLAN D'EAU FERMÉ ---
    [
        'id' => 'lac_tanchet',
        'name' => 'Lac de Tanchet',
        'sector' => 'sables_coast',
        'zone' => 'LAC',
        'lat' => 46.4852,
        'lng' => -1.7645,
        'rando' => 'Plan d\'eau fermé, idéal pour s\'entraîner ou débuter sans marée.',
    ]
];

// --- PARAMETRES DE SECURITE ---

// -- Règles Générales --
// Vent de terre (offshore) est considéré dangereux si sa force dépasse cette valeur (en km/h)
define('OFFSHORE_WIND_DANGER_THRESHOLD', 8);
// La côte étant orientée Sud-Ouest, tout ce qui vient du secteur Nord-Est est dangereux.
define('OFFSHORE_WIND_ANGLES', [0, 112.5]); // [Angle de début, Angle de fin]

// -- Règles MER --
define('SEA_WIND_GREEN', 12);
define('SEA_SWELL_GREEN', 0.4);
define('SEA_WIND_ORANGE', 20);
define('SEA_SWELL_ORANGE', 0.8);

// -- Règles MARAIS --
define('MARSH_WIND_GREEN', 18);
define('MARSH_WIND_ORANGE', 25);
// Fenêtre de marée (Pleine Mer +/- X)
define('TIDE_WINDOW_GREEN_SECONDS', 90 * 60); // 1h30
define('TIDE_WINDOW_ORANGE_SECONDS', 150 * 60); // 2h30

// -- Règles LAC --
define('LAKE_WIND_GREEN', 20);
define('LAKE_WIND_ORANGE', 30);

// --- CLÉS API (définies dans .env, voir .env.example) ---
define('STORMGLASS_API_KEY', getenv('STORMGLASS_API_KEY') ?: '');
define('OPENWEATHER_API_KEY', getenv('OPENWEATHER_API_KEY') ?: '');
define('METEO_FRANCE_API_KEY', getenv('METEO_FRANCE_API_KEY') ?: '');
define('SHOM_API_KEY', getenv('SHOM_API_KEY') ?: '');
define('COPERNICUS_API_KEY', getenv('COPERNICUS_API_KEY') ?: '');

// --- PARAMETRES TECHNIQUES ---
// Durée de validité du cache en secondes (1 heure = 3600 secondes)
define('CACHE_DURATION', 3600);

// Définir le fuseau horaire pour toutes les fonctions de date/heure
date_default_timezone_set('Europe/Paris');
