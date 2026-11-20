<script setup>
import { Link } from '@inertiajs/vue3';
import CopyButton from '../CopyButton.vue';
import { absoluteUrl } from '../../lib/clipboard';

defineProps({ brand: Object, items: Array, canManage: Boolean });
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <p class="max-w-2xl text-sm text-slate-600">
                What the team (and later, the AI) should know about this brand: its story, tone of voice, audience, guidelines and answers to common questions.
                Every entry has its own <b>share link</b>, which can also be added to a content brief as a reference.
            </p>
            <Link v-if="canManage" :href="`/brands/${brand.id}/knowledge/create`" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Add entry</Link>
        </div>

        <ul v-if="items.length" class="divide-y divide-[#f0f0f1] rounded-lg border border-[#dcdcde] bg-white">
            <li v-for="item in items" :key="item.id" class="flex flex-wrap items-start justify-between gap-3 px-5 py-4">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <Link :href="item.share_url" class="font-medium text-indigo-600 hover:underline">{{ item.title }}</Link>
                        <span v-if="item.category" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ item.category }}</span>
                        <span v-if="item.links" class="text-xs text-slate-500">{{ item.links }} link{{ item.links === 1 ? '' : 's' }}</span>
                    </div>
                    <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ item.excerpt }}</p>
                    <p class="mt-1 text-xs text-slate-400">Updated {{ item.updated_label }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <CopyButton :text="() => absoluteUrl(item.share_url)" label="Copy link" />
                    <Link v-if="canManage" :href="`${item.share_url}/edit`" class="text-sm text-indigo-600 hover:underline">Edit</Link>
                </div>
            </li>
        </ul>
        <div v-else class="rounded-lg border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">No knowledge base entries yet.</div>
    </div>
</template>
