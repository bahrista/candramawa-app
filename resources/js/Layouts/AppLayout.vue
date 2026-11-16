<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash?.success);
const locked = computed(() => user.value?.must_change_password);
</script>

<template>
    <div class="min-h-screen">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
                <div class="flex items-center gap-6">
                    <Link href="/" class="font-semibold text-indigo-700">Candramawa App</Link>
                    <nav v-if="!locked" class="flex gap-4 text-sm text-slate-600">
                        <Link v-if="user.role === 'superadmin'" href="/users" class="hover:text-slate-900">Users</Link>
                        <Link href="/profile" class="hover:text-slate-900">Profile</Link>
                    </nav>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <span class="text-slate-600">{{ user.name }}</span>
                    <button class="rounded-md border border-slate-300 px-3 py-1 hover:bg-slate-100" @click="router.post('/logout')">
                        Log out
                    </button>
                </div>
            </div>
        </header>
        <main class="mx-auto max-w-5xl px-4 py-8">
            <div v-if="flash" class="mb-4 rounded-md bg-green-50 px-4 py-2 text-sm text-green-800">{{ flash }}</div>
            <slot />
        </main>
    </div>
</template>
