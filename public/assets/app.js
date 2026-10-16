(() => {
    'use strict';

    const launcher = document.querySelector('[data-apps-launcher]');
    const openButton = document.querySelector('[data-apps-launcher-open]');

    if (!launcher || !openButton) {
        return;
    }

    const sheet = launcher.querySelector('.apps-launcher-sheet');
    const closeButtons = launcher.querySelectorAll('[data-apps-launcher-close]');
    let lastFocus = null;

    const focusable = () => Array.from(launcher.querySelectorAll(
        'a[href], button:not([disabled]):not([tabindex="-1"]), [tabindex]:not([tabindex="-1"])'
    )).filter((element) => !element.hasAttribute('hidden'));

    const openLauncher = () => {
        lastFocus = document.activeElement;
        launcher.hidden = false;
        document.body.classList.add('apps-launcher-open');
        openButton.setAttribute('aria-expanded', 'true');
        window.requestAnimationFrame(() => {
            if (sheet instanceof HTMLElement) {
                sheet.focus();
            }
        });
    };

    const closeLauncher = () => {
        launcher.hidden = true;
        document.body.classList.remove('apps-launcher-open');
        openButton.setAttribute('aria-expanded', 'false');
        if (lastFocus instanceof HTMLElement) {
            lastFocus.focus();
        }
    };

    openButton.addEventListener('click', openLauncher);
    closeButtons.forEach((button) => button.addEventListener('click', closeLauncher));

    launcher.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('click', () => {
            document.body.classList.remove('apps-launcher-open');
            openButton.setAttribute('aria-expanded', 'false');
        });
    });

    document.addEventListener('keydown', (event) => {
        if (launcher.hidden) {
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            closeLauncher();
            return;
        }
        if (event.key !== 'Tab') {
            return;
        }

        const items = focusable();
        if (items.length === 0) {
            return;
        }
        const first = items[0];
        const last = items[items.length - 1];

        if (document.activeElement === sheet) {
            event.preventDefault();
            (event.shiftKey ? last : first).focus();
            return;
        }
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
})();
