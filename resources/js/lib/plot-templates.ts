import type { TFunction } from 'i18next';
import type { PlotPointType } from '@/types/models';

export interface TemplateBeat {
    title: string;
    type: PlotPointType;
}

export interface TemplateAct {
    title: string;
    color: string;
    beats: TemplateBeat[];
}

export interface GenreBadge {
    labelKey: string;
    bgColor: string;
    textColor: string;
}

export interface PlotTemplate {
    key: string;
    name: string;
    description: string;
    featured: boolean;
    genres: GenreBadge[];
    acts: TemplateAct[];
}

type RawBeat = { type: PlotPointType };
type RawAct = { color: string; beats: RawBeat[] };
type RawTemplate = {
    key: string;
    featured: boolean;
    genres: GenreBadge[];
    acts: RawAct[];
};

const RAW_TEMPLATES: RawTemplate[] = [
    {
        key: 'three_act',
        featured: true,
        genres: [
            {
                labelKey: 'genre.literary_fiction',
                bgColor: '#F3EDE4',
                textColor: '#8A7B65',
            },
            {
                labelKey: 'genre.romance',
                bgColor: '#F9E8E8',
                textColor: '#B85C5C',
            },
            {
                labelKey: 'genre.memoir',
                bgColor: '#EDE8F3',
                textColor: '#7B6A8A',
            },
        ],
        acts: [
            {
                color: '#B87333',
                beats: [
                    { type: 'setup' },
                    { type: 'conflict' },
                    { type: 'turning_point' },
                ],
            },
            {
                color: '#8B6914',
                beats: [
                    { type: 'conflict' },
                    { type: 'turning_point' },
                    { type: 'conflict' },
                ],
            },
            {
                color: '#6B4423',
                beats: [{ type: 'turning_point' }, { type: 'resolution' }],
            },
        ],
    },
    {
        key: 'five_act',
        featured: false,
        genres: [
            {
                labelKey: 'genre.historical',
                bgColor: '#E8EFF3',
                textColor: '#5A7A8A',
            },
            {
                labelKey: 'genre.epic_fantasy',
                bgColor: '#EDE8F3',
                textColor: '#7B6A8A',
            },
            {
                labelKey: 'genre.drama',
                bgColor: '#F3EDE4',
                textColor: '#8A7B65',
            },
        ],
        acts: [
            {
                color: '#B87333',
                beats: [
                    { type: 'setup' },
                    { type: 'conflict' },
                    { type: 'turning_point' },
                ],
            },
            {
                color: '#8B6914',
                beats: [{ type: 'conflict' }, { type: 'turning_point' }],
            },
            {
                color: '#A0522D',
                beats: [
                    { type: 'conflict' },
                    { type: 'conflict' },
                    { type: 'turning_point' },
                ],
            },
            {
                color: '#6B4423',
                beats: [{ type: 'turning_point' }, { type: 'resolution' }],
            },
            {
                color: '#4A3728',
                beats: [{ type: 'resolution' }, { type: 'resolution' }],
            },
        ],
    },
    {
        key: 'heros_journey',
        featured: false,
        genres: [
            {
                labelKey: 'genre.fantasy',
                bgColor: '#E4F0E8',
                textColor: '#5A8A65',
            },
            {
                labelKey: 'genre.sci_fi',
                bgColor: '#E8EFF3',
                textColor: '#5A7A8A',
            },
            { labelKey: 'genre.ya', bgColor: '#FFF3E0', textColor: '#A07030' },
        ],
        acts: [
            {
                color: '#B87333',
                beats: [
                    { type: 'setup' },
                    { type: 'conflict' },
                    { type: 'conflict' },
                    { type: 'setup' },
                ],
            },
            {
                color: '#8B6914',
                beats: [
                    { type: 'turning_point' },
                    { type: 'conflict' },
                    { type: 'conflict' },
                    { type: 'turning_point' },
                    { type: 'conflict' },
                ],
            },
            {
                color: '#6B4423',
                beats: [
                    { type: 'turning_point' },
                    { type: 'resolution' },
                    { type: 'resolution' },
                ],
            },
        ],
    },
    {
        key: 'save_the_cat',
        featured: false,
        genres: [
            {
                labelKey: 'genre.thriller',
                bgColor: '#FCE4E4',
                textColor: '#C05050',
            },
            {
                labelKey: 'genre.mystery',
                bgColor: '#E4E8F0',
                textColor: '#5A6A8A',
            },
            {
                labelKey: 'genre.romance',
                bgColor: '#F9E8E8',
                textColor: '#B85C5C',
            },
        ],
        acts: [
            {
                color: '#B87333',
                beats: [
                    { type: 'setup' },
                    { type: 'setup' },
                    { type: 'setup' },
                    { type: 'conflict' },
                    { type: 'conflict' },
                ],
            },
            {
                color: '#8B6914',
                beats: [
                    { type: 'turning_point' },
                    { type: 'setup' },
                    { type: 'conflict' },
                    { type: 'turning_point' },
                    { type: 'conflict' },
                    { type: 'conflict' },
                    { type: 'conflict' },
                ],
            },
            {
                color: '#6B4423',
                beats: [
                    { type: 'turning_point' },
                    { type: 'resolution' },
                    { type: 'resolution' },
                ],
            },
        ],
    },
    {
        key: 'story_circle',
        featured: false,
        genres: [
            {
                labelKey: 'genre.literary',
                bgColor: '#F3EDE4',
                textColor: '#8A7B65',
            },
            {
                labelKey: 'genre.coming_of_age',
                bgColor: '#FFF3E0',
                textColor: '#A07030',
            },
            {
                labelKey: 'genre.drama',
                bgColor: '#F3EDE4',
                textColor: '#8A7B65',
            },
        ],
        acts: [
            {
                color: '#B87333',
                beats: [
                    { type: 'setup' },
                    { type: 'conflict' },
                    { type: 'turning_point' },
                    { type: 'conflict' },
                ],
            },
            {
                color: '#8B6914',
                beats: [
                    { type: 'turning_point' },
                    { type: 'conflict' },
                    { type: 'resolution' },
                    { type: 'resolution' },
                ],
            },
        ],
    },
];

export function getPlotTemplates(t: TFunction): PlotTemplate[] {
    return RAW_TEMPLATES.map((raw) => ({
        key: raw.key,
        name: t(`emptyState.template.${raw.key}.name`),
        description: t(`emptyState.template.${raw.key}.description`),
        featured: raw.featured,
        genres: raw.genres,
        acts: raw.acts.map((act, actIndex) => ({
            title: t(`template.${raw.key}.acts.${actIndex}`),
            color: act.color,
            beats: act.beats.map((beat, beatIndex) => ({
                title: t(`template.${raw.key}.beats.${actIndex}.${beatIndex}`),
                type: beat.type,
            })),
        })),
    }));
}
