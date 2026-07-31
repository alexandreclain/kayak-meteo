<?php
// index.php - View Layer

require_once 'app.php';

$zoneDisplay = [
    'MER'    => ['label' => 'Mer',    'text' => 'text-blue-600',   'border' => 'border-blue-200'],
    'MARAIS' => ['label' => 'Marais', 'text' => 'text-green-600',  'border' => 'border-green-200'],
    'LAC'    => ['label' => 'Lac',    'text' => 'text-purple-600', 'border' => 'border-purple-200'],
];

$dayHeaderFormatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE, null, null, 'EEE');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayak Météo</title>
    
    <!-- Favicons & Manifest -->
    <link rel="icon" href="favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
    <link rel="manifest" href="site.webmanifest">
    <meta name="theme-color" content="#ffffff">

    <!-- Le script de Tailwind CSS (via CDN) est indispensable. Il analyse votre HTML et génère les styles à la volée. -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- NOUVEAU: Leaflet.js pour la carte -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    
    <!-- CSS externe -->
    <link rel="stylesheet" href="assets/style.css">
    <!-- JS externe -->
    <script src="assets/script.js" defer></script>
    <style>
        /* Hauteur pour la carte Leaflet */
        #map { height: 400px; }
    </style>
</head>
<body class="bg-white text-kayak-blue">

    <div class="container mx-auto p-4 md:p-6 max-w-4xl">

        <header class="mb-8 text-center">
            <h1 class="text-3xl md:text-4xl font-bold text-kayak-blue">Kayak Météo</h1>
            <p class="text-slate-500">Les meilleurs créneaux pour vos sorties aux Sables d'Olonne</p>
        </header>

        <main>
            <section class="mb-12">
                <h2 class="text-2xl font-bold mb-4">Carte des conditions actuelles</h2>
                <div id="map" class="w-full rounded-lg shadow-md z-0"></div>
            </section>

            <section>
                <h2 class="text-2xl font-bold mb-4">Créneaux favorables</h2>
                <div class="space-y-6">
                    <?php if (empty($slotsByDay)): ?>
                        <div class="bg-kayak-light-blue border border-sky-200 rounded-lg p-5 shadow-md text-center text-slate-600">
                            <p>Aucun créneau favorable trouvé pour les 7 prochains jours.</p>
                            <?php if (!empty($apiErrors)): ?>
                                <div class="text-left text-xs text-red-600 mt-4 bg-red-50 p-3 rounded">
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
                            <div class="bg-kayak-light-blue/50 rounded-lg">
                                <h3 class="font-bold text-lg p-4 border-b border-slate-200">
                                    <?= ucfirst($dateFormatter->format(new DateTime($day))) ?>
                                </h3>
                                <div class="p-4 space-y-3">
                                    <?php foreach ($daySlots as $slot): ?>
                                        <div class="p-4 bg-white rounded-lg shadow-sm">
                                            <div class="flex justify-between items-start">
                                                <p class="font-bold text-lg md:text-xl text-kayak-blue">
                                                    <?= $slot['start']->format('H:i') ?> - <?= $slot['end']->format('H:i') ?>
                                                </p>
                                                <?php if ($slot['indicative']): ?>
                                                    <span class="text-xs font-semibold bg-yellow-200 text-yellow-800 px-2 py-1 rounded-full">Indicatif</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Grille compacte pour les spots -->
                                            <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-3 text-sm">
                                                <?php foreach ($zoneDisplay as $zone => $meta): ?>
                                                    <?php if (!empty($slot['details'][$zone])): ?>
                                                        <div>
                                                            <span class="block font-bold <?= $meta['text'] ?> border-b <?= $meta['border'] ?> mb-1 pb-1"><?= $meta['label'] ?></span>
                                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 text-xs mt-1">
                                                                <?php foreach ($slot['details'][$zone] as $spot): ?>
                                                                    <?php
                                                                        $color = match ($spot['status']) {
                                                                            'green' => 'text-green-700 font-medium',
                                                                            'grey' => 'text-slate-400 italic',
                                                                            default => 'text-orange-600',
                                                                        };
                                                                        $spotName = trim(preg_replace('/\s?\(.*\)/', '', $spot['name']));
                                                                    ?>
                                                                    <span class="<?= $color ?>"><?= htmlspecialchars($spotName) ?></span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>

                                            <!-- MODIFIÉ: Détails météo maintenant visibles par défaut -->
                                            <div class="slot-details mt-4 pt-4 border-t border-slate-200 text-sm">
                                                <?php if (isset($slot['weather'])): 
                                                    $weather = $slot['weather'];
                                                ?>
                                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-slate-700">
                                                        <div class="flex items-center gap-2" title="Vent">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                                                            <span class="font-medium"><?= round($weather['wind_speed']) ?> km/h</span>
                                                        </div>
                                                        <div class="flex items-center gap-2" title="Direction du vent">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 shrink-0 transition-transform duration-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="transform: rotate(<?= $weather['wind_direction'] ?>deg);"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 14l-4-4m4 4l4-4" /></svg>
                                                            <span class="font-medium"><?= $weather['wind_direction'] ?>°</span>
                                                        </div>
                                                        <?php if ($weather['swell_height'] !== null): ?>
                                                        <div class="flex items-center gap-2" title="Houle">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12h2l2-9 2 18 2-9 2 12 2-15 2 10h2"/></svg>
                                                            <span class="font-medium"><?= $weather['swell_height'] ?> m</span>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- MODIFIÉ: Section déplacée en bas -->
            <section class="mt-12">
                <h2 class="text-2xl font-bold mb-4">Synthèse des 7 prochains jours</h2>
                <div class="overflow-x-auto bg-white rounded-lg shadow-md">
                    <table class="min-w-full text-sm text-left">
                        <thead class="bg-kayak-light-blue/60">
                            <tr>
                                <th class="p-3 font-bold">Spot</th>
                                <?php for ($d = 0; $d < 7; $d++): ?>
                                    <?php $headerDay = (new DateTime())->modify("+$d days"); ?>
                                    <th class="p-3 text-center font-bold">
                                        <?= $dayHeaderFormatter->format($headerDay) ?>
                                        <br>
                                        <span class="font-normal text-xs"><?= $headerDay->format('d/m') ?></span>
                                    </th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($spotsForecast as $forecast): ?>
                            <tr class="border-b border-slate-200 last:border-0">
                                <td class="p-3 font-medium"><?= htmlspecialchars($forecast['spot']['name']) ?></td>
                                <?php foreach ($forecast['daily_status'] as $daily_status): ?>
                                    <td class="p-3 text-center" title="<?= htmlspecialchars(implode(', ', $daily_status['reasons'])) ?>">
                                        <?php if ($daily_status['status'] === 'green'): ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mx-auto text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        <?php elseif ($daily_status['status'] === 'orange'): ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mx-auto text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                        <?php elseif ($daily_status['status'] === 'grey'): ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mx-auto text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        <?php else: ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mx-auto text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </main>

        <footer class="text-center mt-12 text-xs text-slate-400">
            <p>Données météo fournies par <a href="https://open-meteo.com/" target="_blank" class="underline">Open-Meteo</a>.</p>
        </footer>

        <!-- NOUVEAU: Données pour la carte -->
        <script>
            const mapData = <?= json_encode(array_values($spotsForecast)); ?>;
            
            document.addEventListener('DOMContentLoaded', function () {
                if (mapData.length > 0) {
                    const map = L.map('map').setView([46.50, -1.78], 11);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '© OpenStreetMap contributors'
                    }).addTo(map);

                    const colorMapping = {
                        green: '#22c55e', // green-500
                        orange: '#f97316', // orange-500
                        grey: '#94a3b8', // slate-400
                        red: '#ef4444' // red-500
                    };

                    mapData.forEach(data => {
                        const spot = data.spot;
                        const status = data.current_status.status;
                        const reasons = data.current_status.reasons;

                        const icon = L.divIcon({
                            className: 'custom-div-icon',
                            html: `<div style='background-color:${colorMapping[status]};' class='w-4 h-4 rounded-full border-2 border-white shadow-md'></div>`,
                            iconSize: [16, 16],
                            iconAnchor: [8, 8]
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
