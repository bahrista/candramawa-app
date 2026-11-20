<script setup>
defineProps({ icon: String, size: { type: Number, default: 20 } });
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <template v-if="icon === 'image'">
            <rect x="3" y="3" width="18" height="18" rx="3" />
            <circle cx="9" cy="9" r="1.5" />
            <path d="M21 15l-5-5L5 21" />
        </template>
        <template v-else-if="icon === 'carousel'">
            <rect x="7" y="4" width="14" height="14" rx="2" />
            <path d="M3 8v11a2 2 0 002 2h11" />
        </template>
        <template v-else-if="icon === 'reel'">
            <rect x="3" y="3" width="18" height="18" rx="4" />
            <path d="M3 9h18M8.5 3L11 9M14 3l2.5 6" />
            <path d="M10 13v4l3.5-2z" />
        </template>
        <template v-else-if="icon === 'story'">
            <circle cx="12" cy="12" r="9" stroke-dasharray="3 2.2" />
            <path d="M12 8.5v7M8.5 12h7" />
        </template>
        <template v-else-if="icon === 'feed'">
            <rect x="3" y="3" width="18" height="18" rx="3" />
            <path d="M7 8h10M7 12h10M7 16h6" />
        </template>
        <template v-else-if="icon === 'video'">
            <rect x="3" y="5" width="18" height="14" rx="3" />
            <path d="M10 9.5v5l4.5-2.5z" />
        </template>
        <template v-else-if="icon === 'flyer'">
            <path d="M6 3h9l4 4v14H6z" />
            <path d="M15 3v4h4" />
            <path d="M9 17l2.2-2.5 1.8 1.8 2-2.8" />
        </template>
        <template v-else-if="icon === 'kit'">
            <rect x="3" y="7" width="18" height="13" rx="2" />
            <path d="M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2M3 13h18" />
        </template>
        <template v-else>
            <rect x="4" y="4" width="16" height="16" rx="3" />
        </template>
    </svg>
</template>
