<script setup>
import CopyButton from '../CopyButton.vue';
import TagInput from '../TagInput.vue';
import TextAreaField from '../TextAreaField.vue';
import TypeIcon from './TypeIcon.vue';
import { captionWithTags, tagsText } from '../../lib/captions';
import { longLabel } from '../../lib/dates';
import { parseDriveLink } from '../../lib/drive';
import { cleanedIndex } from '../../lib/forms';

// The details of ONE content type. Each type gets its own instance (keyed by the type) that holds on to
// that type's own `post`, so a late event (such as a field losing focus while you switch types) can
// never write into another type's data.
const props = defineProps({
    type: { type: Object, required: true }, // an entry of the content type tree
    post: { type: Object, required: true }, // { media_links, caption, tags, note }
    form: { type: Object, required: true }, // for errors and the publish date
    limits: Object,
    timezone: Object,
    disabled: Boolean,
});
const emit = defineEmits(['focus-media', 'go-brief']);

const err = (suffix) => props.form.errors[`posts.${props.type.key}.${suffix}`];
const linkError = (i) => err(`media_links.${cleanedIndex(props.post.media_links, i)}`);

const linkInfo = (value) => (String(value ?? '').trim() === '' ? null : (parseDriveLink(value) ?? 'invalid'));

const addLink = () => props.post.media_links.push('');
const removeLink = (i) => {
    props.post.media_links.splice(i, 1);
    if (!props.post.media_links.length) props.post.media_links.push('');
};
const move = (i, step) => {
    const list = props.post.media_links;
    const j = i + step;
    if (j < 0 || j >= list.length) return;
    [list[i], list[j]] = [list[j], list[i]];
};
</script>

<template>
    <section class="space-y-5 rounded-lg border border-[#dcdcde] bg-white p-5">
        <h2 class="flex items-center gap-2 font-medium"><TypeIcon :icon="type.icon" :size="20" /> {{ type.full_label }}</h2>

        <div>
            <span class="mb-1 block text-sm font-medium text-slate-700">Publish date</span>
            <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                <template v-if="form.publish_at">{{ longLabel(form.publish_at, timezone.abbr) }}</template>
                <span v-else class="text-slate-500">Not set yet. Set it in <button type="button" class="font-medium text-indigo-600 underline" @click="emit('go-brief')">The brief</button>.</span>
            </div>
            <span class="mt-1 block text-xs text-slate-500">Follows the date in The brief.</span>
        </div>

        <div>
            <span class="mb-1 block text-sm font-medium text-slate-700">
                Media <span class="font-normal text-slate-400">({{ type.multiple ? `Google Drive links, ${type.min_media} to ${type.max_media}` : 'Google Drive link' }})</span>
            </span>

            <div v-for="(link, i) in post.media_links" :key="i" class="mb-2">
                <div class="flex items-center gap-2">
                    <span v-if="type.multiple" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-medium text-slate-600">{{ i + 1 }}</span>
                    <input
                        v-model="post.media_links[i]"
                        type="url"
                        placeholder="https://drive.google.com/file/d/…"
                        :disabled="disabled"
                        :aria-label="`Media link ${i + 1}`"
                        class="min-w-0 flex-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:bg-slate-100"
                        :class="{ 'border-red-400': linkInfo(link) === 'invalid' || linkError(i) }"
                        @focus="emit('focus-media')"
                    />
                    <a v-if="linkInfo(link)?.downloadUrl" :href="linkInfo(link).downloadUrl" target="_blank" rel="noopener noreferrer" class="rounded-md border border-slate-300 px-2 py-2 text-xs text-slate-600 hover:bg-slate-50" title="Download this file">Download</a>
                    <template v-if="type.multiple && !disabled">
                        <button type="button" class="rounded px-1.5 py-1 text-slate-500 hover:bg-slate-100 disabled:opacity-30" :disabled="i === 0" aria-label="Move up" @click="move(i, -1)">↑</button>
                        <button type="button" class="rounded px-1.5 py-1 text-slate-500 hover:bg-slate-100 disabled:opacity-30" :disabled="i === post.media_links.length - 1" aria-label="Move down" @click="move(i, 1)">↓</button>
                        <button type="button" class="rounded px-1.5 py-1 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Remove link" @click="removeLink(i)">✕</button>
                    </template>
                </div>
                <p v-if="linkError(i)" class="mt-1 text-xs text-red-600">{{ linkError(i) }}</p>
                <p v-else-if="linkInfo(link) === 'invalid'" class="mt-1 text-xs text-red-600">This is not a Google Drive file or folder link.</p>
                <p v-else-if="linkInfo(link)?.kind === 'folder'" class="mt-1 text-xs text-slate-500">Folder link. The preview shows the folder contents.</p>
                <p v-else-if="linkInfo(link)?.kind === 'file'" class="mt-1 text-xs text-green-700">Google Drive file recognised.</p>
            </div>

            <button v-if="type.multiple && !disabled && post.media_links.length < type.max_media" type="button" class="text-sm text-indigo-600 hover:underline" @click="addLink">+ Add link</button>
            <p v-if="err('media_links')" class="mt-1 text-xs text-red-600">{{ err('media_links') }}</p>
        </div>

        <div>
            <TextAreaField v-model="post.caption" label="Caption" :rows="6" :maxlength="type.caption_limit" counter :star="type.caption_required" :disabled="disabled" :error="err('caption')" :hint="type.caption_required ? '' : 'Optional for this content type.'" />
            <div v-if="post.caption.trim() || post.tags.length" class="mt-1 flex gap-4 text-sm">
                <CopyButton v-if="post.caption.trim() || post.tags.length" :text="() => captionWithTags(post)" label="Copy caption" variant="link" />
                <CopyButton v-if="post.tags.length" :text="() => tagsText(post)" label="Copy tags" variant="link" />
            </div>
        </div>

        <TagInput v-model="post.tags" label="Tag" :max="limits.tags" :disabled="disabled" :error="err('tags') || err('tags.0')" hint="Hashtags (#) and accounts to tag (@). Press Enter or space after each. Hashtags appear under the caption in the preview." />

        <TextAreaField v-model="post.note" label="Note" :rows="3" maxlength="2000" placeholder="Anything the team or reviewer should know about this post." :disabled="disabled" :error="err('note')" />
    </section>
</template>
