<script setup>
import { ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Avatar from '../../Components/Avatar.vue';
import TextField from '../../Components/TextField.vue';

const props = defineProps({ profile: Object, sessions: Array });
const locked = usePage().props.auth.user.must_change_password;

const info = useForm({
    name: props.profile.name,
    email: props.profile.email ?? '',
    job_title: props.profile.job_title ?? '',
    whatsapp: props.profile.whatsapp ?? '',
});

const pw = useForm({ current_password: '', password: '', password_confirmation: '' });
const savePassword = () => pw.put('/profile/password', { onFinish: () => pw.reset() });

// Avatar upload: send as soon as a file is chosen.
const fileInput = ref(null);
const photo = useForm({ avatar: null });
const pickPhoto = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    photo.avatar = file;
    photo.post('/profile/avatar', {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            photo.reset();
            fileInput.value.value = '';
        },
    });
};
const removePhoto = () => {
    if (confirm('Remove your photo?')) router.delete('/profile/avatar', { preserveScroll: true });
};

const others = useForm({ current_password: '' });
const logoutOthers = () => others.post('/profile/sessions/others', { preserveScroll: true, onSuccess: () => others.reset() });
</script>

<template>
    <Head title="Profile" />
    <AppLayout>
        <h1 class="mb-6 text-2xl font-semibold">Profile</h1>
        <div v-if="locked" class="mb-6 border-l-4 border-amber-500 bg-white px-4 py-3 text-sm shadow-sm">
            You are using a temporary password. Please set a new password to continue.
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="space-y-6">
                <!-- Photo -->
                <section class="rounded-lg border border-[#dcdcde] bg-white p-5">
                    <h2 class="mb-4 font-medium">Photo</h2>
                    <div class="flex items-center gap-4">
                        <Avatar :name="profile.name" :url="profile.avatar_url" :size="72" />
                        <div class="space-y-2">
                            <div class="flex gap-2">
                                <button type="button" class="rounded-md border border-indigo-600 px-3 py-1.5 text-sm font-medium text-indigo-600 hover:bg-indigo-50" :disabled="photo.processing" @click="fileInput.click()">
                                    {{ profile.avatar_url ? 'Change photo' : 'Upload photo' }}
                                </button>
                                <button v-if="profile.avatar_url" type="button" class="rounded-md px-3 py-1.5 text-sm text-red-600 hover:bg-red-50" @click="removePhoto">Remove</button>
                            </div>
                            <p class="text-xs text-slate-500">JPG, PNG or WebP. Max 2 MB.</p>
                            <p v-if="photo.errors.avatar" class="text-xs text-red-600">{{ photo.errors.avatar }}</p>
                        </div>
                        <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="pickPhoto" />
                    </div>
                </section>

                <!-- Account -->
                <form class="space-y-4 rounded-lg border border-[#dcdcde] bg-white p-5" @submit.prevent="info.put('/profile')">
                    <h2 class="font-medium">Account</h2>
                    <div class="text-sm text-slate-500">Username: <span class="font-mono text-slate-800">{{ profile.username }}</span></div>
                    <TextField v-model="info.name" label="Name" required :error="info.errors.name" />
                    <TextField v-model="info.job_title" label="Job title (optional)" hint="e.g. Content Strategist" :error="info.errors.job_title" />
                    <TextField v-model="info.email" label="Email (optional)" type="email" :error="info.errors.email" />
                    <TextField v-model="info.whatsapp" label="WhatsApp number (optional)" type="tel" hint="e.g. 0812 3456 7890. Saved in international format (+62...)." :error="info.errors.whatsapp" />
                    <button class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700" :disabled="info.processing">Save</button>
                </form>
            </div>

            <div class="space-y-6">
                <!-- Password -->
                <form class="space-y-4 rounded-lg border border-[#dcdcde] bg-white p-5" @submit.prevent="savePassword">
                    <h2 class="font-medium">Change password</h2>
                    <TextField v-model="pw.current_password" label="Current password" type="password" autocomplete="current-password" required :error="pw.errors.current_password" />
                    <TextField v-model="pw.password" label="New password" type="password" autocomplete="new-password" required hint="At least 8 characters." :error="pw.errors.password" />
                    <TextField v-model="pw.password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required />
                    <button class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700" :disabled="pw.processing">Change password</button>
                </form>

                <!-- Sessions -->
                <section v-if="!locked" class="rounded-lg border border-[#dcdcde] bg-white p-5">
                    <h2 class="mb-1 font-medium">Active sessions</h2>
                    <p class="mb-4 text-sm text-slate-500">Devices where you are currently logged in.</p>
                    <ul class="divide-y divide-[#f0f0f1]">
                        <li v-for="s in sessions" :key="s.id" class="flex items-center justify-between py-2 text-sm">
                            <div>
                                <div class="font-medium">{{ s.device }}
                                    <span v-if="s.is_current" class="ml-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-normal text-green-800">This device</span>
                                </div>
                                <div class="text-xs text-slate-500">{{ s.ip }} · {{ s.last_active }}</div>
                            </div>
                        </li>
                    </ul>
                    <form v-if="sessions.length > 1" class="mt-4 space-y-3 border-t border-[#f0f0f1] pt-4" @submit.prevent="logoutOthers">
                        <TextField v-model="others.current_password" label="Confirm your password" type="password" autocomplete="current-password" required :error="others.errors.current_password" />
                        <button class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-900" :disabled="others.processing">Log out other devices</button>
                    </form>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
