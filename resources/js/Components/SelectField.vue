<script setup>
import { useId } from 'vue';

const uid = useId();
const props = defineProps({
    label: String,
    modelValue: { type: [String, Number], default: null },
    options: { type: Array, default: () => [] },
    placeholder: String,
    error: String,
    hint: String,
    disabled: Boolean,
    star: Boolean,
});
const emit = defineEmits(['update:modelValue']);

// <select> always yields strings; hand back the option's own value (e.g. a numeric id).
const pick = (raw) => (raw === '' ? null : (props.options.find((o) => String(o.value) === raw)?.value ?? raw));
</script>

<template>
    <label class="block">
        <span v-if="label" :id="`${uid}-label`" class="mb-1 block text-sm font-medium text-slate-700">{{ label }}<span v-if="star" class="text-red-500" aria-hidden="true"> *</span></span>
        <select
            :value="modelValue ?? ''"
            :disabled="disabled"
            :aria-labelledby="label ? `${uid}-label` : undefined"
            :aria-describedby="error || hint ? `${uid}-note` : undefined"
            :aria-invalid="error ? true : undefined"
            :aria-required="star || undefined"
            class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:bg-slate-100 disabled:text-slate-500"
            :class="{ 'border-red-400': error }"
            @change="emit('update:modelValue', pick($event.target.value))"
        >
            <option v-if="placeholder !== undefined" value="">{{ placeholder }}</option>
            <option v-for="o in options" :key="o.value" :value="o.value">{{ o.label }}</option>
        </select>
        <span v-if="hint && !error" :id="`${uid}-note`" class="mt-1 block text-xs text-slate-500">{{ hint }}</span>
        <span v-if="error" :id="`${uid}-note`" class="mt-1 block text-xs text-red-600">{{ error }}</span>
    </label>
</template>
