import test from 'node:test';
import assert from 'node:assert/strict';
import { parseDriveLink } from '../../resources/js/lib/drive.js';

const ID = '1AbCdEfGhIjKlMnOpQrStUvWxYz123456';

test('recognises file and folder links', () => {
    const cases = [
        [`https://drive.google.com/file/d/${ID}/view?usp=sharing`, 'file'],
        [`https://drive.google.com/file/d/${ID}`, 'file'],
        [`https://drive.google.com/file/u/0/d/${ID}/view`, 'file'],
        [`https://drive.google.com/open?id=${ID}`, 'file'],
        [`https://drive.google.com/uc?export=view&id=${ID}`, 'file'],
        [`https://drive.google.com/drive/folders/${ID}?usp=sharing`, 'folder'],
        [`https://drive.google.com/drive/u/0/folders/${ID}`, 'folder'],
        [`  https://drive.google.com/file/d/${ID}/view  `, 'file'],
    ];
    for (const [url, kind] of cases) {
        const r = parseDriveLink(url);
        assert.equal(r?.kind, kind, url);
        assert.equal(r?.id, ID, url);
    }
});

test('builds embed URLs from the ID only', () => {
    const file = parseDriveLink(`https://drive.google.com/file/d/${ID}/view?x=<script>`);
    assert.equal(file.previewUrl, `https://drive.google.com/file/d/${ID}/preview`);
    assert.equal(file.thumbUrl, `https://drive.google.com/thumbnail?id=${ID}&sz=w1080`);
    assert.equal(file.openUrl, `https://drive.google.com/file/d/${ID}/view`);
    assert.equal(file.downloadUrl, `https://drive.google.com/uc?export=download&id=${ID}`);

    const folder = parseDriveLink(`https://drive.google.com/drive/folders/${ID}`);
    assert.equal(folder.previewUrl, `https://drive.google.com/embeddedfolderview?id=${ID}#grid`);
    assert.equal(folder.downloadUrl, null); // a folder cannot be downloaded in one go
});

test('rejects everything else', () => {
    const bad = [
        null,
        undefined,
        '',
        42,
        `drive.google.com/file/d/${ID}`,
        `http://drive.google.com/file/d/${ID}/view`,
        `https://drive.google.com.evil.example/file/d/${ID}/view`,
        `https://evil.example/file/d/${ID}/view`,
        `https://docs.google.com/document/d/${ID}/edit`,
        `https://drive.google.com@evil.example/file/d/${ID}/view`,
        `https://evil.example\\@drive.google.com/file/d/${ID}/view`,
        `https://drive.google.com:8443/file/d/${ID}/view`,
        'javascript:alert(1)',
        'https://drive.google.com/file/d/abc/view',
        'https://drive.google.com/',
        'https://drive.google.com/open',
        `https://drive.google.com/file/d/${ID} /view`,
    ];
    for (const url of bad) assert.equal(parseDriveLink(url), null, String(url));
});
