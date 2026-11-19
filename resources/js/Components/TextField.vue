<script setup>
import { useId } from 'vue';

const uid = useId();
defineProps({
    label: String,
    modelValue: [String, Number],
    type: { type: String, default: 'text' },
    error: String,
    hint: String,
    autocomplete: String,
    required: Boolean,
    star: Boolean,
    disabled: Boolean,
    placeholder: String,
    list: String,
    maxlength: [String, Number],
});
defineEmits(['update:modelValue']);
</script>

<template>
    <label class="block">
        <span v-if="label" :id="`${uid}-label`" class="mb-1 block text-sm font-medium text-slate-700">{{ label }}<span v-if="star" class="text-red-500" aria-hidden="true"> *</span></span>
        <input
            :type="type"
            :value="modelValue"
            :required="required"
            :autocomplete="autocomplete"
            :disabled="disabled"
            :placeholder="placeholder"
            :list="list"
            :aria-labelledby="label ? `${uid}-label` : undefined"
            :aria-describedby="error || hint ? `${uid}-note` : undefined"
            :aria-invalid="error ? true : undefined"
            :aria-required="star || undefined"
            :maxlength="maxlength"
            class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:bg-slate-100 disabled:text-slate-500"
            :class="{ 'border-red-400': error }"
            @input="$emit('update:modelValue', $event.target.value)"
        />
        <span v-if="hint && !error" :id="`${uid}-note`" class="mt-1 block text-xs text-slate-500">{{ hint }}</span>
        <span v-if="error" :id="`${uid}-note`" class="mt-1 block text-xs text-red-600">{{ error }}</span>
    </label>
</template>
