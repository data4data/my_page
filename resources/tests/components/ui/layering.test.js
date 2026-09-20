import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ToastStack from '../../../js/components/ui/ToastStack.vue';
import AdminLayout from '../../../js/components/admin/AdminLayout.vue';

const cssDir = join(process.cwd(), 'resources/css');

const allCss = readdirSync(cssDir)
    .filter((file) => file.endsWith('.css'))
    .map((file) => readFileSync(join(cssDir, file), 'utf8'))
    .join('\n');

const token = (name) => {
    const match = allCss.match(new RegExp(`--z-${name}:\\s*(\\d+)`));

    return match ? Number(match[1]) : null;
};

describe('stacking order tokens', () => {
    it('defines every layer as a token rather than an ad-hoc number', () => {
        for (const name of ['raised', 'section', 'rail', 'header', 'overlay', 'field', 'confirm', 'toast']) {
            expect(token(name), `--z-${name} should be defined`).toBeTypeOf('number');
        }
    });

    it('puts toasts above modal overlays', () => {
        // Equal values plus DOM order once buried error toasts behind the scrim.
        expect(token('toast')).toBeGreaterThan(token('overlay'));
        expect(token('toast')).toBeGreaterThan(token('confirm'));
    });

    it('puts a confirm above the modal that raised it', () => {
        expect(token('confirm')).toBeGreaterThan(token('overlay'));
    });

    it('puts modal overlays above the fixed header and rails', () => {
        expect(token('overlay')).toBeGreaterThan(token('header'));
        expect(token('header')).toBeGreaterThan(token('rail'));
        expect(token('rail')).toBeGreaterThan(token('section'));
        expect(token('section')).toBeGreaterThan(token('raised'));
    });

    // These panels append to <body>, so inside a modal they are siblings of
    // the scrim rather than its children.
    it('puts a field dropdown above the modal it opens inside, and under a confirm', () => {
        expect(token('field')).toBeGreaterThan(token('overlay'));
        expect(token('confirm')).toBeGreaterThan(token('field'));
    });
});

describe('no layer collides with another', () => {
    it('gives every token a distinct value', () => {
        const names = ['raised', 'section', 'rail', 'header', 'overlay', 'field', 'confirm', 'toast'];
        const values = names.map(token);

        expect(new Set(values).size).toBe(names.length);
    });

    // Below --z-rail, a small literal is a local lift and is left alone.
    it('writes nothing at that level as a bare number', () => {
        const bare = [
            ...allCss.matchAll(/z-index:\s*(\d+)/g),
            ...allCss.matchAll(/@apply[^;]*?\bz-(\d+)/g),
        ].filter((match) => Number(match[1]) >= 30);

        expect(bare.map((match) => match[0])).toEqual([]);
    });
});

describe('components use the layer classes', () => {
    it('gives the toast stack the toast layer rather than a raw z utility', () => {
        const classes = mount(ToastStack).classes();

        expect(classes).toContain('layer-toast');
        expect(classes.some((name) => /^z-\d+$/.test(name))).toBe(false);
    });

    // The rail's fixed panel is the only workspace chrome competing in the root
    // stacking context.
    it('gives the rail panel the rail layer from the token, not a literal', () => {
        const rule = allCss.match(/\.admin-rail-panel\s*\{[^}]*\}/);

        expect(rule, '.admin-rail-panel should be declared in admin.css').not.toBeNull();
        expect(rule[0]).toContain('z-index: var(--z-rail)');
    });
});
