import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ToastStack from '../../../js/components/ui/ToastStack.vue';
import AppPillSwitch from '../../../js/components/ui/AppPillSwitch.vue';

/**
 * A <template> comment is markup: Vue compiles it into a real DOM node unless
 * the compiler is told otherwise. They were turning up in the inspector on the
 * public page, so vue-plugin.js sets `comments: false` for both the app build
 * and this one. This is what says so — the option is a single line in a config
 * and easy to lose.
 */
const sourceOf = (file) => readFileSync(join(process.cwd(), 'resources/js/components/ui', file), 'utf8');

// Guards against passing for the wrong reason: if someone removes the comment
// these components carry, the render assertions below prove nothing.
const templateComment = (file) => /<template>[\s\S]*<!--/.test(sourceOf(file));

describe('template comments', () => {
    it('keeps an authored comment out of the rendered page', () => {
        expect(templateComment('ToastStack.vue')).toBe(true);

        expect(mount(ToastStack).html()).not.toContain('<!--');
    });

    // The anchor Vue writes where an absent v-if branch would go is not an
    // authored comment and cannot be turned off — it is how the framework
    // finds the spot again when the branch comes back.
    it('leaves Vue its own v-if anchors', () => {
        expect(templateComment('AppPillSwitch.vue')).toBe(true);

        const html = mount(AppPillSwitch, {
            props: {
                modelValue: 'en',
                ariaLabel: 'Language',
                options: [{ label: 'EN', value: 'en' }, { label: 'NL', value: 'nl' }],
            },
        }).html();

        expect(html).toContain('<!--v-if-->');
        expect(html.replaceAll('<!--v-if-->', '')).not.toContain('<!--');
    });
});
