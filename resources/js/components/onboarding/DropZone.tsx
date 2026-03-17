import { Upload } from 'lucide-react';
import { useCallback, useRef, useState } from 'react';
import type { DragEvent } from 'react';
import { useTranslation } from 'react-i18next';

export default function DropZone({
    onFiles,
}: {
    onFiles: (files: File[]) => void;
}) {
    const { t } = useTranslation('onboarding');
    const [dragging, setDragging] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    const handleDrop = useCallback(
        (e: DragEvent) => {
            e.preventDefault();
            setDragging(false);
            const supportedExtensions = [
                '.docx',
                '.odt',
                '.txt',
                '.md',
                '.markdown',
            ];
            const files = Array.from(e.dataTransfer.files).filter((f) =>
                supportedExtensions.some((ext) =>
                    f.name.toLowerCase().endsWith(ext),
                ),
            );
            if (files.length > 0) onFiles(files);
        },
        [onFiles],
    );

    return (
        <div
            onDragOver={(e) => {
                e.preventDefault();
                setDragging(true);
            }}
            onDragLeave={() => setDragging(false)}
            onDrop={handleDrop}
            onClick={() => inputRef.current?.click()}
            className={`flex h-40 w-[560px] cursor-pointer flex-col items-center justify-center gap-3.5 rounded-xl border-[1.5px] border-dashed transition-colors ${
                dragging
                    ? 'border-ink-muted bg-neutral-bg/50'
                    : 'border-border-drop'
            }`}
        >
            <Upload size={32} className="text-ink-faint" />
            <div className="flex flex-col items-center gap-1">
                <span className="text-sm leading-[18px] font-medium text-ink">
                    {t('dropZone.title')}
                </span>
                <span className="text-[13px] leading-4 text-ink-muted">
                    {t('dropZone.subtitle')}
                </span>
            </div>
            <input
                ref={inputRef}
                type="file"
                accept=".docx,.odt,.txt,.md,.markdown"
                multiple
                className="hidden"
                onChange={(e) => {
                    const files = Array.from(e.target.files ?? []);
                    if (files.length > 0) onFiles(files);
                    e.target.value = '';
                }}
            />
        </div>
    );
}
