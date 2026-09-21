import { describe, expect, it } from 'vitest';
import { NEW_ITEM, addActionFor } from '../../../../../js/pages/admin/edit/composables/new-item';
import { translatableItemFields } from '../../../../../js/shared/portfolio';
import { copy } from '../../../../../js/shared/i18n';

describe('what "add" starts from', () => {
    it('covers every collection the editor can add to', () => {
        expect(Object.keys(NEW_ITEM).sort())
            .toEqual([...Object.keys(translatableItemFields), 'social_links'].sort());
    });

    /**
     * The object is pushed into the payload and then edited, so two clicks
     * sharing one would edit the same item twice.
     */
    it('builds a new object each time', () => {
        const first = NEW_ITEM.projects.blank();
        const second = NEW_ITEM.projects.blank();

        first.tags.push('Changed');

        expect(second.tags).not.toContain('Changed');
        expect(first).not.toBe(second);
    });

    it('starts every translated field as an {en, nl} pair', () => {
        for (const [collection, fields] of Object.entries(translatableItemFields)) {
            const item = NEW_ITEM[collection].blank();

            for (const field of fields) {
                expect(Object.keys(item[field]), `${collection}.${field}`).toEqual(['en', 'nl']);
            }
        }
    });

    it('names the collection and the button for the tab on screen', () => {
        expect(addActionFor('social')).toMatchObject({ collection: 'social_links', label: copy('socialAdd') });
        expect(addActionFor('projects')).toMatchObject({ collection: 'projects', label: copy('addProject') });
    });

    // Profile and General edit the profile row itself; there is no list.
    it('offers nothing to add on the tabs that edit no collection', () => {
        expect(addActionFor('profile')).toBeNull();
        expect(addActionFor('general')).toBeNull();
    });
});
