// What the publisher pastes into the platform.
export const hashtagsOf = (tags) => (tags ?? []).filter((t) => t.startsWith('#'));

/** The caption followed by its hashtags, the way it is posted. */
export const captionWithTags = (post) =>
    [(post.caption ?? '').trim(), hashtagsOf(post.tags).join(' ')].filter(Boolean).join('\n\n');

/** Every tag: hashtags and the accounts to tag. */
export const tagsText = (post) => (post.tags ?? []).join(' ');
