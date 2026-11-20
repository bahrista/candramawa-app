<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({ items: { type: Array, required: true } }); // [{ label, href? }]
</script>

<template>
    <nav aria-label="Breadcrumb" class="mb-3 flex flex-wrap items-center gap-1.5 text-sm text-slate-500">
        <template v-for="(item, i) in items" :key="i">
            <span v-if="i > 0" aria-hidden="true">›</span>
            <Link v-if="item.href" :href="item.href" class="hover:text-indigo-600 hover:underline">{{ item.label }}</Link>
            <span v-else class="text-slate-700">{{ item.label }}</span>
        </template>
    </nav>
</template>
