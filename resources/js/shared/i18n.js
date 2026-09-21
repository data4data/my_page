import { ref } from 'vue';

/* Split by audience, like pages/: i18n-public.js and i18n-admin.js, with only
   the strings both halves use here. Neither is imported — each entry point
   registers its own, or the public bundle would carry every workspace string
   for a visitor to read. */
const sharedUi = {
    en: {
        loading: 'Loading...',
        admin: 'Admin',
        expertise: 'Expertise',
        error: 'Could not save changes. Please check the fields and try again.',
        errorOffline: 'Could not reach the server. Check your connection and try again.',
        errorSessionExpired: 'Your session expired. Sign in again and retry.',
        sharedInfo: 'These values are the same in both languages, so they are edited once here rather than twice on every other tab.',
        sharedHighlightsHint: 'Words from the headline to accent, comma separated. Put both languages\u2019 spellings in one list — only the words in the headline currently on screen can match.',
        preview: 'Preview',
        retry: 'Try again',
        skipToContent: 'Skip to content',
        themeSwitch: 'Theme',
        themeLight: 'Light',
        themeDark: 'Dark',
    },

    nl: {
        loading: 'Laden...',
        admin: 'Admin',
        expertise: 'Expertise',
        error: 'Opslaan is niet gelukt. Controleer de velden en probeer opnieuw.',
        errorOffline: 'De server is niet bereikbaar. Controleer je verbinding en probeer opnieuw.',
        errorSessionExpired: 'Je sessie is verlopen. Log opnieuw in en probeer het nog eens.',
        sharedInfo: 'Deze waarden zijn in beide talen hetzelfde en worden daarom hier \u00e9\u00e9n keer ingesteld in plaats van op elk ander tabblad twee keer.',
        sharedHighlightsHint: 'Woorden uit de kop die een accentkleur krijgen, gescheiden door komma\u2019s. Zet de spelling van beide talen in \u00e9\u00e9n lijst — alleen woorden uit de kop die nu op het scherm staat kunnen matchen.',
        preview: 'Voorbeeld',
        retry: 'Opnieuw proberen',
        skipToContent: 'Naar de inhoud',
        themeSwitch: 'Thema',
        themeLight: 'Licht',
        themeDark: 'Donker',
    },
};

export const ui = {
    en: { ...sharedUi.en },
    nl: { ...sharedUi.nl },
};

/**
 * Called by the entry point before the app mounts, so every copy() during
 * render already sees the strings. Tests register both halves in setup.js.
 */
export const registerUi = (dictionary) => {
    Object.assign(ui.en, dictionary.en);
    Object.assign(ui.nl, dictionary.nl);
};


export const LANGUAGES = [
    { value: 'en', label: 'English' },
    { value: 'nl', label: 'Nederlands' },
];

// One URL per language: the default keeps the bare path, the others get a
// prefix. Mirrors config('app.locales') and the route constraint in
// routes/web.php — keep the three in step.
const LOCALE_PATTERN = /^\/(en|nl)(?=\/|$)/;

/** The language this URL names, or null for the bare path. */
export const localeFromPath = (pathname = window.location.pathname) => pathname.match(LOCALE_PATTERN)?.[1] ?? null;

/** The same path with the language taken off: /nl/hi-developer -> /hi-developer. */
export const barePath = (pathname = window.location.pathname) => pathname.replace(LOCALE_PATTERN, '') || '/';

/** Where this page lives in `locale`. The default language has no prefix. */
export const pathForLocale = (locale, defaultLocale, pathname = window.location.pathname) => {
    const bare = barePath(pathname);

    return locale === defaultLocale ? bare : `/${locale}${bare === '/' ? '' : bare}`;
};

const STORAGE_KEY = 'site-language';
const LEGACY_STORAGE_KEY = 'oa-language';

// Reads the retired key too, so a returning visitor keeps their language.
const storedLanguage = () => localStorage.getItem(STORAGE_KEY) ?? localStorage.getItem(LEGACY_STORAGE_KEY);

// A module-level singleton. The URL wins over a stored choice: it is what was
// shared and indexed, so /nl must be Dutch whatever this browser remembers.
export const lang = ref(localeFromPath() || storedLanguage() || 'en');

export const setLang = (value) => {
    lang.value = value;
    localStorage.setItem(STORAGE_KEY, value);
};

// Off means the site runs in the default language only.
export const languageSwitcherShown = (profile) => profile?.show_language_toggle !== false;

// With the switcher off the default wins outright: a stored choice cannot be
// honoured when there is no control left to change it back.
export const applyLanguagePolicy = (profile) => {
    const fallback = profile?.default_language || 'en';

    // A language in the URL is the page that was asked for, not a preference.
    if (localeFromPath()) {
        return;
    }

    if (!languageSwitcherShown(profile) || !storedLanguage()) {
        // Assign, not setLang: persisting would make the default
        // indistinguishable from a deliberate choice on the next visit.
        lang.value = fallback;
    }
};

/**
 * Where this visitor belongs, or null when the URL is already right. A stored
 * choice redirects rather than painting a language the URL does not claim,
 * which would leave the server's meta and the rendered body disagreeing.
 */
export const preferredPath = (profile, pathname = window.location.pathname) => {
    const fallback = profile?.default_language || 'en';
    const stored = storedLanguage();

    if (localeFromPath(pathname) || !languageSwitcherShown(profile) || !stored || stored === fallback) {
        return null;
    }

    return pathForLocale(stored, fallback, pathname);
};

// UI chrome string for the current language.
export const copy = (key) => ui[lang.value][key] ?? ui.en[key] ?? key;

// {en, nl} content field -> string for the current language.
export const t = (value) => {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
        return value[lang.value] || value.en || value.nl || '';
    }

    return value ?? '';
};
