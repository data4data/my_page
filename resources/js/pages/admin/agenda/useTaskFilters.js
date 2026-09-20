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

    // Flat "Parent › Child", plus a pseudo-option for tasks with no category.
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

    // computed, so the labels follow the EN/NL switch.
    const statusFilterOptions = computed(() => TASK_STATUSES.map((status) => ({ label: copy(taskStatusLabelKey[status]), value: status })));

    // Tasks are filed against a leaf, so a parent means "this and everything
    // under it" — otherwise it reads as empty while its children hold tasks.
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

    // Empty selection = no filter on that facet; the two AND together.
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
