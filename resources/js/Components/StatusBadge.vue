<script setup>
import { computed } from 'vue';

const props = defineProps({ status: String, label: String });

const LABELS = { draft: 'Draft', in_production: 'In Production', in_review: 'In Review' };
const STYLES = {
    draft: 'bg-slate-200 text-slate-700',
    in_production: 'bg-amber-100 text-amber-800',
    in_review: 'bg-violet-100 text-violet-800',
};
const text = computed(() => props.label ?? LABELS[props.status] ?? props.status);
</script>

<template>
    <span class="inline-block whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium" :class="STYLES[status] ?? STYLES.draft">{{ text }}</span>
</template>
