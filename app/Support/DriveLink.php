<?php

namespace App\Support;

/**
 * Understands the Google Drive link shapes people paste. The same logic lives in
 * resources/js/lib/drive.js for the preview; keep the two in step.
 */
final class DriveLink
{
    private const ID = '[A-Za-z0-9_-]{10,}';

    /** @return array{kind: 'file'|'folder', id: string}|null */
    public static function parse(?string $url): ?array
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);

        // Backslashes, spaces and control characters are how look-alike hosts are smuggled in.
        if ($url === '' || preg_match('/[\\\\\s[:cntrl:]]/', $url)) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || strtolower($parts['host'] ?? '') !== 'drive.google.com'
            || isset($parts['user']) || isset($parts['port'])) {
            return null;
        }

        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);

        if (preg_match('#/folders/('.self::ID.')#', $path, $m)) {
            return ['kind' => 'folder', 'id' => $m[1]];
        }

        if (preg_match('#/file/(?:u/\d+/)?d/('.self::ID.')#', $path, $m)) {
            return ['kind' => 'file', 'id' => $m[1]];
        }

        if (in_array($path, ['/open', '/uc', '/thumbnail'], true)
            && isset($query['id']) && is_string($query['id'])
            && preg_match('/^'.self::ID.'$/', $query['id'])) {
            return ['kind' => 'file', 'id' => $query['id']];
        }

        return null;
    }
}
