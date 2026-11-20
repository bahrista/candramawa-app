<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({ brand: Object, team: Object, pillars: Array, knowledge: Array, products: Array, stats: Object, abilities: Object });

const people = computed(() => new Set(Object.values(props.team).flat().map((m) => m.id)).size);
const cards = computed(() => [
    { label: 'Team members', value: people.value, tab: 'team' },
    { label: 'Content pillars', value: props.pillars.filter((p) => !p.archived).length, tab: 'pillars' },
    { label: 'Knowledge entries', value: props.knowledge.length, tab: 'knowledge' },
    { label: 'Products & services', value: props.products.length, tab: 'products' },
]);
</script>

<template>
    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Link v-for="c in cards" :key="c.tab" :href="`/brands/${brand.id}?tab=${c.tab}`" preserve-scroll class="rounded-lg border border-[#dcdcde] bg-white p-4 hover:border-indigo-500">
                <div class="text-2xl font-semibold">{{ c.value }}</div>
                <div class="text-sm text-slate-500">{{ c.label }}</div>
            </Link>
        </div>

        <section class="rounded-lg border border-[#dcdcde] bg-white p-5">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-medium">Content</h2>
                <div class="flex gap-2 text-sm">
                    <Link :href="`/content-plans?brand=${brand.id}`" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-50">View all content</Link>
                    <Link v-if="abilities.createContent" :href="`/content-plans/create?brand=${brand.id}`" class="rounded-md bg-indigo-600 px-3 py-1.5 font-medium text-white hover:bg-indigo-700">Plan new content</Link>
                </div>
            </div>
            <dl class="grid gap-4 text-sm sm:grid-cols-3">
                <div><dt class="text-slate-500">Content plans you can see</dt><dd class="text-lg font-semibold">{{ stats.plans }}</dd></div>
                <div><dt class="text-slate-500">In production</dt><dd class="text-lg font-semibold">{{ stats.in_production }}</dd></div>
                <div><dt class="text-slate-500">In review</dt><dd class="text-lg font-semibold">{{ stats.in_review }}</dd></div>
            </dl>
        </section>

        <section class="rounded-lg border border-[#dcdcde] bg-white p-5 text-sm">
            <h2 class="mb-2 font-medium">About</h2>
            <p class="whitespace-pre-wrap text-slate-700">{{ brand.description || 'No description yet.' }}</p>
            <p class="mt-3 text-xs text-slate-500">Created {{ brand.created_label }}<template v-if="brand.created_by"> by {{ brand.created_by }}</template></p>
        </section>
    </div>
</template>
