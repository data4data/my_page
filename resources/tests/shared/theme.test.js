import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * theme.js keeps `theme` as a module-level singleton and counts holders, so
 * every case needs a fresh module registry rather than a shared one carrying
 * state between them.
 */
const loadTheme = async () => {
    vi.resetModules();

    return import('../../js/shared/theme.js');
};

const prefersDark = (matches) => vi.stubGlobal('matchMedia', vi.fn(() => ({
    matches,
    addEventListener: vi.fn(),
})));

beforeEach(() => {
    localStorage.clear();
    document.documentElement.removeAttribute('data-theme');
    prefersDark(false);
});

describe('what a visitor starts on', () => {
    it('follows the operating system until someone chooses', async () => {
        prefersDark(true);

        expect((await loadTheme()).theme.value).toBe('dark');
    });

    it('prefers a stored choice over the operating system', async () => {
        localStorage.setItem('site-theme', 'light');
        prefersDark(true);

        expect((await loadTheme()).theme.value).toBe('light');
    });

    it('ignores a stored value that is not a theme', async () => {
        localStorage.setItem('site-theme', 'chartreuse');

        expect((await loadTheme()).theme.value).toBe('light');
    });
});

/**
 * `data-theme` is what picks the half of every light-dark() in theme.css, so
 * holding it is the same as being in dark mode. It is counted rather than a
 * boolean because a route change can mount the next layout before the
 * previous one tears down — with a boolean, that teardown would strip the
 * attribute off the layout that had just asked for it.
 */
describe('holding the theme', () => {
    it('writes the attribute while something holds it, and removes it after', async () => {
        const { holdTheme, releaseTheme } = await loadTheme();

        expect(document.documentElement.hasAttribute('data-theme')).toBe(false);

        holdTheme();
        expect(document.documentElement.getAttribute('data-theme')).toBe('light');

        releaseTheme();
        expect(document.documentElement.hasAttribute('data-theme')).toBe(false);
    });

    it('keeps the attribute while a second holder is still there', async () => {
        const { holdTheme, releaseTheme } = await loadTheme();

        holdTheme();
        holdTheme();
        releaseTheme();

        // The overlapping mount is still holding.
        expect(document.documentElement.getAttribute('data-theme')).toBe('light');

        releaseTheme();
        expect(document.documentElement.hasAttribute('data-theme')).toBe(false);
    });

    it('never counts below zero, so a stray release cannot unbalance it', async () => {
        const { holdTheme, releaseTheme } = await loadTheme();

        releaseTheme();
        releaseTheme();
        holdTheme();

        expect(document.documentElement.getAttribute('data-theme')).toBe('light');
    });

    // The watcher lives at module scope for this reason: one created inside a
    // component's setup would be stopped when that component unmounted, even
    // though a second holder was still there.
    it('follows a change of theme while held', async () => {
        const { holdTheme, setTheme } = await loadTheme();

        holdTheme();
        setTheme('dark');
        await Promise.resolve();

        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
        expect(localStorage.getItem('site-theme')).toBe('dark');
    });

    it('does not write the attribute when nothing is holding it', async () => {
        const { setTheme } = await loadTheme();

        setTheme('dark');
        await Promise.resolve();

        // The public page before it mounts, and the login page always.
        expect(document.documentElement.hasAttribute('data-theme')).toBe(false);
    });
});
