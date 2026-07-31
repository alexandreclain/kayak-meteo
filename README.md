# Kayak Météo

Application PHP qui identifie les créneaux favorables pour faire du kayak aux Sables d'Olonne (mer, marais, lac), à partir de la météo, de la houle et des marées.

## Fonctionnement

- Les spots et secteurs géographiques sont définis dans [`config.php`](config.php).
- Les données météo/marée viennent d'[Open-Meteo](https://open-meteo.com/) (`services/weather.php`), avec un repli optionnel sur [Stormglass](https://stormglass.io/) si la houle est manquante.
- Les règles de sécurité par zone (mer/marais/lac) sont dans `services/rules.php`.
- L'agrégation des créneaux et la synthèse 7 jours sont dans `services/aggregator.php`.
- La vue est `index.php`, orchestrée par `app.php`.

## Installation

1. Copier `.env.example` en `.env` et renseigner les clés API optionnelles :
   ```bash
   cp .env.example .env
   ```
   Seule `OPENWEATHER_API_KEY` / `STORMGLASS_API_KEY` sont utilisées actuellement, et uniquement en repli si Open-Meteo ne fournit pas la houle. L'application fonctionne sans aucune clé.

2. Lancer un serveur PHP local :
   ```bash
   php -S localhost:8000
   ```

3. Ouvrir `http://localhost:8000`.

## Cache

Les réponses météo sont mises en cache dans `cache/` (1h par défaut, voir `CACHE_DURATION` dans `config.php`). Ce dossier n'est pas versionné.
