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
        expect(localStorage.getItem('oa-language')).toBeNull();
    });

    it('keeps a returning visitor on their own choice', async () => {
        localStorage.setItem('oa-language', 'nl');

        const { applyLanguagePolicy, lang } = await loadI18n();

        applyLanguagePolicy({ default_language: 'en', show_language_toggle: true });

        expect(lang.value).toBe('nl');
    });

    it('lets the default override a stored choice when the switcher is off', async () => {
        localStorage.setItem('oa-language', 'nl');

        const { applyLanguagePolicy, lang } = await loadI18n();

        applyLanguagePolicy({ default_language: 'en', show_language_toggle: false });

        expect(lang.value).toBe('en');
    });

    it('preserves a stored choice through the switcher being turned off and back on', async () => {
        localStorage.setItem('oa-language', 'nl');

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
        expect(localStorage.getItem('oa-language')).toBe('nl');
    });
});
