<script setup>
import { computed } from 'vue';

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    icon: {
        type: Object,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    index: {
        type: Number,
        default: 0,
    },
});

const size = computed(() => Math.min(Math.max(Number(props.item.gear_size) || 120, 82), 180));
const rotation = computed(() => `${props.index % 2 === 0 ? 1 : -1}`);
</script>

<template>
    <div
        class="skill-gear"
        :style="{
            '--gear-size': `${size}px`,
            '--gear-rotation': rotation,
        }"
    >
        <svg class="gear-shape" viewBox="0 0 120 120" aria-hidden="true">
            <path
                d="M66 5 72 17a46 46 0 0 1 9 4l12-5 11 11-5 12a46 46 0 0 1 4 9l12 6v16l-12 6a46 46 0 0 1-4 9l5 12-11 11-12-5a46 46 0 0 1-9 4l-6 12H50l-6-12a46 46 0 0 1-9-4l-12 5-11-11 5-12a46 46 0 0 1-4-9L1 70V54l12-6a46 46 0 0 1 4-9l-5-12 11-11 12 5a46 46 0 0 1 9-4L50 5h16Z"
            />
            <circle cx="60" cy="60" r="35" />
            <circle cx="60" cy="60" r="19" />
        </svg>
        <div class="gear-content">
            <component :is="icon" :size="Math.max(20, size * 0.2)" />
            <strong>{{ title }}</strong>
            <span>{{ subtitle }}</span>
        </div>
    </div>
</template>
