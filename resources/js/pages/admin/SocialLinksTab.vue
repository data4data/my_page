<script setup>
import { Plus } from '@lucide/vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppIconSelect from '../../components/ui/AppIconSelect.vue';
import AppCheckbox from '../../components/ui/AppCheckbox.vue';
import AppButton from '../../components/ui/AppButton.vue';
import EditableCard from '../../components/EditableCard.vue';
import { copy } from '../../shared/i18n';
import { iconMap } from '../../shared/icons';

// An ordinary child collection now, so it goes through AdminPage's helpers
// like every other tab rather than editing an array on the profile in place.
defineProps({
    links: {
        type: Array,
        required: true,
    },
    addItem: {
        type: Function,
        required: true,
    },
    removeItem: {
        type: Function,
        required: true,
    },
    moveItem: {
        type: Function,
        required: true,
    },
});

const blank = () => ({ label: '', url: '', icon: 'link', in_rail: true, in_footer: true });
</script>

<template>
    <div class="flex flex-col gap-3">
        <p class="admin-note">{{ copy('socialInfo') }}</p>

        <p v-if="links.length === 0" class="admin-note">{{ copy('socialEmpty') }}</p>

        <!-- No Visible toggle: the two placement boxes below already decide
             where a link shows, so a third switch would be a second answer to
             the same question. -->
        <EditableCard
            v-for="(link, index) in links"
            :key="index"
            :title="link.label || copy('socialLink')"
            :index="index"
            :total="links.length"
            collection="social_links"
            @move="moveItem"
            @remove="removeItem"
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

                <div class="field-label">
                    <span>{{ copy('socialWhereShown') }}</span>
                    <div class="flex min-h-9 flex-wrap items-center gap-x-6 gap-y-2">
                        <AppCheckbox v-model="link.in_rail">{{ copy('socialInRail') }}</AppCheckbox>
                        <AppCheckbox v-model="link.in_footer">{{ copy('socialInFooter') }}</AppCheckbox>
                    </div>
                </div>
            </div>
        </EditableCard>

        <AppButton variant="solid" class="self-start" @click="addItem('social_links', blank())">
            <Plus :size="14" aria-hidden="true" />
            {{ copy('socialAdd') }}
        </AppButton>
    </div>
</template>
