import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { computed, ref } from 'vue';
import { copy } from '../../../../../js/shared/i18n';

const addItem = vi.fn();

vi.mock('../../../../../js/pages/admin/usePortfolioEditor', () => ({
    usePortfolioEditor: () => ({
        profile: computed(() => ({ default_language: 'en' })),
        metrics: computed(() => []),
        expertise: computed(() => []),
        projects: computed(() => []),
        processSteps: computed(() => []),
        socialLinks: computed(() => []),
        loading: ref(false),
        loadFailed: computed(() => false),
        ready: computed(() => true),
        dirty: computed(() => false),
        saving: ref(false),
        saveStatus: computed(() => ''),
        reload: vi.fn(),
        save: vi.fn(),
        addItem,
        removeItem: vi.fn(),
        moveItem: vi.fn(),
    }),
}));

const EditPage = (await import('../../../../../js/pages/admin/edit/components/EditPage.vue')).default;

// Every tab is stubbed: this is about where the add button is, not what the
// tabs draw. AdminSheet is real, because the bar is the claim.
const stubs = {
    ProfileTab: true,
    MetricsTab: true,
    ExpertiseTab: true,
    ProcessTab: true,
    ProjectsTab: true,
    SocialLinksTab: true,
    GeneralTab: true,
    AppButton: { template: '<button><slot /></button>' },
};

const mountPage = () => mount(EditPage, { global: { stubs } });

const barButtons = (wrapper) => wrapper.find('.admin-sheet-bar').findAll('button');
const openTab = (wrapper, label) => wrapper.findAll('[role="tab"]')
    .find((item) => item.text() === label)
    .trigger('click');

describe('EditPage add button', () => {
    beforeEach(() => addItem.mockReset());

    /**
     * The bar is sticky, so adding a tenth project does not mean scrolling
     * past nine cards to reach the button.
     */
    it('lives in the action bar rather than under the list', async () => {
        const wrapper = mountPage();
        await openTab(wrapper, copy('tabMetrics'));

        expect(barButtons(wrapper)[0].text()).toContain(copy('addMetric'));
    });

    it('names the collection of the tab on screen', async () => {
        const wrapper = mountPage();

        await openTab(wrapper, copy('tabProjects'));
        expect(barButtons(wrapper)[0].text()).toContain(copy('addProject'));

        await openTab(wrapper, copy('tabSocial'));
        expect(barButtons(wrapper)[0].text()).toContain(copy('socialAdd'));
    });

    it('adds to that collection when pressed', async () => {
        const wrapper = mountPage();
        await openTab(wrapper, copy('tabMetrics'));

        await barButtons(wrapper)[0].trigger('click');

        expect(addItem).toHaveBeenCalledWith('metrics', expect.objectContaining({ value: '1+' }));
    });

    // Profile and General edit the profile row itself: nothing to add to.
    it('is absent on the tabs that edit no collection', async () => {
        const wrapper = mountPage();

        expect(barButtons(wrapper)[0].text()).toContain(copy('cancel'));

        await openTab(wrapper, copy('tabGeneral'));
        expect(barButtons(wrapper)[0].text()).toContain(copy('cancel'));
    });
});
