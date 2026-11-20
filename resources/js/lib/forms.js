export const isBlank = (v) => String(v ?? '').trim() === '';

/**
 * The server drops blank list items before validating, so an error on "item 1" refers to
 * the cleaned list. This maps a row of the visible list to that cleaned index.
 */
export const cleanedIndex = (list, i) => list.slice(0, i).filter((v) => !isBlank(v)).length;

/** A reference row that is just an empty link box (dropped before saving). */
export const isBlankReference = (r) => r.kind === 'link' && isBlank(r.url);

/** Same idea as cleanedIndex, for the brief's references. */
export const cleanedRefIndex = (list, i) => list.slice(0, i).filter((r) => !isBlankReference(r)).length;
