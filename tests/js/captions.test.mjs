import test from 'node:test';
import assert from 'node:assert/strict';
import { captionWithTags, hashtagsOf, tagsText } from '../../resources/js/lib/captions.js';

test('caption + hashtags, accounts left out of the caption', () => {
    const post = { caption: '  Ramadan is here  ', tags: ['#kopi', '@partner', '#ramadan'] };
    assert.equal(captionWithTags(post), 'Ramadan is here\n\n#kopi #ramadan');
    assert.deepEqual(hashtagsOf(post.tags), ['#kopi', '#ramadan']);
    assert.equal(tagsText(post), '#kopi @partner #ramadan');
});

test('works with a missing caption or tags', () => {
    assert.equal(captionWithTags({ caption: '', tags: ['#a'] }), '#a');
    assert.equal(captionWithTags({ caption: 'Only text', tags: [] }), 'Only text');
    assert.equal(captionWithTags({}), '');
    assert.equal(tagsText({}), '');
});
