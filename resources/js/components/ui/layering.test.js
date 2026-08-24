import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ToastStack from './ToastStack.vue';

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
        for (const name of ['rail', 'header', 'overlay', 'confirm', 'toast']) {
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
    });
});

describe('ToastStack', () => {
    it('uses the toast layer class instead of a raw z utility', () => {
        const classes = mount(ToastStack).classes();

        expect(classes).toContain('layer-toast');
        expect(classes.some((name) => /^z-\d+$/.test(name))).toBe(false);
    });
});
