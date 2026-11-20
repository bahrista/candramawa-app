<script setup>
import { computed } from 'vue';
import SelectField from '../SelectField.vue';
import PostFields from './PostFields.vue';
import { isBlank } from '../../lib/forms';

const props = defineProps({
    form: Object,
    contentTypes: Array, // the tree
    activeType: { type: String, default: null },
    limits: Object,
    timezone: Object,
    disabled: Boolean,
});
const emit = defineEmits(['update:activeType', 'go-brief']);

const selected = computed(() => props.contentTypes.flatMap((p) => p.types).filter((t) => props.form.content_types.includes(t.key)));
const type = computed(() => selected.value.find((t) => t.key === props.activeType) ?? null);
const post = computed(() => (type.value ? props.form.posts[type.value.key] : null));

const complete = (t) => {
    const p = props.form.posts[t.key];
    return p.media_links.filter((m) => !isBlank(m)).length >= t.min_media && (!t.caption_required || !isBlank(p.caption));
};
// one <optgroup> per platform keeps a long list tidy
const groups = computed(() =>
    props.contentTypes
        .map((p) => ({
            label: p.label,
            options: selected.value.filter((t) => t.platform === p.key).map((t) => ({ value: t.key, label: `${t.label}${complete(t) ? '  ✓' : ''}` })),
        }))
        .filter((g) => g.options.length),
);

const panel = 'rounded-lg border border-[#dcdcde] bg-white p-5';
</script>

<template>
    <div class="space-y-6">
        <div v-if="!selected.length" :class="panel" class="text-center text-sm text-slate-500">
            Choose at least one content type in <button type="button" class="font-medium text-indigo-600 underline" @click="emit('go-brief')">The brief</button> first.
        </div>

        <template v-else>
            <section :class="panel">
                <SelectField :model-value="activeType" label="Content type" :groups="groups" @update:model-value="emit('update:activeType', $event)" />
                <p class="mt-1 text-xs text-slate-500">One set of details per content type. A ✓ means media and caption are ready.</p>
            </section>

            <PostFields
                v-if="type && post"
                :key="type.key"
                :type="type"
                :post="post"
                :form="form"
                :limits="limits"
                :timezone="timezone"
                :disabled="disabled"
                @focus-media="emit('update:activeType', type.key)"
                @go-brief="emit('go-brief')"
            />
        </template>
    </div>
</template>
