<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import CopyButton from '../CopyButton.vue';
import { absoluteUrl } from '../../lib/clipboard';

// One link per audience. They all open the same plan, on the tab that suits that audience,
// and everyone still has to log in and be on the brand's team (or assigned) to see it.
const props = defineProps({
    plan: { type: Object, required: true }, // { id, title, status, publish_label, deadline_label }
    brandName: { type: String, default: '' },
    people: { type: Object, default: () => ({}) }, // names per audience: { production: [...], approval: [...], publisher: [...] }
});

const open = ref(false);
const root = ref(null);
const isDraft = computed(() => props.plan.status === 'draft');

const url = (view) => absoluteUrl(`/content-plans/${props.plan.id}?view=${view}`);
const when = computed(() => props.plan.publish_label ?? 'a date to be set');

const audiences = computed(() => [
    {
        view: 'production',
        title: 'Production team',
        who: 'Multimedia Creator, Copy Writer',
        hint: 'Opens the brief so they can add media, captions and tags.',
        message: `Hi! Please work on “${props.plan.title}” for ${props.brandName}. Publish: ${when.value}. Deadline: ${props.plan.deadline_label ?? 'to be set'}. Brief and details: ${url('production')}`,
    },
    {
        view: 'approval',
        title: 'Approval',
        who: 'Content Manager, Brand Owner',
        hint: 'Opens the caption and preview, ready to review.',
        message: `Hi! “${props.plan.title}” for ${props.brandName} is ready for your review (publish: ${when.value}). Please check it here: ${url('approval')}`,
    },
    {
        view: 'publisher',
        title: 'Publisher',
        who: 'Publisher',
        hint: 'Opens the publish kit with copy and download buttons.',
        message: `Hi! “${props.plan.title}” for ${props.brandName} is ready to publish on ${when.value}. Caption and files are here: ${url('publisher')}`,
    },
]);

const whatsapp = (message) => `https://wa.me/?text=${encodeURIComponent(message)}`;

const outside = (e) => {
    if (root.value && !root.value.contains(e.target)) open.value = false;
};
const esc = (e) => e.key === 'Escape' && (open.value = false);
onMounted(() => {
    document.addEventListener('click', outside);
    document.addEventListener('keydown', esc);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', outside);
    document.removeEventListener('keydown', esc);
});
</script>

<template>
    <div ref="root" class="relative">
        <button type="button" class="flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50" :aria-expanded="open" @click="open = !open">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1M14 10a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1" /></svg>
            Share
        </button>

        <div v-if="open" class="absolute right-0 z-30 mt-2 w-[22rem] max-w-[92vw] rounded-lg border border-[#dcdcde] bg-white p-4 shadow-lg" role="dialog" aria-label="Share this content plan">
            <div v-if="isDraft" class="text-sm text-slate-600">
                <p class="font-medium text-slate-800">Drafts are private</p>
                <p class="mt-1">Only you can see a draft. Submit it to production first, then share the link with the team.</p>
            </div>

            <template v-else>
                <p class="mb-3 text-xs text-slate-500">Pick who it is for. People must log in and be on the brand’s team (or assigned to this plan) to open it.</p>
                <ul class="space-y-3">
                    <li v-for="a in audiences" :key="a.view" class="rounded-md border border-slate-200 p-3">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="font-medium">{{ a.title }}</span>
                            <span class="truncate text-xs text-slate-500">{{ (people[a.view] ?? []).join(', ') || a.who }}</span>
                        </div>
                        <p class="mb-2 mt-0.5 text-xs text-slate-500">{{ a.hint }}</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <CopyButton :text="() => url(a.view)" label="Copy link" />
                            <a :href="whatsapp(a.message)" target="_blank" rel="noopener noreferrer" class="rounded-md border border-green-600 bg-white px-2.5 py-1 text-sm font-medium text-green-700 hover:bg-green-50">WhatsApp</a>
                            <CopyButton :text="a.message" label="Copy message" variant="link" />
                        </div>
                    </li>
                </ul>
            </template>
        </div>
    </div>
</template>
