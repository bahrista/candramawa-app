<script setup>
import { useForm } from '@inertiajs/vue3';
import Avatar from '../Avatar.vue';
import UserMultiSelect from '../UserMultiSelect.vue';

const props = defineProps({ brand: Object, roles: Array, team: Object, users: Array, canEdit: Boolean });

const form = useForm({ team: Object.fromEntries(props.roles.map((r) => [r.value, props.team[r.value].map((m) => m.id)])) });
const save = () => form.put(`/brands/${props.brand.id}/team`, { preserveScroll: true });
</script>

<template>
    <form class="space-y-4" @submit.prevent="save">
        <p class="text-sm text-slate-600">
            The team decides who can work on this brand. People you add can see its content, and
            <b>Content Manager</b> and <b>Brand Owner</b> can plan new content. Production roles are picked from this team when a content plan is assigned.
        </p>

        <section v-for="r in roles" :key="r.value" class="rounded-lg border border-[#dcdcde] bg-white p-5">
            <h2 class="font-medium">{{ r.label }}</h2>
            <p class="mb-3 text-sm text-slate-500">{{ r.description }}</p>

            <UserMultiSelect v-if="canEdit" v-model="form.team[r.value]" :users="users" :known="team[r.value]" :placeholder="`Add a ${r.label}…`" />
            <ul v-else-if="team[r.value].length" class="flex flex-wrap gap-2">
                <li v-for="m in team[r.value]" :key="m.id" class="flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 py-1 pl-1 pr-3 text-sm">
                    <Avatar :name="m.name" :url="m.avatar_url" :size="22" />
                    {{ m.name }}<span v-if="m.job_title" class="text-slate-500">· {{ m.job_title }}</span>
                </li>
            </ul>
            <p v-else class="text-sm text-slate-500">Nobody yet.</p>

            <p v-if="form.errors[`team.${r.value}`]" class="mt-2 text-xs text-red-600">{{ form.errors[`team.${r.value}`] }}</p>
        </section>

        <div v-if="canEdit" class="flex items-center gap-3">
            <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50" :disabled="form.processing || !form.isDirty">Save team</button>
            <span v-if="form.isDirty" class="text-xs text-amber-700">Unsaved changes</span>
            <span v-else-if="form.recentlySuccessful" class="text-xs text-green-700">Saved</span>
        </div>
        <p v-else class="text-xs text-slate-500">Only the brand’s owners can change the team.</p>
    </form>
</template>
