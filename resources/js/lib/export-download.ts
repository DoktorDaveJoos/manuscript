import { doExport } from '@/actions/App/Http/Controllers/BookSettingsController';
import { getXsrfToken } from '@/lib/csrf';

export function downloadExport(
    book: { id: number; title: string },
    options: Record<string, unknown>,
): Promise<void> {
    const format = (options.format as string) ?? 'docx';

    return fetch(doExport.url(book), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': getXsrfToken(),
            Accept: 'application/octet-stream',
        },
        body: JSON.stringify(options),
    }).then(async (res) => {
        if (!res.ok) throw new Error('Export failed');
        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${book.title}.${format}`;
        a.click();
        URL.revokeObjectURL(url);
    });
}
