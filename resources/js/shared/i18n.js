import { ref } from 'vue';

/* The dictionary is split by audience, the same way pages/ is: i18n-public.js
   for the visit card, i18n-admin.js for the workspace. Only the few strings
   both halves use live here.

   Neither is imported from this file. They are handed in by the entry point —
   app-public.js registers one, app-admin.js the other — because importing
   both would put all 493 workspace strings in the public bundle, where a
   visitor can read "Two-step sign-in" and "Recovery codes" out of it. That is
   the same leak the two bundles exist to close (see vite.config.js).

   Once registered, copy() and t() work exactly as before and no component
   knows about the split. */
const sharedUi = {
    en: {
        loading: 'Loading...',
        admin: 'Admin',
        expertise: 'Expertise',
        error: 'Could not save changes. Please check the fields and try again.',
        sharedInfo: 'These values are the same in both languages, so they are edited once here rather than twice on every other tab.',
        sharedHighlightsHint: 'Words from the headline to accent, comma separated. Put both languages\u2019 spellings in one list — only the words in the headline currently on screen can match.',
        preview: 'Preview',
        themeSwitch: 'Theme',
        themeLight: 'Light',
        themeDark: 'Dark',
    },

    nl: {
        loading: 'Laden...',
        admin: 'Admin',
        expertise: 'Expertise',
        error: 'Opslaan is niet gelukt. Controleer de velden en probeer opnieuw.',
        sharedInfo: 'Deze waarden zijn in beide talen hetzelfde en worden daarom hier \u00e9\u00e9n keer ingesteld in plaats van op elk ander tabblad twee keer.',
        sharedHighlightsHint: 'Woorden uit de kop die een accentkleur krijgen, gescheiden door komma\u2019s. Zet de spelling van beide talen in \u00e9\u00e9n lijst — alleen woorden uit de kop die nu op het scherm staat kunnen matchen.',
        preview: 'Voorbeeld',
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
 * Adds one half's strings to the dictionary. Called by the entry point before
 * the app mounts, so every copy() during render already sees them.
 *
 * Tests get both halves from resources/tests/setup.js, which mounts
 * components directly and so never runs an entry point.
 */
export const registerUi = (dictionary) => {
    Object.assign(ui.en, dictionary.en);
    Object.assign(ui.nl, dictionary.nl);
};


// Every language the app knows about, in the order they're offered.
export const LANGUAGES = [
    { value: 'en', label: 'English' },
    { value: 'nl', label: 'Nederlands' },
];

// Nothing in this app is tied to one person's initials — the profile is
// seeded and then edited, so a storage key naming a particular owner would
// survive a fork that changed everything else.
// The public page is published at one URL per language: the default language
// keeps the bare path and the others get a prefix. Mirrors config('app.locales')
// and the route constraint in routes/web.php — keep the three in step.
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

// Reads the retired key once so a returning visitor keeps the language they
// chose. Safe to delete once no browser can still be holding the old one.
const storedLanguage = () => localStorage.getItem(STORAGE_KEY) ?? localStorage.getItem(LEGACY_STORAGE_KEY);

// Module-level singleton: the language toggle is global site state, shared
// by whichever page (public/admin) happens to be mounted.
// The URL wins over a stored choice: it is what was shared, linked and
// indexed, so a visitor opening /nl must get Dutch whatever this browser
// happens to remember.
export const lang = ref(localeFromPath() || storedLanguage() || 'en');

export const setLang = (value) => {
    lang.value = value;
    localStorage.setItem(STORAGE_KEY, value);
};

// The switcher is one on/off setting (admin Language tab). Off means the
// site runs in the default language only, so there is nothing to switch.
export const languageSwitcherShown = (profile) => profile?.show_language_toggle !== false;

// Settles `lang` against the profile's policy: with the switcher off the
// default wins outright — a stored choice can't be honoured when there's no
// control left to change it back. Otherwise a first-time visitor starts on
// the default and a returning one keeps their choice.
export const applyLanguagePolicy = (profile) => {
    const fallback = profile?.default_language || 'en';

    // A language in the URL is not a preference to be overruled — it is the
    // page that was asked for.
    if (localeFromPath()) {
        return;
    }

    if (!languageSwitcherShown(profile) || !storedLanguage()) {
        // Assign rather than setLang: persisting here would make the default
        // indistinguishable from a deliberate choice on the next visit, so a
        // later change to default_language would never reach anyone who had
        // already visited, and switching the toggle off then on again would
        // have destroyed the visitor's real choice.
        lang.value = fallback;
    }
};

/**
 * Where this visitor belongs, or null when the URL is already right.
 *
 * A stored choice redirects rather than quietly painting a language the URL
 * does not claim — otherwise the server would serve English meta while the
 * body rendered Dutch, and the address bar would agree with neither.
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
