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
