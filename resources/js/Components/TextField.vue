<script setup>
defineProps({
    label: String,
    modelValue: [String, Number],
    type: { type: String, default: 'text' },
    error: String,
    hint: String,
    autocomplete: String,
    required: Boolean,
});
defineEmits(['update:modelValue']);
</script>

<template>
    <label class="block">
        <span class="mb-1 block text-sm font-medium text-slate-700">{{ label }}</span>
        <input
            :type="type"
            :value="modelValue"
            :required="required"
            :autocomplete="autocomplete"
            class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            :class="{ 'border-red-400': error }"
            @input="$emit('update:modelValue', $event.target.value)"
        />
        <span v-if="hint && !error" class="mt-1 block text-xs text-slate-500">{{ hint }}</span>
        <span v-if="error" class="mt-1 block text-xs text-red-600">{{ error }}</span>
    </label>
</template>
