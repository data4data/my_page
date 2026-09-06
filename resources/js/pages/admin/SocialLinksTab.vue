<script setup>
import { Plus } from '@lucide/vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppIconSelect from '../../components/ui/AppIconSelect.vue';
import AppCheckbox from '../../components/ui/AppCheckbox.vue';
import AppButton from '../../components/ui/AppButton.vue';
import EditableCard from '../../components/EditableCard.vue';
import { copy } from '../../shared/i18n';

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

const add = () => links().push({ label: '', url: '', icon: 'link', is_visible: true });

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
    <div class="space-y-4">
        <div class="admin-note">{{ copy('socialInfo') }}</div>

        <p v-if="links().length === 0" class="admin-note">{{ copy('socialEmpty') }}</p>

        <EditableCard
            v-for="(link, index) in links()"
            :key="index"
            :title="copy('socialLink')"
            :index="index"
            collection="social_links"
            @move="move"
            @remove="remove"
        >
            <label class="admin-full">{{ copy('socialLabel') }}<AppInput v-model="link.label" maxlength="60" /></label>
            <label class="admin-full">{{ copy('socialUrl') }}<AppInput v-model="link.url" placeholder="https://" /></label>
            <label>{{ copy('socialIcon') }}<AppIconSelect v-model="link.icon" /></label>
            <AppCheckbox v-model="link.is_visible">{{ copy('socialVisible') }}</AppCheckbox>
        </EditableCard>

        <AppButton variant="accent" size="sm" class="fab-add" @click="add">
            <Plus :size="16" /> {{ copy('socialAdd') }}
        </AppButton>
    </div>
</template>
