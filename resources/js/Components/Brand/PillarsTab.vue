<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({ brand: Object, pillars: Array, canManage: Boolean });

const base = computed(() => `/brands/${props.brand.id}/pillars`);

const add = useForm({ name: '', description: '' });
const submitAdd = () => add.post(base.value, { preserveScroll: true, onSuccess: () => add.reset() });

watch(() => add.name, () => add.clearErrors('name'));

const editingId = ref(null);
const edit = useForm({ name: '', description: '' });
const startEdit = (p) => {
    editingId.value = p.id;
    edit.clearErrors();
    edit.name = p.name;
    edit.description = p.description ?? '';
};
const saveEdit = (p) => edit.put(`${base.value}/${p.id}`, { preserveScroll: true, onSuccess: () => (editingId.value = null) });

watch(() => edit.name, () => edit.clearErrors('name'));

const archive = (p) => router.patch(`${base.value}/${p.id}/archive`, {}, { preserveScroll: true });
const remove = (p) => {
    if (window.confirm(`Delete the pillar "${p.name}"?`)) router.delete(`${base.value}/${p.id}`, { preserveScroll: true });
};

const deleteError = computed(() => usePage().props.errors?.pillar);
const input = 'rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500';
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-slate-600">
            Pillars are the themes this brand’s content is built around. They fill the <b>Content pillar</b> dropdown when planning content.
            A pillar already used by content can be archived instead of deleted: it stays on old content for reports but is no longer offered.
        </p>

        <div v-if="deleteError" class="border-l-4 border-red-500 bg-white px-4 py-2 text-sm shadow-sm" role="alert">{{ deleteError }}</div>

        <form v-if="canManage" class="rounded-lg border border-[#dcdcde] bg-white p-5" @submit.prevent="submitAdd">
            <h2 class="mb-3 font-medium">Add a pillar</h2>
            <div class="flex flex-wrap items-start gap-3">
                <div class="min-w-[12rem] flex-1">
                    <input v-model="add.name" type="text" maxlength="100" placeholder="Name, e.g. Educational" aria-label="Pillar name" :class="[input, 'w-full', add.errors.name && 'border-red-400']" />
                    <p v-if="add.errors.name" class="mt-1 text-xs text-red-600">{{ add.errors.name }}</p>
                </div>
                <div class="min-w-[12rem] flex-[2_1_16rem]">
                    <input v-model="add.description" type="text" maxlength="255" placeholder="What is it about? (optional)" aria-label="Pillar description" :class="[input, 'w-full']" />
                </div>
                <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50" :disabled="add.processing">Add</button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border border-[#dcdcde] bg-white">
            <table class="w-full min-w-[34rem] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-2.5">Pillar</th><th class="px-4 py-2.5">Used by</th><th class="px-4 py-2.5">Status</th><th v-if="canManage" class="px-4 py-2.5"></th></tr>
                </thead>
                <tbody>
                    <tr v-for="p in pillars" :key="p.id" class="border-t border-[#f0f0f1] align-top">
                        <td class="px-4 py-3">
                            <form v-if="editingId === p.id" class="space-y-2" @submit.prevent="saveEdit(p)">
                                <input v-model="edit.name" type="text" maxlength="100" aria-label="Pillar name" :class="[input, 'w-full', edit.errors.name && 'border-red-400']" />
                                <p v-if="edit.errors.name" class="text-xs text-red-600">{{ edit.errors.name }}</p>
                                <input v-model="edit.description" type="text" maxlength="255" placeholder="Description (optional)" aria-label="Pillar description" :class="[input, 'w-full']" />
                                <div class="flex gap-3 text-sm">
                                    <button class="font-medium text-indigo-600 hover:underline" :disabled="edit.processing">Save</button>
                                    <button type="button" class="text-slate-500 hover:underline" @click="editingId = null">Cancel</button>
                                </div>
                            </form>
                            <template v-else>
                                <div class="font-medium" :class="p.archived && 'text-slate-500'">{{ p.name }}</div>
                                <div v-if="p.description" class="text-xs text-slate-500">{{ p.description }}</div>
                            </template>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ p.plans }} content plan{{ p.plans === 1 ? '' : 's' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="p.archived ? 'bg-slate-200 text-slate-600' : 'bg-green-100 text-green-800'">{{ p.archived ? 'Archived' : 'Active' }}</span>
                        </td>
                        <td v-if="canManage" class="space-x-3 whitespace-nowrap px-4 py-3 text-right">
                            <button v-if="editingId !== p.id" type="button" class="text-indigo-600 hover:underline" @click="startEdit(p)">Edit</button>
                            <button type="button" class="text-slate-600 hover:underline" @click="archive(p)">{{ p.archived ? 'Restore' : 'Archive' }}</button>
                            <button v-if="!p.plans" type="button" class="text-red-600 hover:underline" @click="remove(p)">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="!pillars.length"><td :colspan="canManage ? 4 : 3" class="px-4 py-10 text-center text-slate-500">No content pillars yet.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
