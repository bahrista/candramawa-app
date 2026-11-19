<script setup>
import InstagramPreview from './InstagramPreview.vue';
import PostTypeIcon from './PostTypeIcon.vue';

defineProps({
    brand: { type: Object, default: null },
    types: { type: Array, default: () => [] }, // [{ value, label }] for the post types ticked in the brief
    posts: { type: Object, default: () => ({}) },
    activeType: { type: String, default: null },
    publishAt: { type: String, default: '' },
});
const emit = defineEmits(['update:activeType']);

const short = (label) => label.replace('Instagram ', '');
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-[#dcdcde] bg-white">
        <div class="flex items-center justify-between border-b border-[#f0f0f1] px-4 py-3">
            <h2 class="font-medium">Preview</h2>
            <span class="text-xs text-slate-500">Approximate</span>
        </div>

        <div v-if="types.length" class="p-4">
            <div role="tablist" aria-label="Post type preview" class="mb-4 flex flex-wrap gap-1.5">
                <button
                    v-for="t in types"
                    :key="t.value"
                    type="button"
                    role="tab"
                    :aria-selected="activeType === t.value"
                    class="flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition"
                    :class="activeType === t.value ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50'"
                    @click="emit('update:activeType', t.value)"
                >
                    <PostTypeIcon :type="t.value" :size="14" />
                    {{ short(t.label) }}
                </button>
            </div>

            <InstagramPreview
                v-if="activeType && posts[activeType]"
                :key="activeType"
                :type="activeType"
                :brand="brand"
                :media="posts[activeType].media_links"
                :caption="posts[activeType].caption"
                :tags="posts[activeType].tags"
                :publish-at="publishAt"
            />
        </div>
        <div v-else class="px-6 py-14 text-center text-sm text-slate-500">
            Tick a post type in <b>The brief</b> to see its preview here.
        </div>

        <p class="border-t border-[#f0f0f1] bg-slate-50 px-4 py-2 text-xs text-slate-500">
            Previews load from Google Drive. Share each file as <b>“Anyone with the link”</b>, or be signed in to a Google account that has access.
        </p>
    </div>
</template>
