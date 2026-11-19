export const isBlank = (v) => String(v ?? '').trim() === '';

/**
 * The server drops blank list items before validating, so an error on "item 1" refers to
 * the cleaned list. This maps a row of the visible list to that cleaned index.
 */
export const cleanedIndex = (list, i) => list.slice(0, i).filter((v) => !isBlank(v)).length;
