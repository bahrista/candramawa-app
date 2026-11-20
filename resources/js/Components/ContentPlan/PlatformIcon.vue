<script setup>
defineProps({ platform: String, size: { type: Number, default: 20 } });
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <template v-if="platform === 'instagram'">
            <rect x="3" y="3" width="18" height="18" rx="5" />
            <circle cx="12" cy="12" r="4" />
            <circle cx="17.2" cy="6.8" r=".8" fill="currentColor" stroke="none" />
        </template>
        <template v-else-if="platform === 'facebook'">
            <circle cx="12" cy="12" r="9" />
            <path d="M14.5 8H13a1.5 1.5 0 00-1.5 1.5V21M9 13h5.5" />
        </template>
        <template v-else-if="platform === 'tiktok'">
            <path d="M14 4v10.5a3.5 3.5 0 11-3.5-3.5" />
            <path d="M14 4c.3 2.3 1.8 3.8 4 4" />
        </template>
        <template v-else>
            <rect x="4" y="4" width="7" height="7" rx="1.5" />
            <rect x="13" y="4" width="7" height="7" rx="1.5" />
            <rect x="4" y="13" width="7" height="7" rx="1.5" />
            <rect x="13" y="13" width="7" height="7" rx="1.5" />
        </template>
    </svg>
</template>
