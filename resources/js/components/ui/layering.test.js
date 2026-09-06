import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ToastStack from './ToastStack.vue';
import AdminLayout from '../admin/AdminLayout.vue';

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
        for (const name of ['raised', 'section', 'rail', 'header', 'fab', 'overlay', 'field', 'confirm', 'toast']) {
            expect(token(name), `--z-${name} should be defined`).toBeTypeOf('number');
        }
    });

    it('puts toasts above modal overlays', () => {
        // Equal values plus DOM order is what buried error toasts behind the
        // modal scrim: a failed save reported nothing the user could see.
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

    it('keeps the floating action button above the chrome but under a modal', () => {
        expect(token('fab')).toBeGreaterThan(token('header'));
        expect(token('overlay')).toBeGreaterThan(token('fab'));
    });

    // AppSelect and AppDatePicker panels are appended to <body>, so inside a
    // modal they are siblings of the scrim rather than children. Both sat on
    // 50, which left the winner to DOM insertion order.
    it('puts a field dropdown above the modal it opens inside, and under a confirm', () => {
        expect(token('field')).toBeGreaterThan(token('overlay'));
        expect(token('confirm')).toBeGreaterThan(token('field'));
    });
});

describe('no layer collides with another', () => {
    // Two things landing on the same value is the bug the scale exists to
    // prevent: equal z-index leaves the winner to DOM order, which is how
    // error toasts once ended up behind the modal scrim.
    it('gives every token a distinct value', () => {
        const names = ['raised', 'section', 'rail', 'header', 'fab', 'overlay', 'field', 'confirm', 'toast'];
        const values = names.map(token);

        expect(new Set(values).size).toBe(names.length);
    });

    // 30 (--z-rail) is the lowest layer that competes in the page's root
    // stacking context. Below that, a small literal is a local lift inside an
    // element's own context — text over its card's decoration, a focused
    // input over its sibling — and is left alone on purpose.
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

    // The admin header carried z-30, the rail's value, so the one piece of
    // chrome that has to sit above the rail was tied with it.
    it('gives the admin header the header layer', () => {
        const header = mount(AdminLayout, {
            props: { navItems: [], activeKey: 'edit' },
            global: { stubs: { AppButton: true, LogOut: true } },
        }).get('header');

        expect(header.classes()).toContain('layer-header');
        expect(header.classes().some((name) => /^z-\d+$/.test(name))).toBe(false);
    });
});
