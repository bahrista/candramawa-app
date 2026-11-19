<script setup>
import { computed, nextTick, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import Avatar from '../Avatar.vue';
import DateTimeField from '../DateTimeField.vue';
import SelectField from '../SelectField.vue';
import TextAreaField from '../TextAreaField.vue';
import TextField from '../TextField.vue';
import PostTypeIcon from './PostTypeIcon.vue';
import { longLabel, minusHours } from '../../lib/dates';
import { cleanedIndex } from '../../lib/forms';

const props = defineProps({
    form: Object,
    brands: Array,
    users: Array,
    postTypes: Array,
    suggestions: Object,
    limits: Object,
    contentManager: Object,
    timezone: Object,
    deadlineHours: Number,
    disabled: Boolean,
});
const emit = defineEmits(['toggle-post-type']);

const err = (key) => props.form.errors[key];

const brandOptions = computed(() => props.brands.map((b) => ({ value: b.id, label: b.name })));
const userOptions = computed(() => props.users.map((u) => ({ value: u.id, label: u.job_title ? `${u.name} — ${u.job_title}` : u.name })));

// Pillar suggestions: this brand's own pillars first, then the common ones.
const pillarOptions = computed(() => [...new Set([...(props.suggestions.pillarsByBrand?.[props.form.brand_id] ?? []), ...props.suggestions.pillars])]);

const autoDeadline = computed(() => minusHours(props.form.publish_at, props.deadlineHours));

// Switching to "manual" starts from the automatic value so there is something to adjust.
watch(
    () => props.form.deadline_mode,
    (mode) => {
        if (mode === 'manual' && !props.form.deadline_at && autoDeadline.value) props.form.deadline_at = autoDeadline.value;
    },
);

// If the parent refuses the change (e.g. the person cancels the "discard?" prompt), put the real
// checkbox back in line with the data so screen readers hear the true state.
function togglePostType(event, value) {
    emit('toggle-post-type', value);
    nextTick(() => (event.target.checked = props.form.post_types.includes(value)));
}

const addReference = () => props.form.reference_links.push('');
const removeReference = (i) => props.form.reference_links.splice(i, 1);
const isHttp = (v) => /^https?:\/\/\S+$/i.test((v || '').trim());

const panel = 'rounded-lg border border-[#dcdcde] bg-white p-5';
</script>

<template>
    <div class="space-y-6">
        <!-- Content -->
        <section :class="panel">
            <h2 class="mb-4 font-medium">Content</h2>
            <div class="space-y-4">
                <div v-if="!brands.length" class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    You don't have a brand yet. <Link href="/brands/create" class="font-medium underline">Create a brand</Link> first, then come back to plan its content.
                </div>
                <SelectField v-else v-model="form.brand_id" label="Brand" star :options="brandOptions" placeholder="Choose a brand" :disabled="disabled" :error="err('brand_id')" />

                <TextField v-model="form.title" label="Title" star :disabled="disabled" maxlength="255" :error="err('title')" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <TextField v-model="form.content_pillar" label="Content pillar" list="pillar-options" placeholder="e.g. Educational" :disabled="disabled" maxlength="100" :error="err('content_pillar')" />
                    <TextField v-model="form.format" label="Format" list="format-options" placeholder="e.g. Video" :disabled="disabled" maxlength="100" :error="err('format')" />
                    <datalist id="pillar-options"><option v-for="p in pillarOptions" :key="p" :value="p" /></datalist>
                    <datalist id="format-options"><option v-for="f in suggestions.formats" :key="f" :value="f" /></datalist>
                </div>

                <fieldset>
                    <legend class="mb-1 text-sm font-medium text-slate-700">Post type <span class="font-normal text-slate-400">(choose one or more)</span></legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="t in postTypes"
                            :key="t.value"
                            class="flex cursor-pointer items-center gap-3 rounded-md border p-3 text-sm transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-indigo-500"
                            :class="[form.post_types.includes(t.value) ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-slate-300 bg-white hover:bg-slate-50', disabled && 'pointer-events-none opacity-60']"
                        >
                            <input type="checkbox" class="sr-only" :checked="form.post_types.includes(t.value)" :disabled="disabled" @change="togglePostType($event, t.value)" />
                            <PostTypeIcon :type="t.value" :size="22" />
                            <span class="font-medium">{{ t.label }}</span>
                            <svg v-if="form.post_types.includes(t.value)" class="ml-auto" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7" /></svg>
                        </label>
                    </div>
                    <p v-if="err('post_types')" class="mt-1 text-xs text-red-600">{{ err('post_types') }}</p>
                </fieldset>
            </div>
        </section>

        <!-- Schedule -->
        <section :class="panel">
            <h2 class="mb-4 font-medium">Schedule</h2>
            <div class="space-y-5">
                <DateTimeField v-model="form.publish_at" label="Publish date" :tz="timezone.abbr" :disabled="disabled" :error="err('publish_at')" />

                <div>
                    <span class="mb-1 block text-sm font-medium text-slate-700">Deadline</span>
                    <div class="mb-2 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                        <label class="flex items-center gap-2"><input v-model="form.deadline_mode" type="radio" value="auto" :disabled="disabled" /> Automatic ({{ deadlineHours }} hours before publish)</label>
                        <label class="flex items-center gap-2"><input v-model="form.deadline_mode" type="radio" value="manual" :disabled="disabled" /> Manual</label>
                    </div>
                    <div v-if="form.deadline_mode === 'auto'" class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                        <template v-if="autoDeadline">{{ longLabel(autoDeadline, timezone.abbr) }}</template>
                        <span v-else class="text-slate-500">Set a publish date to calculate the deadline.</span>
                    </div>
                    <DateTimeField v-else v-model="form.deadline_at" aria-name="Deadline" :tz="timezone.abbr" :disabled="disabled" :error="err('deadline_at')" />
                </div>
            </div>
        </section>

        <!-- Team -->
        <section :class="panel">
            <h2 class="mb-4 font-medium">Team</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <span class="mb-1 block text-sm font-medium text-slate-700">Content manager</span>
                    <div class="flex items-center gap-2.5 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                        <Avatar :name="contentManager.name" :url="contentManager.avatar_url" :size="26" />
                        <span class="truncate">{{ contentManager.name }}</span>
                    </div>
                    <span class="mt-1 block text-xs text-slate-500">Automatically the person who creates the brief.</span>
                </div>
                <SelectField v-model="form.multimedia_creator_id" label="Multimedia creator" :options="userOptions" placeholder="Not assigned" :disabled="disabled" :error="err('multimedia_creator_id')" />
                <SelectField v-model="form.copywriter_id" label="Copy writer" :options="userOptions" placeholder="Not assigned" :disabled="disabled" :error="err('copywriter_id')" />
                <SelectField v-model="form.publisher_id" label="Publisher" :options="userOptions" placeholder="Not assigned" :disabled="disabled" :error="err('publisher_id')" />
            </div>
            <p class="mt-3 text-xs text-slate-500">Assigned people can see this plan once it is submitted to production.</p>
        </section>

        <!-- Brief -->
        <section :class="panel">
            <h2 class="mb-4 font-medium">Brief</h2>
            <div class="space-y-5">
                <TextAreaField v-model="form.brief" label="Brief" :rows="7" maxlength="10000" placeholder="What is this content about? Key message, tone, do's and don'ts…" :disabled="disabled" :error="err('brief')" />

                <div>
                    <span class="mb-1 block text-sm font-medium text-slate-700">Reference <span class="font-normal text-slate-400">(links, optional)</span></span>
                    <div v-for="(link, i) in form.reference_links" :key="i" class="mb-2">
                        <div class="flex items-center gap-2">
                            <input
                                v-model="form.reference_links[i]"
                                type="url"
                                placeholder="https://…"
                                :disabled="disabled"
                                class="min-w-0 flex-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:bg-slate-100"
                                :class="{ 'border-red-400': err(`reference_links.${cleanedIndex(form.reference_links, i)}`) }"
                            />
                            <a v-if="isHttp(link)" :href="link.trim()" target="_blank" rel="noopener noreferrer" class="rounded-md border border-slate-300 px-2 py-2 text-xs text-slate-600 hover:bg-slate-50" title="Open link">↗</a>
                            <button v-if="!disabled" type="button" class="rounded-md px-2 py-2 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Remove reference" @click="removeReference(i)">✕</button>
                        </div>
                        <p v-if="err(`reference_links.${cleanedIndex(form.reference_links, i)}`)" class="mt-1 text-xs text-red-600">{{ err(`reference_links.${cleanedIndex(form.reference_links, i)}`) }}</p>
                    </div>
                    <button v-if="!disabled && form.reference_links.length < limits.references" type="button" class="text-sm text-indigo-600 hover:underline" @click="addReference">+ Add reference</button>
                    <p v-if="err('reference_links')" class="mt-1 text-xs text-red-600">{{ err('reference_links') }}</p>
                </div>
            </div>
        </section>
    </div>
</template>
