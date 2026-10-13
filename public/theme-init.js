/*
 * Applies the saved colour theme before the page paints, so dark mode does
 * not flash white. Loaded as an external file because the CSP forbids inline
 * scripts. Keep in sync with resources/js/theme/ThemeContext.jsx.
 */
(function () {
    var choice = 'system';
    try {
        var saved = localStorage.getItem('pal_market_theme');
        if (saved === 'light' || saved === 'dark' || saved === 'system') choice = saved;
    } catch (e) {}
    var dark = choice === 'dark' || (choice === 'system' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
    var root = document.documentElement;
    root.setAttribute('data-theme', dark ? 'dark' : 'light');
    root.style.colorScheme = dark ? 'dark' : 'light';
    try {
        var lang = localStorage.getItem('pal_market_locale');
        if (lang === 'sw' || lang === 'en') root.lang = lang;
    } catch (e) {}
})();
