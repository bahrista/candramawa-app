// Understands the Google Drive links people paste. Mirror of app/Support/DriveLink.php:
// keep the two in step. Only drive.google.com over https is accepted, and every URL we
// put in an <img>/<iframe> is built from the extracted ID, never from the pasted text.
const ID = '[A-Za-z0-9_-]{10,}';

function build(kind, id) {
    return {
        kind,
        id,
        previewUrl:
            kind === 'folder'
                ? `https://drive.google.com/embeddedfolderview?id=${id}#grid`
                : `https://drive.google.com/file/d/${id}/preview`,
        thumbUrl: `https://drive.google.com/thumbnail?id=${id}&sz=w1080`,
        // Files can be downloaded straight away; a folder can only be opened (Drive zips it on its own page).
        downloadUrl: kind === 'folder' ? null : `https://drive.google.com/uc?export=download&id=${id}`,
        openUrl:
            kind === 'folder'
                ? `https://drive.google.com/drive/folders/${id}`
                : `https://drive.google.com/file/d/${id}/view`,
    };
}

export function parseDriveLink(raw) {
    if (typeof raw !== 'string') return null;
    const text = raw.trim();
    if (!text || /[\\\s\u0000-\u001f]/.test(text)) return null;

    let url;
    try {
        url = new URL(text);
    } catch {
        return null;
    }

    if (url.protocol !== 'https:' || url.hostname !== 'drive.google.com') return null;
    if (url.username || url.password || url.port) return null;

    const folder = url.pathname.match(new RegExp(`/folders/(${ID})`));
    if (folder) return build('folder', folder[1]);

    const file = url.pathname.match(new RegExp(`/file/(?:u/\\d+/)?d/(${ID})`));
    if (file) return build('file', file[1]);

    if (['/open', '/uc', '/thumbnail'].includes(url.pathname)) {
        const id = url.searchParams.get('id');
        if (id && new RegExp(`^${ID}$`).test(id)) return build('file', id);
    }

    return null;
}
