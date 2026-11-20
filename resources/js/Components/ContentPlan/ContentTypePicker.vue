<script setup>
import { computed, nextTick, reactive, ref } from 'vue';
import PlatformIcon from './PlatformIcon.vue';
import TypeIcon from './TypeIcon.vue';

// A compact, collapsible tree (like the placements picker in Meta Ads): platforms are the parents,
// content types the children. Ticking a platform ticks all of its types. It scales to many types:
// groups stay closed until needed and the search box narrows the list.
const props = defineProps({
    tree: { type: Array, required: true },
    modelValue: { type: Array, default: () => [] }, // selected type keys
    disabled: Boolean,
    error: String,
});
const emit = defineEmits(['change']); // { add: [keys], remove: [keys] }: the parent may ask for confirmation first

const query = ref('');
const open = reactive({}); // platform key -> true/false once the person has chosen
const selected = computed(() => new Set(props.modelValue));
const flat = computed(() => Object.fromEntries(props.tree.flatMap((p) => p.types.map((t) => [t.key, t]))));
const chips = computed(() => props.modelValue.map((k) => flat.value[k]).filter(Boolean));

const needle = computed(() => query.value.trim().toLowerCase());
// While searching, a platform shows only the types that match (or all of them if the platform itself matches).
const visibleTypes = (p) =>
    !needle.value || p.label.toLowerCase().includes(needle.value) ? p.types : p.types.filter((t) => t.full_label.toLowerCase().includes(needle.value));
const visibleGroups = computed(() => props.tree.filter((p) => visibleTypes(p).length > 0));

const countOf = (p) => p.types.filter((t) => selected.value.has(t.key)).length;
const isOpen = (p) => (needle.value ? true : (open[p.key] ?? countOf(p) > 0));
const toggleOpen = (p) => (open[p.key] = !isOpen(p));
const setAll = (value) => props.tree.forEach((p) => (open[p.key] = value));

const keep = (event, checkedNow) => nextTick(() => (event.target.checked = checkedNow()));

function toggleType(event, key) {
    const on = selected.value.has(key);
    emit('change', { add: on ? [] : [key], remove: on ? [key] : [] });
    keep(event, () => selected.value.has(key)); // the parent may have refused (e.g. a cancelled confirmation)
}

function toggleGroup(event, p) {
    const keys = p.types.map((t) => t.key);
    const all = keys.every((k) => selected.value.has(k));
    emit('change', all ? { add: [], remove: keys } : { add: keys.filter((k) => !selected.value.has(k)), remove: [] });
    keep(event, () => keys.every((k) => selected.value.has(k)));
}

const remove = (key) => emit('change', { add: [], remove: [key] });
</script>

<template>
    <fieldset :disabled="disabled">
        <legend class="mb-1 text-sm font-medium text-slate-700">Content type <span class="text-red-500" aria-hidden="true">*</span> <span class="font-normal text-slate-400">(choose one or more)</span></legend>

        <div class="rounded-md border bg-white" :class="error ? 'border-red-400' : 'border-slate-300'">
            <!-- search + expand/collapse -->
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-2">
                <input v-model="query" type="search" placeholder="Search content types…" aria-label="Search content types" class="min-w-[10rem] flex-1 rounded border border-slate-200 px-2.5 py-1.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                <div class="flex gap-3 text-xs">
                    <button type="button" class="text-indigo-600 hover:underline" @click="setAll(true)">Expand all</button>
                    <button type="button" class="text-indigo-600 hover:underline" @click="setAll(false)">Collapse all</button>
                </div>
            </div>

            <!-- what is chosen -->
            <div class="flex min-h-[2.5rem] flex-wrap items-center gap-1.5 border-b border-slate-200 bg-slate-50 px-2 py-1.5" aria-live="polite">
                <span v-if="!chips.length" class="px-1 text-xs text-slate-500">Nothing chosen yet.</span>
                <span v-for="t in chips" :key="t.key" class="inline-flex items-center gap-1 rounded-full bg-indigo-50 py-0.5 pl-2 pr-1 text-xs font-medium text-indigo-700">
                    <TypeIcon :icon="t.icon" :size="13" />
                    {{ t.full_label }}
                    <button v-if="!disabled" type="button" class="rounded-full px-1 text-indigo-400 hover:bg-indigo-100 hover:text-indigo-700" :aria-label="`Remove ${t.full_label}`" @click="remove(t.key)">×</button>
                </span>
            </div>

            <!-- the tree -->
            <ul>
                <li v-for="p in visibleGroups" :key="p.key" class="border-b border-slate-100 last:border-0">
                    <div class="flex items-center gap-3 px-3 py-2">
                        <input
                            type="checkbox"
                            class="h-4 w-4 rounded border-slate-300"
                            :checked="countOf(p) === p.types.length"
                            :indeterminate.prop="countOf(p) > 0 && countOf(p) < p.types.length"
                            :aria-label="`Select all ${p.label} content types`"
                            @change="toggleGroup($event, p)"
                        />
                        <button type="button" class="flex min-w-0 flex-1 items-center gap-2 text-left text-sm font-medium text-slate-800" :aria-expanded="isOpen(p)" @click="toggleOpen(p)">
                            <PlatformIcon :platform="p.key" :size="18" />
                            {{ p.label }}
                            <span class="text-xs font-normal text-slate-500">{{ countOf(p) }} of {{ p.types.length }}</span>
                            <svg class="ml-auto h-4 w-4 shrink-0 text-slate-400 transition-transform" :class="isOpen(p) ? 'rotate-90' : ''" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
                        </button>
                    </div>

                    <div v-show="isOpen(p)" class="grid gap-1.5 px-3 pb-3 pl-10 sm:grid-cols-2">
                        <label
                            v-for="t in visibleTypes(p)"
                            :key="t.key"
                            class="flex cursor-pointer items-center gap-2.5 rounded-md border px-2.5 py-2 text-sm transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-indigo-500"
                            :class="[selected.has(t.key) ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-slate-200 hover:bg-slate-50', disabled && 'pointer-events-none opacity-60']"
                        >
                            <input type="checkbox" class="sr-only" :checked="selected.has(t.key)" :disabled="disabled" @change="toggleType($event, t.key)" />
                            <TypeIcon :icon="t.icon" :size="18" />
                            <span class="font-medium">{{ t.label }}</span>
                            <svg v-if="selected.has(t.key)" class="ml-auto" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7" /></svg>
                        </label>
                    </div>
                </li>
                <li v-if="!visibleGroups.length" class="px-3 py-6 text-center text-sm text-slate-500">No content type matches “{{ query }}”.</li>
            </ul>
        </div>
        <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    </fieldset>
</template>
