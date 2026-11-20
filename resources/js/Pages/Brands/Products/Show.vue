<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import Breadcrumb from '../../../Components/Breadcrumb.vue';
import BrandLogo from '../../../Components/BrandLogo.vue';
import CopyButton from '../../../Components/CopyButton.vue';
import { absoluteUrl } from '../../../lib/clipboard';

defineProps({ brand: Object, item: Object, canManage: Boolean });
</script>

<template>
    <Head :title="item.name" />
    <AppLayout>
        <Breadcrumb :items="[{ label: 'Brands', href: '/brands' }, { label: brand.name, href: `/brands/${brand.id}?tab=products` }, { label: item.name }]" />

        <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <BrandLogo :name="brand.name" :url="brand.logo_url" :color="brand.color" :size="40" />
                <div class="min-w-0">
                    <h1 class="text-2xl font-semibold">{{ item.name }}</h1>
                    <p class="text-sm capitalize text-slate-500">{{ item.type }} · {{ brand.name }}<template v-if="!item.is_active"> · inactive</template></p>
                </div>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <CopyButton :text="() => absoluteUrl(item.share_url)" label="Copy share link" />
                <Link v-if="canManage" :href="`${item.share_url}/edit`" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-50">Edit</Link>
            </div>
        </div>

        <article class="max-w-3xl space-y-6 rounded-lg border border-[#dcdcde] bg-white p-6">
            <p v-if="item.price" class="text-lg font-semibold">{{ item.price }}</p>

            <section v-if="item.description">
                <h2 class="mb-1 text-sm font-medium text-slate-700">Description</h2>
                <p class="whitespace-pre-wrap break-words text-[15px] leading-relaxed text-slate-800">{{ item.description }}</p>
            </section>

            <section v-if="item.key_benefits">
                <h2 class="mb-1 text-sm font-medium text-slate-700">Key benefits</h2>
                <p class="whitespace-pre-wrap break-words text-[15px] leading-relaxed text-slate-800">{{ item.key_benefits }}</p>
            </section>

            <section v-if="item.links.length">
                <h2 class="mb-2 text-sm font-medium text-slate-700">Links</h2>
                <ul class="space-y-1 text-sm">
                    <li v-for="(link, i) in item.links" :key="i"><a :href="link.url" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">{{ link.label || link.url }} ↗</a></li>
                </ul>
            </section>

            <p class="border-t border-[#f0f0f1] pt-4 text-xs text-slate-500">
                Updated {{ item.updated_label }}<template v-if="item.created_by"> · added by {{ item.created_by }}</template>.
                Anyone on this brand’s team can open the share link after logging in.
            </p>
        </article>
    </AppLayout>
</template>
