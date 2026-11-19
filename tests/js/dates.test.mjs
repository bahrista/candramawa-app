import test from 'node:test';
import assert from 'node:assert/strict';
import { dayMonth, longLabel, minusHours, parseLocal } from '../../resources/js/lib/dates.js';
import { cleanedIndex } from '../../resources/js/lib/forms.js';

test('formats a wall-clock string without touching time zones', () => {
    assert.equal(longLabel('2026-11-20T14:30', 'WIB'), 'Friday, 20 November 2026, 14:30 WIB');
    assert.equal(longLabel('2026-12-01T09:05'), 'Tuesday, 1 December 2026, 09:05');
    assert.equal(dayMonth('2026-11-20T14:30'), '20 November');
});

test('blank or malformed input gives empty strings', () => {
    for (const bad of ['', null, undefined, '2026-11-20', '20/11/2026 14:30']) {
        assert.equal(parseLocal(bad), null);
        assert.equal(longLabel(bad), '');
        assert.equal(minusHours(bad, 24), '');
    }
});

test('automatic deadline is publish date minus the hours, across month and year ends', () => {
    assert.equal(minusHours('2026-12-01T19:00', 24), '2026-11-30T19:00');
    assert.equal(minusHours('2027-01-01T00:30', 24), '2026-12-31T00:30');
    assert.equal(minusHours('2026-03-01T08:00', 24), '2026-02-28T08:00');
    assert.equal(minusHours('2026-12-01T19:00', 36), '2026-11-30T07:00');
});

test('error indexes refer to the list without blank rows', () => {
    const list = ['a', '', 'b', '  ', 'c'];
    assert.equal(cleanedIndex(list, 0), 0);
    assert.equal(cleanedIndex(list, 2), 1);
    assert.equal(cleanedIndex(list, 4), 2);
});
