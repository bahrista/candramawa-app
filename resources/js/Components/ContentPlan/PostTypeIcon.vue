<script setup>
defineProps({ type: String, size: { type: Number, default: 20 } });
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <template v-if="type === 'instagram_feed'">
            <rect x="3" y="3" width="18" height="18" rx="3" />
            <circle cx="9" cy="9" r="1.5" />
            <path d="M21 15l-5-5L5 21" />
        </template>
        <template v-else-if="type === 'instagram_carousel'">
            <rect x="7" y="4" width="14" height="14" rx="2" />
            <path d="M3 8v11a2 2 0 002 2h11" />
        </template>
        <template v-else-if="type === 'instagram_reel'">
            <rect x="3" y="3" width="18" height="18" rx="4" />
            <path d="M3 9h18M8.5 3L11 9M14 3l2.5 6" />
            <path d="M10 13v4l3.5-2z" />
        </template>
        <template v-else-if="type === 'instagram_story'">
            <circle cx="12" cy="12" r="9" stroke-dasharray="3 2.2" />
            <path d="M12 8.5v7M8.5 12h7" />
        </template>
    </svg>
</template>
