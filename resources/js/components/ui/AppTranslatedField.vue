<script setup>
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import InputGroup from 'primevue/inputgroup';
import InputGroupAddon from 'primevue/inputgroupaddon';

// One label + EN/NL inputs grouped as a single record. `modelValue` is the
// {en, nl} object itself, mutated in place.
defineProps({
    label: {
        type: String,
        required: true,
    },
    modelValue: {
        type: Object,
        required: true, // { en, nl }
    },
    multiline: {
        type: Boolean,
        default: false,
    },
    rows: {
        type: [String, Number],
        default: 3,
    },
});
</script>

<template>
    <div>
        <span class="field-group-label">{{ label }}</span>
        <div class="field-group-row">
            <InputGroup class="field-group-unit" unstyled>
                <InputGroupAddon class="field-group-addon" unstyled><span aria-hidden="true">🇬🇧</span> EN</InputGroupAddon>
                <Textarea v-if="multiline" v-model="modelValue.en" :rows="rows" class="field-group-field" unstyled />
                <InputText v-else v-model="modelValue.en" class="field-group-field" unstyled />
            </InputGroup>
            <InputGroup class="field-group-unit" unstyled>
                <InputGroupAddon class="field-group-addon" unstyled><span aria-hidden="true">🇳🇱</span> NL</InputGroupAddon>
                <Textarea v-if="multiline" v-model="modelValue.nl" :rows="rows" class="field-group-field" unstyled />
                <InputText v-else v-model="modelValue.nl" class="field-group-field" unstyled />
            </InputGroup>
        </div>
    </div>
</template>
