<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';

const props = defineProps({ status: { type: Number, default: 404 } });
const loggedIn = computed(() => !!usePage().props.auth?.user);

const copy = computed(() =>
    props.status === 403
        ? { title: 'You don’t have access to this page', text: 'It may belong to a brand or content plan you are not part of. Ask the content manager or brand owner to add you to the brand’s team, then open the link again.' }
        : { title: 'We can’t find that page', text: 'The link may be wrong, or the content may have been removed.' },
);
</script>

<template>
    <Head :title="copy.title" />
    <component :is="loggedIn ? AppLayout : 'div'" :class="loggedIn ? '' : 'flex min-h-screen items-center justify-center px-4'">
        <div class="mx-auto max-w-md rounded-lg border border-[#dcdcde] bg-white p-8 text-center">
            <div class="text-5xl font-semibold text-slate-300">{{ status }}</div>
            <h1 class="mt-3 text-xl font-semibold">{{ copy.title }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ copy.text }}</p>
            <Link :href="loggedIn ? '/' : '/login'" class="mt-5 inline-block rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                {{ loggedIn ? 'Back to dashboard' : 'Log in' }}
            </Link>
        </div>
    </component>
</template>
