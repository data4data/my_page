import { copy } from '../../../shared/i18n';

/**
 * What "add" starts from, per collection, and what the button offering it is
 * called.
 *
 * One file rather than a `blank()` in each tab: the add button lives in the
 * sheet's action bar now, so the page renders it and the page needs the
 * default. It is still a named factory in a script — never an object written
 * into a `@click`, where nobody looking for a default would find it.
 *
 * A factory, not a literal: the object is pushed into the payload and then
 * edited, so two "add" clicks must not share one.
 */
export const NEW_ITEM = {
    metrics: {
        label: 'addMetric',
        blank: () => ({
            value: '1+',
            label: { en: 'New metric', nl: 'Nieuwe metriek' },
        }),
    },
    expertise_items: {
        label: 'addExpertise',
        blank: () => ({
            title: { en: 'New expertise', nl: 'Nieuwe expertise' },
            description: { en: 'Describe the result and capability.', nl: 'Beschrijf het resultaat en de vaardigheid.' },
            icon: 'sparkles',
            category: 'General',
        }),
    },
    process_steps: {
        label: 'addProcessStep',
        blank: () => ({
            group: 'core',
            title: { en: 'New step', nl: 'Nieuwe stap' },
            description: { en: 'Short description', nl: 'Korte beschrijving' },
            icon: 'sparkles',
        }),
    },
    projects: {
        label: 'addProject',
        blank: () => ({
            title: { en: 'New project', nl: 'Nieuw project' },
            summary: { en: 'Describe the system and result.', nl: 'Beschrijf het systeem en resultaat.' },
            result: { en: 'What improved.', nl: 'Wat is verbeterd.' },
            tags: ['Laravel'],
            visual_style: 'dashboard',
        }),
    },
    social_links: {
        label: 'socialAdd',
        blank: () => ({ label: '', url: '', icon: 'link', in_rail: true, in_footer: true }),
    },
};

/** Which collection a tab edits, for the tabs that edit one. */
export const COLLECTION_FOR_TAB = {
    metrics: 'metrics',
    expertise: 'expertise_items',
    process: 'process_steps',
    projects: 'projects',
    social: 'social_links',
};

/** The add button for the tab on screen, or null where there is nothing to add. */
export const addActionFor = (tab) => {
    const collection = COLLECTION_FOR_TAB[tab];

    if (!collection) {
        return null;
    }

    return {
        collection,
        label: copy(NEW_ITEM[collection].label),
        blank: NEW_ITEM[collection].blank,
    };
};
