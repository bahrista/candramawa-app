<script setup>
import { computed, ref, watch } from 'vue';
import { parseDriveLink } from '../../lib/drive';

const props = defineProps({
    url: { type: String, default: '' },
    // 'thumb' shows the still image Drive generates; 'frame' shows Drive's own viewer (video, private files).
    mode: { type: String, default: 'thumb' },
    fit: { type: String, default: 'cover' },
    // Small tiles never load Drive's heavy viewer: they show a plain placeholder instead.
    frameFallback: { type: Boolean, default: true },
});
const emit = defineEmits(['update:mode']);

const link = computed(() => parseDriveLink(props.url));
const failed = ref(false);
watch(() => props.url, () => (failed.value = false));

function onError() {
    if (props.frameFallback) emit('update:mode', 'frame');
    else failed.value = true;
}
</script>

<template>
    <div class="relative h-full w-full overflow-hidden bg-slate-200">
        <template v-if="link">
            <iframe
                v-if="link.kind === 'folder' || mode === 'frame'"
                :key="link.id"
                :src="link.previewUrl"
                class="h-full w-full border-0 bg-white"
                loading="lazy"
                allow="autoplay; fullscreen"
                referrerpolicy="no-referrer"
                :title="link.kind === 'folder' ? 'Google Drive folder' : 'Google Drive file'"
            />
            <div v-else-if="failed" class="flex h-full w-full items-center justify-center px-1 text-center text-[10px] leading-tight text-slate-500">No preview</div>
            <img
                v-else
                :key="link.id"
                :src="link.thumbUrl"
                alt=""
                referrerpolicy="no-referrer"
                class="h-full w-full"
                :class="fit === 'contain' ? 'object-contain' : 'object-cover'"
                @error="onError"
            />
        </template>

        <div v-else class="flex h-full w-full flex-col items-center justify-center gap-1 px-6 text-center text-xs text-slate-500">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="3" width="18" height="18" rx="3" />
                <circle cx="9" cy="9" r="1.5" />
                <path d="M21 15l-5-5L5 21" />
            </svg>
            <span v-if="url">This doesn't look like a Google Drive link.</span>
            <span v-else>Paste a Google Drive link in <b>Post detail</b> to preview it here.</span>
        </div>
    </div>
</template>
