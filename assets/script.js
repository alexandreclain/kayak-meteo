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

// Panneau d'info : ouvre au clic/tap sur une icône de statut, avec le détail des raisons.
document.addEventListener('DOMContentLoaded', function () {
    const sheet = document.getElementById('info-sheet');
    const backdrop = document.getElementById('info-sheet-backdrop');
    const titleEl = document.getElementById('info-sheet-title');
    const contentEl = document.getElementById('info-sheet-content');
    const closeBtn = document.getElementById('info-sheet-close');

    if (!sheet || !backdrop || !titleEl || !contentEl || !closeBtn) return;

    function openInfoSheet(title, reasons) {
        titleEl.textContent = title;
        contentEl.innerHTML = '';

        if (!reasons || reasons.length === 0) {
            const p = document.createElement('p');
            p.textContent = 'Conditions favorables, aucun risque identifié pour ce créneau.';
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
            openInfoSheet(trigger.dataset.title || '', reasons);
        }
    });

    closeBtn.addEventListener('click', closeInfoSheet);
    backdrop.addEventListener('click', closeInfoSheet);
});