<script setup>
import { computed } from 'vue';
import Avatar from './Avatar.vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] }, // user ids
    users: { type: Array, default: () => [] }, // everyone who can be added: [{ id, name, job_title, avatar_url }]
    known: { type: Array, default: () => [] }, // people already chosen (so deactivated users still show a chip)
    disabled: Boolean,
    placeholder: { type: String, default: 'Add a person…' },
});
const emit = defineEmits(['update:modelValue']);

const byId = computed(() => Object.fromEntries([...props.known, ...props.users].map((u) => [u.id, u])));
const chosen = computed(() => props.modelValue.map((id) => byId.value[id]).filter(Boolean));
const available = computed(() => props.users.filter((u) => !props.modelValue.includes(u.id)));

const add = (event) => {
    const id = Number(event.target.value);
    event.target.value = '';
    if (id) emit('update:modelValue', [...props.modelValue, id]);
};
const remove = (id) => emit('update:modelValue', props.modelValue.filter((x) => x !== id));
const label = (u) => (u.job_title ? `${u.name} — ${u.job_title}` : u.name);
</script>

<template>
    <div>
        <ul v-if="chosen.length" class="mb-2 flex flex-wrap gap-2">
            <li v-for="u in chosen" :key="u.id" class="flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 py-1 pl-1 pr-2 text-sm">
                <Avatar :name="u.name" :url="u.avatar_url" :size="22" />
                <span>{{ u.name }}</span>
                <span v-if="u.is_active === false" class="rounded bg-slate-200 px-1 text-[11px] text-slate-600">inactive</span>
                <button v-if="!disabled" type="button" class="text-slate-400 hover:text-red-600" :aria-label="`Remove ${u.name}`" @click="remove(u.id)">×</button>
            </li>
        </ul>
        <p v-else class="mb-2 text-sm text-slate-500">Nobody yet.</p>

        <select
            v-if="!disabled"
            class="w-full max-w-xs rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            :aria-label="placeholder"
            @change="add"
        >
            <option value="">{{ available.length ? placeholder : 'Everyone has been added' }}</option>
            <option v-for="u in available" :key="u.id" :value="u.id">{{ label(u) }}</option>
        </select>
    </div>
</template>
