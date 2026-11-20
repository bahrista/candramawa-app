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
                The products and services this brand sells. Like the knowledge base, each entry has a <b>share link</b> that can be added to a content brief as a reference.
            </p>
            <Link v-if="canManage" :href="`/brands/${brand.id}/products/create`" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Add product or service</Link>
        </div>

        <ul v-if="items.length" class="divide-y divide-[#f0f0f1] rounded-lg border border-[#dcdcde] bg-white">
            <li v-for="item in items" :key="item.id" class="flex flex-wrap items-start justify-between gap-3 px-5 py-4">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <Link :href="item.share_url" class="font-medium text-indigo-600 hover:underline" :class="!item.is_active && 'text-slate-500'">{{ item.name }}</Link>
                        <span class="rounded-full px-2 py-0.5 text-xs capitalize" :class="item.type === 'service' ? 'bg-violet-100 text-violet-800' : 'bg-sky-100 text-sky-800'">{{ item.type }}</span>
                        <span v-if="!item.is_active" class="rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-600">Inactive</span>
                        <span v-if="item.price" class="text-sm text-slate-600">{{ item.price }}</span>
                    </div>
                    <p v-if="item.excerpt" class="mt-1 line-clamp-2 text-sm text-slate-600">{{ item.excerpt }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <CopyButton :text="() => absoluteUrl(item.share_url)" label="Copy link" />
                    <Link v-if="canManage" :href="`${item.share_url}/edit`" class="text-sm text-indigo-600 hover:underline">Edit</Link>
                </div>
            </li>
        </ul>
        <div v-else class="rounded-lg border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">No products or services yet.</div>
    </div>
</template>
