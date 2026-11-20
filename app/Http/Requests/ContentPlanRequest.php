<?php

namespace App\Http\Requests;

use App\Enums\BrandRole;
use App\Enums\ContentStatus;
use App\Models\Brand;
use App\Models\BrandContentPillar;
use App\Models\BrandKnowledgeItem;
use App\Models\BrandMember;
use App\Models\BrandProduct;
use App\Models\ContentPlan;
use App\Rules\GoogleDriveUrl;
use App\Support\ContentTypes;
use App\Support\LocalTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * One request for every button on the content plan form. What is required depends on
 * the button (draft < production < review) and on who is pressing it.
 */
class ContentPlanRequest extends FormRequest
{
    public function plan(): ?ContentPlan
    {
        $plan = $this->route('contentPlan');

        return $plan instanceof ContentPlan ? $plan : null;
    }

    public function planAction(): string
    {
        return (string) $this->input('action');
    }

    public function authorize(): bool
    {
        $action = $this->planAction();
        $plan = $this->plan();

        if (! in_array($action, ContentPlan::ACTIONS, true)) {
            return false;
        }

        if ($plan === null) {
            return in_array($action, ['save', 'production', 'review'], true);
        }

        return $this->user()->can('perform', [$plan, $action]);
    }

    /** The brief tab (brand, title, schedule, team ...) belongs to the people who manage the plan. */
    public function canEditBrief(): bool
    {
        $plan = $this->plan();

        return $plan === null || $this->user()->can('editBrief', $plan);
    }

    public function canEditPosts(): bool
    {
        $plan = $this->plan();

        return $plan === null || $this->user()->can('editPosts', $plan);
    }

    protected function prepareForValidation(): void
    {
        $posts = [];
        foreach ((array) $this->input('posts', []) as $key => $post) {
            if (! is_array($post)) {
                continue;
            }
            $posts[$key] = [
                'media_links' => $this->cleanList($post['media_links'] ?? []),
                'caption' => is_string($post['caption'] ?? null) ? $post['caption'] : null,
                'tags' => $this->cleanTags($post['tags'] ?? []),
                'note' => is_string($post['note'] ?? null) ? $post['note'] : null,
            ];
        }

        $this->merge([
            'content_types' => $this->cleanList($this->input('content_types', [])),
            'brief_references' => $this->cleanReferences($this->input('brief_references', [])),
            'posts' => $posts,
        ]);
    }

    public function rules(): array
    {
        $plan = $this->plan();
        $action = $this->planAction();

        // Once a plan has left the draft stage its brief must stay complete.
        $strict = in_array($action, ['production', 'review', 'revise'], true)
            || ($plan !== null && $plan->status !== ContentStatus::Draft);
        $reviewStrict = $action === 'review' || $plan?->status === ContentStatus::InReview;

        $rules = [];

        if ($this->canEditBrief()) {
            $rules += $this->briefRules($strict);
        }

        if ($this->canEditPosts()) {
            $rules += $this->postRules($reviewStrict);
        }

        return $rules;
    }

    private function briefRules(bool $strict): array
    {
        $required = $strict ? 'required' : 'nullable';
        $manual = $this->input('deadline_mode') === ContentPlan::DEADLINE_MANUAL;

        $rules = [
            'brand_id' => ['required', 'integer', function ($attribute, $value, $fail) {
                if (! $this->brandIsAllowed((int) $value)) {
                    $fail('Choose one of your brands.');
                }
            }],
            'title' => ['required', 'string', 'max:255'],
            'content_pillar_id' => [$required, 'integer', function ($attribute, $value, $fail) {
                if (! $this->pillarIsAllowed((int) $value)) {
                    $fail('Choose one of this brand\'s content pillars.');
                }
            }],
            'content_types' => $strict ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
            'content_types.*' => ['distinct', Rule::in(ContentTypes::keys())],
            'publish_at' => [$required, 'date_format:'.LocalTime::INPUT_FORMAT],
            'deadline_mode' => ['required', Rule::in([ContentPlan::DEADLINE_AUTO, ContentPlan::DEADLINE_MANUAL])],
            'multimedia_creator_id' => $this->assigneeRules('multimedia_creator_id', BrandRole::MultimediaCreator),
            'copywriter_id' => $this->assigneeRules('copywriter_id', BrandRole::Copywriter),
            'publisher_id' => $this->assigneeRules('publisher_id', BrandRole::Publisher),
            'brief' => [$required, 'string', 'max:10000'],
            'brief_references' => ['nullable', 'array', 'max:'.config('content.max_references')],
            'brief_references.*.kind' => ['required', Rule::in(['link', 'knowledge', 'product'])],
            'brief_references.*.url' => ['required_if:brief_references.*.kind,link', 'nullable', 'url:http,https', 'max:500'],
            'brief_references.*.id' => ['required_if:brief_references.*.kind,knowledge,product', 'nullable', 'integer'],
        ];

        // A stale deadline sent along with "automatic" is ignored, not validated.
        if ($manual) {
            $rules['deadline_at'] = [
                $strict ? 'required' : 'nullable',
                'date_format:'.LocalTime::INPUT_FORMAT,
                'before_or_equal:publish_at',
            ];
        }

        return $rules;
    }

    /** People are picked from the brand's team (for the right role); the current person may stay. */
    private function assigneeRules(string $field, BrandRole $role): array
    {
        return ['nullable', 'integer', function ($attribute, $value, $fail) use ($field, $role) {
            if (! $this->assigneeIsAllowed($field, $role, (int) $value)) {
                $fail("Choose someone from the brand's team with the {$role->label()} role.");
            }
        }];
    }

    private function postRules(bool $reviewStrict): array
    {
        $rules = ['posts' => ['nullable', 'array']];

        foreach ($this->contentTypeKeys() as $key) {
            $type = ContentTypes::find($key);
            $p = "posts.$key";

            $media = $reviewStrict ? ['required', 'array', 'min:'.$type['min_media']] : ['nullable', 'array'];
            $media[] = 'max:'.$type['max_media'];

            $rules["$p.media_links"] = $media;
            $rules["$p.media_links.*"] = ['string', 'max:500', new GoogleDriveUrl];
            $rules["$p.caption"] = [
                $reviewStrict && $type['caption_required'] ? 'required' : 'nullable',
                'string',
                'max:'.$type['caption_limit'],
            ];
            $rules["$p.tags"] = ['nullable', 'array', 'max:'.config('content.max_tags')];
            $rules["$p.tags.*"] = ['string', 'max:100', 'regex:/^[#@][\p{L}\p{N}_.]+$/u'];
            $rules["$p.note"] = ['nullable', 'string', 'max:2000'];
        }

        return $rules;
    }

    /** Knowledge and product references must belong to the plan's own brand. */
    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $this->canEditBrief()) {
                return;
            }

            $brandId = (int) $this->input('brand_id');
            $wanted = ['knowledge' => [], 'product' => []];

            foreach ($this->input('brief_references', []) as $i => $reference) {
                if (isset($wanted[$reference['kind'] ?? ''])) {
                    $wanted[$reference['kind']][$i] = (int) ($reference['id'] ?? 0);
                }
            }

            $valid = [
                'knowledge' => BrandKnowledgeItem::query()->where('brand_id', $brandId)->whereIn('id', $wanted['knowledge'])->pluck('id')->all(),
                'product' => BrandProduct::query()->where('brand_id', $brandId)->whereIn('id', $wanted['product'])->pluck('id')->all(),
            ];

            foreach ($wanted as $kind => $ids) {
                foreach ($ids as $i => $id) {
                    if (! in_array($id, $valid[$kind], true)) {
                        $validator->errors()->add("brief_references.$i.id", 'This entry does not belong to the selected brand.');
                    }
                }
            }
        }];
    }

    /** @return list<string> the content types the posts rules apply to */
    private function contentTypeKeys(): array
    {
        $keys = $this->canEditBrief()
            ? $this->input('content_types', [])
            : ($this->plan()?->contentTypeKeys() ?? []);

        return array_values(array_filter((array) $keys, fn ($k) => is_string($k) && ContentTypes::find($k) !== null));
    }

    private function brandIsAllowed(int $brandId): bool
    {
        $brand = Brand::find($brandId);

        if ($brand === null) {
            return false;
        }

        return $this->user()->can('createContent', $brand) || $this->plan()?->brand_id === $brand->id;
    }

    private function pillarIsAllowed(int $pillarId): bool
    {
        $pillar = BrandContentPillar::find($pillarId);

        if ($pillar === null || $pillar->brand_id !== (int) $this->input('brand_id')) {
            return false;
        }

        return ! $pillar->isArchived() || $this->plan()?->content_pillar_id === $pillar->id;
    }

    private function assigneeIsAllowed(string $field, BrandRole $role, int $userId): bool
    {
        $brandId = (int) $this->input('brand_id');
        $plan = $this->plan();

        // Whoever is already assigned on this brand may stay, even if they left the team since.
        if ($plan !== null && $plan->brand_id === $brandId && $plan->{$field} === $userId) {
            return true;
        }

        return BrandMember::query()
            ->where('brand_id', $brandId)->where('user_id', $userId)->where('role', $role->value)
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->exists();
    }

    /** @return list<string> */
    private function cleanList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = array_map(fn ($v) => is_string($v) ? trim($v) : '', $value);

        return array_values(array_filter($items, fn ($v) => $v !== ''));
    }

    /** Blank links are dropped; internal references are de-duplicated. */
    private function cleanReferences(mixed $value): array
    {
        $references = [];

        foreach ((array) $value as $reference) {
            if (! is_array($reference)) {
                continue;
            }

            $kind = is_string($reference['kind'] ?? null) ? $reference['kind'] : 'link';

            if ($kind === 'link') {
                $url = is_string($reference['url'] ?? null) ? trim($reference['url']) : '';
                if ($url !== '') {
                    $references[] = ['kind' => 'link', 'url' => $url];
                }

                continue;
            }

            $references[$kind.':'.($reference['id'] ?? '')] = ['kind' => $kind, 'id' => $reference['id'] ?? null];
        }

        return array_values($references);
    }

    /** "kopi" becomes "#kopi"; "@kopi" and "#kopi" stay; duplicates are dropped. */
    private function cleanTags(mixed $value): array
    {
        $tags = [];

        foreach ($this->cleanList($value) as $tag) {
            $tag = preg_replace('/\s+/u', '', $tag);

            if ($tag === '' || $tag === null) {
                continue;
            }

            if (! in_array($tag[0], ['#', '@'], true)) {
                $tag = '#'.$tag;
            }

            $tags[mb_strtolower($tag)] ??= $tag; // first spelling wins
        }

        return array_values($tags);
    }

    public function messages(): array
    {
        return [
            'brand_id.required' => 'Choose a brand.',
            'title.required' => 'Give the content a title.',
            'content_pillar_id.required' => 'Choose a content pillar.',
            'content_types.required' => 'Choose at least one content type.',
            'content_types.min' => 'Choose at least one content type.',
            'publish_at.required' => 'Set the publish date and time.',
            'publish_at.date_format' => 'Enter a valid publish date and time.',
            'deadline_at.required' => 'Set the deadline.',
            'deadline_at.date_format' => 'Enter a valid deadline.',
            'deadline_at.before_or_equal' => 'The deadline must be on or before the publish date.',
            'brief.required' => 'Write the brief.',
            'brief_references.*.url.required_if' => 'Enter a link.',
            'brief_references.*.url.url' => 'Enter a valid link starting with http:// or https://.',
            'posts.*.media_links.required' => 'Add at least one Google Drive link.',
            'posts.*.media_links.min' => 'This content type needs at least :min media links.',
            'posts.*.media_links.max' => 'Too many media links (maximum :max).',
            'posts.*.caption.required' => 'Write a caption.',
            'posts.*.caption.max' => 'The caption may not be longer than :max characters.',
            'posts.*.tags.*.regex' => 'Tags may only contain letters, numbers, dots and underscores.',
        ];
    }

    public function attributes(): array
    {
        $attributes = [
            'content_pillar_id' => 'content pillar',
            'publish_at' => 'publish date',
            'deadline_at' => 'deadline',
            'multimedia_creator_id' => 'multimedia creator',
            'copywriter_id' => 'copy writer',
            'publisher_id' => 'publisher',
            'brief_references.*.url' => 'reference link',
        ];

        foreach (ContentTypes::all() as $key => $type) {
            $attributes["posts.$key.media_links.*"] = $type['full_label'].' media link';
            $attributes["posts.$key.tags.*"] = 'tag';
            $attributes["posts.$key.note"] = 'note';
        }

        return $attributes;
    }
}
