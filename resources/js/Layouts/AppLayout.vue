<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Avatar from '../Components/Avatar.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash?.success);
const locked = computed(() => user.value?.must_change_password);

const sidebarOpen = ref(false);
const menuOpen = ref(false);
const menuRoot = ref(null);

const icons = {
    dashboard: 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10',
    content: 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z',
    brands: 'M20.6 13.4l-7.2 7.2a2 2 0 01-2.8 0L2 12V2h10l8.6 8.6a2 2 0 010 2.8zM7 7h.01',
    users: 'M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M10 10a4 4 0 100-8 4 4 0 000 8zM21 20v-2a4 4 0 00-3-3.87M16 2.13a4 4 0 010 7.75',
};

const items = computed(() => {
    if (locked.value) return [];
    const list = [
        { label: 'Dashboard', href: '/', icon: 'dashboard', active: (u) => u === '/' },
        { label: 'Brands', href: '/brands', icon: 'brands', active: (u) => u === '/brands' || u.startsWith('/brands/') },
        {
            label: 'Content Plan',
            icon: 'content',
            children: [
                { label: 'Add Content Plan', href: '/content-plans/create', active: (u) => u === '/content-plans/create' },
                { label: 'All Content', href: '/content-plans', active: (u) => u === '/content-plans' || /^\/content-plans\/\d+/.test(u) },
            ],
        },
    ];
    if (user.value.role === 'superadmin') list.push({ label: 'Users', href: '/users', icon: 'users', active: (u) => u === '/users' || u.startsWith('/users/') });
    return list;
});

const path = computed(() => page.url.split('?')[0]);
const isActive = (item) => (item.children ? item.children.some((c) => c.active(path.value)) : item.active(path.value));

// A group is open while one of its pages is showing, or when the person opened it by hand.
const toggled = ref({});
const isOpen = (item) => toggled.value[item.label] ?? isActive(item);
const toggle = (item) => (toggled.value = { ...toggled.value, [item.label]: !isOpen(item) });

const sectionTitle = computed(() => {
    if (page.url.startsWith('/profile')) return 'Profile';
    if (path.value === '/content-plans/create') return 'Content Plan · Add Content Plan';
    if (path.value.startsWith('/content-plans')) return 'Content Plan · All Content';
    return items.value.find(isActive)?.label ?? '';
});

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
                <template v-for="item in items" :key="item.label">
                    <!-- Plain item -->
                    <Link
                        v-if="!item.children"
                        :href="item.href"
                        class="relative flex items-center gap-2.5 px-3 py-2.5 text-[14px] leading-tight"
                        :class="isActive(item) ? 'bg-indigo-600 font-semibold text-white' : 'hover:bg-wp-submenu hover:text-wp-hover'"
                        @click="sidebarOpen = false"
                    >
                        <span v-if="isActive(item)" class="absolute right-0 top-1/2 -translate-y-1/2 border-y-[8px] border-r-[8px] border-y-transparent border-r-wp-canvas" />
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path :d="icons[item.icon]" /></svg>
                        {{ item.label }}
                    </Link>

                    <!-- Group with sub-menu -->
                    <div v-else>
                        <button
                            type="button"
                            class="relative flex w-full items-center gap-2.5 px-3 py-2.5 text-left text-[14px] leading-tight"
                            :class="isActive(item) ? 'bg-indigo-600 font-semibold text-white' : 'hover:bg-wp-submenu hover:text-wp-hover'"
                            :aria-expanded="isOpen(item)"
                            @click="toggle(item)"
                        >
                            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path :d="icons[item.icon]" /></svg>
                            {{ item.label }}
                            <svg class="ml-auto h-4 w-4 transition-transform" :class="isOpen(item) ? 'rotate-90' : ''" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
                        </button>
                        <ul v-if="isOpen(item)" class="bg-wp-submenu py-1.5">
                            <li v-for="child in item.children" :key="child.href">
                                <Link
                                    :href="child.href"
                                    class="block py-1.5 pl-11 pr-3 text-[13px]"
                                    :class="child.active(path) ? 'font-semibold text-white' : 'text-[#c3c4c7] hover:text-wp-hover'"
                                    @click="sidebarOpen = false"
                                >
                                    {{ child.label }}
                                </Link>
                            </li>
                        </ul>
                    </div>
                </template>
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
                        <Avatar :name="user.name" :url="user.avatar_url" :size="32" />
                    </button>

                    <div v-if="menuOpen" class="absolute right-0 mt-1 w-56 rounded border border-[#dcdcde] bg-white py-1 shadow-lg">
                        <div class="flex items-center gap-3 border-b border-[#f0f0f1] px-4 py-3">
                            <Avatar :name="user.name" :url="user.avatar_url" :size="40" />
                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium">{{ user.name }}</div>
                                <div class="truncate text-xs text-slate-500">{{ user.job_title || user.username }}</div>
                                <div class="text-xs capitalize text-slate-400">{{ user.role }}</div>
                            </div>
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
