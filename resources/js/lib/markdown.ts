import MarkdownIt from 'markdown-it';

export const md = new MarkdownIt({ linkify: true, breaks: true });

/**
 * Strip Markdown formatting and collapse whitespace into a single-line plain
 * string — for cards / list previews that want the prose without `###`,
 * `**bold**`, `- bullets`, etc. showing through.
 */
export function markdownToPlainText(text: string): string {
    if (!text) {
        return '';
    }

    return md
        .render(text)
        .replace(/<[^>]*>/g, ' ')
        .replace(/&quot;/g, '"')
        .replace(/&#39;/g, "'")
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&amp;/g, '&')
        .replace(/\s+/g, ' ')
        .trim();
}
