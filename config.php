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
    ],
    'ile_olonne_marais' => [
        'name' => 'Marais Nord (Île d\'Olonne)',
        'lat' => 46.5900,
        'lng' => -1.8150,
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
        'name' => 'Port Olona (Cale publique - La Cabaude)',
        'sector' => 'sables_coast',
        'zone' => 'MIXTE',
        'lat' => 46.506252,
        'lng' => -1.795720,
        'rando' => 'Cale protégée pour rejoindre le chenal ou remonter vers la rivière Vertonne.',
    ],

    // --- SPOTS MARAIS & ESTUAIRES ---
    [
        'id' => 'salines',
        'name' => 'Embarcadère des Salines (L\'Aubraie)',
        'sector' => 'brem_north_marais',
        'zone' => 'MARAIS',
        'lat' => 46.516788,
        'lng' => -1.804913,
        'rando' => 'Boucle des marais salants (6-8 km), 100% abritée du vent.',
    ],
    [
        'id' => 'aire_mireille',
        'name' => 'Aire de Mireille (Le Marais)',
        'sector' => 'brem_north_marais',
        'zone' => 'MARAIS',
        'lat' => 46.522285,
        'lng' => -1.808390,
        'rando' => 'Point de mise à l\'eau au cœur du marais, calme et abrité du vent.',
    ],
    [
        'id' => 'pont_forgerie',
        'name' => 'Pont de la Forgerie',
        'sector' => 'brem_north_marais',
        'zone' => 'MARAIS',
        'lat' => 46.524238,
        'lng' => -1.809153,
        'rando' => 'Départ pratique sur le canal, à deux pas du restaurant Mireille Oasis.',
    ],
    [
        'id' => 'riviere_loirs',
        'name' => 'Écluse des Loirs (Bauduère)',
        'sector' => 'brem_north_marais',
        'zone' => 'MARAIS',
        'lat' => 46.545005,
        'lng' => -1.792103,
        'rando' => 'Départ depuis l\'écluse, au bout d\'une aire de retournement.',
    ],
    [
        'id' => 'passerelle_bauduere',
        'name' => 'Passerelle de la Bauduère',
        'sector' => 'brem_north_marais',
        'zone' => 'MARAIS',
        'lat' => 46.539750,
        'lng' => -1.802526,
        'rando' => 'Petite passerelle sur le canal de la Bauduère, mise à l\'eau facile.',
    ],
    [
        'id' => 'pont_vertou',
        'name' => 'Pont de Vertou',
        'sector' => 'brem_north_marais',
        'zone' => 'MARAIS',
        'lat' => 46.549981,
        'lng' => -1.766032,
        'rando' => 'Accès direct au canal depuis la route D32, secteur calme.',
    ],
    [
        'id' => 'pont_champclou',
        'name' => 'Pont de Champclou',
        'sector' => 'brem_north_marais',
        'zone' => 'MARAIS',
        'lat' => 46.554495,
        'lng' => -1.790937,
        'rando' => 'Attention à la canalisation basse sous le pont, passage resserré à deux kayaks.',
    ],
    [
        'id' => 'pont_salaire',
        'name' => 'Pont de La Salaire',
        'sector' => 'ile_olonne_marais',
        'zone' => 'MARAIS',
        'lat' => 46.581607,
        'lng' => -1.806203,
        'rando' => 'Point le plus au nord du réseau de canaux, vers L\'Île-d\'Olonne.',
    ],
    [
        'id' => 'base_canoe_auzance',
        'name' => 'La Blainière (Base Canoë de l\'Auzance)',
        'sector' => 'ile_olonne_marais',
        'zone' => 'MARAIS',
        'lat' => 46.593412,
        'lng' => -1.784910,
        'rando' => 'Base canoë sur l\'Auzance, encadrement possible sur place.',
    ],
    [
        'id' => 'la_cabane_brem',
        'name' => 'La Cabane (Brem-sur-Mer)',
        'sector' => 'ile_olonne_marais',
        'zone' => 'MARAIS',
        'lat' => 46.591950,
        'lng' => -1.820756,
        'rando' => 'Départ pour le tour de l\'île de la Chaboissière via l\'Auzance et la Vertonne.',
    ],
    [
        'id' => 'havre_gachere',
        'name' => 'Écluse du Havre de la Gachère',
        'sector' => 'ile_olonne_marais',
        'zone' => 'MARAIS',
        'lat' => 46.585872,
        'lng' => -1.845964,
        'rando' => 'Écluse du havre de la Gachère, accès parfois délicat après travaux.',
    ],
    [
        'id' => 'gachere_village',
        'name' => 'La Gachère (village)',
        'sector' => 'ile_olonne_marais',
        'zone' => 'MARAIS',
        'lat' => 46.595372,
        'lng' => -1.834790,
        'rando' => 'Mise à l\'eau au village de la Gachère, secteur nord du marais.',
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
    [
        'id' => 'plage_veillon',
        'name' => 'Plage du Veillon',
        'sector' => 'talmont_estuary',
        'zone' => 'MER',
        'lat' => 46.434336,
        'lng' => -1.658979,
        'rando' => 'Plage à rouleaux exposée : houle plus marquée qu\'à l\'abri de l\'estuaire.',
    ],
    [
        'id' => 'debarcadere_talmont',
        'name' => 'Débarcadère de Talmont-Saint-Hilaire',
        'sector' => 'talmont_estuary',
        'zone' => 'MARAIS',
        'lat' => 46.466968,
        'lng' => -1.616680,
        'rando' => 'Débarcadère au cœur du bourg de Talmont-Saint-Hilaire, sur le Payré.',
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
define('LAKE_WIND_ORANGE', 30);
define('LAKE_WIND_GREEN', 20);

// -- Rafales --
// Marge au-delà du seuil orange d'une zone à partir de laquelle une rafale devient dangereuse
define('GUST_DANGER_MARGIN', 10);

// -- Orage --
// Codes météo OMM (weathercode Open-Meteo) correspondant à un orage
define('STORM_WEATHER_CODES', [95, 96, 99]);

// -- Fiabilité des prévisions --
// Au-delà de ce nombre de jours, les prévisions sont affichées comme indicatives
define('RELIABLE_FORECAST_DAYS', 3);

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
