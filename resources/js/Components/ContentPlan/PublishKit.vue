<script setup>
import { computed } from 'vue';
import CopyButton from '../CopyButton.vue';
import StatusBadge from '../StatusBadge.vue';
import DriveMedia from './DriveMedia.vue';
import TypeIcon from './TypeIcon.vue';
import { captionWithTags, tagsText } from '../../lib/captions';
import { parseDriveLink } from '../../lib/drive';
import { isBlank } from '../../lib/forms';

// Everything the publisher needs in one place, read-only: copy the caption, download the files, post on time.
const props = defineProps({
    plan: { type: Object, required: true },
    brand: { type: Object, default: null },
    types: { type: Array, default: () => [] }, // chosen content types (registry entries)
    posts: { type: Object, default: () => ({}) },
    publisher: { type: Object, default: null },
});

const filesOf = (key) => (props.posts[key]?.media_links ?? []).filter((m) => !isBlank(m)).map((url) => ({ url, link: parseDriveLink(url) }));

const hasCaption = (key) => !isBlank(props.posts[key]?.caption) || (props.posts[key]?.tags ?? []).length > 0;
const captionLength = (key) => (props.posts[key]?.caption ?? '').length;

// Opens every downloadable file. Browsers may allow only the first window: they then offer to allow pop-ups.
function downloadAll(key) {
    filesOf(key)
        .filter((f) => f.link?.downloadUrl)
        .forEach((f) => {
            const a = document.createElement('a');
            a.href = f.link.downloadUrl;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            document.body.appendChild(a);
            a.click();
            a.remove();
        });
}

const downloadable = computed(() => Object.fromEntries(props.types.map((t) => [t.key, filesOf(t.key).filter((f) => f.link?.downloadUrl).length])));
</script>

<template>
    <div class="space-y-6">
        <section class="rounded-lg border border-[#dcdcde] bg-white p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-medium">Publish kit</h2>
                    <p class="mt-1 text-sm text-slate-600">Everything needed to publish: copy the caption, download the files, then post at the scheduled time.</p>
                </div>
                <StatusBadge :status="plan.status" :label="plan.status_label" />
            </div>
            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
                <div><dt class="text-slate-500">Content</dt><dd class="font-medium">{{ plan.title }}<span v-if="brand" class="font-normal text-slate-500"> · {{ brand.name }}</span></dd></div>
                <div><dt class="text-slate-500">Publish at</dt><dd class="text-base font-semibold">{{ plan.publish_label ?? 'Not set' }}</dd></div>
                <div><dt class="text-slate-500">Publisher</dt><dd class="font-medium">{{ publisher?.name ?? 'Not assigned' }}</dd></div>
            </dl>
        </section>

        <p v-if="!types.length" class="rounded-lg border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">No content types chosen yet.</p>

        <section v-for="t in types" :key="t.key" class="rounded-lg border border-[#dcdcde] bg-white p-5">
            <h3 class="mb-4 flex flex-wrap items-center gap-2 font-medium">
                <TypeIcon :icon="t.icon" :size="20" /> {{ t.full_label }}
                <span v-if="plan.publish_label" class="text-sm font-normal text-slate-500">· {{ plan.publish_label }}</span>
            </h3>

            <!-- files -->
            <div class="mb-5">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm font-medium text-slate-700">Files <span class="font-normal text-slate-400">({{ filesOf(t.key).length }})</span></span>
                    <button v-if="downloadable[t.key] > 1" type="button" class="rounded-md border border-slate-300 bg-white px-2.5 py-1 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="downloadAll(t.key)">Download all ({{ downloadable[t.key] }})</button>
                </div>
                <ul v-if="filesOf(t.key).length" class="grid gap-3 sm:grid-cols-2">
                    <li v-for="(f, i) in filesOf(t.key)" :key="i" class="flex items-center gap-3 rounded-md border border-slate-200 p-2">
                        <div class="h-20 w-16 shrink-0 overflow-hidden rounded bg-slate-100">
                            <DriveMedia :url="f.url" :frame-fallback="false" />
                        </div>
                        <div class="min-w-0 flex-1 text-sm">
                            <div class="font-medium">{{ filesOf(t.key).length > 1 ? `File ${i + 1}` : 'File' }}<span v-if="f.link?.kind === 'folder'" class="ml-1 text-xs font-normal text-slate-500">(folder)</span></div>
                            <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1">
                                <a v-if="f.link?.downloadUrl" :href="f.link.downloadUrl" target="_blank" rel="noopener noreferrer" class="font-medium text-indigo-600 hover:underline">Download</a>
                                <a v-if="f.link" :href="f.link.openUrl" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">{{ f.link.kind === 'folder' ? 'Open folder' : 'Open in Drive' }} ↗</a>
                                <span v-else class="text-xs text-red-600">Not a Google Drive link</span>
                            </div>
                        </div>
                    </li>
                </ul>
                <p v-else class="rounded-md border border-dashed border-slate-300 px-3 py-4 text-center text-sm text-slate-500">No files added yet.</p>
            </div>

            <!-- caption -->
            <div>
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm font-medium text-slate-700">Caption <span v-if="captionLength(t.key)" class="font-normal text-slate-400">({{ captionLength(t.key) }}/{{ t.caption_limit }})</span></span>
                    <div v-if="hasCaption(t.key)" class="flex gap-2">
                        <CopyButton :text="() => captionWithTags(posts[t.key])" label="Copy caption" />
                        <CopyButton v-if="(posts[t.key].tags ?? []).length" :text="() => tagsText(posts[t.key])" label="Copy tags" />
                    </div>
                </div>
                <div v-if="hasCaption(t.key)" class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm">
                    <p v-if="!isBlank(posts[t.key].caption)" class="whitespace-pre-wrap break-words">{{ posts[t.key].caption }}</p>
                    <ul v-if="(posts[t.key].tags ?? []).length" class="mt-2 flex flex-wrap gap-1.5">
                        <li v-for="tag in posts[t.key].tags" :key="tag" class="rounded-full px-2 py-0.5 text-xs" :class="tag.startsWith('@') ? 'bg-violet-50 text-violet-700' : 'bg-blue-50 text-blue-700'">{{ tag }}</li>
                    </ul>
                </div>
                <p v-else class="rounded-md border border-dashed border-slate-300 px-3 py-4 text-center text-sm text-slate-500">{{ t.caption_required ? 'No caption yet.' : 'No caption (optional for this content type).' }}</p>
            </div>

            <p v-if="!isBlank(posts[t.key]?.note)" class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"><b>Note:</b> {{ posts[t.key].note }}</p>
        </section>
    </div>
</template>
