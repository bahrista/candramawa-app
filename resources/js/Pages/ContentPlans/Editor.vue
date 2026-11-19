<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import BriefTab from '../../Components/ContentPlan/BriefTab.vue';
import PostDetailTab from '../../Components/ContentPlan/PostDetailTab.vue';
import PreviewPane from '../../Components/ContentPlan/PreviewPane.vue';

const props = defineProps({
    plan: { type: Object, default: null },
    brands: Array,
    users: Array,
    contentManager: Object,
    postTypes: Array,
    abilities: Object,
    suggestions: Object,
    limits: Object,
    timezone: Object,
    deadlineHours: Number,
});

// ---- form state -------------------------------------------------------------------------
const posts = {};
for (const t of props.postTypes) {
    const saved = props.plan?.posts?.[t.value];
    posts[t.value] = saved
        ? { media_links: saved.media_links.length ? [...saved.media_links] : [''], caption: saved.caption, tags: [...saved.tags], note: saved.note }
        : { media_links: [''], caption: '', tags: [], note: '' };
}

const form = useForm({
    brand_id: props.plan?.brand_id ?? (props.brands.length === 1 ? props.brands[0].id : null),
    title: props.plan?.title ?? '',
    content_pillar: props.plan?.content_pillar ?? '',
    format: props.plan?.format ?? '',
    post_types: [...(props.plan?.post_types ?? [])],
    publish_at: props.plan?.publish_at ?? '',
    deadline_mode: props.plan?.deadline_mode ?? 'auto',
    deadline_at: props.plan?.deadline_at ?? '',
    multimedia_creator_id: props.plan?.multimedia_creator_id ?? null,
    copywriter_id: props.plan?.copywriter_id ?? null,
    publisher_id: props.plan?.publisher_id ?? null,
    brief: props.plan?.brief ?? '',
    reference_links: [...(props.plan?.reference_links ?? [])],
    posts,
});

const status = computed(() => props.plan?.status ?? 'draft');
const brand = computed(() => props.brands.find((b) => b.id === form.brand_id) ?? null);
const selectedTypes = computed(() => props.postTypes.filter((t) => form.post_types.includes(t.value)));

// ---- tabs & layout ---------------------------------------------------------------------
const storedTab = () => {
    try {
        return sessionStorage.getItem('content-plan-tab');
    } catch {
        return null;
    }
};
// A new plan always starts on The brief; an existing one reopens on the tab you last used.
const tab = ref(props.plan && ['brief', 'detail'].includes(storedTab()) ? storedTab() : 'brief');
watch(tab, (value) => {
    try {
        if (value !== 'preview') sessionStorage.setItem('content-plan-tab', value);
    } catch {
        /* storage may be unavailable; the tab just won't be remembered */
    }
});

const media = window.matchMedia('(min-width: 1280px)');
const isWide = ref(media.matches);
const onMedia = (e) => {
    isWide.value = e.matches;
    if (e.matches && tab.value === 'preview') tab.value = 'brief';
};
if (isWide.value && tab.value === 'preview') tab.value = 'brief';

const activeType = ref(form.post_types[0] ?? null);
watch(
    () => form.post_types.slice(),
    (types) => {
        if (!types.includes(activeType.value)) activeType.value = types[0] ?? null;
    },
);

const hasContent = (p) => p.media_links.some((m) => m.trim() !== '') || p.caption.trim() !== '' || p.tags.length > 0 || p.note.trim() !== '';

function togglePostType(value) {
    const at = form.post_types.indexOf(value);

    if (at === -1) {
        const order = props.postTypes.map((t) => t.value);
        form.post_types = order.filter((v) => v === value || form.post_types.includes(v));
        if (!activeType.value) activeType.value = value;
        return;
    }

    const label = props.postTypes.find((t) => t.value === value).label;
    if (hasContent(form.posts[value]) && !window.confirm(`Remove ${label}? Its media, caption, tags and note will be discarded when you save.`)) return;
    form.post_types.splice(at, 1);
}

// ---- submitting --------------------------------------------------------------------------
const CONFIRM = {
    production: 'Submit to production? The people you assigned will be able to see this plan and fill in the post details.',
    review: 'Submit to review? The production team will no longer be able to edit the post details.',
    revise: 'Return this plan to production? The production team will be able to edit the post details again.',
};

const errorKeys = computed(() => Object.keys(form.errors));

// An error disappears as soon as the person edits the field it belongs to.
function clearErrorsFor(...prefixes) {
    const stale = errorKeys.value.filter((k) => prefixes.some((p) => k === p || k.startsWith(`${p}.`)));
    if (stale.length) form.clearErrors(...stale);
}
for (const key of Object.keys(form.data())) {
    if (key === 'posts') continue;
    const related = key === 'publish_at' ? [key, 'deadline_at'] : [key];
    watch(() => form[key], () => clearErrorsFor(...related), { deep: true });
}
watch(
    () => form.posts,
    () => activeType.value && clearErrorsFor(`posts.${activeType.value}`),
    { deep: true },
);
const briefErrors = computed(() => errorKeys.value.filter((k) => !k.startsWith('posts.')));
const postErrors = computed(() => errorKeys.value.filter((k) => k.startsWith('posts.')));

function onErrors() {
    if (briefErrors.value.length) tab.value = 'brief';
    else if (postErrors.value.length) {
        tab.value = 'detail';
        const withError = props.postTypes.find((t) => postErrors.value.some((k) => k.startsWith(`posts.${t.value}.`)));
        if (withError) activeType.value = withError.value;
    }
    nextTick(() => document.getElementById('error-summary')?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
}

function submit(action) {
    if (CONFIRM[action] && !window.confirm(CONFIRM[action])) return;

    form.transform((data) => ({
        ...data,
        action,
        // Only the ticked post types are sent; blank media rows are dropped.
        posts: Object.fromEntries(
            data.post_types.map((t) => [t, { ...data.posts[t], media_links: data.posts[t].media_links.filter((m) => m.trim() !== '') }]),
        ),
    }));

    // 'errors': keep what was typed when validation fails, but start fresh from the server after a save.
    const options = { preserveScroll: true, preserveState: 'errors', onError: onErrors };
    if (props.plan) form.put(`/content-plans/${props.plan.id}`, options);
    else form.post('/content-plans', options);
}

function destroy() {
    if (window.confirm(`Delete the draft "${props.plan.title}"? This cannot be undone.`)) router.delete(`/content-plans/${props.plan.id}`);
}

const buttons = computed(() => {
    const allowed = props.abilities.actions;
    const list = [];
    if (allowed.includes('save')) list.push({ action: 'save', label: status.value === 'draft' ? 'Save Draft' : 'Save Changes', style: 'secondary' });
    if (allowed.includes('revise')) list.push({ action: 'revise', label: 'Return to Production', style: 'secondary' });
    if (allowed.includes('production')) list.push({ action: 'production', label: 'Submit to Production', style: 'primary' });
    if (allowed.includes('review')) list.push({ action: 'review', label: 'Submit to Review', style: 'review' });
    return list;
});
const buttonStyle = {
    secondary: 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
    primary: 'bg-indigo-600 text-white hover:bg-indigo-700',
    review: 'bg-slate-800 text-white hover:bg-slate-900',
};

const banner = computed(() => {
    if (!props.plan) return null;
    if (!props.abilities.editBrief && props.abilities.editPosts)
        return 'The brief was set by the content manager. Fill in the post details, then submit to review when everything is ready.';
    if (!props.abilities.editBrief && !props.abilities.editPosts) return 'You can view this plan, but it can’t be edited right now.';
    return null;
});

// ---- leaving with unsaved changes -------------------------------------------------------
const stopGuard = router.on('before', (event) => {
    if (event.detail.visit.method === 'get' && form.isDirty && !window.confirm('You have unsaved changes. Leave this page?')) return false;
});
const warnOnUnload = (e) => {
    if (form.isDirty) {
        e.preventDefault();
        e.returnValue = '';
    }
};
onMounted(() => {
    media.addEventListener('change', onMedia);
    window.addEventListener('beforeunload', warnOnUnload);
});
onBeforeUnmount(() => {
    stopGuard();
    media.removeEventListener('change', onMedia);
    window.removeEventListener('beforeunload', warnOnUnload);
});

const tabClass = (name) =>
    tab.value === name ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-800';
</script>

<template>
    <Head :title="plan ? plan.title : 'Add Content Plan'" />
    <AppLayout>
        <div class="mb-5 flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold">{{ plan ? 'Content Plan' : 'Add Content Plan' }}</h1>
                <StatusBadge :status="status" />
            </div>
            <div v-if="plan" class="text-xs text-slate-500">
                <span v-if="plan.production_started_label">Production started {{ plan.production_started_label }}</span>
                <span v-if="plan.review_submitted_label"> · Submitted to review {{ plan.review_submitted_label }}</span>
            </div>
        </div>

        <div v-if="banner" class="mb-4 border-l-4 border-indigo-500 bg-white px-4 py-3 text-sm shadow-sm">{{ banner }}</div>

        <div v-if="errorKeys.length" id="error-summary" class="mb-4 border-l-4 border-red-500 bg-white px-4 py-3 text-sm shadow-sm" role="alert">
            <b>Please fix {{ errorKeys.length }} {{ errorKeys.length === 1 ? 'field' : 'fields' }} before continuing.</b>
            <span v-if="briefErrors.length" class="ml-1">{{ briefErrors.length }} in The brief.</span>
            <span v-if="postErrors.length" class="ml-1">{{ postErrors.length }} in Post detail.</span>
        </div>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_400px]">
            <!-- Column 1: tabs -->
            <section class="min-w-0">
                <div role="tablist" class="mb-4 flex gap-1 border-b border-[#dcdcde]">
                    <button type="button" role="tab" :aria-selected="tab === 'brief'" class="-mb-px flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium" :class="tabClass('brief')" @click="tab = 'brief'">
                        The brief
                        <span v-if="briefErrors.length" class="rounded-full bg-red-600 px-1.5 text-[11px] leading-5 text-white">{{ briefErrors.length }}</span>
                    </button>
                    <button type="button" role="tab" :aria-selected="tab === 'detail'" class="-mb-px flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium" :class="tabClass('detail')" @click="tab = 'detail'">
                        Post detail
                        <span v-if="postErrors.length" class="rounded-full bg-red-600 px-1.5 text-[11px] leading-5 text-white">{{ postErrors.length }}</span>
                    </button>
                    <button v-if="!isWide" type="button" role="tab" :aria-selected="tab === 'preview'" class="-mb-px border-b-2 px-4 py-2.5 text-sm font-medium" :class="tabClass('preview')" @click="tab = 'preview'">Preview</button>
                </div>

                <BriefTab
                    v-show="tab === 'brief'"
                    :form="form"
                    :brands="brands"
                    :users="users"
                    :post-types="postTypes"
                    :suggestions="suggestions"
                    :limits="limits"
                    :content-manager="contentManager"
                    :timezone="timezone"
                    :deadline-hours="deadlineHours"
                    :disabled="!abilities.editBrief"
                    @toggle-post-type="togglePostType"
                />
                <PostDetailTab
                    v-show="tab === 'detail'"
                    v-model:active-type="activeType"
                    :form="form"
                    :post-types="postTypes"
                    :limits="limits"
                    :timezone="timezone"
                    :disabled="!abilities.editPosts"
                    @go-brief="tab = 'brief'"
                />
                <PreviewPane v-if="!isWide" v-show="tab === 'preview'" v-model:active-type="activeType" :brand="brand" :types="selectedTypes" :posts="form.posts" :publish-at="form.publish_at" />
            </section>

            <!-- Column 2: preview -->
            <aside v-if="isWide" class="sticky top-20">
                <PreviewPane v-model:active-type="activeType" :brand="brand" :types="selectedTypes" :posts="form.posts" :publish-at="form.publish_at" />
            </aside>
        </div>

        <!-- Actions -->
        <div class="sticky bottom-0 z-10 -mx-4 mt-6 flex flex-wrap items-center gap-x-3 gap-y-2 border-t border-[#dcdcde] bg-white/95 px-4 py-2.5 backdrop-blur lg:-mx-8 lg:px-8 lg:py-3">
            <Link href="/content-plans" class="hidden text-sm text-slate-600 hover:underline sm:inline">← All Content</Link>
            <button v-if="abilities.canDelete" type="button" class="text-sm text-red-600 hover:underline" @click="destroy">Delete draft</button>
            <span v-if="form.isDirty" class="text-xs text-amber-700">Unsaved changes</span>
            <div class="flex w-full flex-wrap justify-end gap-2 sm:ml-auto sm:w-auto">
                <button
                    v-for="b in buttons"
                    :key="b.action"
                    type="button"
                    class="flex-1 rounded-md px-3 py-2 text-[13px] font-medium disabled:opacity-50 sm:flex-none sm:px-4 sm:text-sm"
                    :class="buttonStyle[b.style]"
                    :disabled="form.processing"
                    @click="submit(b.action)"
                >
                    {{ b.label }}
                </button>
            </div>
        </div>
    </AppLayout>
</template>
