<?php

namespace Tests\Unit;

use App\Support\DriveLink;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DriveLinkTest extends TestCase
{
    private const ID = '1AbCdEfGhIjKlMnOpQrStUvWxYz123456';

    #[DataProvider('valid')]
    public function test_recognises_drive_links(string $url, string $kind): void
    {
        $this->assertSame(['kind' => $kind, 'id' => self::ID], DriveLink::parse($url));
    }

    public static function valid(): array
    {
        $id = self::ID;

        return [
            'file view' => ["https://drive.google.com/file/d/$id/view?usp=sharing", 'file'],
            'file without suffix' => ["https://drive.google.com/file/d/$id", 'file'],
            'file with account index' => ["https://drive.google.com/file/u/0/d/$id/view", 'file'],
            'open?id' => ["https://drive.google.com/open?id=$id", 'file'],
            'uc export' => ["https://drive.google.com/uc?export=view&id=$id", 'file'],
            'folder' => ["https://drive.google.com/drive/folders/$id?usp=sharing", 'folder'],
            'folder with account index' => ["https://drive.google.com/drive/u/0/folders/$id", 'folder'],
            'surrounding spaces' => ["  https://drive.google.com/file/d/$id/view  ", 'file'],
        ];
    }

    #[DataProvider('invalid')]
    public function test_rejects_everything_else(?string $url): void
    {
        $this->assertNull(DriveLink::parse($url));
    }

    public static function invalid(): array
    {
        $id = self::ID;

        return [
            'null' => [null],
            'empty' => [''],
            'not a url' => ['drive.google.com/file/d/'.$id],
            'plain http' => ["http://drive.google.com/file/d/$id/view"],
            'look-alike subdomain' => ["https://drive.google.com.evil.example/file/d/$id/view"],
            'other host' => ["https://evil.example/file/d/$id/view"],
            'docs host' => ["https://docs.google.com/document/d/$id/edit"],
            'userinfo trick' => ["https://drive.google.com@evil.example/file/d/$id/view"],
            'backslash trick' => ["https://evil.example\\@drive.google.com/file/d/$id/view"],
            'custom port' => ["https://drive.google.com:8443/file/d/$id/view"],
            'javascript scheme' => ['javascript:alert(1)'],
            'id too short' => ['https://drive.google.com/file/d/abc/view'],
            'no id' => ['https://drive.google.com/'],
            'inner space' => ["https://drive.google.com/file/d/$id /view"],
            'open without id' => ['https://drive.google.com/open'],
        ];
    }
}
