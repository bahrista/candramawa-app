<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { cleanedRefIndex } from '../../lib/forms';

// The brief's references: ordinary links, plus entries from the brand's knowledge base and products,
// which are stored by id so they always show the current title and share link.
const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    brand: { type: Object, default: null }, // { id, knowledge: [{id,title,category}], products: [{id,name,type}] }
    limit: { type: Number, default: 15 },
    errors: { type: Object, default: () => ({}) },
    disabled: Boolean,
});
const emit = defineEmits(['update:modelValue']);

const panel = ref(false);

const has = (kind, id) => props.modelValue.some((r) => r.kind === kind && r.id === id);
const atLimit = computed(() => props.modelValue.length >= props.limit);

const set = (list) => emit('update:modelValue', list);
const addLink = () => set([...props.modelValue, { kind: 'link', url: '' }]);
const addEntry = (kind, item) =>
    set([...props.modelValue, { kind, id: item.id, label: kind === 'knowledge' ? item.title : item.name, url: `/brands/${props.brand.id}/${kind === 'knowledge' ? 'knowledge' : 'products'}/${item.id}` }]);
const remove = (i) => set(props.modelValue.filter((_, idx) => idx !== i));
const edit = (i, url) => set(props.modelValue.map((r, idx) => (idx === i ? { ...r, url } : r)));

const isHttp = (v) => /^https?:\/\/\S+$/i.test((v || '').trim());
const errorFor = (r, i) => {
    const n = cleanedRefIndex(props.modelValue, i);
    return props.errors[`brief_references.${n}.${r.kind === 'link' ? 'url' : 'id'}`];
};
const kindLabel = (r) => (r.kind === 'knowledge' ? 'Knowledge base' : 'Product / service');
</script>

<template>
    <div>
        <ul class="space-y-2">
            <li v-for="(r, i) in modelValue" :key="`${r.kind}-${r.id ?? i}`">
                <!-- a plain link -->
                <div v-if="r.kind === 'link'" class="flex items-center gap-2">
                    <input
                        :value="r.url"
                        type="url"
                        placeholder="https://…"
                        :disabled="disabled"
                        :aria-label="`Reference link ${i + 1}`"
                        class="min-w-0 flex-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:bg-slate-100"
                        :class="{ 'border-red-400': errorFor(r, i) }"
                        @input="edit(i, $event.target.value)"
                    />
                    <a v-if="isHttp(r.url)" :href="r.url.trim()" target="_blank" rel="noopener noreferrer" class="rounded-md border border-slate-300 px-2 py-2 text-xs text-slate-600 hover:bg-slate-50" title="Open link">↗</a>
                    <button v-if="!disabled" type="button" class="rounded-md px-2 py-2 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Remove reference" @click="remove(i)">✕</button>
                </div>

                <!-- an entry from the brand's knowledge base or products -->
                <div v-else class="flex items-center gap-2 rounded-md border border-indigo-100 bg-indigo-50/60 px-3 py-2 text-sm">
                    <svg class="shrink-0 text-indigo-500" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path v-if="r.kind === 'knowledge'" d="M4 5a2 2 0 012-2h13v16H6a2 2 0 00-2 2zM4 19a2 2 0 012-2h13" />
                        <path v-else d="M21 8l-9-5-9 5v8l9 5 9-5zM3 8l9 5 9-5M12 13v8" />
                    </svg>
                    <a v-if="r.url && !r.missing" :href="r.url" target="_blank" rel="noopener noreferrer" class="min-w-0 truncate font-medium text-indigo-700 hover:underline">{{ r.label }}</a>
                    <span v-else class="min-w-0 truncate text-slate-500 line-through">{{ r.label }}</span>
                    <span class="shrink-0 text-xs text-slate-500">{{ kindLabel(r) }}</span>
                    <button v-if="!disabled" type="button" class="ml-auto rounded px-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Remove reference" @click="remove(i)">✕</button>
                </div>
                <p v-if="errorFor(r, i)" class="mt-1 text-xs text-red-600">{{ errorFor(r, i) }}</p>
            </li>
        </ul>

        <div v-if="!disabled" class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm">
            <button type="button" class="text-indigo-600 hover:underline disabled:opacity-40" :disabled="atLimit" @click="addLink">+ Add link</button>
            <button type="button" class="text-indigo-600 hover:underline disabled:opacity-40" :disabled="atLimit" :aria-expanded="panel" @click="panel = !panel">
                + Add from knowledge base &amp; products
            </button>
        </div>
        <p v-if="atLimit" class="mt-1 text-xs text-slate-500">You can add up to {{ limit }} references.</p>
        <p v-if="errors.brief_references" class="mt-1 text-xs text-red-600">{{ errors.brief_references }}</p>

        <!-- picker -->
        <div v-if="panel && !disabled" class="mt-3 rounded-md border border-slate-200 bg-slate-50 p-3 text-sm">
            <p v-if="!brand" class="text-slate-500">Choose a brand first.</p>
            <template v-else>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <h3 class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Knowledge base</h3>
                        <ul v-if="brand.knowledge.length" class="space-y-1">
                            <li v-for="k in brand.knowledge" :key="k.id" class="flex items-center justify-between gap-2">
                                <span class="min-w-0 truncate">{{ k.title }}<span v-if="k.category" class="text-xs text-slate-400"> · {{ k.category }}</span></span>
                                <button type="button" class="shrink-0 text-indigo-600 hover:underline disabled:text-slate-400 disabled:no-underline" :disabled="has('knowledge', k.id) || atLimit" @click="addEntry('knowledge', k)">{{ has('knowledge', k.id) ? 'Added' : 'Add' }}</button>
                            </li>
                        </ul>
                        <p v-else class="text-slate-500">No entries yet. <Link :href="`/brands/${brand.id}?tab=knowledge`" class="text-indigo-600 hover:underline">Open the knowledge base</Link></p>
                    </div>
                    <div>
                        <h3 class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Products &amp; services</h3>
                        <ul v-if="brand.products.length" class="space-y-1">
                            <li v-for="p in brand.products" :key="p.id" class="flex items-center justify-between gap-2">
                                <span class="min-w-0 truncate">{{ p.name }}<span class="text-xs capitalize text-slate-400"> · {{ p.type }}</span></span>
                                <button type="button" class="shrink-0 text-indigo-600 hover:underline disabled:text-slate-400 disabled:no-underline" :disabled="has('product', p.id) || atLimit" @click="addEntry('product', p)">{{ has('product', p.id) ? 'Added' : 'Add' }}</button>
                            </li>
                        </ul>
                        <p v-else class="text-slate-500">No entries yet. <Link :href="`/brands/${brand.id}?tab=products`" class="text-indigo-600 hover:underline">Open products &amp; services</Link></p>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
