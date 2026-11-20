<?php

namespace App\Support;

/** Reads config/content_types.php: platforms (parents) with their content types (children). */
final class ContentTypes
{
    /** @return array<string, array<string, mixed>> every content type, keyed by its key, in config order */
    public static function all(): array
    {
        $all = [];

        foreach (config('content_types.platforms') as $platformKey => $platform) {
            foreach ($platform['types'] as $key => $type) {
                [$min, $max] = $type['media'];

                $all[$key] = [
                    'key' => $key,
                    'label' => $type['label'],
                    'full_label' => ($platform['prefix'] ?? true) ? $platform['label'].' '.$type['label'] : $type['label'],
                    'platform' => $platformKey,
                    'platform_label' => $platform['label'],
                    'icon' => $type['icon'],
                    'preview' => $type['preview'],
                    'min_media' => $min,
                    'max_media' => $max,
                    'multiple' => $max > 1,
                    'caption_required' => $type['caption']['required'],
                    'caption_limit' => $type['caption']['limit'],
                ];
            }
        }

        return $all;
    }

    /** @return list<array{key: string, label: string, types: list<array<string, mixed>>}> */
    public static function tree(): array
    {
        $tree = [];

        foreach (self::all() as $type) {
            $tree[$type['platform']] ??= ['key' => $type['platform'], 'label' => $type['platform_label'], 'types' => []];
            $tree[$type['platform']]['types'][] = $type;
        }

        return array_values($tree);
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /** @return array<string, mixed>|null */
    public static function find(?string $key): ?array
    {
        return $key === null ? null : (self::all()[$key] ?? null);
    }

    public static function label(string $key): string
    {
        return self::find($key)['full_label'] ?? $key;
    }

    /** Keeps only known keys, in the order of the config (a stable order for the UI and reports). */
    public static function order(array $keys): array
    {
        return array_values(array_filter(self::keys(), fn ($k) => in_array($k, $keys, true)));
    }
}
