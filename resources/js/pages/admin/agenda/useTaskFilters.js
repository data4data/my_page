import { computed, ref } from 'vue';
import { TASK_STATUSES, taskStatusLabelKey } from '../../../shared/planning';
import { copy } from '../../../shared/i18n';

/**
 * The category and status filters above the calendar views.
 *
 * @param {import('vue').Ref<Array>} tasks       the loaded task list
 * @param {import('vue').Ref<Array>} categories  top-level categories, children nested
 */
export function useTaskFilters(tasks, categories) {
    const selectedCategoryIds = ref([]);
    const selectedStatuses = ref([]);

    // Flat "Parent › Child" list (one level of nesting, same as TaskModal's own
    // category select) plus a pseudo-option for tasks with no category at all.
    const categoryFilterOptions = computed(() => {
        const options = [{ label: copy('uncategorized'), value: null }];

        for (const category of categories.value) {
            options.push({ label: category.name, value: category.id });

            for (const child of category.children ?? []) {
                options.push({ label: `${category.name} › ${child.name}`, value: child.id });
            }
        }

        return options;
    });

    // computed, so the labels re-render when the admin switches their own
    // working language.
    const statusFilterOptions = computed(() => TASK_STATUSES.map((status) => ({ label: copy(taskStatusLabelKey[status]), value: status })));

    // Tasks are filed against a leaf category ("Learning › Laravel"), so picking
    // the parent alone would match nothing. Selecting a parent is taken to mean
    // "this and everything under it" — otherwise "Learning" reads as an empty
    // category even while its subcategories hold tasks.
    const activeCategoryIds = computed(() => {
        const ids = new Set(selectedCategoryIds.value);

        for (const parent of categories.value) {
            if (ids.has(parent.id)) {
                for (const child of parent.children ?? []) {
                    ids.add(child.id);
                }
            }
        }

        return ids;
    });

    // Empty selection = no filter on that facet; both facets AND together.
    const filteredTasks = computed(() => tasks.value.filter((task) => {
        const matchesCategory = selectedCategoryIds.value.length === 0 || activeCategoryIds.value.has(task.category_id);
        const matchesStatus = selectedStatuses.value.length === 0 || selectedStatuses.value.includes(task.status);

        return matchesCategory && matchesStatus;
    }));

    return {
        selectedCategoryIds,
        selectedStatuses,
        categoryFilterOptions,
        statusFilterOptions,
        filteredTasks,
    };
}
