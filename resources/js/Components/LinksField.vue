<script setup>
import { cleanedIndex } from '../lib/forms';

const props = defineProps({
    modelValue: { type: Array, default: () => [] }, // [{ url, label }]
    errors: { type: Object, default: () => ({}) }, // the form's errors, keys like "links.0.url"
    prefix: { type: String, default: 'links' },
    max: { type: Number, default: 10 },
    disabled: Boolean,
});
const emit = defineEmits(['update:modelValue']);

const urls = () => props.modelValue.map((l) => l.url);
const errorFor = (i, field) => props.errors[`${props.prefix}.${cleanedIndex(urls(), i)}.${field}`];

const update = (i, field, value) => emit('update:modelValue', props.modelValue.map((l, idx) => (idx === i ? { ...l, [field]: value } : l)));
const add = () => emit('update:modelValue', [...props.modelValue, { url: '', label: '' }]);
const remove = (i) => emit('update:modelValue', props.modelValue.filter((_, idx) => idx !== i));

const input = 'rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:bg-slate-100';
</script>

<template>
    <div>
        <div v-for="(link, i) in modelValue" :key="i" class="mb-3">
            <div class="flex flex-wrap items-center gap-2">
                <input :value="link.url" type="url" placeholder="https://…" :disabled="disabled" :class="[input, 'min-w-0 flex-[2_1_14rem]', errorFor(i, 'url') && 'border-red-400']" :aria-label="`Link ${i + 1} address`" @input="update(i, 'url', $event.target.value)" />
                <input :value="link.label" type="text" placeholder="Label (optional)" maxlength="100" :disabled="disabled" :class="[input, 'min-w-0 flex-[1_1_9rem]']" :aria-label="`Link ${i + 1} label`" @input="update(i, 'label', $event.target.value)" />
                <button v-if="!disabled" type="button" class="rounded px-2 py-1 text-slate-400 hover:bg-red-50 hover:text-red-600" :aria-label="`Remove link ${i + 1}`" @click="remove(i)">✕</button>
            </div>
            <p v-if="errorFor(i, 'url')" class="mt-1 text-xs text-red-600">{{ errorFor(i, 'url') }}</p>
        </div>
        <button v-if="!disabled && modelValue.length < max" type="button" class="text-sm text-indigo-600 hover:underline" @click="add">+ Add link</button>
        <p v-if="errors[prefix]" class="mt-1 text-xs text-red-600">{{ errors[prefix] }}</p>
    </div>
</template>
