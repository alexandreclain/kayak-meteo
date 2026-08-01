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

// PWA : enregistre le service worker pour rendre l'app installable
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js').catch(function (error) {
            console.warn('Service worker non enregistré :', error);
        });
    });
}

// Panneau d'info : ouvre au clic/tap sur une icône de statut, avec le détail des raisons
// et, quand disponible, le détail météo complet de ce créneau.
document.addEventListener('DOMContentLoaded', function () {
    const sheet = document.getElementById('info-sheet');
    const backdrop = document.getElementById('info-sheet-backdrop');
    const titleEl = document.getElementById('info-sheet-title');
    const contentEl = document.getElementById('info-sheet-content');
    const closeBtn = document.getElementById('info-sheet-close');

    if (!sheet || !backdrop || !titleEl || !contentEl || !closeBtn) return;

    function appendWeatherBlock(weatherItems) {
        if (!weatherItems || weatherItems.length === 0) return;

        const wrapper = document.createElement('div');
        wrapper.className = 'grid grid-cols-2 gap-2 pt-3 mt-1 border-t border-slate-100';

        weatherItems.forEach(function (item) {
            const cell = document.createElement('div');
            cell.className = 'flex items-center gap-1.5 text-xs text-slate-600';
            cell.title = item.label;
            cell.innerHTML = '<span>' + item.icon + '</span><span>' + item.value + '</span>';
            wrapper.appendChild(cell);
        });

        contentEl.appendChild(wrapper);
    }

    function openInfoSheet(title, status, reasons, weatherItems) {
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

        appendWeatherBlock(weatherItems);

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
            const weatherItems = trigger.dataset.weather ? JSON.parse(trigger.dataset.weather) : [];
            openInfoSheet(trigger.dataset.title || '', trigger.dataset.status || '', reasons, weatherItems);
        }
    });

    closeBtn.addEventListener('click', closeInfoSheet);
    backdrop.addEventListener('click', closeInfoSheet);
});

// Bouton "retour en haut" : apparaît après un peu de défilement, scroll fluide au clic.
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

// Effet de parallaxe léger sur les photos du site : chaque image glisse doucement selon sa
// position à l'écran pendant le défilement. Désactivé si l'utilisateur préfère moins de mouvement.
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
