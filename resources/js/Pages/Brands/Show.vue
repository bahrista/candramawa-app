<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import BrandLogo from '../../Components/BrandLogo.vue';
import Breadcrumb from '../../Components/Breadcrumb.vue';
import OverviewTab from '../../Components/Brand/OverviewTab.vue';
import TeamTab from '../../Components/Brand/TeamTab.vue';
import PillarsTab from '../../Components/Brand/PillarsTab.vue';
import KnowledgeList from '../../Components/Brand/KnowledgeList.vue';
import ProductList from '../../Components/Brand/ProductList.vue';

const props = defineProps({
    brand: Object,
    tab: String,
    abilities: Object,
    roles: Array,
    team: Object,
    users: Array,
    pillars: Array,
    knowledge: Array,
    products: Array,
    stats: Object,
});

const tabs = computed(() => [
    { key: 'overview', label: 'Overview' },
    { key: 'team', label: 'Team' },
    { key: 'pillars', label: 'Content Pillars', count: props.pillars.length },
    { key: 'knowledge', label: 'Knowledge Base', count: props.knowledge.length },
    { key: 'products', label: 'Products & Services', count: props.products.length },
]);
</script>

<template>
    <Head :title="brand.name" />
    <AppLayout>
        <Breadcrumb :items="[{ label: 'Brands', href: '/brands' }, { label: brand.name }]" />

        <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-4">
                <BrandLogo :name="brand.name" :url="brand.logo_url" :color="brand.color" :size="56" />
                <div class="min-w-0">
                    <h1 class="truncate text-2xl font-semibold">{{ brand.name }}</h1>
                    <p v-if="brand.description" class="line-clamp-1 text-sm text-slate-500">{{ brand.description }}</p>
                </div>
            </div>
            <div class="flex gap-2 text-sm">
                <Link v-if="abilities.update" :href="`/brands/${brand.id}/edit`" class="rounded-md border border-slate-300 bg-white px-3 py-2 font-medium text-slate-700 hover:bg-slate-50">Edit brand</Link>
                <Link v-if="abilities.createContent" :href="`/content-plans/create?brand=${brand.id}`" class="rounded-md bg-indigo-600 px-3 py-2 font-medium text-white hover:bg-indigo-700">Plan new content</Link>
            </div>
        </div>

        <div role="tablist" class="mb-5 flex flex-wrap gap-x-1 border-b border-[#dcdcde]">
            <Link
                v-for="t in tabs"
                :key="t.key"
                :href="`/brands/${brand.id}?tab=${t.key}`"
                role="tab"
                :aria-selected="tab === t.key"
                preserve-scroll
                preserve-state
                replace
                class="-mb-px flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium"
                :class="tab === t.key ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-800'"
            >
                {{ t.label }}
                <span v-if="t.count !== undefined" class="rounded-full bg-slate-100 px-1.5 text-[11px] leading-5 text-slate-600">{{ t.count }}</span>
            </Link>
        </div>

        <OverviewTab v-if="tab === 'overview'" :brand="brand" :team="team" :pillars="pillars" :knowledge="knowledge" :products="products" :stats="stats" :abilities="abilities" />
        <TeamTab v-else-if="tab === 'team'" :key="'team'" :brand="brand" :roles="roles" :team="team" :users="users" :can-edit="abilities.update" />
        <PillarsTab v-else-if="tab === 'pillars'" :brand="brand" :pillars="pillars" :can-manage="abilities.manageAssets" />
        <KnowledgeList v-else-if="tab === 'knowledge'" :brand="brand" :items="knowledge" :can-manage="abilities.manageAssets" />
        <ProductList v-else-if="tab === 'products'" :brand="brand" :items="products" :can-manage="abilities.manageAssets" />
    </AppLayout>
</template>
