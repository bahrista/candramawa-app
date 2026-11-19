<script setup>
import { ref } from 'vue';

const props = defineProps({
    label: String,
    modelValue: { type: Array, default: () => [] },
    max: { type: Number, default: 30 },
    disabled: Boolean,
    error: String,
    hint: String,
});
const emit = defineEmits(['update:modelValue']);
const draft = ref('');

// "kopi" -> "#kopi"; "@kopi" and "#kopi" are kept; anything but letters, numbers, _ and . is dropped.
function normalise(raw) {
    let tag = raw.trim().replace(/\s+/g, '');
    if (!tag) return '';
    if (tag[0] !== '#' && tag[0] !== '@') tag = '#' + tag;
    tag = tag[0] + tag.slice(1).replace(/[^\p{L}\p{N}_.]/gu, '');
    return tag.length > 1 ? tag : '';
}

function commit() {
    const next = [...props.modelValue];
    for (const part of draft.value.split(/[\s,;]+/)) {
        const tag = normalise(part);
        if (tag && next.length < props.max && !next.some((t) => t.toLowerCase() === tag.toLowerCase())) next.push(tag);
    }
    draft.value = '';
    emit('update:modelValue', next);
}

function onKeydown(e) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        commit();
    } else if (e.key === 'Backspace' && !draft.value && props.modelValue.length) {
        emit('update:modelValue', props.modelValue.slice(0, -1));
    }
}

// Typing or pasting a separator ("a b, c") commits the tags straight away.
function onInput() {
    if (/[\s,;]/.test(draft.value)) commit();
}

const remove = (i) => emit('update:modelValue', props.modelValue.filter((_, idx) => idx !== i));
</script>

<template>
    <div>
        <span v-if="label" class="mb-1 block text-sm font-medium text-slate-700">{{ label }}</span>
        <div
            class="flex min-h-[2.5rem] flex-wrap items-center gap-1.5 rounded-md border bg-white px-2 py-1.5 shadow-sm focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500"
            :class="[error ? 'border-red-400' : 'border-slate-300', disabled && 'bg-slate-100']"
        >
            <span v-for="(tag, i) in modelValue" :key="tag" class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs" :class="tag.startsWith('@') ? 'bg-violet-50 text-violet-700' : 'bg-blue-50 text-blue-700'">
                {{ tag }}
                <button v-if="!disabled" type="button" class="text-current opacity-60 hover:opacity-100" :aria-label="`Remove ${tag}`" @click="remove(i)">×</button>
            </span>
            <input
                v-model="draft"
                :disabled="disabled || modelValue.length >= max"
                :placeholder="modelValue.length ? '' : '#hashtag or @account'"
                class="min-w-[8rem] flex-1 border-0 bg-transparent p-0.5 text-sm outline-none"
                @keydown="onKeydown"
                @input="onInput"
                @blur="commit"
            />
        </div>
        <span v-if="error" class="mt-1 block text-xs text-red-600">{{ error }}</span>
        <span v-else-if="hint" class="mt-1 block text-xs text-slate-500">{{ hint }}</span>
    </div>
</template>
