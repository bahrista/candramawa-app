<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, default: '' },
    url: { type: String, default: null },
    size: { type: Number, default: 32 },
});

const initials = computed(() =>
    props.name.split(' ').filter(Boolean).map((w) => w[0]).slice(0, 2).join('').toUpperCase(),
);
const style = computed(() => ({ width: props.size + 'px', height: props.size + 'px', fontSize: props.size * 0.36 + 'px' }));
</script>

<template>
    <img v-if="url" :src="url" :alt="name" :style="style" class="shrink-0 rounded-full object-cover" />
    <span v-else :style="style" class="flex shrink-0 items-center justify-center rounded-full bg-wp-sidebar font-semibold text-white">{{ initials }}</span>
</template>
