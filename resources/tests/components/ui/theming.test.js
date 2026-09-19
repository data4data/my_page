import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * The visit card has a dark mode now, which means the rule the workspace has
 * always had applies to it too: a colour with one value cannot follow the
 * theme, so a literal in a themed stylesheet stays light while everything
 * around it does not.
 *
 * The public page's palette is the brand palette, so these check the brand
 * tokens carry both halves and that public.css reaches for them rather than
 * writing cream and white by hand.
 */
const css = (file) => readFileSync(join(process.cwd(), 'resources/css', file), 'utf8');

describe('the public page can go dark', () => {
    // Every colour the visit card is built from. A new one added without a
    // dark half is the failure this catches.
    const themed = [
        'ink', 'ink-hover', 'ink-text', 'accent', 'accent-hover', 'accent-text',
        'sand', 'sand-light', 'tan', 'graphite', 'charcoal', 'taupe', 'muted',
        'cream', 'parchment', 'ivory', 'paper', 'brass', 'gold', 'gold-dark',
        'bronze', 'bronze-text',
    ];

    it('gives every brand colour both halves', () => {
        const theme = css('theme.css');

        for (const name of themed) {
            const rule = theme.match(new RegExp(`--color-${name}:\\s*([^;]+);`));

            expect(rule, `--color-${name} should be declared`).not.toBeNull();
            expect(rule[1], `--color-${name} has one value, so it cannot follow the theme`)
                .toMatch(/^light-dark\(/);
        }
    });

    // These are the two the visit card used to be written in: white cards on
    // a cream page. Both stay light whatever the theme is set to.
    it('writes no white or cream by hand in the visit card stylesheet', () => {
        const source = css('public.css');

        expect(source).not.toMatch(/\bbg-white\b/);
        expect(source).not.toMatch(/rgba\(\s*255\s*,\s*255\s*,\s*255/);
        expect(source).not.toMatch(/rgba\(\s*248\s*,\s*244\s*,\s*237/);
        expect(source).not.toMatch(/#f{3,6}\b/i);
    });

    // The photographs do not change with the theme, so something has to.
    it('lays a dim over the hero photograph in dark mode', () => {
        expect(css('base.css')).toMatch(/--photo-dim:\s*light-dark\(/);
        expect(css('public.css')).toContain('var(--photo-dim)');
    });

    /*
     * The one panel that stays dark in both. It is a dark feature band over a
     * photograph in the light design; flipping it would put a light slab on a
     * dark page, which is the opposite of what the band is for.
     */
    it('keeps the contact band dark in both themes', () => {
        // The painted rule, not the shape one — public.css declares
        // .contact-band twice.
        const band = css('public.css')
            .match(/\.contact-band\s*\{[^}]*contact-still-life[^}]*\}/s)[0];

        expect(band).toContain('text-white');
        expect(band).not.toContain('bg-ink');
    });
});
