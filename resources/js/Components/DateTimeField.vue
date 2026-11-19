<script setup>
import { computed } from 'vue';
import { longLabel } from '../lib/dates';

const props = defineProps({
    label: String,
    // Name announced by screen readers when there is no visible label.
    ariaName: { type: String, default: 'Date and time' },
    modelValue: { type: String, default: '' },
    error: String,
    disabled: Boolean,
    tz: { type: String, default: '' },
    star: Boolean,
});
const emit = defineEmits(['update:modelValue']);

const name = computed(() => props.label || props.ariaName);
const date = computed(() => props.modelValue.slice(0, 10));
const hour = computed(() => props.modelValue.slice(11, 13));
const minute = computed(() => props.modelValue.slice(14, 16));

const HOURS = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));
const minutes = computed(() => {
    const steps = Array.from({ length: 12 }, (_, i) => String(i * 5).padStart(2, '0'));
    return minute.value && !steps.includes(minute.value) ? [...steps, minute.value].sort() : steps;
});

// Picking a date first fills in a sensible time (09:00); clearing the date clears everything.
function change(part, value) {
    const d = part === 'date' ? value : date.value;
    if (!d) return emit('update:modelValue', '');
    const h = part === 'hour' ? value : hour.value || '09';
    const m = part === 'minute' ? value : minute.value || '00';
    emit('update:modelValue', `${d}T${h}:${m}`);
}

const field = 'rounded-md border border-slate-300 bg-white px-2 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:bg-slate-100 disabled:text-slate-500';
</script>

<template>
    <div>
        <span v-if="label" class="mb-1 block text-sm font-medium text-slate-700">{{ label }}<span v-if="star" class="text-red-500" aria-hidden="true"> *</span></span>
        <div class="flex flex-wrap items-center gap-2">
            <input type="date" :value="date" :disabled="disabled" :class="[field, 'min-w-[10rem] flex-1', error && 'border-red-400']" :aria-label="`${name} date`" @input="change('date', $event.target.value)" />
            <select :value="hour" :disabled="disabled || !date" :class="[field, 'w-[4.5rem]']" :aria-label="`${name} hour`" @change="change('hour', $event.target.value)">
                <option v-for="h in HOURS" :key="h" :value="h">{{ h }}</option>
            </select>
            <span class="text-slate-400">:</span>
            <select :value="minute" :disabled="disabled || !date" :class="[field, 'w-[4.5rem]']" :aria-label="`${name} minute`" @change="change('minute', $event.target.value)">
                <option v-for="m in minutes" :key="m" :value="m">{{ m }}</option>
            </select>
            <span v-if="tz" class="text-xs font-medium text-slate-500">{{ tz }}</span>
        </div>
        <span v-if="error" class="mt-1 block text-xs text-red-600">{{ error }}</span>
        <span v-else-if="modelValue" class="mt-1 block text-xs text-slate-600">{{ longLabel(modelValue, tz) }}</span>
    </div>
</template>
