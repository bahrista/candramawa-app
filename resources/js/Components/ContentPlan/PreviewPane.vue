<script setup>
import { computed } from 'vue';
import ContentPreview from './ContentPreview.vue';
import PlatformIcon from './PlatformIcon.vue';
import TypeIcon from './TypeIcon.vue';

const props = defineProps({
    brand: { type: Object, default: null },
    types: { type: Array, default: () => [] }, // the ticked content types (registry entries), in tree order
    posts: { type: Object, default: () => ({}) },
    activeType: { type: String, default: null },
    publishAt: { type: String, default: '' },
});
const emit = defineEmits(['update:activeType']);

const active = computed(() => props.types.find((t) => t.key === props.activeType) ?? null);

// Two levels keep it compact however many types are ticked: platform first, then its types.
const platforms = computed(() => {
    const seen = new Map();
    for (const t of props.types) if (!seen.has(t.platform)) seen.set(t.platform, { key: t.platform, label: t.platform_label });
    return [...seen.values()];
});
const ofPlatform = computed(() => props.types.filter((t) => t.platform === active.value?.platform));

const pickPlatform = (key) => emit('update:activeType', props.types.find((t) => t.platform === key).key);
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-[#dcdcde] bg-white">
        <div class="flex items-center justify-between border-b border-[#f0f0f1] px-4 py-3">
            <h2 class="font-medium">Preview</h2>
            <span class="text-xs text-slate-500">Approximate</span>
        </div>

        <div v-if="active" class="p-4">
            <div v-if="platforms.length > 1" role="tablist" aria-label="Platform" class="mb-2 flex flex-wrap gap-1.5">
                <button
                    v-for="p in platforms"
                    :key="p.key"
                    type="button"
                    role="tab"
                    :aria-selected="active.platform === p.key"
                    class="flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-semibold transition"
                    :class="active.platform === p.key ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                    @click="pickPlatform(p.key)"
                >
                    <PlatformIcon :platform="p.key" :size="14" /> {{ p.label }}
                </button>
            </div>

            <div role="tablist" aria-label="Content type" class="mb-4 flex flex-wrap gap-1.5">
                <button
                    v-for="t in ofPlatform"
                    :key="t.key"
                    type="button"
                    role="tab"
                    :aria-selected="activeType === t.key"
                    class="flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition"
                    :class="activeType === t.key ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50'"
                    @click="emit('update:activeType', t.key)"
                >
                    <TypeIcon :icon="t.icon" :size="14" /> {{ t.label }}
                </button>
            </div>

            <ContentPreview
                v-if="posts[active.key]"
                :key="active.key"
                :type="active"
                :brand="brand"
                :media="posts[active.key].media_links"
                :caption="posts[active.key].caption"
                :tags="posts[active.key].tags"
                :publish-at="publishAt"
            />
        </div>
        <div v-else class="px-6 py-14 text-center text-sm text-slate-500">
            Choose a content type in <b>The brief</b> to see its preview here.
        </div>

        <p class="border-t border-[#f0f0f1] bg-slate-50 px-4 py-2 text-xs text-slate-500">
            Previews load from Google Drive. Share each file as <b>“Anyone with the link”</b>, or be signed in to a Google account that has access.
        </p>
    </div>
</template>
