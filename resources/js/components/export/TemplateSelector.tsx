import TemplateCard from '@/components/export/TemplateCard';
import type { TemplateDef } from '@/components/export/types';

interface TemplateSelectorProps {
    templates: TemplateDef[];
    selectedTemplate: string;
    onChange: (slug: string) => void;
}

export default function TemplateSelector({
    templates,
    selectedTemplate,
    onChange,
}: TemplateSelectorProps) {
    return (
        <div className="flex gap-2.5 overflow-x-auto p-1">
            {templates.map((template) => (
                <TemplateCard
                    key={template.slug}
                    template={template}
                    isSelected={template.slug === selectedTemplate}
                    onClick={() => onChange(template.slug)}
                />
            ))}
        </div>
    );
}
