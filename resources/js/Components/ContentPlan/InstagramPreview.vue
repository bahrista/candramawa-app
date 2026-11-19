<script setup>
import { computed, ref, watch } from 'vue';
import BrandLogo from '../BrandLogo.vue';
import DriveMedia from './DriveMedia.vue';
import { dayMonth } from '../../lib/dates';
import { parseDriveLink } from '../../lib/drive';

const props = defineProps({
    type: String,
    brand: { type: Object, default: null },
    media: { type: Array, default: () => [] },
    caption: { type: String, default: '' },
    tags: { type: Array, default: () => [] },
    publishAt: { type: String, default: '' },
});

const links = computed(() => props.media.filter((m) => m && m.trim() !== ''));
const index = ref(0);
const current = computed(() => links.value[Math.min(index.value, Math.max(links.value.length - 1, 0))] ?? '');
const go = (step) => (index.value = (index.value + step + links.value.length) % links.value.length);

const link = computed(() => parseDriveLink(current.value));
const mode = ref('thumb');
watch(current, () => (mode.value = 'thumb'));

const name = computed(() => props.brand?.name ?? 'your_brand');
const handle = computed(() => name.value.toLowerCase().replace(/[^a-z0-9._]+/g, '') || 'your_brand');
const hashtags = computed(() => props.tags.filter((t) => t.startsWith('#')).join(' '));
const mentions = computed(() => props.tags.filter((t) => t.startsWith('@')));

const expanded = ref(false);
const longCaption = computed(() => props.caption.length > 110 || props.caption.includes('\n'));
const date = computed(() => dayMonth(props.publishAt));

const isCarousel = computed(() => props.type === 'instagram_carousel');
const heart = 'M12 21s-7.5-4.6-9.5-9.2C1.2 8.6 3 5 6.4 5c2 0 3.5 1.1 4.1 2.4.2.4.8.4 1 0C12.1 6.1 13.6 5 15.6 5 19 5 20.8 8.6 21.5 11.8 19.5 16.4 12 21 12 21z';
const comment = 'M21 12a8 8 0 01-11.6 7.1L3 21l1.9-5.4A8 8 0 1121 12z';
const send = 'M22 3L9.5 13.5M22 3l-7 18-5.5-7.5L2 9z';
const save = 'M6 3h12v18l-6-4-6 4z';
</script>

<template>
  <div>
    <!-- Feed and Carousel -->
    <div v-if="type === 'instagram_feed' || type === 'instagram_carousel'" class="mx-auto w-full max-w-[360px] overflow-hidden rounded-lg border border-slate-200 bg-white text-[13px] text-slate-900 shadow-sm">
        <div class="flex items-center gap-2.5 px-3 py-2">
            <BrandLogo :name="name" :url="brand?.logo_url" :color="brand?.color" :size="30" round />
            <div class="min-w-0 flex-1 truncate font-semibold">{{ handle }}</div>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.6" /><circle cx="12" cy="12" r="1.6" /><circle cx="19" cy="12" r="1.6" /></svg>
        </div>

        <div class="relative aspect-[4/5] w-full bg-slate-100">
            <DriveMedia v-model:mode="mode" :url="current" />
            <template v-if="isCarousel && links.length > 1">
                <span class="absolute right-3 top-10 rounded-full bg-black/65 px-2 py-0.5 text-[11px] text-white">{{ index + 1 }}/{{ links.length }}</span>
                <button type="button" class="absolute left-2 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow hover:bg-white" aria-label="Previous slide" @click="go(-1)">‹</button>
                <button type="button" class="absolute right-2 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow hover:bg-white" aria-label="Next slide" @click="go(1)">›</button>
            </template>
        </div>

        <div class="flex items-center gap-4 px-3 pt-2.5">
            <svg v-for="p in [heart, comment, send]" :key="p" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="p" /></svg>
            <div v-if="isCarousel && links.length > 1" class="mx-auto flex gap-1">
                <span v-for="(l, i) in links" :key="i" class="h-1.5 w-1.5 rounded-full" :class="i === index ? 'bg-blue-500' : 'bg-slate-300'" />
            </div>
            <svg class="ml-auto" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="save" /></svg>
        </div>

        <div class="space-y-1 px-3 pb-3 pt-1.5">
            <p v-if="caption" :class="expanded ? '' : 'line-clamp-2'">
                <span class="mr-1 font-semibold">{{ handle }}</span><span class="whitespace-pre-wrap">{{ caption }}</span>
            </p>
            <button v-if="longCaption" type="button" class="text-slate-500" @click="expanded = !expanded">{{ expanded ? 'less' : 'more' }}</button>
            <p v-if="hashtags" class="break-words text-[#00376b]">{{ hashtags }}</p>
            <p v-if="mentions.length" class="text-xs text-slate-500">With {{ mentions.join(', ') }}</p>
            <p v-if="date" class="pt-0.5 text-[10px] uppercase tracking-wide text-slate-500">{{ date }}</p>
        </div>
    </div>

    <!-- Reel -->
    <div v-else-if="type === 'instagram_reel'" class="relative mx-auto aspect-[9/16] w-full max-w-[270px] overflow-hidden rounded-2xl bg-black text-white shadow-sm">
        <DriveMedia v-model:mode="mode" :url="current" />
        <div class="pointer-events-none absolute inset-0 bg-linear-to-t from-black/75 via-transparent to-black/30" />
        <div class="pointer-events-none absolute inset-x-0 top-0 flex items-center justify-between p-3 text-sm font-semibold">
            <span>Reels</span>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h3l1.5-2h7L17 8h3v11H4z" /><circle cx="12" cy="13" r="3.2" /></svg>
        </div>
        <div class="pointer-events-none absolute bottom-24 right-2.5 flex flex-col items-center gap-4">
            <svg v-for="p in [heart, comment, send]" :key="p" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="p" /></svg>
            <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.6" /><circle cx="12" cy="12" r="1.6" /><circle cx="12" cy="19" r="1.6" /></svg>
        </div>
        <div class="pointer-events-none absolute inset-x-0 bottom-0 space-y-1.5 p-3 pr-12 text-[12px]">
            <div class="flex items-center gap-2">
                <BrandLogo :name="name" :url="brand?.logo_url" :color="brand?.color" :size="26" round />
                <span class="truncate font-semibold">{{ handle }}</span>
                <span class="rounded border border-white/70 px-1.5 py-px text-[10px] font-medium">Follow</span>
            </div>
            <p v-if="caption" class="line-clamp-2 whitespace-pre-wrap">{{ caption }}</p>
            <p v-if="hashtags" class="line-clamp-1 break-all opacity-90">{{ hashtags }}</p>
            <p class="flex items-center gap-1 text-[11px] opacity-90">♪ <span class="truncate">Original audio · {{ handle }}</span></p>
        </div>
    </div>

    <!-- Story -->
    <div v-else class="relative mx-auto aspect-[9/16] w-full max-w-[270px] overflow-hidden rounded-2xl bg-black text-white shadow-sm">
        <DriveMedia v-model:mode="mode" :url="current" />
        <div class="pointer-events-none absolute inset-x-0 top-0 bg-linear-to-b from-black/55 to-transparent p-3">
            <div class="mb-2 h-[3px] rounded-full bg-white/90" />
            <div class="flex items-center gap-2 text-[12px]">
                <BrandLogo :name="name" :url="brand?.logo_url" :color="brand?.color" :size="28" round />
                <span class="truncate font-semibold">{{ handle }}</span>
                <span class="opacity-80">now</span>
            </div>
        </div>
        <div class="pointer-events-none absolute inset-x-0 bottom-0 flex items-center gap-3 bg-linear-to-t from-black/55 to-transparent p-3">
            <div class="flex-1 rounded-full border border-white/70 px-3 py-1.5 text-[12px] text-white/90">Send message</div>
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="heart" /></svg>
        </div>
    </div>

    <!-- Viewer controls live outside the mockup so they never cover its own buttons -->
    <div v-if="link" class="mx-auto mt-3 flex w-full max-w-[360px] flex-wrap items-center justify-center gap-2 text-xs">
        <button
            v-if="link.kind === 'file'"
            type="button"
            class="flex items-center gap-1 rounded-full border border-slate-300 bg-white px-3 py-1 font-medium text-slate-700 hover:bg-slate-50"
            @click="mode = mode === 'thumb' ? 'frame' : 'thumb'"
        >
            <svg v-if="mode === 'thumb'" width="10" height="10" viewBox="0 0 10 10" fill="currentColor" aria-hidden="true"><path d="M2 1l7 4-7 4z" /></svg>
            {{ mode === 'thumb' ? 'Play / open viewer' : 'Show still image' }}
        </button>
        <a :href="link.openUrl" target="_blank" rel="noopener noreferrer" class="rounded-full border border-slate-300 bg-white px-3 py-1 font-medium text-slate-700 hover:bg-slate-50">Open in Drive ↗</a>
    </div>
  </div>
</template>
