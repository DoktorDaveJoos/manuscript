// Root-level textContent glues block boundaries together ("…end.Start…").
// Join the top-level block nodes with blank lines instead so prompts see
// real paragraph breaks.
export function proseMirrorBlockText(pm: HTMLElement | null): string {
    if (!pm) return '';
    return Array.from(pm.children)
        .map((el) => el.textContent?.trim() ?? '')
        .filter(Boolean)
        .join('\n\n');
}

// Extract block-aware plain text from a stored HTML content string
// (e.g. a scene's ProseMirror content) without needing it mounted.
export function htmlBlockText(html: string | null): string {
    if (!html) return '';
    const div = document.createElement('div');
    div.innerHTML = html;
    return proseMirrorBlockText(div);
}
