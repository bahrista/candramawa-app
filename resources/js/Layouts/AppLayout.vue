<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash?.success);
const locked = computed(() => user.value?.must_change_password);
const open = ref(false);

const icons = {
    dashboard: 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10',
    users: 'M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M10 10a4 4 0 100-8 4 4 0 000 8zM21 20v-2a4 4 0 00-3-3.87M16 2.13a4 4 0 010 7.75',
    profile: 'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z',
};

const items = computed(() => {
    if (locked.value) return [{ label: 'Profile', href: '/profile', icon: 'profile', match: '/profile' }];
    const list = [{ label: 'Dashboard', href: '/', icon: 'dashboard', match: '/' }];
    if (user.value.role === 'superadmin') list.push({ label: 'Users', href: '/users', icon: 'users', match: '/users' });
    list.push({ label: 'Profile', href: '/profile', icon: 'profile', match: '/profile' });
    return list;
});

const isActive = (item) => {
    const url = page.url.split('?')[0];
    return item.match === '/' ? url === '/' : url === item.match || url.startsWith(item.match + '/');
};

const initials = computed(() => user.value.name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase());
const logout = () => router.post('/logout');
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <!-- Mobile backdrop -->
        <div v-if="open" class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden" @click="open = false" />

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-slate-900 text-slate-300 transition-transform lg:translate-x-0"
            :class="open ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-16 items-center gap-2 px-5 text-lg font-semibold text-white">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-500 text-sm">C</span>
                Candramawa
            </div>

            <nav class="flex-1 space-y-1 px-3 py-4">
                <Link
                    v-for="item in items"
                    :key="item.href"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition"
                    :class="isActive(item) ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800 hover:text-white'"
                    @click="open = false"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path :d="icons[item.icon]" />
                    </svg>
                    {{ item.label }}
                </Link>
            </nav>

            <div class="border-t border-slate-800 p-4">
                <div class="mb-3 flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-700 text-xs font-semibold text-white">{{ initials }}</span>
                    <div class="min-w-0">
                        <div class="truncate text-sm font-medium text-white">{{ user.name }}</div>
                        <div class="text-xs capitalize text-slate-400">{{ user.role }}</div>
                    </div>
                </div>
                <button class="w-full rounded-lg border border-slate-700 px-3 py-2 text-sm hover:bg-slate-800 hover:text-white" @click="logout">
                    Log out
                </button>
            </div>
        </aside>

        <!-- Content -->
        <div class="lg:pl-64">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white px-4 lg:hidden">
                <button class="rounded-md p-2 text-slate-600 hover:bg-slate-100" aria-label="Open menu" @click="open = true">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <span class="font-semibold text-indigo-700">Candramawa App</span>
            </header>

            <main class="mx-auto max-w-6xl px-4 py-8 lg:px-8">
                <div v-if="flash" class="mb-4 rounded-md bg-green-50 px-4 py-2 text-sm text-green-800">{{ flash }}</div>
                <slot />
            </main>
        </div>
    </div>
</template>
