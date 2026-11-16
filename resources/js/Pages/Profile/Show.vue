<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import TextField from '../../Components/TextField.vue';

const props = defineProps({ profile: Object });
const locked = usePage().props.auth.user.must_change_password;

const info = useForm({ name: props.profile.name, email: props.profile.email ?? '' });
const pw = useForm({ current_password: '', password: '', password_confirmation: '' });
const savePassword = () => pw.put('/profile/password', { onFinish: () => pw.reset() });
</script>

<template>
    <Head title="Profile" />
    <AppLayout>
        <h1 class="mb-6 text-2xl font-semibold">Profile</h1>
        <div v-if="locked" class="mb-6 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800">
            You are using a temporary password. Please set a new password to continue.
        </div>
        <div class="grid gap-6 md:grid-cols-2">
            <form class="space-y-4 rounded-lg border border-slate-200 bg-white p-5" @submit.prevent="info.put('/profile')">
                <h2 class="font-medium">Account</h2>
                <div class="text-sm text-slate-500">Username: <span class="font-mono text-slate-800">{{ profile.username }}</span></div>
                <TextField v-model="info.name" label="Name" required :error="info.errors.name" />
                <TextField v-model="info.email" label="Email (optional)" type="email" :error="info.errors.email" />
                <button class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700" :disabled="info.processing">Save</button>
            </form>
            <form class="space-y-4 rounded-lg border border-slate-200 bg-white p-5" @submit.prevent="savePassword">
                <h2 class="font-medium">Change password</h2>
                <TextField v-model="pw.current_password" label="Current password" type="password" autocomplete="current-password" required :error="pw.errors.current_password" />
                <TextField v-model="pw.password" label="New password" type="password" autocomplete="new-password" required hint="At least 8 characters." :error="pw.errors.password" />
                <TextField v-model="pw.password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required />
                <button class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700" :disabled="pw.processing">Change password</button>
            </form>
        </div>
    </AppLayout>
</template>
