import { doExport } from '@/actions/App/Http/Controllers/BookSettingsController';
import { jsonFetchHeaders } from '@/lib/utils';

export async function downloadExport(
    book: { id: number; title: string },
    options: Record<string, unknown>,
): Promise<void> {
    const format = (options.format as string) ?? 'docx';

    const res = await fetch(doExport.url(book), {
        method: 'POST',
        headers: {
            ...jsonFetchHeaders(),
            Accept: 'application/octet-stream, application/json',
        },
        body: JSON.stringify(options),
    });

    const contentType = res.headers.get('content-type') ?? '';
    const isJson = contentType.includes('application/json');

    if (!res.ok) {
        const body = isJson ? await res.json().catch(() => null) : null;
        throw new Error(body?.error ?? `Export failed (${res.status})`);
    }

    if (isJson) return;

    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${book.title}.${format}`;
    a.click();
    URL.revokeObjectURL(url);
}
