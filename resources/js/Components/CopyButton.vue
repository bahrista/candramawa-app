<script setup>
import { onBeforeUnmount, ref } from 'vue';
import { copyText } from '../lib/clipboard';

const props = defineProps({
    // The text, or a function that returns it (evaluated when clicked).
    text: { type: [String, Function], required: true },
    label: { type: String, default: 'Copy' },
    copiedLabel: { type: String, default: 'Copied!' },
    variant: { type: String, default: 'outline' }, // outline | link
});

const state = ref('idle'); // idle | copied | failed
let timer;

async function copy() {
    const ok = await copyText(typeof props.text === 'function' ? props.text() : props.text);
    state.value = ok ? 'copied' : 'failed';
    clearTimeout(timer);
    timer = setTimeout(() => (state.value = 'idle'), 1800);
}
onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <button
        type="button"
        class="whitespace-nowrap text-sm transition"
        :class="[
            variant === 'link' ? 'text-indigo-600 hover:underline' : 'rounded-md border px-2.5 py-1 font-medium',
            variant !== 'link' && (state === 'copied' ? 'border-green-600 bg-green-50 text-green-700' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'),
            state === 'failed' && 'text-red-600',
        ]"
        @click="copy"
    >
        <slot :state="state">{{ state === 'copied' ? copiedLabel : state === 'failed' ? 'Copy failed' : label }}</slot>
    </button>
</template>
