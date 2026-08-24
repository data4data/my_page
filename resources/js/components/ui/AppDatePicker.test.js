import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AppDatePicker from './AppDatePicker.vue';

const mountPicker = () => mount(AppDatePicker, {
    global: {
        stubs: {
            DatePicker: {
                name: 'DatePicker',
                props: ['ariaLabel', 'dateFormat', 'hourFormat'],
                template: '<div class="dp" />',
            },
        },
    },
});

describe('AppDatePicker', () => {
    it('does not announce the field as the language code', () => {
        const picker = mountPicker().findComponent({ name: 'DatePicker' });

        // "en" / "nl" as an accessible name replaces the visible label, so a
        // screen reader announces the date field as simply "en".
        expect(['en', 'nl']).not.toContain(picker.props('ariaLabel'));
    });

    it('keeps one predictable date format regardless of OS locale', () => {
        const picker = mountPicker().findComponent({ name: 'DatePicker' });

        expect(picker.props('dateFormat')).toBe('dd/mm/yy');
        expect(picker.props('hourFormat')).toBe('24');
    });
});
