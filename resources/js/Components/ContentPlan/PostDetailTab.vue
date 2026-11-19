<script setup>
import { computed } from 'vue';
import SelectField from '../SelectField.vue';
import TagInput from '../TagInput.vue';
import TextAreaField from '../TextAreaField.vue';
import { parseDriveLink } from '../../lib/drive';
import { longLabel } from '../../lib/dates';
import { cleanedIndex, isBlank } from '../../lib/forms';

const props = defineProps({
    form: Object,
    postTypes: Array,
    activeType: { type: String, default: null },
    limits: Object,
    timezone: Object,
    disabled: Boolean,
});
const emit = defineEmits(['update:activeType', 'go-brief']);

const selected = computed(() => props.postTypes.filter((t) => props.form.post_types.includes(t.value)));
const type = computed(() => selected.value.find((t) => t.value === props.activeType) ?? null);
const post = computed(() => (type.value ? props.form.posts[type.value.value] : null));

const complete = (t) => {
    const p = props.form.posts[t.value];
    const media = p.media_links.filter((m) => !isBlank(m)).length;
    return media >= t.minMedia && (!t.captionRequired || !isBlank(p.caption));
};
const options = computed(() => selected.value.map((t) => ({ value: t.value, label: `${t.label}${complete(t) ? '  ✓' : ''}` })));

const err = (suffix) => props.form.errors[`posts.${props.activeType}.${suffix}`];
const linkError = (i) => err(`media_links.${cleanedIndex(post.value.media_links, i)}`);

const linkState = (value) => {
    if (isBlank(value)) return null;
    const link = parseDriveLink(value);
    return link ? link.kind : 'invalid';
};

const addLink = () => post.value.media_links.push('');
const removeLink = (i) => {
    post.value.media_links.splice(i, 1);
    if (!post.value.media_links.length) post.value.media_links.push('');
};
const move = (i, step) => {
    const list = post.value.media_links;
    const j = i + step;
    if (j < 0 || j >= list.length) return;
    [list[i], list[j]] = [list[j], list[i]];
};

const panel = 'rounded-lg border border-[#dcdcde] bg-white p-5';
</script>

<template>
    <div class="space-y-6">
        <div v-if="!selected.length" :class="panel" class="text-center text-sm text-slate-500">
            Choose at least one post type in <button type="button" class="font-medium text-indigo-600 underline" @click="emit('go-brief')">The brief</button> first.
        </div>

        <template v-else>
            <section :class="panel">
                <SelectField :model-value="activeType" label="Post type" :options="options" @update:model-value="emit('update:activeType', $event)" />
                <p class="mt-1 text-xs text-slate-500">One set of details per post type. A ✓ means media and caption are ready.</p>
            </section>

            <section v-if="type && post" :key="type.value" :class="panel" class="space-y-5">
                <h2 class="font-medium">{{ type.label }}</h2>

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
                        Media <span class="font-normal text-slate-400">({{ type.multiple ? `Google Drive links, ${type.minMedia} to ${type.maxMedia}` : 'Google Drive link' }})</span>
                    </span>

                    <div v-for="(link, i) in post.media_links" :key="i" class="mb-2">
                        <div class="flex items-center gap-2">
                            <span v-if="type.multiple" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-medium text-slate-600">{{ i + 1 }}</span>
                            <input
                                v-model="post.media_links[i]"
                                type="url"
                                placeholder="https://drive.google.com/file/d/…"
                                :disabled="disabled"
                                class="min-w-0 flex-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:bg-slate-100"
                                :class="{ 'border-red-400': linkState(link) === 'invalid' || linkError(i) }"
                                @focus="emit('update:activeType', type.value)"
                            />
                            <template v-if="type.multiple && !disabled">
                                <button type="button" class="rounded px-1.5 py-1 text-slate-500 hover:bg-slate-100 disabled:opacity-30" :disabled="i === 0" aria-label="Move up" @click="move(i, -1)">↑</button>
                                <button type="button" class="rounded px-1.5 py-1 text-slate-500 hover:bg-slate-100 disabled:opacity-30" :disabled="i === post.media_links.length - 1" aria-label="Move down" @click="move(i, 1)">↓</button>
                            </template>
                            <button v-if="type.multiple && !disabled" type="button" class="rounded px-1.5 py-1 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Remove link" @click="removeLink(i)">✕</button>
                        </div>
                        <p v-if="linkError(i)" class="mt-1 text-xs text-red-600">{{ linkError(i) }}</p>
                        <p v-else-if="linkState(link) === 'invalid'" class="mt-1 text-xs text-red-600">This is not a Google Drive file or folder link.</p>
                        <p v-else-if="linkState(link) === 'folder'" class="mt-1 text-xs text-slate-500">Folder link. The preview shows the folder contents.</p>
                        <p v-else-if="linkState(link) === 'file'" class="mt-1 text-xs text-green-700">Google Drive file recognised.</p>
                    </div>

                    <button v-if="type.multiple && !disabled && post.media_links.length < type.maxMedia" type="button" class="text-sm text-indigo-600 hover:underline" @click="addLink">+ Add link</button>
                    <p v-if="err('media_links')" class="mt-1 text-xs text-red-600">{{ err('media_links') }}</p>
                </div>

                <TextAreaField v-model="post.caption" label="Caption" :rows="6" :maxlength="limits.caption" counter :star="type.captionRequired" :disabled="disabled" :error="err('caption')" :hint="type.captionRequired ? '' : 'Optional for stories.'" />

                <TagInput v-model="post.tags" label="Tag" :max="limits.tags" :disabled="disabled" :error="err('tags') || err('tags.0')" hint="Hashtags (#) and accounts to tag (@). Press Enter or space after each. Hashtags appear under the caption in the preview." />

                <TextAreaField v-model="post.note" label="Note" :rows="3" maxlength="2000" placeholder="Anything the team or reviewer should know about this post." :disabled="disabled" :error="err('note')" />
            </section>
        </template>
    </div>
</template>
