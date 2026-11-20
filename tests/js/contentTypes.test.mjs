import test from 'node:test';
import assert from 'node:assert/strict';
import { flattenTypes, orderKeys } from '../../resources/js/lib/contentTypes.js';

const tree = [
    { key: 'instagram', label: 'Instagram', types: [{ key: 'instagram_single' }, { key: 'instagram_reel' }] },
    { key: 'tiktok', label: 'TikTok', types: [{ key: 'tiktok_video' }] },
];

test('flattens the tree into a lookup by key', () => {
    const flat = flattenTypes(tree);
    assert.deepEqual(Object.keys(flat), ['instagram_single', 'instagram_reel', 'tiktok_video']);
    assert.equal(flat.tiktok_video.key, 'tiktok_video');
});

test('orders keys like the tree and drops unknown ones', () => {
    assert.deepEqual(orderKeys(tree, ['tiktok_video', 'nope', 'instagram_reel']), ['instagram_reel', 'tiktok_video']);
    assert.deepEqual(orderKeys(tree, []), []);
});
