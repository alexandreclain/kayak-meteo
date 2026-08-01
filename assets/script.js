tailwind.config = {
    theme: {
        extend: {
            colors: {
                ocean: {
                    50: '#F8FAFC',
                    100: '#F1F5F9',
                    brand: '#0369A1',
                    action: '#0891B2',
                },
                status: {
                    safe: '#059669',
                    warning: '#D97706',
                    danger: '#DC2626',
                },
            },
            fontFamily: {
                heading: ['Outfit', 'sans-serif'],
                body: ['Plus Jakarta Sans', 'sans-serif'],
            },
        },
    },
};

if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js').catch(function (error) {
            console.warn('Service worker non enregistré :', error);
        });
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const sheet = document.getElementById('info-sheet');
    const backdrop = document.getElementById('info-sheet-backdrop');
    const titleEl = document.getElementById('info-sheet-title');
    const contentEl = document.getElementById('info-sheet-content');
    const closeBtn = document.getElementById('info-sheet-close');

    if (!sheet || !backdrop || !titleEl || !contentEl || !closeBtn) return;

    function appendWeatherBlock(weatherHtml) {
        if (!weatherHtml) return;

        const wrapper = document.createElement('div');
        wrapper.className = 'pt-3 mt-1 border-t border-slate-100';
        wrapper.innerHTML = weatherHtml;

        contentEl.appendChild(wrapper);
    }

    function openInfoSheet(title, status, reasons, weatherHtml) {
        titleEl.textContent = title;
        contentEl.innerHTML = '';

        if (status === 'green') {
            const p = document.createElement('p');
            p.textContent = 'Conditions favorables, aucun risque identifié pour ce créneau.';
            contentEl.appendChild(p);
        } else if (!reasons || reasons.length === 0) {
            const p = document.createElement('p');
            p.textContent = "Aucune information disponible pour ce créneau — vérifiez les conditions par un autre moyen avant de partir.";
            contentEl.appendChild(p);
        } else {
            reasons.forEach(function (reason) {
                const block = document.createElement('div');
                const label = document.createElement('p');
                label.className = 'font-semibold text-slate-800';
                label.textContent = reason;
                const desc = document.createElement('p');
                desc.textContent = (typeof reasonDescriptions !== 'undefined' && reasonDescriptions[reason])
                    ? reasonDescriptions[reason]
                    : 'Condition à vérifier sur place avant de partir.';
                block.appendChild(label);
                block.appendChild(desc);
                contentEl.appendChild(block);
            });
        }

        appendWeatherBlock(weatherHtml);

        sheet.classList.remove('translate-y-full');
        backdrop.classList.remove('hidden');
    }

    function closeInfoSheet() {
        sheet.classList.add('translate-y-full');
        backdrop.classList.add('hidden');
    }

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('.info-trigger');
        if (trigger) {
            const reasons = trigger.dataset.reasons ? JSON.parse(trigger.dataset.reasons) : [];
            openInfoSheet(trigger.dataset.title || '', trigger.dataset.status || '', reasons, trigger.dataset.weatherHtml || '');
        }
    });

    closeBtn.addEventListener('click', closeInfoSheet);
    backdrop.addEventListener('click', closeInfoSheet);
});

document.addEventListener('DOMContentLoaded', function () {
    const backToTop = document.getElementById('back-to-top');
    if (!backToTop) return;

    function toggleBackToTop() {
        const show = window.scrollY > 480;
        backToTop.classList.toggle('hidden', !show);
        backToTop.classList.toggle('flex', show);
    }

    window.addEventListener('scroll', toggleBackToTop, { passive: true });
    toggleBackToTop();

    backToTop.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const images = document.querySelectorAll('.parallax-img');
    if (!images.length) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    let ticking = false;

    function updateParallax() {
        const viewportHeight = window.innerHeight;

        images.forEach(function (img) {
            const rect = img.getBoundingClientRect();
            const center = rect.top + rect.height / 2;
            const progress = (center - viewportHeight / 2) / (viewportHeight / 2 + rect.height / 2);
            const offset = Math.max(-1, Math.min(1, progress)) * 22;
            img.style.setProperty('--parallax-offset', offset.toFixed(1) + 'px');
        });

        ticking = false;
    }

    function onScroll() {
        if (!ticking) {
            window.requestAnimationFrame(updateParallax);
            ticking = true;
        }
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll);
    updateParallax();
});

function fetchKayakSpots(map) {
    const overpassMirrors = [
        'https://overpass-api.de/api/interpreter',
        'https://overpass.kumi.systems/api/interpreter',
        'https://overpass.openstreetmap.fr/api/interpreter',
        'https://overpass.openstreetmap.ru/api/interpreter'
    ];
    const mirrorTimeoutMs = 12000;
    const bbox = '46.4000,-1.8800,46.6300,-1.5900';
    const query = `[out:json][timeout:25];
(
  node["leisure"="slipway"](${bbox});
  way["leisure"="slipway"](${bbox});
  node["waterway"="access_point"](${bbox});
  node["canoe"="put_in"](${bbox});
  node["canoe"~"^(yes|designated|permissive)$"](${bbox});
  way["canoe"~"^(yes|designated|permissive)$"](${bbox});
  node["sport"="canoe"](${bbox});
  way["sport"="canoe"](${bbox});
);
out body;
>;
out skel qt;`;

    const kayakSpotIcon = L.divIcon({
        className: 'custom-div-icon',
        html: '<img src="assets/blue.jpg" style="width:22px;height:22px;border-radius:9999px;border:2px solid white;box-shadow:0 1px 3px rgba(3,105,161,0.5);object-fit:cover;">',
        iconSize: [22, 22],
        iconAnchor: [11, 11]
    });

    function queryMirror(index) {
        if (index >= overpassMirrors.length) {
            return Promise.reject(new Error('Tous les serveurs Overpass ont échoué ou sont trop lents.'));
        }

        const mirrorUrl = overpassMirrors[index];
        const controller = new AbortController();
        const timeoutId = setTimeout(function () { controller.abort(); }, mirrorTimeoutMs);

        return fetch(mirrorUrl, {
            method: 'POST',
            body: 'data=' + encodeURIComponent(query),
            signal: controller.signal
        })
            .then(function (response) {
                clearTimeout(timeoutId);
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .catch(function (error) {
                clearTimeout(timeoutId);
                console.warn('Serveur Overpass indisponible (' + mirrorUrl + ') : ' + error.message + ' — tentative avec le miroir suivant...');
                return queryMirror(index + 1);
            });
    }

    return queryMirror(0)
        .then(function (data) {
            const elements = (data && data.elements) || [];
            if (elements.length === 0) {
                console.info('Aucune cale de mise à l\'eau OSM trouvée sur la zone des Sables-d\'Olonne.');
                return;
            }

            const nodesById = {};
            elements.forEach(function (el) {
                if (el.type === 'node') nodesById[el.id] = el;
            });

            let addedCount = 0;

            elements.forEach(function (el) {
                let lat, lon;

                if (el.type === 'node' && el.tags) {
                    lat = el.lat;
                    lon = el.lon;
                } else if (el.type === 'way' && el.tags && Array.isArray(el.nodes)) {
                    const points = el.nodes.map(function (id) { return nodesById[id]; }).filter(Boolean);
                    if (points.length === 0) return;
                    lat = points.reduce(function (sum, p) { return sum + p.lat; }, 0) / points.length;
                    lon = points.reduce(function (sum, p) { return sum + p.lon; }, 0) / points.length;
                } else {
                    return;
                }

                const tags = el.tags || {};
                const name = tags.name || 'Cale de mise à l\'eau';
                const accessType = tags.leisure || tags.waterway || tags.canoe || tags.sport || 'Accès kayak';
                const surface = tags.surface ? '<br>Surface : ' + tags.surface : '';

                L.marker([lat, lon], { icon: kayakSpotIcon })
                    .addTo(map)
                    .bindPopup('<b>' + name + '</b><br>Type : ' + accessType + surface);

                addedCount++;
            });

            console.info(addedCount + ' cale(s)/accès kayak OSM ajouté(s) à la carte.');
        })
        .catch(function (error) {
            console.warn('Impossible de récupérer les cales de mise à l\'eau OSM :', error);
        });
}
