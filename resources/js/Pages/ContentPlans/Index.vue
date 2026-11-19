<script setup>
import { reactive, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Avatar from '../../Components/Avatar.vue';
import BrandLogo from '../../Components/BrandLogo.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import PostTypeIcon from '../../Components/ContentPlan/PostTypeIcon.vue';

const props = defineProps({
    plans: Object,
    filters: Object,
    counts: Object,
    statuses: Array,
    postTypes: Array,
    brands: Array,
});

const f = reactive({
    search: props.filters.search,
    brand: props.filters.brand ?? '',
    post_type: props.filters.post_type ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    mine: props.filters.mine,
});
watch(
    () => props.filters,
    (n) => Object.assign(f, { search: n.search, brand: n.brand ?? '', post_type: n.post_type ?? '', from: n.from ?? '', to: n.to ?? '', mine: n.mine }),
);

function visit(extra = {}) {
    const params = { ...f, status: props.filters.status, sort: props.filters.sort, dir: props.filters.dir, ...extra };
    const clean = Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== undefined && v !== false));
    router.get('/content-plans', clean, { preserveState: true, preserveScroll: true, replace: true });
}

let timer;
const searchLater = () => {
    clearTimeout(timer);
    timer = setTimeout(() => visit(), 350);
};

const sortBy = (key) => visit({ sort: key, dir: props.filters.sort === key && props.filters.dir === 'asc' ? 'desc' : key === 'title' ? 'asc' : props.filters.sort === key ? 'asc' : 'desc' });
const arrow = (key) => (props.filters.sort === key ? (props.filters.dir === 'asc' ? ' ▲' : ' ▼') : '');

const hasFilters = () => f.search || f.brand || f.post_type || f.from || f.to || f.mine || props.filters.status;

const remove = (plan) => {
    if (window.confirm(`Delete the draft "${plan.title}"? This cannot be undone.`)) router.delete(`/content-plans/${plan.id}`, { preserveScroll: true });
};

// "Sat, 5 Dec 2026 · 19:30" -> date on one line, time under it.
const split = (label) => (label ? label.split(' · ') : []);

const postTypeLabel = (value) => props.postTypes.find((t) => t.value === value)?.label ?? value;
const input = 'rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500';
</script>

<template>
    <Head title="All Content" />
    <AppLayout>
        <div class="mb-3 flex items-center justify-between">
            <h1 class="text-2xl font-semibold">All Content</h1>
            <Link href="/content-plans/create" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Add Content Plan</Link>
        </div>

        <!-- Status links, WordPress style -->
        <ul class="mb-4 flex flex-wrap items-center gap-x-1 text-sm">
            <li>
                <button type="button" :class="!filters.status ? 'font-semibold text-slate-900' : 'text-indigo-600 hover:underline'" @click="visit({ status: '' })">
                    All <span class="font-normal text-slate-500">({{ counts.all }})</span>
                </button>
            </li>
            <li v-for="s in statuses" :key="s.value" class="flex items-center gap-1">
                <span class="text-slate-300">|</span>
                <button type="button" :class="filters.status === s.value ? 'font-semibold text-slate-900' : 'text-indigo-600 hover:underline'" @click="visit({ status: s.value })">
                    {{ s.label }} <span class="font-normal text-slate-500">({{ counts[s.value] }})</span>
                </button>
            </li>
        </ul>

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <input v-model="f.search" type="search" placeholder="Search title or brand" :class="[input, 'w-56']" @input="searchLater" @keydown.enter="visit()" />
            <select v-model="f.brand" :class="input" aria-label="Brand" @change="visit()">
                <option value="">All brands</option>
                <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
            <select v-model="f.post_type" :class="input" aria-label="Post type" @change="visit()">
                <option value="">All post types</option>
                <option v-for="t in postTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
            <label class="flex items-center gap-1.5 text-sm text-slate-600">
                Publish from <input v-model="f.from" type="date" :class="input" @change="visit()" />
            </label>
            <label class="flex items-center gap-1.5 text-sm text-slate-600">
                to <input v-model="f.to" type="date" :class="input" @change="visit()" />
            </label>
            <label class="flex items-center gap-1.5 text-sm text-slate-700">
                <input v-model="f.mine" type="checkbox" @change="visit()" /> Assigned to me
            </label>
            <Link v-if="hasFilters()" href="/content-plans" class="text-sm text-indigo-600 hover:underline">Reset</Link>
        </div>

        <div class="overflow-x-auto rounded-lg border border-[#dcdcde] bg-white">
            <table class="w-full min-w-[980px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="min-w-[13rem] px-3 py-2.5"><button type="button" class="font-semibold uppercase hover:text-slate-800" @click="sortBy('title')">Content{{ arrow('title') }}</button></th>
                        <th class="px-3 py-2.5">Brand</th>
                        <th class="px-3 py-2.5">Post type</th>
                        <th class="px-3 py-2.5"><button type="button" class="font-semibold uppercase hover:text-slate-800" @click="sortBy('publish')">Publish{{ arrow('publish') }}</button></th>
                        <th class="px-3 py-2.5"><button type="button" class="font-semibold uppercase hover:text-slate-800" @click="sortBy('deadline')">Deadline{{ arrow('deadline') }}</button></th>
                        <th class="px-3 py-2.5">Status</th>
                        <th class="px-3 py-2.5">Team</th>
                        <th class="px-3 py-2.5"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in plans.data" :key="p.id" class="border-t border-[#f0f0f1] align-top hover:bg-[#f6f7f7]">
                        <td class="min-w-[13rem] px-3 py-3">
                            <Link :href="`/content-plans/${p.id}`" class="font-medium text-indigo-600 hover:underline">{{ p.title }}</Link>
                            <div v-if="p.content_pillar || p.format" class="mt-0.5 text-xs text-slate-500">{{ [p.content_pillar, p.format].filter(Boolean).join(' · ') }}</div>
                        </td>
                        <td class="px-3 py-3">
                            <div v-if="p.brand" class="flex items-center gap-2">
                                <BrandLogo :name="p.brand.name" :url="p.brand.logo_url" :color="p.brand.color" :size="26" />
                                <span class="max-w-[7rem] truncate" :title="p.brand.name">{{ p.brand.name }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3">
                            <div v-if="p.post_types.length" class="flex gap-1.5 text-slate-600">
                                <span v-for="t in p.post_types" :key="t" :title="postTypeLabel(t)"><PostTypeIcon :type="t" :size="20" /></span>
                            </div>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-3">
                            <template v-if="p.publish_label"><div>{{ split(p.publish_label)[0] }}</div><div class="text-xs text-slate-500">{{ split(p.publish_label)[1] }}</div></template>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-3">
                            <template v-if="p.deadline_label">
                                <div :class="p.is_overdue ? 'font-medium text-red-600' : ''">{{ split(p.deadline_label)[0] }}</div>
                                <div class="text-xs" :class="p.is_overdue ? 'text-red-600' : 'text-slate-500'">
                                    {{ split(p.deadline_label)[1] }}
                                    <span v-if="p.is_overdue" class="ml-1 rounded bg-red-50 px-1.5 py-0.5 text-[11px] font-medium text-red-700">Overdue</span>
                                </div>
                            </template>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-3 py-3"><StatusBadge :status="p.status" :label="p.status_label" /></td>
                        <td class="px-3 py-3">
                            <div class="flex -space-x-1.5">
                                <span v-for="(person, role) in p.team" :key="role" :title="`${role}: ${person.name}`" class="rounded-full ring-2 ring-white">
                                    <Avatar :name="person.name" :url="person.avatar_url" :size="28" />
                                </span>
                            </div>
                        </td>
                        <td class="space-x-3 whitespace-nowrap px-3 py-3 text-right">
                            <Link :href="`/content-plans/${p.id}`" class="text-indigo-600 hover:underline">Open</Link>
                            <button v-if="p.can_delete" type="button" class="text-red-600 hover:underline" @click="remove(p)">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="!plans.data.length">
                        <td colspan="8" class="px-4 py-12 text-center text-slate-500">
                            <template v-if="hasFilters()">No content matches these filters.</template>
                            <template v-else>No content yet. <Link href="/content-plans/create" class="text-indigo-600 hover:underline">Add your first content plan</Link>.</template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="plans.last_page > 1" class="mt-4 flex flex-wrap gap-1">
            <template v-for="l in plans.links" :key="l.label">
                <Link v-if="l.url" :href="l.url" v-html="l.label" class="rounded border px-3 py-1 text-sm" :class="l.active ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 bg-white'" />
                <span v-else v-html="l.label" class="rounded border border-slate-200 px-3 py-1 text-sm text-slate-400" />
            </template>
        </div>
    </AppLayout>
</template>
