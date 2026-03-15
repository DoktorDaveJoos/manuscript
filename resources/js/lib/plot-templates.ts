import type { PlotPointType } from '@/types/models';
import type { TFunction } from 'i18next';

export interface TemplateBeat {
    title: string;
    type: PlotPointType;
}

export interface TemplateAct {
    title: string;
    color: string;
    beats: TemplateBeat[];
}

export interface PlotTemplate {
    key: string;
    name: string;
    description: string;
    acts: TemplateAct[];
}

type RawBeat = { type: PlotPointType };
type RawAct = { color: string; beats: RawBeat[] };
type RawTemplate = { key: string; acts: RawAct[] };

const RAW_TEMPLATES: RawTemplate[] = [
    {
        key: 'three_act',
        acts: [
            { color: '#B87333', beats: [{ type: 'setup' }, { type: 'conflict' }, { type: 'turning_point' }] },
            { color: '#8B6914', beats: [{ type: 'conflict' }, { type: 'turning_point' }, { type: 'conflict' }] },
            { color: '#6B4423', beats: [{ type: 'turning_point' }, { type: 'resolution' }] },
        ],
    },
    {
        key: 'five_act',
        acts: [
            { color: '#B87333', beats: [{ type: 'setup' }, { type: 'conflict' }, { type: 'turning_point' }] },
            { color: '#8B6914', beats: [{ type: 'conflict' }, { type: 'turning_point' }] },
            { color: '#A0522D', beats: [{ type: 'conflict' }, { type: 'conflict' }, { type: 'turning_point' }] },
            { color: '#6B4423', beats: [{ type: 'turning_point' }, { type: 'resolution' }] },
            { color: '#4A3728', beats: [{ type: 'resolution' }, { type: 'resolution' }] },
        ],
    },
    {
        key: 'heros_journey',
        acts: [
            { color: '#B87333', beats: [{ type: 'setup' }, { type: 'conflict' }, { type: 'conflict' }, { type: 'setup' }] },
            { color: '#8B6914', beats: [{ type: 'turning_point' }, { type: 'conflict' }, { type: 'conflict' }, { type: 'turning_point' }, { type: 'conflict' }] },
            { color: '#6B4423', beats: [{ type: 'turning_point' }, { type: 'resolution' }, { type: 'resolution' }] },
        ],
    },
];

export function getPlotTemplates(t: TFunction): PlotTemplate[] {
    return RAW_TEMPLATES.map((raw) => ({
        key: raw.key,
        name: t(`emptyState.template.${raw.key}.name`),
        description: t(`emptyState.template.${raw.key}.description`),
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
