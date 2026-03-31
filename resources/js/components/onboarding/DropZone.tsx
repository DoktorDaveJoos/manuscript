import { Upload } from 'lucide-react';
import { useCallback, useRef, useState } from 'react';
import type { DragEvent } from 'react';
import { useTranslation } from 'react-i18next';

const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB
const SUPPORTED_EXTENSIONS = ['.docx', '.odt', '.txt', '.md', '.markdown'];

export default function DropZone({
    onFiles,
    onReject,
}: {
    onFiles: (files: File[]) => void;
    onReject?: (message: string) => void;
}) {
    const { t } = useTranslation('onboarding');
    const [dragging, setDragging] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    const handleDrop = useCallback(
        (e: DragEvent) => {
            e.preventDefault();
            setDragging(false);
            const allFiles = Array.from(e.dataTransfer.files);

            const supported: File[] = [];
            const unsupported: string[] = [];
            const tooLarge: string[] = [];

            for (const f of allFiles) {
                const isSupported = SUPPORTED_EXTENSIONS.some((ext) =>
                    f.name.toLowerCase().endsWith(ext),
                );
                if (!isSupported) {
                    unsupported.push(f.name);
                } else if (f.size > MAX_FILE_SIZE) {
                    tooLarge.push(f.name);
                } else {
                    supported.push(f);
                }
            }

            if (supported.length > 0) onFiles(supported);

            if (unsupported.length > 0) {
                onReject?.(
                    t('dropZone.unsupportedFormat', {
                        files: unsupported.join(', '),
                    }),
                );
            } else if (tooLarge.length > 0) {
                onReject?.(
                    t('dropZone.fileTooLarge', {
                        files: tooLarge.join(', '),
                    }),
                );
            }
        },
        [onFiles, onReject, t],
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
                    const allFiles = Array.from(e.target.files ?? []);
                    const tooLarge = allFiles.filter(
                        (f) => f.size > MAX_FILE_SIZE,
                    );
                    const valid = allFiles.filter(
                        (f) => f.size <= MAX_FILE_SIZE,
                    );

                    if (valid.length > 0) onFiles(valid);
                    if (tooLarge.length > 0) {
                        onReject?.(
                            t('dropZone.fileTooLarge', {
                                files: tooLarge.map((f) => f.name).join(', '),
                            }),
                        );
                    }
                    e.target.value = '';
                }}
            />
        </div>
    );
}
