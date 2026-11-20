/**
 * Copies text. The modern clipboard API only exists on secure pages (https or localhost),
 * so a plain-http local site (e.g. http://candramawa-app.test) falls back to the old way.
 */
export async function copyText(text) {
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return true;
        }
    } catch {
        /* fall through to the fallback */
    }

    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();

    let ok = false;
    try {
        ok = document.execCommand('copy');
    } catch {
        ok = false;
    }
    document.body.removeChild(area);

    return ok;
}

/** "/brands/3/knowledge/9" -> "https://this-site/brands/3/knowledge/9" (what people paste into WhatsApp). */
export const absoluteUrl = (path) => new URL(path, window.location.origin).href;
