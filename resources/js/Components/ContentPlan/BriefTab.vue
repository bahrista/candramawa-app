<script setup>
import { computed, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import Avatar from '../Avatar.vue';
import DateTimeField from '../DateTimeField.vue';
import SelectField from '../SelectField.vue';
import TextAreaField from '../TextAreaField.vue';
import TextField from '../TextField.vue';
import ContentTypePicker from './ContentTypePicker.vue';
import ReferencePicker from './ReferencePicker.vue';
import { longLabel, minusHours } from '../../lib/dates';

const props = defineProps({
    form: Object,
    brands: Array,
    contentTypes: Array,
    contentManager: Object,
    assignees: { type: Object, default: () => ({}) }, // people already assigned (may have left the brand's team)
    timezone: Object,
    deadlineHours: Number,
    limits: Object,
    disabled: Boolean,
});
const emit = defineEmits(['change-types']);

const err = (key) => props.form.errors[key];

const brand = computed(() => props.brands.find((b) => b.id === props.form.brand_id) ?? null);
const brandOptions = computed(() => props.brands.map((b) => ({ value: b.id, label: b.name })));
const pillarOptions = computed(() => (brand.value?.pillars ?? []).map((p) => ({ value: p.id, label: p.archived ? `${p.name} (archived)` : p.name })));

// People come from the brand's team, for the matching role.
const ROLES = [
    { field: 'multimedia_creator_id', role: 'multimedia_creator', label: 'Multimedia creator', name: 'Multimedia Creator' },
    { field: 'copywriter_id', role: 'copywriter', label: 'Copy writer', name: 'Copy Writer' },
    { field: 'publisher_id', role: 'publisher', label: 'Publisher', name: 'Publisher' },
];
const personLabel = (u) => (u.job_title ? `${u.name} — ${u.job_title}` : u.name);
const teamOf = (r) => brand.value?.team[r.role] ?? [];
const optionsFor = (r) => {
    const options = teamOf(r).map((u) => ({ value: u.id, label: personLabel(u) }));
    const current = props.assignees[r.field];
    if (current && props.form[r.field] === current.id && !teamOf(r).some((u) => u.id === current.id)) {
        options.push({ value: current.id, label: `${personLabel(current)} (no longer on the team)` });
    }
    return options;
};

const autoDeadline = computed(() => minusHours(props.form.publish_at, props.deadlineHours));

// Switching to "manual" starts from the automatic value so there is something to adjust.
watch(
    () => props.form.deadline_mode,
    (mode) => {
        if (mode === 'manual' && !props.form.deadline_at && autoDeadline.value) props.form.deadline_at = autoDeadline.value;
    },
);

const panel = 'rounded-lg border border-[#dcdcde] bg-white p-5';
</script>

<template>
    <div class="space-y-6">
        <!-- Content -->
        <section :class="panel">
            <h2 class="mb-4 font-medium">Content</h2>
            <div class="space-y-4">
                <div v-if="!brands.length" class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    You can’t plan content for any brand yet. <Link href="/brands/create" class="font-medium underline">Create a brand</Link>, or ask a brand owner to add you to a brand’s team as Content Manager.
                </div>
                <SelectField v-else v-model="form.brand_id" label="Brand" star :options="brandOptions" placeholder="Choose a brand" :disabled="disabled" :error="err('brand_id')" />

                <TextField v-model="form.title" label="Title" star :disabled="disabled" maxlength="255" :error="err('title')" />

                <div>
                    <SelectField v-model="form.content_pillar_id" label="Content pillar" :options="pillarOptions" :placeholder="brand ? 'Choose a pillar' : 'Choose a brand first'" :disabled="disabled || !brand" :error="err('content_pillar_id')" />
                    <p v-if="brand && !brand.pillars.length" class="mt-1 text-xs text-amber-700">
                        This brand has no content pillars yet.
                        <Link v-if="brand.can_manage" :href="`/brands/${brand.id}?tab=pillars`" class="font-medium underline">Add some</Link>
                        <template v-else>Ask a content manager or brand owner to add them.</template>
                    </p>
                </div>

                <ContentTypePicker :tree="contentTypes" :model-value="form.content_types" :disabled="disabled" :error="err('content_types') || err('content_types.0')" @change="emit('change-types', $event)" />
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

                <div v-for="r in ROLES" :key="r.field">
                    <SelectField v-model="form[r.field]" :label="r.label" :options="optionsFor(r)" placeholder="Not assigned" :disabled="disabled || !brand" :error="err(r.field)" />
                    <p v-if="brand && !teamOf(r).length" class="mt-1 text-xs text-slate-500">
                        No {{ r.name }} on {{ brand.name }}’s team yet.
                        <Link :href="`/brands/${brand.id}?tab=team`" class="text-indigo-600 hover:underline">Open the team</Link>
                    </p>
                </div>
            </div>
            <p class="mt-3 text-xs text-slate-500">People are chosen from the brand’s team. Assigned people can see this plan once it is submitted to production.</p>
        </section>

        <!-- Brief -->
        <section :class="panel">
            <h2 class="mb-4 font-medium">Brief</h2>
            <div class="space-y-5">
                <TextAreaField v-model="form.brief" label="Brief" :rows="7" maxlength="10000" placeholder="What is this content about? Key message, tone, do's and don'ts…" :disabled="disabled" :error="err('brief')" />

                <div>
                    <span class="mb-1 block text-sm font-medium text-slate-700">Reference <span class="font-normal text-slate-400">(optional)</span></span>
                    <ReferencePicker v-model="form.brief_references" :brand="brand" :limit="limits.references" :errors="form.errors" :disabled="disabled" />
                </div>
            </div>
        </section>
    </div>
</template>
