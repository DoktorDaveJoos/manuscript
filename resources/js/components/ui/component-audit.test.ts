import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const uiDirectory = new URL('./', import.meta.url);
const sourceDirectory = fileURLToPath(new URL('../../', import.meta.url));

function readComponent(name: string): string {
    return readFileSync(new URL(name, uiDirectory), 'utf8');
}

function sourceFiles(directory: string): string[] {
    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const path = join(directory, entry.name);

        if (entry.isDirectory()) {
            return sourceFiles(path);
        }

        return /\.(?:ts|tsx)$/.test(entry.name) ? [path] : [];
    });
}

describe('shared UI component guardrails', () => {
    it('keeps dialogs and drawers accessibly titled', () => {
        expect(readComponent('Dialog.tsx')).toContain(
            '<DialogPrimitive.Title className="sr-only">{title}</DialogPrimitive.Title>',
        );
        expect(readComponent('Drawer.tsx')).toContain(
            '<DialogPrimitive.Title className="sr-only">',
        );
    });

    it('keeps shared typography aligned with the design system', () => {
        expect(readComponent('PageHeader.tsx')).toContain(
            'text-xl font-semibold tracking-[-0.01em] text-ink',
        );
        expect(readComponent('PageHeader.tsx')).not.toContain('font-serif');
        expect(readComponent('SectionLabel.tsx')).toContain(
            'text-[11px] font-medium tracking-wide uppercase',
        );
    });

    it('does not reintroduce forbidden component sizing or radius values', () => {
        const source = [
            readComponent('Command.tsx'),
            readComponent('RadioGroup.tsx'),
        ].join('\n');

        expect(source).not.toMatch(/rounded-\[(?:6)px\]/);
        expect(source).not.toMatch(/size-\[(?:10|18)px\]/);
    });

    it('defaults ordinary buttons away from accidental form submission', () => {
        expect(readComponent('Button.tsx')).toContain("type = 'button'");
        expect(readComponent('Button.tsx')).toContain(
            '<button ref={ref} type={type}',
        );
    });

    it('keeps every shared component referenced', () => {
        const applicationSource = sourceFiles(sourceDirectory)
            .filter((path) => !path.endsWith('component-audit.test.ts'))
            .map((path) => readFileSync(path, 'utf8'))
            .join('\n');
        const sharedComponents = readdirSync(uiDirectory)
            .filter((name) => /\.(?:ts|tsx)$/.test(name))
            .filter((name) => !name.endsWith('.test.ts'))
            .filter((name) => name !== 'menu-primitives.ts');

        for (const component of sharedComponents) {
            const importPath = `@/components/ui/${component.replace(/\.(?:ts|tsx)$/, '')}`;
            expect(applicationSource, `${component} is unused`).toContain(
                importPath,
            );
        }

        expect(applicationSource).toContain("from './menu-primitives'");
    });

    it('keeps grouped shadcn items inside their group components', () => {
        for (const path of sourceFiles(sourceDirectory)) {
            if (path.includes('/components/ui/')) {
                continue;
            }

            const source = readFileSync(path, 'utf8');

            if (
                source.includes('<DropdownMenuItem') ||
                source.includes('<DropdownMenuCheckboxItem') ||
                source.includes('<DropdownMenuLabel')
            ) {
                expect(source, path).toContain('<DropdownMenuGroup');
            }

            if (source.includes('<CommandItem')) {
                expect(source, path).toContain('<CommandGroup');
            }
        }
    });

    it('does not rebuild dialogs or native selects outside the shared layer', () => {
        for (const path of sourceFiles(sourceDirectory)) {
            if (path.includes('/components/ui/')) {
                continue;
            }

            const source = readFileSync(path, 'utf8');
            expect(source, path).not.toContain('role="dialog"');
            expect(source, path).not.toContain('aria-modal="true"');
            expect(source, path).not.toContain('<select');
        }
    });
});
