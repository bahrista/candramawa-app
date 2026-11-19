<?php

namespace App\Http\Requests;

use App\Enums\ContentStatus;
use App\Enums\PostType;
use App\Models\Brand;
use App\Models\ContentPlan;
use App\Rules\GoogleDriveUrl;
use App\Support\LocalTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

    /** The brief tab (brand, title, schedule, team ...) belongs to the content manager. */
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
        foreach ((array) $this->input('posts', []) as $type => $post) {
            if (! is_array($post)) {
                continue;
            }
            $posts[$type] = [
                'media_links' => $this->cleanList($post['media_links'] ?? []),
                'caption' => is_string($post['caption'] ?? null) ? $post['caption'] : null,
                'tags' => $this->cleanTags($post['tags'] ?? []),
                'note' => is_string($post['note'] ?? null) ? $post['note'] : null,
            ];
        }

        $this->merge([
            'post_types' => $this->cleanList($this->input('post_types', [])),
            'reference_links' => $this->cleanList($this->input('reference_links', [])),
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
        $activeUser = Rule::exists('users', 'id')->where('is_active', true);

        $rules = [
            'brand_id' => ['required', 'integer', function ($attribute, $value, $fail) {
                if (! $this->brandIsAllowed((int) $value)) {
                    $fail('Choose one of your brands.');
                }
            }],
            'title' => ['required', 'string', 'max:255'],
            'content_pillar' => [$required, 'string', 'max:100'],
            'format' => [$required, 'string', 'max:100'],
            'post_types' => $strict ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
            'post_types.*' => ['distinct', Rule::enum(PostType::class)],
            'publish_at' => [$required, 'date_format:'.LocalTime::INPUT_FORMAT],
            'deadline_mode' => ['required', Rule::in([ContentPlan::DEADLINE_AUTO, ContentPlan::DEADLINE_MANUAL])],
            'multimedia_creator_id' => ['nullable', 'integer', $activeUser],
            'copywriter_id' => ['nullable', 'integer', $activeUser],
            'publisher_id' => ['nullable', 'integer', $activeUser],
            'brief' => [$required, 'string', 'max:10000'],
            'reference_links' => ['nullable', 'array', 'max:'.config('content.max_references')],
            'reference_links.*' => ['url:http,https', 'max:500'],
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

    private function postRules(bool $reviewStrict): array
    {
        $rules = ['posts' => ['nullable', 'array']];

        foreach ($this->postTypes() as $value) {
            $type = PostType::from($value);
            $p = "posts.$value";

            $media = $reviewStrict ? ['required', 'array', 'min:'.$type->minMedia()] : ['nullable', 'array'];
            $media[] = 'max:'.$type->maxMedia();

            $rules["$p.media_links"] = $media;
            $rules["$p.media_links.*"] = ['string', 'max:500', new GoogleDriveUrl];
            $rules["$p.caption"] = [
                $reviewStrict && $type->captionRequired() ? 'required' : 'nullable',
                'string',
                'max:'.config('content.caption_limit'),
            ];
            $rules["$p.tags"] = ['nullable', 'array', 'max:'.config('content.max_tags')];
            $rules["$p.tags.*"] = ['string', 'max:100', 'regex:/^[#@][\p{L}\p{N}_.]+$/u'];
            $rules["$p.note"] = ['nullable', 'string', 'max:2000'];
        }

        return $rules;
    }

    /** @return list<string> the post types the posts rules apply to */
    private function postTypes(): array
    {
        $types = $this->canEditBrief()
            ? $this->input('post_types', [])
            : ($this->plan()?->postTypeValues() ?? []);

        return array_values(array_filter((array) $types, fn ($t) => is_string($t) && PostType::tryFrom($t) !== null));
    }

    private function brandIsAllowed(int $brandId): bool
    {
        $brand = Brand::find($brandId);

        if ($brand === null) {
            return false;
        }

        $user = $this->user();

        return $user->isSuperadmin()
            || $brand->created_by === $user->id
            || $this->plan()?->brand_id === $brand->id;
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
            'content_pillar.required' => 'Add a content pillar.',
            'format.required' => 'Add a format.',
            'post_types.required' => 'Choose at least one post type.',
            'post_types.min' => 'Choose at least one post type.',
            'publish_at.required' => 'Set the publish date and time.',
            'publish_at.date_format' => 'Enter a valid publish date and time.',
            'deadline_at.required' => 'Set the deadline.',
            'deadline_at.date_format' => 'Enter a valid deadline.',
            'deadline_at.before_or_equal' => 'The deadline must be on or before the publish date.',
            'brief.required' => 'Write the brief.',
            'reference_links.*.url' => 'Enter a valid link starting with http:// or https://.',
            'posts.*.media_links.required' => 'Add at least one Google Drive link.',
            'posts.*.media_links.min' => 'A carousel needs at least :min media links.',
            'posts.*.media_links.max' => 'Too many media links (maximum :max).',
            'posts.*.caption.required' => 'Write a caption.',
            'posts.*.caption.max' => 'The caption may not be longer than :max characters.',
            'posts.*.tags.*.regex' => 'Tags may only contain letters, numbers, dots and underscores.',
        ];
    }

    public function attributes(): array
    {
        $attributes = [
            'content_pillar' => 'content pillar',
            'publish_at' => 'publish date',
            'deadline_at' => 'deadline',
            'multimedia_creator_id' => 'multimedia creator',
            'copywriter_id' => 'copy writer',
            'publisher_id' => 'publisher',
            'reference_links.*' => 'reference link',
        ];

        foreach (PostType::cases() as $type) {
            $attributes["posts.{$type->value}.media_links.*"] = $type->label().' media link';
            $attributes["posts.{$type->value}.tags.*"] = 'tag';
            $attributes["posts.{$type->value}.note"] = 'note';
        }

        return $attributes;
    }
}
