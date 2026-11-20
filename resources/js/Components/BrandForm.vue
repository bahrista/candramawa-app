<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import BrandLogo from './BrandLogo.vue';
import TextField from './TextField.vue';

const props = defineProps({
    form: Object,
    existingLogoUrl: { type: String, default: null },
    submitLabel: { type: String, default: 'Save' },
    cancelHref: { type: String, default: '/brands' },
});
const emit = defineEmits(['submit']);

const fileInput = ref(null);
const preview = ref(null);

const shownLogo = computed(() => {
    if (preview.value) return preview.value;
    return props.form.remove_logo ? null : props.existingLogoUrl;
});

const revoke = () => preview.value && URL.revokeObjectURL(preview.value);
onBeforeUnmount(revoke);

const pick = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    revoke();
    props.form.logo = file;
    props.form.remove_logo = false;
    preview.value = URL.createObjectURL(file);
};
const clearLogo = () => {
    revoke();
    preview.value = null;
    props.form.logo = null;
    props.form.remove_logo = true;
    if (fileInput.value) fileInput.value.value = '';
};
</script>

<template>
    <form class="max-w-2xl space-y-5 rounded-lg border border-[#dcdcde] bg-white p-5" @submit.prevent="emit('submit')">
        <div>
            <span class="mb-2 block text-sm font-medium text-slate-700">Logo (optional)</span>
            <div class="flex items-center gap-4">
                <BrandLogo :name="form.name" :url="shownLogo" :color="form.color" :size="72" />
                <div class="space-y-2">
                    <div class="flex gap-2">
                        <button type="button" class="rounded-md border border-indigo-600 px-3 py-1.5 text-sm font-medium text-indigo-600 hover:bg-indigo-50" @click="fileInput.click()">
                            {{ shownLogo ? 'Change logo' : 'Upload logo' }}
                        </button>
                        <button v-if="shownLogo" type="button" class="rounded-md px-3 py-1.5 text-sm text-red-600 hover:bg-red-50" @click="clearLogo">Remove</button>
                    </div>
                    <p class="text-xs text-slate-500">JPG, PNG or WebP. Max 2 MB.</p>
                    <p v-if="form.errors.logo" class="text-xs text-red-600">{{ form.errors.logo }}</p>
                </div>
                <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="pick" />
            </div>
        </div>

        <TextField v-model="form.name" label="Brand name" required :error="form.errors.name" />

        <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">Description (optional)</span>
            <textarea v-model="form.description" rows="3" maxlength="1000" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
            <span v-if="form.errors.description" class="text-xs text-red-600">{{ form.errors.description }}</span>
        </label>

        <div>
            <span class="mb-1 block text-sm font-medium text-slate-700">Brand colour</span>
            <div class="flex items-center gap-3">
                <input v-model="form.color" type="color" class="h-10 w-14 cursor-pointer rounded border border-slate-300 bg-white p-1" />
                <span class="font-mono text-sm text-slate-600">{{ form.color }}</span>
            </div>
            <span v-if="form.errors.color" class="text-xs text-red-600">{{ form.errors.color }}</span>
        </div>

        <div class="flex gap-3">
            <button class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50" :disabled="form.processing">{{ submitLabel }}</button>
            <Link :href="cancelHref" class="px-3 py-2 text-sm text-slate-600">Cancel</Link>
        </div>
    </form>
</template>
