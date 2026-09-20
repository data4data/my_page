import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SharedTab from '../../../js/pages/admin/SharedTab.vue';
import { copy } from '../../../js/shared/i18n';

// One flat [{text, tone}] list, edited as two comma-separated fields.
const stubs = {
    AppInput: {
        name: 'AppInput',
        props: ['modelValue'],
        emits: ['update:modelValue'],
        template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    },
};

const mountTab = (profile) => mount(SharedTab, { props: { profile }, global: { stubs } });

// By label, not position, so reordering the tab cannot silently point these at
// a different field.
const highlightInputs = (wrapper) => {
    const byLabel = (text) => wrapper.findAll('label')
        .find((label) => label.text().startsWith(text))
        .findComponent({ name: 'AppInput' });

    return { blue: byLabel(copy('fieldAccentBlue')), gold: byLabel(copy('fieldAccentGold')) };
};

describe('SharedTab headline highlights', () => {
    it('shows each tone as its own comma-separated list', () => {
        const wrapper = mountTab({
            headline_highlights: [
                { text: 'precision', tone: 'blue' },
                { text: 'impact', tone: 'gold' },
                { text: 'precisie', tone: 'blue' },
            ],
        });

        const { blue, gold } = highlightInputs(wrapper);
        expect(blue.props('modelValue')).toBe('precision, precisie');
        expect(gold.props('modelValue')).toBe('impact');
    });

    it('writes an edited tone back without disturbing the other one', async () => {
        const profile = {
            headline_highlights: [
                { text: 'precision', tone: 'blue' },
                { text: 'impact', tone: 'gold' },
            ],
        };
        const wrapper = mountTab(profile);

        await highlightInputs(wrapper).blue.vm.$emit('update:modelValue', 'clarity, craft');

        expect(profile.headline_highlights).toEqual([
            { text: 'clarity', tone: 'blue' },
            { text: 'craft', tone: 'blue' },
            { text: 'impact', tone: 'gold' },
        ]);
    });

    it('drops blank entries left by trailing commas and stray spaces', async () => {
        const profile = { headline_highlights: [] };
        const wrapper = mountTab(profile);

        await highlightInputs(wrapper).gold.vm.$emit('update:modelValue', ' impact , , results, ');

        expect(profile.headline_highlights).toEqual([
            { text: 'impact', tone: 'gold' },
            { text: 'results', tone: 'gold' },
        ]);
    });

    it('handles a profile that has no highlights column value yet', () => {
        const wrapper = mountTab({ headline_highlights: null });

        expect(highlightInputs(wrapper).blue.props('modelValue')).toBe('');
    });

    it('clearing a field removes only that tone', async () => {
        const profile = {
            headline_highlights: [
                { text: 'precision', tone: 'blue' },
                { text: 'impact', tone: 'gold' },
            ],
        };
        const wrapper = mountTab(profile);

        await highlightInputs(wrapper).blue.vm.$emit('update:modelValue', '');

        expect(profile.headline_highlights).toEqual([{ text: 'impact', tone: 'gold' }]);
    });
});
