import { beforeEach, describe, expect, it, vi } from 'vitest';

// i18n keeps `lang` as a module-level singleton, so every test needs a fresh
// module registry rather than a shared one carrying state between cases.
const loadI18n = async () => {
    vi.resetModules();

    return import('./i18n.js');
};

beforeEach(() => {
    localStorage.clear();
});

describe('applyLanguagePolicy', () => {
    it('starts a first-time visitor on the profile default', async () => {
        const { applyLanguagePolicy, lang } = await loadI18n();

        applyLanguagePolicy({ default_language: 'nl', show_language_toggle: true });

        expect(lang.value).toBe('nl');
    });

    it('does not persist the default, so later default changes still reach the visitor', async () => {
        const { applyLanguagePolicy } = await loadI18n();

        applyLanguagePolicy({ default_language: 'nl', show_language_toggle: true });

        // Writing the default here would make it indistinguishable from a
        // deliberate user choice on the next visit, permanently pinning the
        // visitor to whatever the default happened to be the first time.
        expect(localStorage.getItem('site-language')).toBeNull();
    });

    it('keeps a returning visitor on their own choice', async () => {
        localStorage.setItem('site-language', 'nl');

        const { applyLanguagePolicy, lang } = await loadI18n();

        applyLanguagePolicy({ default_language: 'en', show_language_toggle: true });

        expect(lang.value).toBe('nl');
    });

    it('lets the default override a stored choice when the switcher is off', async () => {
        localStorage.setItem('site-language', 'nl');

        const { applyLanguagePolicy, lang } = await loadI18n();

        applyLanguagePolicy({ default_language: 'en', show_language_toggle: false });

        expect(lang.value).toBe('en');
    });

    it('preserves a stored choice through the switcher being turned off and back on', async () => {
        localStorage.setItem('site-language', 'nl');

        const off = await loadI18n();
        off.applyLanguagePolicy({ default_language: 'en', show_language_toggle: false });

        const on = await loadI18n();
        on.applyLanguagePolicy({ default_language: 'en', show_language_toggle: true });

        expect(on.lang.value).toBe('nl');
    });
});

describe('setLang', () => {
    it('persists an explicit user choice', async () => {
        const { setLang, lang } = await loadI18n();

        setLang('nl');

        expect(lang.value).toBe('nl');
        expect(localStorage.getItem('site-language')).toBe('nl');
    });

    // The key used to name one particular owner. A visitor who chose a
    // language before the rename keeps it rather than being reset.
    it('still honours a choice stored under the retired key', async () => {
        localStorage.setItem('oa-language', 'nl');

        const { applyLanguagePolicy, lang } = await loadI18n();

        applyLanguagePolicy({ default_language: 'en', show_language_toggle: true });

        expect(lang.value).toBe('nl');
    });
});

// The dictionary is split across i18n-public.js, i18n-admin.js and the shared
// block in i18n.js. copy() falls back to English when a key is missing from
// Dutch, so a dropped or mistyped Dutch key ships silently and simply reads in
// the wrong language. These two check what the fallback would otherwise hide.
describe('the en and nl dictionaries agree', () => {
    it('holds the same keys in both languages', async () => {
        const { ui } = await loadI18n();

        expect(Object.keys(ui.nl).sort()).toEqual(Object.keys(ui.en).sort());
    });

    it('leaves no string empty', async () => {
        const { ui } = await loadI18n();

        for (const language of ['en', 'nl']) {
            for (const [key, value] of Object.entries(ui[language])) {
                expect(value, `${language}.${key}`).not.toBe('');
            }
        }
    });
});

// One URL per language. These helpers decide what that URL is; the same rule
// is implemented server-side in PortfolioController::alternates(), and the
// route constraint in routes/web.php has to allow the same set.
describe('language in the URL', () => {
    it('reads the language off the path, and only a whole segment', async () => {
        const { localeFromPath } = await loadI18n();

        expect(localeFromPath('/nl')).toBe('nl');
        expect(localeFromPath('/nl/hi-developer')).toBe('nl');
        expect(localeFromPath('/en')).toBe('en');
        expect(localeFromPath('/')).toBeNull();
        expect(localeFromPath('/hi-developer')).toBeNull();
        // Not a language prefix, just a path that happens to start with those
        // two letters.
        expect(localeFromPath('/news')).toBeNull();
        expect(localeFromPath('/enterprise')).toBeNull();
    });

    it('strips the language back off', async () => {
        const { barePath } = await loadI18n();

        expect(barePath('/nl')).toBe('/');
        expect(barePath('/nl/hi-developer')).toBe('/hi-developer');
        expect(barePath('/hi-developer')).toBe('/hi-developer');
        expect(barePath('/')).toBe('/');
    });

    it('gives the default language the bare path and the other a prefix', async () => {
        const { pathForLocale } = await loadI18n();

        expect(pathForLocale('en', 'en', '/nl')).toBe('/');
        expect(pathForLocale('nl', 'en', '/')).toBe('/nl');
        expect(pathForLocale('nl', 'en', '/hi-developer')).toBe('/nl/hi-developer');
        expect(pathForLocale('en', 'en', '/nl/hi-developer')).toBe('/hi-developer');
    });

    it('follows whichever language is default, not whichever is English', async () => {
        const { pathForLocale } = await loadI18n();

        expect(pathForLocale('nl', 'nl', '/en')).toBe('/');
        expect(pathForLocale('en', 'nl', '/')).toBe('/en');
    });

    it('sends a remembered choice to that language’s own URL', async () => {
        localStorage.setItem('site-language', 'nl');

        const { preferredPath } = await loadI18n();

        expect(preferredPath({ default_language: 'en', show_language_toggle: true }, '/')).toBe('/nl');
    });

    it('leaves a URL that already names a language alone', async () => {
        localStorage.setItem('site-language', 'nl');

        const { preferredPath } = await loadI18n();

        // Following an English link must not bounce the reader to Dutch.
        expect(preferredPath({ default_language: 'en', show_language_toggle: true }, '/en')).toBeNull();
        expect(preferredPath({ default_language: 'en', show_language_toggle: true }, '/nl')).toBeNull();
    });

    it('does not redirect when there is no switcher or no choice', async () => {
        const { preferredPath } = await loadI18n();

        expect(preferredPath({ default_language: 'en', show_language_toggle: true }, '/')).toBeNull();

        localStorage.setItem('site-language', 'nl');
        const again = await loadI18n();
        expect(again.preferredPath({ default_language: 'en', show_language_toggle: false }, '/')).toBeNull();
    });

    it('takes the language from the URL over the stored choice', async () => {
        localStorage.setItem('site-language', 'en');
        // jsdom serves '/', so point the module at a Dutch URL as it loads.
        window.history.replaceState({}, '', '/nl');

        const { lang } = await loadI18n();

        expect(lang.value).toBe('nl');

        window.history.replaceState({}, '', '/');
    });
});
