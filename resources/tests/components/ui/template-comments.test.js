import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ToastStack from '../../../js/components/ui/ToastStack.vue';
import AppPillSwitch from '../../../js/components/ui/AppPillSwitch.vue';

/**
 * A <template> comment is markup: Vue compiles it into a real DOM node unless
 * told otherwise, which is what vue-plugin.js sets for the app and for these
 * tests. The option is one line in a config and easy to lose.
 */
const sourceOf = (file) => readFileSync(join(process.cwd(), 'resources/js/components/ui', file), 'utf8');

// Guards against passing for the wrong reason: with the comment gone from the
// component, the render assertions below prove nothing.
const templateComment = (file) => /<template>[\s\S]*<!--/.test(sourceOf(file));

describe('template comments', () => {
    it('keeps an authored comment out of the rendered page', () => {
        expect(templateComment('ToastStack.vue')).toBe(true);

        expect(mount(ToastStack).html()).not.toContain('<!--');
    });

    // Vue's own v-if anchors are not authored comments: they mark the place an
    // absent branch would go.
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
