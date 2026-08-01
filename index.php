<?php
// index.php - View Layer

require_once 'app.php';

$zoneDisplay = [
    'MER'    => ['label' => 'Mer'],
    'MARAIS' => ['label' => 'Marais'],
    'LAC'    => ['label' => 'Lac'],
];

$dayHeaderFormatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE, null, null, 'EEE');
$indicativeCutoff = (new DateTime())->modify('+' . RELIABLE_FORECAST_DAYS . ' days');
$isFirstDayAccordion = true;
$todayIdeal = $idealDepartureHours[(new DateTime())->format('Y-m-d')] ?? null;

$siteUrl = 'https://kayak.weclain.com/';
$siteName = 'Kayak Météo Olonne';
$pageTitle = "Kayak Météo Olonne — créneaux, marée et vent aux Sables-d'Olonne | weclain.com";
$pageDescription = "Kayak Météo Olonne : les meilleurs créneaux pour naviguer aux Sables-d'Olonne. Vent, houle et marée analysés heure par heure pour la mer, le marais et le lac de Tanchet.";

$faqItems = [
    [
        'question' => 'Comment savoir si les conditions sont bonnes pour faire du kayak aujourd\'hui ?',
        'answer' => 'Regarde le score du jour dans « Créneaux favorables » : vert veut dire qu\'un créneau sans risque existe, orange qu\'il faut rester prudent, rouge qu\'il vaut mieux reporter. Chaque statut est calculé heure par heure à partir du vent, de la houle et de la marée réels, pas d\'une moyenne vague.',
    ],
    [
        'question' => 'Qu\'est-ce qu\'un « vent de terre » et pourquoi c\'est dangereux ?',
        'answer' => 'Un vent de terre souffle de la côte vers le large. Il te pousse loin du rivage sans que tu t\'en rendes compte, et te fatigue à contre-courant pour rentrer. Aux Sables d\'Olonne, la côte est orientée sud-ouest : tout vent de secteur nord-est est considéré comme vent de terre dès qu\'il dépasse 8 km/h.',
    ],
    [
        'question' => 'Comment savoir si la marée est montante ou descendante ?',
        'answer' => 'Chaque créneau et le bloc météo en haut de page affichent le sens de la marée (montante ou descendante) et l\'heure de la prochaine pleine mer, calculés à partir du niveau réel de la mer heure par heure.',
    ],
    [
        'question' => 'Quelle est la différence entre les zones Mer, Marais et Lac ?',
        'answer' => 'En mer, le vent et la houle commandent. Dans le marais, c\'est la marée : rater la fenêtre de pleine mer, c\'est finir à pied dans la vase. Sur le lac de Tanchet, ni marée ni houle — seul le vent compte, un bon terrain pour débuter.',
    ],
    [
        'question' => 'D\'où viennent les données météo ?',
        'answer' => 'D\'Open-Meteo (API Marine et prévisions générales), gratuites et mises à jour toutes les heures. En secours, si la houle venait à manquer, Stormglass prend le relais.',
    ],
    [
        'question' => 'Que veut dire l\'icône grise sur un spot ou un jour ?',
        'answer' => 'Une icône grise signifie que la donnée est indisponible pour cet horaire (panne d\'API, données manquantes) — pas que les conditions sont dangereuses. Vérifie par un autre moyen avant de partir.',
    ],
    [
        'question' => 'C\'est quoi le « créneau idéal » affiché chaque jour ?',
        'answer' => 'C\'est la meilleure heure pour te mettre à l\'eau ce jour-là, calculée en croisant le vent, la houle et la marée de tous les spots suivis. La météo et le statut de chaque spot affichés dans la synthèse correspondent à cette heure précise.',
    ],
    [
        'question' => 'Est-ce que l\'app fonctionne hors connexion ?',
        'answer' => 'Oui, en partie : une fois installée sur l\'écran d\'accueil, la dernière version consultée reste accessible sans connexion, même si les données ne se rafraîchissent pas tant que le réseau n\'est pas revenu.',
    ],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= $siteUrl ?>">

    <!-- Open Graph / Twitter -->
    <meta property="og:type" content="website">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:url" content="<?= $siteUrl ?>">
    <meta property="og:image" content="<?= $siteUrl ?>android-chrome-512x512.png">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($pageDescription) ?>">

    <!-- Favicons & Manifest (PWA) -->
    <link rel="icon" href="favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
    <link rel="manifest" href="site.webmanifest">
    <meta name="theme-color" content="#0369A1">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars($siteName) ?>">

    <!-- Polices -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Le script de Tailwind CSS (via CDN) est indispensable. Il analyse votre HTML et génère les styles à la volée. -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Leaflet.js pour la carte -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <!-- CSS externe (cache-busting automatique basé sur la date de modification) -->
    <link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?: '1' ?>">
    <!-- JS externe -->
    <script src="assets/script.js?v=<?= @filemtime(__DIR__ . '/assets/script.js') ?: '1' ?>" defer></script>

    <!-- Données structurées (SEO) -->
    <script type="application/ld+json">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => $siteName,
        'url' => $siteUrl,
        'description' => $pageDescription,
        'applicationCategory' => 'WeatherApplication',
        'operatingSystem' => 'Any',
        'publisher' => ['@type' => 'Organization', 'name' => 'weclain', 'url' => 'https://weclain.com'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    </script>
    <script type="application/ld+json">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn($item) => [
            '@type' => 'Question',
            'name' => $item['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
        ], $faqItems),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    </script>
</head>
<body class="bg-slate-50 text-slate-900">

    <div class="container mx-auto p-4 md:p-6 max-w-5xl">

        <header class="mb-8 text-center">
            <h1 class="font-heading text-3xl md:text-4xl font-extrabold text-slate-900 flex items-center justify-center gap-2">
                <img src="apple-touch-icon.png" alt="" width="36" height="36" class="h-9 w-9 rounded-lg shadow-sm">
                <span><?= htmlspecialchars($siteName) ?></span>
            </h1>
            <p class="text-slate-500 mt-1">Les meilleurs spots et créneaux pour vos sorties aux Sables d'Olonne</p>
        </header>

        <nav aria-label="Sommaire" class="mb-12">
            <ul class="flex flex-wrap justify-center gap-2 text-sm font-heading font-semibold">
                <li><a href="#synthese" class="inline-flex items-center bg-white border border-slate-200 text-slate-600 px-4 py-1.5 rounded-lg shadow-sm hover:border-ocean-brand hover:text-ocean-brand transition-colors">Synthèse</a></li>
                <li><a href="#creneaux" class="inline-flex items-center bg-white border border-slate-200 text-slate-600 px-4 py-1.5 rounded-lg shadow-sm hover:border-ocean-brand hover:text-ocean-brand transition-colors">Créneaux</a></li>
                <li><a href="#spots" class="inline-flex items-center bg-white border border-slate-200 text-slate-600 px-4 py-1.5 rounded-lg shadow-sm hover:border-ocean-brand hover:text-ocean-brand transition-colors">Spots</a></li>
                <li><a href="#faq" class="inline-flex items-center bg-white border border-slate-200 text-slate-600 px-4 py-1.5 rounded-lg shadow-sm hover:border-ocean-brand hover:text-ocean-brand transition-colors">FAQ</a></li>
            </ul>
        </nav>

        <main>
            <section class="mb-12">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:h-[560px]">
                    <div class="flex flex-col lg:h-full lg:min-h-0 order-2 lg:order-1">
                        <h2 class="font-heading text-2xl font-bold text-slate-900 mb-2">Carte Kayak des conditions à venir</h2>
                        <p class="text-sm text-slate-500 mb-4">Aperçu pour l'heure qui vient : vent, houle et marée pour chaque spot.</p>
                        <div id="map" class="w-full min-h-[320px] lg:min-h-0 lg:flex-1 rounded-lg border border-slate-200 shadow-sm z-0"></div>
                    </div>
                    <div class="flex flex-col lg:h-full lg:min-h-0 order-1 lg:order-2">
                        <h2 class="font-heading text-2xl font-bold text-slate-900 mb-2">La météo des Sables-d'Olonne</h2>
                        <p class="text-slate-600 leading-relaxed">
                            <strong><?= htmlspecialchars($siteName) ?></strong> analyse en temps réel
                            <mark class="bg-yellow-200 px-1 rounded">le vent, la houle et les marées</mark>
                            pour vous indiquer, heure par heure, les meilleurs créneaux pour naviguer en toute
                            sécurité : en mer, dans le marais ou sur le <strong>lac de Tanchet</strong>, aux
                            <strong>Sables d'Olonne</strong> et à Brem-sur-Mer. Chaque créneau est passé au crible
                            de <strong>règles de sécurité</strong> claires, avec les raisons expliquées en un clic.
                        </p>

                        <?php if ($currentWeather): $cwx = weatherCodeToLabel($currentWeather['weather_code'] ?? null); ?>
                            <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
                                <div class="flex items-center gap-2 bg-white/80 border border-slate-200 rounded-lg px-3 py-2">
                                    <span class="text-lg"><?= $cwx['icon'] ?></span>
                                    <div>
                                        <div class="font-bold text-slate-900"><?php if ($currentWeather['air_temperature'] !== null): ?><?= round($currentWeather['air_temperature']) ?>°C<?php endif; ?></div>
                                        <div class="text-xs text-slate-500"><?= $cwx['label'] ?></div>
                                    </div>
                                </div>
                                <?php if ($currentWeather['uv_index'] !== null): ?>
                                <div class="flex items-center gap-2 bg-white/80 border border-slate-200 rounded-lg px-3 py-2">
                                    <span class="text-lg">☀️</span>
                                    <div>
                                        <div class="font-bold text-slate-900">UV <?= round($currentWeather['uv_index'], 1) ?></div>
                                        <div class="text-xs text-slate-500">Indice UV</div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="flex items-center gap-2 bg-white/80 border border-slate-200 rounded-lg px-3 py-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                                    <div>
                                        <div class="font-bold text-slate-900"><?= round($currentWeather['wind_speed']) ?> km/h</div>
                                        <div class="text-xs text-slate-500">Vent</div>
                                    </div>
                                </div>
                                <?php if ($currentWeather['swell_height'] !== null): ?>
                                <div class="flex items-center gap-2 bg-white/80 border border-slate-200 rounded-lg px-3 py-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12h2l2-9 2 18 2-9 2 12 2-15 2 10h2"/></svg>
                                    <div>
                                        <div class="font-bold text-slate-900"><?= $currentWeather['swell_height'] ?> m</div>
                                        <div class="text-xs text-slate-500">Houle</div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($currentWeather['water_temperature'] !== null): ?>
                                <div class="flex items-center gap-2 bg-white/80 border border-slate-200 rounded-lg px-3 py-2">
                                    <span class="text-lg">🌊</span>
                                    <div>
                                        <div class="font-bold text-slate-900"><?= round($currentWeather['water_temperature']) ?>°C</div>
                                        <div class="text-xs text-slate-500">Eau</div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if (($currentWeather['tide_direction'] ?? null) !== null): ?>
                                <div class="flex items-center gap-2 bg-white/80 border border-slate-200 rounded-lg px-3 py-2">
                                    <span class="text-lg"><?= tideDirectionIcon($currentWeather['tide_direction']) ?></span>
                                    <div>
                                        <div class="font-bold text-slate-900">Marée <?= $currentWeather['tide_direction'] ?></div>
                                        <?php if ($currentWeather['tide_next_high']): ?><div class="text-xs text-slate-500">Pleine mer <?= $currentWeather['tide_next_high'] ?></div><?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="tape-photo mt-4 w-full h-56 lg:h-auto lg:min-h-0 lg:flex-1">
                            <span class="tape" aria-hidden="true"></span>
                            <div class="tape-photo-inner rounded-lg border border-slate-200 shadow-sm">
                                <img src="assets/kayak.jpg" alt="Sortie kayak aux Sables d'Olonne" class="parallax-img absolute inset-0 w-full h-full object-cover" loading="lazy">
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="synthese" class="mb-12 scroll-mt-6">
                <h2 class="font-heading text-2xl font-bold text-slate-900 mb-2">
                    Synthèse météorologique des 7 prochains jours
                    <?php if ($todayIdeal && $todayIdeal['hour']): ?>
                        <span class="text-base font-semibold text-ocean-brand">— Créneau idéal <?= $todayIdeal['hour'] ?></span>
                    <?php endif; ?>
                </h2>
                <p class="text-sm text-slate-500 mb-4">
                    Un coup d'œil sur la semaine pour planifier vos sorties à l'avance. Les spots identiques toute la
                    semaine sont regroupés sur une seule ligne. Plus on avance dans les 7 jours, plus l'affichage
                    s'estompe : les prévisions à J+1 sont bien plus fiables qu'à J+7.
                </p>

                <?php
                    // Largeurs partagées entre la table météo et la table de données : deux vraies
                    // <table> distinctes, alignées via des <col> identiques (table-layout: fixed).
                    $colWidths = array_merge([30], array_fill(0, 7, 10));
                ?>
                <div class="overflow-x-auto">
                    <div class="min-w-[760px]">
                        <!-- Météo par jour : hors du tableau de données, mais alignée sur les mêmes colonnes -->
                        <table class="w-full table-fixed border-collapse mb-2 text-sm">
                            <colgroup>
                                <?php foreach ($colWidths as $w): ?><col style="width: <?= $w ?>%"><?php endforeach; ?>
                            </colgroup>
                            <tbody>
                                <tr>
                                    <td></td>
                                    <?php for ($d = 0; $d < 7; $d++): ?>
                                        <?php
                                            $headerDay = (new DateTime())->modify("+$d days");
                                            $headerOpacity = dayFadeOpacity($d);
                                            $headerWeather = $idealDepartureHours[$headerDay->format('Y-m-d')]['weather'] ?? null;
                                            $headerWx = $headerWeather ? weatherCodeToLabel($headerWeather['weather_code'] ?? null) : null;
                                        ?>
                                        <td class="text-center text-[11px] text-slate-500 leading-tight px-1" style="opacity: <?= $headerOpacity ?>">
                                            <?php if ($headerWeather): ?>
                                                <?php if ($headerWx): ?><div><?= $headerWx['icon'] ?> <?php if ($headerWeather['air_temperature'] !== null): ?><?= round($headerWeather['air_temperature']) ?>°C<?php endif; ?></div><?php endif; ?>
                                                <?php if ($headerWeather['wind_speed'] !== null): ?><div>💨 <?= round($headerWeather['wind_speed']) ?> km/h</div><?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            </tbody>
                        </table>

                        <table class="w-full table-fixed border-collapse bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden text-sm text-left">
                            <colgroup>
                                <?php foreach ($colWidths as $w): ?><col style="width: <?= $w ?>%"><?php endforeach; ?>
                            </colgroup>
                            <thead class="bg-ocean-100">
                                <tr>
                                    <th class="p-3 font-bold text-slate-900">Les meilleurs spots</th>
                                    <?php for ($d = 0; $d < 7; $d++): ?>
                                        <?php
                                            $headerDay = (new DateTime())->modify("+$d days");
                                            $headerOpacity = dayFadeOpacity($d);
                                        ?>
                                        <th class="p-3 text-center font-bold text-slate-900" style="opacity: <?= $headerOpacity ?>">
                                            <?= $dayHeaderFormatter->format($headerDay) ?>
                                            <br>
                                            <span class="font-normal text-xs text-slate-500"><?= $headerDay->format('d/m') ?></span>
                                        </th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupedForecasts as $group): ?>
                                    <tr class="border-t border-slate-200">
                                        <td class="p-3 font-medium text-slate-800">
                                            <?php foreach ($group['spots'] as $i => $spot): ?>
                                                <?php if ($i > 0): ?><span class="text-slate-200">•</span> <?php endif; ?>
                                                <?= htmlspecialchars(splitSpotName($spot['name'])['main']) ?>
                                            <?php endforeach; ?>
                                        </td>
                                        <?php foreach ($group['daily_status'] as $d => $daily_status): ?>
                                            <?php
                                                $cellDay = (new DateTime())->modify("+$d days");
                                                $cellOpacity = dayFadeOpacity($d);
                                                $groupTitle = htmlspecialchars(implode(', ', array_map(fn($s) => $s['name'], $group['spots']))) . ' — ' . ucfirst($dateFormatter->format($cellDay));
                                                $weatherItems = formatWeatherForDisplay($daily_status['weather'] ?? null);
                                            ?>
                                            <td class="p-3 text-center" style="opacity: <?= $cellOpacity ?>">
                                                <button type="button" class="info-trigger" data-title="<?= $groupTitle ?>" data-status="<?= htmlspecialchars($daily_status['status']) ?>" data-reasons='<?= htmlspecialchars(json_encode($daily_status['reasons']), ENT_QUOTES) ?>' data-weather='<?= htmlspecialchars(json_encode($weatherItems), ENT_QUOTES) ?>' title="<?= htmlspecialchars(implode(', ', $daily_status['reasons'])) ?>">
                                                    <img src="assets/<?= statusIconFile($daily_status['status']) ?>" alt="<?= htmlspecialchars($daily_status['status']) ?>" width="28" height="28" class="h-7 w-7 mx-auto rounded-full object-cover border-2 border-white/70 ring-1 ring-slate-900/10 shadow-md" loading="lazy">
                                                </button>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-2">Touchez une icône pour voir le détail des raisons et de la météo.</p>
            </section>

            <section id="creneaux" class="scroll-mt-6">
                <h2 class="font-heading text-2xl font-bold text-slate-900 mb-2">Les prochains créneaux kayak à ne pas manquer</h2>
                <p class="text-sm text-slate-500 mb-4">Ouvrez un jour pour voir le détail heure par heure et savoir où pagayer.</p>
                <div class="space-y-3">
                    <?php if (empty($slotsByDay)): ?>
                        <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm text-center text-slate-600">
                            <p>Aucun créneau favorable trouvé pour les 7 prochains jours.</p>
                            <?php if (!empty($apiErrors)): ?>
                                <div class="text-left text-xs text-status-danger mt-4 bg-red-50 border border-red-200 p-3 rounded">
                                    <p class="font-bold mb-1">Détails des erreurs API :</p>
                                    <ul class="list-disc list-inside">
                                        <?php foreach ($apiErrors as $error): ?>
                                            <li><?= $error ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($slotsByDay as $day => $daySlots): ?>
                            <?php
                                $dayDate = new DateTime($day);
                                $dayScore = $dayScores[$day]['score'] ?? null;
                                $isDayIndicative = $dayDate > $indicativeCutoff;
                                $dayWx = weatherCodeToLabel($idealDepartureHours[$day]['weather']['weather_code'] ?? null);
                            ?>
                            <details class="group bg-white border border-slate-200 rounded-lg shadow-sm" <?= $isFirstDayAccordion ? 'open' : '' ?>>
                                <summary class="grid grid-cols-3 items-center gap-2 p-4 cursor-pointer select-none list-none">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="font-heading font-bold text-lg text-slate-900 truncate"><?= ucfirst($dateFormatter->format($dayDate)) ?></span>
                                        <?php if ($isDayIndicative): ?>
                                            <span class="text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200 px-2 py-1 rounded-full shrink-0">Indicatif</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center justify-center">
                                        <span class="text-lg" title="<?= htmlspecialchars($dayWx['label']) ?>"><?= $dayWx['icon'] ?></span>
                                    </div>
                                    <div class="flex items-center justify-end gap-2 shrink-0">
                                        <img src="assets/<?= dayScoreIconFile($dayScore) ?>" alt="" width="24" height="24" class="h-6 w-6 rounded-full object-cover border-2 border-white/70 ring-1 ring-slate-900/10 shadow-md shrink-0">
                                        <span class="text-sm font-bold px-2.5 py-1 rounded-full <?= dayScoreBadgeClasses($dayScore) ?>">
                                            <?= $dayScore !== null ? $dayScore . '/5' : 'N/A' ?>
                                        </span>
                                        <svg class="h-5 w-5 text-slate-400 transition-transform duration-200 group-open:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                    </div>
                                </summary>
                                <div class="px-4 pb-4 space-y-3 border-t border-slate-100 pt-3">
                                    <?php foreach ($daySlots as $slot): ?>
                                        <div class="p-4 bg-slate-50 border border-slate-100 rounded-lg">
                                            <p class="font-heading font-bold text-base text-slate-900">
                                                <?= $slot['start']->format('H:i') ?> - <?= $slot['end']->format('H:i') ?>
                                            </p>

                                            <!-- Zones affichées en une colonne, lisibles sur mobile -->
                                            <div class="mt-3 space-y-2">
                                                <?php foreach ($zoneDisplay as $zone => $meta): ?>
                                                    <?php if (!empty($slot['details'][$zone])): ?>
                                                        <div class="flex items-start gap-2 text-sm">
                                                            <span class="shrink-0 w-14 font-bold text-slate-900"><?= $meta['label'] ?></span>
                                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                                <?php foreach ($slot['details'][$zone] as $i => $spot): ?>
                                                                    <?php
                                                                        $color = match ($spot['status']) {
                                                                            'green' => 'text-status-safe font-medium',
                                                                            'grey' => 'text-slate-400 italic',
                                                                            default => 'text-status-warning',
                                                                        };
                                                                        $spotName = splitSpotName($spot['name'])['main'];
                                                                    ?>
                                                                    <?php if ($i > 0): ?><span class="text-slate-200">•</span><?php endif; ?>
                                                                    <span class="<?= $color ?>"><?= htmlspecialchars($spotName) ?></span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>

                                            <!-- Détails météo -->
                                            <?php if (isset($slot['weather'])):
                                                $weather = $slot['weather'];
                                                $wx = weatherCodeToLabel($weather['weather_code'] ?? null);
                                                $showTide = !empty($slot['details']['MARAIS']) && ($weather['tide_direction'] ?? null) !== null;
                                            ?>
                                                <div class="mt-3 pt-3 border-t border-slate-200 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs text-slate-600 text-left">
                                                    <!-- Ligne 1 : météo, UV, eau, (case vide) -->
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
                                                    <?php if ($weather['water_temperature'] !== null): ?>
                                                    <div class="flex items-center gap-1.5" title="Température de l'eau">
                                                        <span>🌊</span>
                                                        <span><?= round($weather['water_temperature']) ?>°C</span>
                                                    </div>
                                                    <?php endif; ?>
                                                    <div class="hidden sm:block" aria-hidden="true"></div>

                                                    <!-- Ligne 2 : vent (+ direction), houle, température de l'air -->
                                                    <div class="flex items-center gap-1.5" title="Vent moyen (rafales) et direction">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                                                        <span>
                                                            <?= round($weather['wind_speed']) ?> km/h<?php if ($weather['wind_gusts'] !== null): ?> <span class="text-slate-400">(raf. <?= round($weather['wind_gusts']) ?>)</span><?php endif; ?>
                                                        </span>
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="transform: rotate(<?= $weather['wind_direction'] ?>deg);"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 14l-4-4m4 4l4-4" /></svg>
                                                        <span><?= $weather['wind_direction'] ?>°</span>
                                                    </div>
                                                    <?php if ($weather['swell_height'] !== null): ?>
                                                    <div class="flex items-center gap-1.5" title="Houle">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12h2l2-9 2 18 2-9 2 12 2-15 2 10h2"/></svg>
                                                        <span><?= $weather['swell_height'] ?> m</span>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php if ($weather['air_temperature'] !== null): ?>
                                                    <div class="flex items-center gap-1.5" title="Température de l'air">
                                                        <span>🌡️</span>
                                                        <span><?= round($weather['air_temperature']) ?>°C</span>
                                                    </div>
                                                    <?php endif; ?>

                                                    <?php if ($showTide): ?>
                                                    <div class="col-span-2 sm:col-span-4 flex items-center gap-1.5 pt-2 mt-1 border-t border-slate-100" title="Marée">
                                                        <span><?= tideDirectionIcon($weather['tide_direction']) ?></span>
                                                        <span>
                                                            Marée <?= $weather['tide_direction'] ?>
                                                            <?php if ($weather['tide_next_high']): ?><span class="text-slate-400">· pleine mer <?= $weather['tide_next_high'] ?></span><?php endif; ?>
                                                        </span>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                            <?php $isFirstDayAccordion = false; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="mt-16 bg-white border border-slate-200 rounded-lg shadow-sm p-6 md:p-8">
                <h2 class="font-heading text-2xl font-bold text-slate-900 mb-4">La météo classique ne dit rien de vos vraies conditions de mer</h2>
                <div class="text-slate-600 leading-relaxed space-y-4">
                    <p>
                        Tu regardes la météo, tu vois « 15 km/h, ensoleillé », et tu te dis que c'est calme. Mauvais réflexe.
                        Un vent de 15 km/h de secteur nord-est aux Sables d'Olonne, c'est un <strong>vent de terre</strong> :
                        <mark class="bg-yellow-200 px-1 rounded">il te pousse vers le large, pas vers la plage</mark>.
                        La météo classique ne fait pas la différence. <strong><?= htmlspecialchars($siteName) ?></strong>, si.
                    </p>
                    <p>
                        Cette page croise trois données que les applis météo grand public ignorent superbement : le sens du
                        vent par rapport à la côte, la hauteur de houle heure par heure, et l'état réel de la marée. Résultat :
                        un statut clair — <strong class="text-status-safe">vert</strong>, <strong class="text-status-warning">orange</strong>,
                        <strong class="text-status-danger">rouge</strong> — pour chaque spot, et la raison écrite noir sur blanc
                        si ça coince.
                    </p>

                    <h3 class="font-heading text-lg font-bold text-slate-900 pt-2">Mer, marais, lac : trois terrains, trois règles</h3>
                    <p>
                        Tu ne pagaies pas pareil à <strong>Port Olona</strong> qu'à l'<strong>Embarcadère des Salines</strong>.
                        En mer, la houle et le vent de terre sont tes deux ennemis. Dans le marais, c'est la marée qui commande :
                        rater la fenêtre de pleine mer, c'est finir à pied dans la vase. Sur le <strong>lac de Tanchet</strong>,
                        seul le vent compte — pas de marée, pas de houle, un terrain idéal pour progresser sans stress.
                    </p>

                    <div class="tape-photo w-full h-64 my-2">
                        <span class="tape" aria-hidden="true"></span>
                        <div class="tape-photo-inner rounded-lg border border-slate-200 shadow-sm">
                            <img src="assets/kayak2.jpg" alt="Kayak posé sur les rochers aux Sables d'Olonne" class="parallax-img absolute inset-0 w-full h-full object-cover" loading="lazy">
                        </div>
                    </div>

                    <h3 class="font-heading text-lg font-bold text-slate-900 pt-2">La sécurité avant la sortie, pas pendant</h3>
                    <ul class="space-y-1 list-none pl-0">
                        <li>☐ Vérifie le sens du vent avant de mettre le kayak à l'eau.</li>
                        <li>☐ Regarde la fenêtre de marée si tu vises le marais ou l'estuaire du Payré.</li>
                        <li>☐ Ne sors jamais si un orage est annoncé — <mark class="bg-yellow-200 px-1 rounded">la foudre ne pardonne pas sur l'eau</mark>.</li>
                    </ul>
                    <p>
                        Ces trois réflexes, c'est ce que <strong><?= htmlspecialchars($siteName) ?></strong> calcule à ta place,
                        heure par heure, pour les Sables d'Olonne et Brem-sur-Mer.
                    </p>

                    <h3 class="font-heading text-lg font-bold text-slate-900 pt-2">Consulte, pagaie, reviens</h3>
                    <p>
                        Ouvre l'app avant de partir. Regarde le score du jour. Si c'est vert, file à l'eau. Si c'est orange,
                        ajuste ton itinéraire vers un spot plus abrité. Si c'est rouge, la mer sera toujours là demain.
                    </p>

                    <div class="tape-photo w-full h-64 my-2">
                        <span class="tape" aria-hidden="true"></span>
                        <div class="tape-photo-inner rounded-lg border border-slate-200 shadow-sm">
                            <img src="assets/kaya3.jpg" alt="Kayak au coucher de soleil aux Sables d'Olonne" class="parallax-img absolute inset-0 w-full h-full object-cover" loading="lazy">
                        </div>
                    </div>
                </div>
            </section>

            <section id="spots" class="mt-12 scroll-mt-6 bg-white border border-slate-200 rounded-lg shadow-sm p-6 md:p-8">
                <h2 class="font-heading text-2xl font-bold text-slate-900 mb-2">Tous les spots kayak, secteur par secteur</h2>
                <p class="text-sm text-slate-500 mb-6">
                    <?= count($spots) ?> points de mise à l'eau suivis autour des Sables d'Olonne, de Brem-sur-Mer et de
                    Talmont-Saint-Hilaire, classés par secteur géographique.
                </p>
                <?php
                    $sectorImages = [
                        'sables_coast' => ['src' => 'assets/cote.jpg', 'alt' => 'La côte des Sables d\'Olonne'],
                        'talmont_estuary' => ['src' => 'assets/estuaire.jpg', 'alt' => 'L\'estuaire du Payré à Talmont-Saint-Hilaire'],
                        'brem_north_marais' => ['src' => 'assets/maree.jpg', 'alt' => 'Le marais entre Brem-sur-Mer et Olonne-sur-Mer'],
                        'ile_olonne_marais' => ['src' => 'assets/maree.jpg', 'alt' => 'Le marais au nord de L\'Île-d\'Olonne'],
                    ];
                ?>
                <div class="space-y-8">
                    <?php foreach ($sectors as $sectorId => $sector): ?>
                        <?php $sectorSpots = array_values(array_filter($spots, fn($spot) => $spot['sector'] === $sectorId)); ?>
                        <?php if (empty($sectorSpots)) continue; ?>
                        <?php $sectorImage = $sectorImages[$sectorId] ?? null; ?>
                        <div class="flex flex-col sm:flex-row gap-4">
                            <?php if ($sectorImage): ?>
                                <div class="tape-photo w-full h-40 sm:w-48 shrink-0">
                                    <span class="tape" aria-hidden="true"></span>
                                    <div class="tape-photo-inner rounded-lg border border-slate-200 shadow-sm">
                                        <img src="<?= $sectorImage['src'] ?>" alt="<?= htmlspecialchars($sectorImage['alt']) ?>" class="parallax-img absolute inset-0 w-full h-full object-cover" loading="lazy">
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h3 class="font-heading text-lg font-bold text-slate-900"><?= htmlspecialchars($sector['name']) ?></h3>
                                <p class="text-sm text-slate-600 mt-1 mb-3"><?= htmlspecialchars($sector['description'] ?? '') ?></p>
                                <ul class="space-y-2 text-sm text-slate-600">
                                    <?php foreach ($sectorSpots as $spot): ?>
                                        <li>
                                            <strong class="text-slate-900"><?= htmlspecialchars($spot['name']) ?></strong>
                                            <span class="text-slate-400">— <?= htmlspecialchars($spot['zone']) ?> —</span>
                                            <?= htmlspecialchars($spot['rando']) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section id="faq" class="mt-12 mb-4 scroll-mt-6">
                <h2 class="font-heading text-2xl font-bold text-slate-900 mb-2">Questions fréquentes pour le kayak aux Sables-d'Olonne</h2>
                <p class="text-sm text-slate-500 mb-6">Ce que tu dois savoir pour lire les créneaux et sortir en sécurité.</p>
                <div class="space-y-2">
                    <?php foreach ($faqItems as $item): ?>
                        <details class="group bg-white border border-slate-200 rounded-lg shadow-sm">
                            <summary class="flex items-center justify-between gap-3 p-4 cursor-pointer select-none list-none font-heading font-bold text-slate-900">
                                <span><?= htmlspecialchars($item['question']) ?></span>
                                <svg class="h-5 w-5 text-slate-400 shrink-0 transition-transform duration-200 group-open:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            </summary>
                            <p class="px-4 pb-4 text-sm text-slate-600 leading-relaxed"><?= htmlspecialchars($item['answer']) ?></p>
                        </details>
                    <?php endforeach; ?>
                </div>
            </section>

        </main>

        <footer class="text-center mt-12 text-xs text-slate-400 space-y-1">
            <p>Données météo fournies par <a href="https://open-meteo.com/" target="_blank" class="underline">Open-Meteo</a>.</p>
            <p>Réalisé par <a href="https://weclain.com" target="_blank" class="underline">weclain</a> avec ❤</p>
        </footer>

        <!-- Panneau d'info (clic/tap sur une icône de statut) -->
        <div id="info-sheet-backdrop" class="fixed inset-0 bg-black/30 z-40 hidden"></div>
        <div id="info-sheet" class="fixed inset-x-0 bottom-0 z-50 translate-y-full transition-transform duration-300 ease-out">
            <div class="bg-white rounded-t-2xl shadow-2xl max-h-[70vh] overflow-y-auto border-t border-slate-200 mx-auto max-w-4xl">
                <div class="flex items-center justify-between p-4 border-b border-slate-100 sticky top-0 bg-white">
                    <h3 id="info-sheet-title" class="font-heading font-bold text-base text-slate-900"></h3>
                    <button type="button" id="info-sheet-close" class="text-slate-400 hover:text-slate-600 p-1" aria-label="Fermer">
                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div id="info-sheet-content" class="p-4 space-y-3 text-sm text-slate-600"></div>
            </div>
        </div>

        <!-- Retour en haut de page -->
        <button type="button" id="back-to-top" aria-label="Retour en haut de la page" class="fixed bottom-6 right-6 z-30 hidden h-11 w-11 items-center justify-center rounded-full bg-ocean-brand text-white shadow-lg hover:bg-ocean-action transition-colors">
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
        </button>

        <!-- Données pour la carte et le panneau d'info -->
        <script>
            const mapData = <?= json_encode(array_values($spotsForecast)); ?>;
            const reasonDescriptions = <?= json_encode(REASON_DESCRIPTIONS); ?>;

            document.addEventListener('DOMContentLoaded', function () {
                if (mapData.length > 0) {
                    const map = L.map('map').setView([46.50, -1.78], 11);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '© OpenStreetMap contributors'
                    }).addTo(map);

                    const iconMapping = {
                        green: 'assets/green.jpg',
                        orange: 'assets/orange.jpg',
                        grey: 'assets/grey.jpg',
                        red: 'assets/red.jpg'
                    };

                    mapData.forEach(data => {
                        const spot = data.spot;
                        const status = data.current_status.status;
                        const reasons = data.current_status.reasons;

                        const icon = L.divIcon({
                            className: 'custom-div-icon',
                            html: `<img src="${iconMapping[status]}" style="width:24px;height:24px;border-radius:9999px;border:2px solid white;box-shadow:0 1px 3px rgba(0,0,0,0.3);object-fit:cover;">`,
                            iconSize: [24, 24],
                            iconAnchor: [12, 12]
                        });

                        L.marker([spot.lat, spot.lng], { icon: icon })
                            .addTo(map)
                            .bindPopup(`<b>${spot.name}</b><br>Conditions: ${status}` + (reasons.length > 0 ? `<br><small>Raison: ${reasons.join(', ')}</small>` : ''));
                    });
                }
            });
        </script>
    </div>
</body>
</html>
