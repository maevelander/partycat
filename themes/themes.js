// Party Cat themes
// Loaded in <head> (not deferred) so the saved theme is applied before the page paints.
// Each theme lives in themes/<id>/theme.css with its artwork alongside it.
(function () {
    var THEMES = [
        { id: 'confetti', name: 'Confetti' },
        { id: 'astro', name: 'Astro Cat' },
        { id: 'loose-thread', name: 'Loose Thread' },
        { id: 'calm', name: 'Calm Cat' }
    ];
    var STORAGE_KEY = 'partycat-theme';
    // Cache-busting version, shared with the ?v= on the <link>/<script> tags in the
    // HTML pages. Bump them all together on each deploy so the CDN serves fresh files.
    var ASSET_VERSION = '2026102701';
    var DEFAULT_THEME = 'confetti';

    function findTheme(id) {
        for (var i = 0; i < THEMES.length; i++) {
            if (THEMES[i].id === id) return THEMES[i];
        }
        return null;
    }

    function savedThemeId() {
        try {
            var saved = localStorage.getItem(STORAGE_KEY);
            if (findTheme(saved)) return saved;
        } catch (e) { /* storage unavailable: use the default */ }
        return DEFAULT_THEME;
    }

    function labelToggle(theme) {
        var button = document.querySelector('.theme-toggle');
        if (!button) return;
        var label = 'Change theme (current: ' + theme.name + ')';
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
    }

    function applyTheme(id) {
        var theme = findTheme(id) || findTheme(DEFAULT_THEME);
        var link = document.getElementById('theme-stylesheet');
        var href = 'themes/' + theme.id + '/theme.css?v=' + ASSET_VERSION;
        if (link && link.getAttribute('href') !== href) {
            link.setAttribute('href', href);
        }
        document.documentElement.setAttribute('data-theme', theme.id);
        labelToggle(theme);
        return theme;
    }

    window.cycleTheme = function () {
        var current = document.documentElement.getAttribute('data-theme') || DEFAULT_THEME;
        var index = 0;
        for (var i = 0; i < THEMES.length; i++) {
            if (THEMES[i].id === current) index = i;
        }
        var next = THEMES[(index + 1) % THEMES.length];
        applyTheme(next.id);
        try {
            localStorage.setItem(STORAGE_KEY, next.id);
        } catch (e) { /* not saved: theme still changes for this visit */ }
    };

    applyTheme(savedThemeId());
    document.addEventListener('DOMContentLoaded', function () {
        labelToggle(findTheme(document.documentElement.getAttribute('data-theme')) || findTheme(DEFAULT_THEME));
    });
})();
