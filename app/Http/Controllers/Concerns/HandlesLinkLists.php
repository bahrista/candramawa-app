<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/** The "links" list (url + optional label) shared by knowledge base entries and products. */
trait HandlesLinkLists
{
    /** Drops blank rows before validation. */
    protected function cleanLinks(Request $request): void
    {
        $links = [];

        foreach ((array) $request->input('links', []) as $link) {
            $url = is_array($link) && is_string($link['url'] ?? null) ? trim($link['url']) : '';

            if ($url !== '') {
                $links[] = ['url' => $url, 'label' => is_string($link['label'] ?? null) ? trim($link['label']) : null];
            }
        }

        $request->merge(['links' => $links]);
    }

    /** @return array<string, array<int, mixed>> */
    protected function linkRules(): array
    {
        return [
            'links' => ['nullable', 'array', 'max:10'],
            'links.*.url' => ['required', 'url:http,https', 'max:500'],
            'links.*.label' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return list<array{url: string, label: string|null}> */
    protected function presentLinks(?array $links): array
    {
        return array_values(array_map(
            fn ($l) => ['url' => $l['url'], 'label' => ($l['label'] ?? '') !== '' ? $l['label'] : null],
            $links ?? [],
        ));
    }
}
