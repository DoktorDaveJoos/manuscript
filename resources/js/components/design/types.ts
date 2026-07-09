import type { TrimSizeOption } from '@/components/export/types';

export type FontPairingDef = {
    value: string;
    label: string;
    headingFont: string;
    bodyFont: string;
};

export type SceneBreakStyleDef = {
    value: string;
    label: string;
};

export type DesignPageSettings = {
    trim_size: string;
    custom_width: number | null;
    custom_height: number | null;
    bleed: number;
    bleed_mode: 'all' | 'outer';
    margin_top: number;
    margin_bottom: number;
    margin_inner: number;
    margin_outer: number;
};

export type DesignTypographySettings = {
    font_pairing: string;
    font_size: number;
    line_height: number;
    alignment: 'justify' | 'left';
    hyphenation: boolean;
    first_line_indent: boolean;
    paragraph_spacing_em: number;
};

export type DesignHeadingSettings = {
    chapter_heading: string;
    heading_scale_em: number;
    heading_top_space_em: number;
    drop_caps: boolean;
    scene_break_style: string;
};

export type DesignStructureSettings = {
    show_page_numbers: boolean;
    include_act_breaks: boolean;
};

export type DesignSettings = {
    page: DesignPageSettings;
    typography: DesignTypographySettings;
    headings: DesignHeadingSettings;
    structure: DesignStructureSettings;
};

export type BuiltInTemplateDef = {
    slug: string;
    name: string;
    settings: DesignSettings;
};

export type CustomTemplateDef = {
    id: number;
    slug: string;
    name: string;
    basedOn: string;
    settings: DesignSettings;
};

export type DesignTrimSizeOption = TrimSizeOption & {
    margins: { top: number; bottom: number; outer: number; gutter: number };
};
