<script setup>
import { useId } from 'vue';

const uid = useId();
defineProps({
    label: String,
    modelValue: { type: String, default: '' },
    rows: { type: Number, default: 4 },
    maxlength: Number,
    counter: Boolean,
    error: String,
    hint: String,
    disabled: Boolean,
    placeholder: String,
    star: Boolean,
});
defineEmits(['update:modelValue']);
</script>

<template>
    <label class="block">
        <span v-if="label" class="mb-1 flex items-center justify-between text-sm font-medium text-slate-700">
            <span :id="`${uid}-label`">{{ label }}<span v-if="star" class="text-red-500" aria-hidden="true"> *</span></span>
            <span v-if="counter && maxlength" class="text-xs font-normal" :class="modelValue.length > maxlength * 0.95 ? 'text-red-600' : 'text-slate-400'">{{ modelValue.length }}/{{ maxlength }}</span>
        </span>
        <textarea
            :value="modelValue"
            :rows="rows"
            :maxlength="maxlength"
            :disabled="disabled"
            :placeholder="placeholder"
            :aria-labelledby="label ? `${uid}-label` : undefined"
            :aria-describedby="error || hint ? `${uid}-note` : undefined"
            :aria-invalid="error ? true : undefined"
            :aria-required="star || undefined"
            class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:bg-slate-100 disabled:text-slate-500"
            :class="{ 'border-red-400': error }"
            @input="$emit('update:modelValue', $event.target.value)"
        />
        <span v-if="hint && !error" :id="`${uid}-note`" class="mt-1 block text-xs text-slate-500">{{ hint }}</span>
        <span v-if="error" :id="`${uid}-note`" class="mt-1 block text-xs text-red-600">{{ error }}</span>
    </label>
</template>
