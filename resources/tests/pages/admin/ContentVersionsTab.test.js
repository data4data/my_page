import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ContentVersionsTab from '../../../js/pages/admin/ContentVersionsTab.vue';

const revisions = [
    { id: 7, created_at: '2026-08-27T14:05:00.000000Z', author: 'Alex Blake' },
    { id: 6, created_at: '2026-08-27T09:30:00.000000Z', author: null },
];

const mountTab = (props = {}) => mount(ContentVersionsTab, {
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

describe('ContentVersionsTab', () => {
    it('leads the list with the default content rather than a separate section', () => {
        const rows = mountTab().findAll('.history-row');

        expect(rows[0].classes()).toContain('history-row-defaults');
        expect(rows[0].text()).toContain('Default content');
    });

    it('lists one row per saved version after the defaults row', () => {
        expect(mountTab().findAll('.history-row-revision')).toHaveLength(2);
    });

    it('shows who made each change', () => {
        expect(mountTab().findAll('.history-row-revision')[0].text()).toContain('Alex Blake');
    });

    it('labels a revision with no author as the starting content rather than a missing person', () => {
        const rows = mountTab().findAll('.history-row-revision');

        // user_id is null only for the baseline snapshot taken before the very
        // first save — nobody made that state.
        expect(rows[1].text()).toContain('Starting content');
        expect(rows[1].text()).not.toContain('by');
    });

    it('passes the revision id to the restore handler', async () => {
        const restoreRevision = vi.fn();
        const wrapper = mountTab({ restoreRevision });

        await wrapper.findAll('.history-row-revision')[0].find('button').trigger('click');

        expect(restoreRevision).toHaveBeenCalledWith(7);
    });

    it('restores the defaults from the first row', async () => {
        const restoreDefaults = vi.fn();
        const wrapper = mountTab({ restoreDefaults });

        await wrapper.find('.history-row-defaults button').trigger('click');

        expect(restoreDefaults).toHaveBeenCalled();
    });

    it('locks every restore button, defaults included, while a revision is restoring', () => {
        const buttons = mountTab({ restoringId: 7 }).findAll('.history-row button');

        expect(buttons.every((button) => button.attributes('disabled') !== undefined)).toBe(true);
    });

    it('locks the saved versions while the defaults are being restored', () => {
        const buttons = mountTab({ restoring: true }).findAll('.history-row button');

        expect(buttons.every((button) => button.attributes('disabled') !== undefined)).toBe(true);
    });

    it('keeps the defaults row available when nothing has been saved yet', () => {
        const wrapper = mountTab({ revisions: [] });

        expect(wrapper.findAll('.history-row-revision')).toHaveLength(0);
        expect(wrapper.find('.history-row-defaults').exists()).toBe(true);
        expect(wrapper.text()).toContain('No saved versions yet');
    });

    it('shows a loading state while the history is being fetched', () => {
        const wrapper = mountTab({ revisions: [], revisionsLoading: true });

        expect(wrapper.text()).toContain('Loading history');
        expect(wrapper.find('.history-row-defaults').exists()).toBe(true);
    });
});
