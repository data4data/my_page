<script setup>
import { Plus } from '@lucide/vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppIconSelect from '../../components/ui/AppIconSelect.vue';
import AppCheckbox from '../../components/ui/AppCheckbox.vue';
import AppButton from '../../components/ui/AppButton.vue';
import EditableCard from '../../components/EditableCard.vue';
import { copy } from '../../shared/i18n';
import { showsIn } from '../../shared/portfolio';
import { iconMap } from '../../shared/icons';

// Unlike the other collections, social links are a JSON column on the profile
// rather than a child table, so this tab edits the array in place instead of
// going through AdminPage's collection helpers.
const props = defineProps({
    profile: {
        type: Object,
        required: true,
    },
});

const links = () => {
    if (! Array.isArray(props.profile.social_links)) {
        props.profile.social_links = [];
    }

    return props.profile.social_links;
};

const add = () => links().push({ label: '', url: '', icon: 'link', in_rail: true, in_footer: true });

const remove = (collection, index) => links().splice(index, 1);

const move = (collection, index, direction) => {
    const next = index + direction;
    const items = links();

    if (next < 0 || next >= items.length) {
        return;
    }

    [items[index], items[next]] = [items[next], items[index]];
};
</script>

<template>
    <div class="flex flex-col gap-3">
        <p class="admin-note">{{ copy('socialInfo') }}</p>

        <p v-if="links().length === 0" class="admin-note">{{ copy('socialEmpty') }}</p>

        <!-- No Visible toggle: the two placement boxes below already decide
             where a link shows, so a third switch would be a second answer to
             the same question. -->
        <EditableCard
            v-for="(link, index) in links()"
            :key="index"
            :title="link.label || copy('socialLink')"
            :index="index"
            :total="links().length"
            collection="social_links"
            @move="move"
            @remove="remove"
        >
            <template #lead>
                <span class="item-card-lead">
                    <component :is="iconMap[link.icon]" v-if="iconMap[link.icon]" :size="15" aria-hidden="true" />
                </span>
            </template>

            <div class="lang-grid">
                <label class="field-label">{{ copy('socialLabel') }}<AppInput v-model="link.label" maxlength="60" /></label>
                <label class="field-label">{{ copy('socialUrl') }}<AppInput v-model="link.url" placeholder="https://" /></label>
                <label class="field-label">{{ copy('socialIcon') }}<AppIconSelect v-model="link.icon" /></label>

                <!-- Read through showsIn, not straight off the key: a link saved
                     before the two placements existed carries only is_visible, and
                     an unticked box would misreport a link that is on show. -->
                <div class="field-label">
                    <span>{{ copy('socialWhereShown') }}</span>
                    <div class="flex min-h-9 flex-wrap items-center gap-x-6 gap-y-2">
                        <AppCheckbox :model-value="showsIn(link, 'rail')" @update:model-value="link.in_rail = $event">
                            {{ copy('socialInRail') }}
                        </AppCheckbox>
                        <AppCheckbox :model-value="showsIn(link, 'footer')" @update:model-value="link.in_footer = $event">
                            {{ copy('socialInFooter') }}
                        </AppCheckbox>
                    </div>
                </div>
            </div>
        </EditableCard>

        <AppButton variant="solid" class="self-start" @click="add">
            <Plus :size="14" aria-hidden="true" />
            {{ copy('socialAdd') }}
        </AppButton>
    </div>
</template>
