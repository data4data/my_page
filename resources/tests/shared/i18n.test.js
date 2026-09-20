import { beforeEach, describe, expect, it, vi } from 'vitest';

// `lang` is a module-level singleton, so each test needs a fresh registry.
const loadI18n = async () => {
    vi.resetModules();

    return import('../../js/shared/i18n.js');
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

        // Writing the default would make it indistinguishable from a deliberate
        // choice on the next visit.
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

    // A visitor who chose before the key was renamed keeps their language.
    it('still honours a choice stored under the retired key', async () => {
        localStorage.setItem('oa-language', 'nl');

        const { applyLanguagePolicy, lang } = await loadI18n();

        applyLanguagePolicy({ default_language: 'en', show_language_toggle: true });

        expect(lang.value).toBe('nl');
    });
});

// copy() falls back to English on a missing key, so a dropped Dutch string
// ships silently. These check what that fallback would otherwise hide.
describe('the en and nl dictionaries agree', () => {
    // Each file on its own, not the merged `ui`, which after the split holds
    // only what the test itself registered.
    const dictionaries = async () => {
        vi.resetModules();

        return [
            ['shared', (await import('../../js/shared/i18n.js')).ui],
            ['public', (await import('../../js/shared/i18n-public.js')).publicUi],
            ['admin', (await import('../../js/shared/i18n-admin.js')).adminUi],
        ];
    };

    it('holds the same keys in both languages', async () => {
        for (const [name, dictionary] of await dictionaries()) {
            expect(Object.keys(dictionary.nl).sort(), name)
                .toEqual(Object.keys(dictionary.en).sort());
        }
    });

    it('leaves no string empty', async () => {
        for (const [name, dictionary] of await dictionaries()) {
            for (const language of ['en', 'nl']) {
                for (const [key, value] of Object.entries(dictionary[language])) {
                    expect(value, `${name}.${language}.${key}`).not.toBe('');
                }
            }
        }
    });

    // A key drifting back into the shared file would undo the split.
    it('keeps the shared file to strings both halves really use', async () => {
        const [[, shared]] = await dictionaries();

        expect(Object.keys(shared.en).length).toBeLessThan(20);
    });
});

// The same rule lives in PortfolioController::alternates() and the route
// constraint in routes/web.php — keep the three in step.
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
