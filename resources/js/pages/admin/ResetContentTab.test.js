import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ResetContentTab from './ResetContentTab.vue';

const revisions = [
    { id: 7, created_at: '2026-08-27T14:05:00.000000Z', author: 'Olha' },
    { id: 6, created_at: '2026-08-27T09:30:00.000000Z', author: null },
];

const mountTab = (props = {}) => mount(ResetContentTab, {
    props: {
        restoring: false,
        restoreDefaults: vi.fn(),
        revisions,
        revisionsLoading: false,
        restoreRevision: vi.fn(),
        restoringId: null,
        ...props,
    },
});

describe('ResetContentTab', () => {
    it('lists one row per saved version', () => {
        expect(mountTab().findAll('.history-row')).toHaveLength(2);
    });

    it('shows who made each change', () => {
        const rows = mountTab().findAll('.history-row');

        expect(rows[0].text()).toContain('Olha');
    });

    it('labels a revision with no author as the starting content rather than a missing person', () => {
        const rows = mountTab().findAll('.history-row');

        // user_id is null only for the baseline snapshot taken before the very
        // first save — nobody made that state.
        expect(rows[1].text()).toContain('Starting content');
        expect(rows[1].text()).not.toContain('by');
    });

    it('passes the revision id to the restore handler', async () => {
        const restoreRevision = vi.fn();
        const wrapper = mountTab({ restoreRevision });

        await wrapper.findAll('.history-row')[0].find('button').trigger('click');

        expect(restoreRevision).toHaveBeenCalledWith(7);
    });

    it('disables every restore button while one restore is in flight', () => {
        const wrapper = mountTab({ restoringId: 7 });

        const buttons = wrapper.findAll('.history-row button');

        expect(buttons.every((button) => button.attributes('disabled') !== undefined)).toBe(true);
    });

    it('shows an empty state instead of a list when nothing has been saved', () => {
        const wrapper = mountTab({ revisions: [] });

        expect(wrapper.find('.history-list').exists()).toBe(false);
        expect(wrapper.text()).toContain('No saved versions yet');
    });

    it('shows a loading state while the history is being fetched', () => {
        const wrapper = mountTab({ revisions: [], revisionsLoading: true });

        expect(wrapper.text()).toContain('Loading history');
    });

    it('still offers the reset-to-defaults action', async () => {
        const restoreDefaults = vi.fn();
        const wrapper = mountTab({ restoreDefaults });

        await wrapper.find('button').trigger('click');

        expect(restoreDefaults).toHaveBeenCalled();
    });
});
