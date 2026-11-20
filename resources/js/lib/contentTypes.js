// The content type tree comes from the server (config/content_types.php):
// [{ key, label, types: [{ key, label, full_label, platform, icon, preview, min_media, max_media, multiple, caption_required, caption_limit }] }]

/** { instagram_single: {...}, ... } */
export const flattenTypes = (tree) => Object.fromEntries(tree.flatMap((p) => p.types.map((t) => [t.key, t])));

/** Keys in the order of the tree (a stable order, however they were ticked). */
export const orderKeys = (tree, keys) => tree.flatMap((p) => p.types.map((t) => t.key)).filter((k) => keys.includes(k));
