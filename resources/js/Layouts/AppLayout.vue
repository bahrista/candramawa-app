<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash?.success);
const locked = computed(() => user.value?.must_change_password);

const sidebarOpen = ref(false);
const menuOpen = ref(false);
const menuRoot = ref(null);

const icons = {
    dashboard: 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10',
    users: 'M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M10 10a4 4 0 100-8 4 4 0 000 8zM21 20v-2a4 4 0 00-3-3.87M16 2.13a4 4 0 010 7.75',
};

const items = computed(() => {
    if (locked.value) return [];
    const list = [{ label: 'Dashboard', href: '/', icon: 'dashboard', match: '/' }];
    if (user.value.role === 'superadmin') list.push({ label: 'Users', href: '/users', icon: 'users', match: '/users' });
    return list;
});

const isActive = (item) => {
    const url = page.url.split('?')[0];
    return item.match === '/' ? url === '/' : url === item.match || url.startsWith(item.match + '/');
};

const sectionTitle = computed(() => {
    if (page.url.startsWith('/profile')) return 'Profile';
    return items.value.find(isActive)?.label ?? '';
});

const initials = computed(() => user.value.name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase());
const logout = () => router.post('/logout');

const closeOnOutside = (e) => {
    if (menuRoot.value && !menuRoot.value.contains(e.target)) menuOpen.value = false;
};
const closeOnEsc = (e) => {
    if (e.key === 'Escape') menuOpen.value = false;
};
onMounted(() => {
    document.addEventListener('click', closeOnOutside);
    document.addEventListener('keydown', closeOnEsc);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', closeOnOutside);
    document.removeEventListener('keydown', closeOnEsc);
});
</script>

<template>
    <div class="min-h-screen bg-wp-canvas text-[#1d2327]">
        <!-- Mobile backdrop -->
        <div v-if="sidebarOpen" class="fixed inset-0 z-30 bg-black/50 lg:hidden" @click="sidebarOpen = false" />

        <!-- Sidebar (WordPress style) -->
        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-[200px] flex-col bg-wp-sidebar text-[#f0f0f1] transition-transform lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-14 items-center px-4 text-[17px] font-semibold text-white">Candramawa</div>

            <nav class="flex-1 py-1">
                <Link
                    v-for="item in items"
                    :key="item.href"
                    :href="item.href"
                    class="relative flex items-center gap-2.5 px-3 py-2.5 text-[14px] leading-tight"
                    :class="isActive(item) ? 'bg-indigo-600 font-semibold text-white' : 'hover:bg-wp-submenu hover:text-wp-hover'"
                    @click="sidebarOpen = false"
                >
                    <span v-if="isActive(item)" class="absolute right-0 top-1/2 -translate-y-1/2 border-y-[8px] border-r-[8px] border-y-transparent border-r-wp-canvas" />
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path :d="icons[item.icon]" />
                    </svg>
                    {{ item.label }}
                </Link>
            </nav>
        </aside>

        <div class="lg:pl-[200px]">
            <!-- Top bar -->
            <header class="sticky top-0 z-20 flex h-14 items-center justify-between border-b border-[#dcdcde] bg-white px-4 lg:px-6">
                <div class="flex items-center gap-3">
                    <button class="rounded p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Open menu" @click="sidebarOpen = true">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>
                    <span class="text-sm font-medium text-slate-500">{{ sectionTitle }}</span>
                </div>

                <!-- User menu -->
                <div ref="menuRoot" class="relative">
                    <button
                        class="flex items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-slate-100"
                        :aria-expanded="menuOpen"
                        @click="menuOpen = !menuOpen"
                    >
                        <span class="hidden text-slate-600 sm:inline">Howdy, <span class="font-medium text-[#1d2327]">{{ user.name }}</span></span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-wp-sidebar text-xs font-semibold text-white">{{ initials }}</span>
                    </button>

                    <div v-if="menuOpen" class="absolute right-0 mt-1 w-56 rounded border border-[#dcdcde] bg-white py-1 shadow-lg">
                        <div class="border-b border-[#f0f0f1] px-4 py-2">
                            <div class="truncate text-sm font-medium">{{ user.name }}</div>
                            <div class="text-xs capitalize text-slate-500">{{ user.role }} · {{ user.username }}</div>
                        </div>
                        <Link href="/profile" class="block px-4 py-2 text-sm hover:bg-[#f0f6fc] hover:text-indigo-600" @click="menuOpen = false">Edit Profile</Link>
                        <button class="block w-full px-4 py-2 text-left text-sm hover:bg-[#f0f6fc] hover:text-indigo-600" @click="logout">Log Out</button>
                    </div>
                </div>
            </header>

            <main class="px-4 py-6 lg:px-8">
                <div v-if="flash" class="mb-4 border-l-4 border-green-600 bg-white px-4 py-3 text-sm shadow-sm">{{ flash }}</div>
                <slot />
            </main>
        </div>
    </div>
</template>
