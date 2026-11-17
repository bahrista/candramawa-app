<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, default: '' },
    url: { type: String, default: null },
    color: { type: String, default: '#2271b1' },
    size: { type: Number, default: 48 },
});

const style = computed(() => ({ width: props.size + 'px', height: props.size + 'px', fontSize: props.size * 0.42 + 'px' }));
</script>

<template>
    <img v-if="url" :src="url" :alt="name" :style="style" class="shrink-0 rounded-lg border border-[#dcdcde] bg-white object-contain" />
    <span v-else :style="{ ...style, backgroundColor: color }" class="flex shrink-0 items-center justify-center rounded-lg font-semibold text-white">
        {{ (name || '?').trim().charAt(0).toUpperCase() }}
    </span>
</template>
