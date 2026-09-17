import { ref } from 'vue';
import { describe, expect, it } from 'vitest';
import { useTaskFilters } from '../agenda/useTaskFilters';

// One parent with two children, plus a standalone parent — the one level of
// nesting the category tree allows.
const categories = ref([
    {
        id: 1,
        name: 'Learning',
        children: [
            { id: 11, name: 'Laravel' },
            { id: 12, name: 'Vue' },
        ],
    },
    { id: 2, name: 'Health', children: [] },
]);

const tasks = ref([
    { id: 1, title: 'Read the docs', category_id: 11, status: 'done' },
    { id: 2, title: 'Build a component', category_id: 12, status: 'planned' },
    { id: 3, title: 'Go for a run', category_id: 2, status: 'planned' },
    { id: 4, title: 'Nothing filed', category_id: null, status: 'skipped' },
]);

const titles = (list) => list.map((task) => task.title);

const filters = () => useTaskFilters(tasks, categories);

describe('useTaskFilters', () => {
    it('shows every task while nothing is selected', () => {
        const { filteredTasks } = filters();

        expect(filteredTasks.value).toHaveLength(4);
    });

    it('filters by a single category', () => {
        const { selectedCategoryIds, filteredTasks } = filters();

        selectedCategoryIds.value = [2];

        expect(titles(filteredTasks.value)).toEqual(['Go for a run']);
    });

    it('treats a parent as itself plus everything under it', () => {
        const { selectedCategoryIds, filteredTasks } = filters();

        selectedCategoryIds.value = [1];

        // Tasks are filed against the leaf, so picking "Learning" alone would
        // otherwise match nothing at all.
        expect(titles(filteredTasks.value)).toEqual(['Read the docs', 'Build a component']);
    });

    it('matches unfiled tasks through the uncategorized option', () => {
        const { selectedCategoryIds, categoryFilterOptions, filteredTasks } = filters();

        expect(categoryFilterOptions.value[0].value).toBeNull();
        selectedCategoryIds.value = [null];

        expect(titles(filteredTasks.value)).toEqual(['Nothing filed']);
    });

    it('filters by status', () => {
        const { selectedStatuses, filteredTasks } = filters();

        selectedStatuses.value = ['planned'];

        expect(titles(filteredTasks.value)).toEqual(['Build a component', 'Go for a run']);
    });

    it('ANDs the two filters together', () => {
        const { selectedCategoryIds, selectedStatuses, filteredTasks } = filters();

        selectedCategoryIds.value = [1];
        selectedStatuses.value = ['planned'];

        expect(titles(filteredTasks.value)).toEqual(['Build a component']);
    });

    it('lists categories flat, with children under their parent', () => {
        const { categoryFilterOptions } = filters();

        expect(categoryFilterOptions.value.slice(1)).toEqual([
            { label: 'Learning', value: 1 },
            { label: 'Learning › Laravel', value: 11 },
            { label: 'Learning › Vue', value: 12 },
            { label: 'Health', value: 2 },
        ]);
    });

    it('offers every task status', () => {
        const { statusFilterOptions } = filters();

        expect(statusFilterOptions.value.map((option) => option.value))
            .toEqual(['planned', 'in_progress', 'paused', 'done', 'skipped']);
    });
});
