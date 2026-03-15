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

export interface PlotTemplate {
    key: string;
    name: string;
    description: string;
    acts: TemplateAct[];
}

export const PLOT_TEMPLATES: PlotTemplate[] = [
    {
        key: 'three_act',
        name: 'Three-Act Structure',
        description:
            'Setup → Confrontation → Resolution. The most universal storytelling framework.',
        acts: [
            {
                title: 'The Setup',
                color: '#B87333',
                beats: [
                    { title: 'Opening Image', type: 'setup' },
                    { title: 'Inciting Incident', type: 'conflict' },
                    { title: 'Into Turning Point', type: 'turning_point' },
                ],
            },
            {
                title: 'The Confrontation',
                color: '#8B6914',
                beats: [
                    { title: 'Rising Action', type: 'conflict' },
                    { title: 'Midpoint', type: 'turning_point' },
                    { title: 'Crisis', type: 'conflict' },
                ],
            },
            {
                title: 'The Resolution',
                color: '#6B4423',
                beats: [
                    { title: 'Climax', type: 'turning_point' },
                    { title: 'Final Image', type: 'resolution' },
                ],
            },
        ],
    },
    {
        key: 'five_act',
        name: 'Five-Act Structure',
        description:
            'The classic dramatic structure: exposition, rising action, climax, falling action, resolution.',
        acts: [
            {
                title: 'Exposition',
                color: '#B87333',
                beats: [
                    { title: 'Hook', type: 'setup' },
                    { title: 'Inciting Incident', type: 'conflict' },
                    { title: 'Key Event', type: 'turning_point' },
                ],
            },
            {
                title: 'Rising Action',
                color: '#8B6914',
                beats: [
                    { title: 'First Pinch Point', type: 'conflict' },
                    { title: 'Midpoint', type: 'turning_point' },
                ],
            },
            {
                title: 'Climax',
                color: '#A0522D',
                beats: [
                    { title: 'Second Pinch Point', type: 'conflict' },
                    { title: 'Crisis', type: 'conflict' },
                    { title: 'Climactic Moment', type: 'turning_point' },
                ],
            },
            {
                title: 'Falling Action',
                color: '#6B4423',
                beats: [
                    { title: 'Third Plot Point', type: 'turning_point' },
                    { title: 'Consequences', type: 'resolution' },
                ],
            },
            {
                title: 'Resolution',
                color: '#4A3728',
                beats: [
                    { title: 'Denouement', type: 'resolution' },
                    { title: 'Final Image', type: 'resolution' },
                ],
            },
        ],
    },
    {
        key: 'heros_journey',
        name: "Hero's Journey",
        description:
            'The mythic quest in 12 stages across three acts. Perfect for fantasy, adventure, and coming-of-age.',
        acts: [
            {
                title: 'Departure',
                color: '#B87333',
                beats: [
                    { title: 'Ordinary World', type: 'setup' },
                    { title: 'Call to Adventure', type: 'conflict' },
                    { title: 'Refusal of the Call', type: 'conflict' },
                    { title: 'Meeting the Mentor', type: 'setup' },
                ],
            },
            {
                title: 'Initiation',
                color: '#8B6914',
                beats: [
                    { title: 'Crossing the Threshold', type: 'turning_point' },
                    { title: 'Tests, Allies, Enemies', type: 'conflict' },
                    { title: 'The Ordeal', type: 'conflict' },
                    { title: 'The Reward', type: 'turning_point' },
                    { title: 'The Road Back', type: 'conflict' },
                ],
            },
            {
                title: 'Return',
                color: '#6B4423',
                beats: [
                    { title: 'The Resurrection', type: 'turning_point' },
                    { title: 'Return with the Elixir', type: 'resolution' },
                    { title: 'The New Normal', type: 'resolution' },
                ],
            },
        ],
    },
];
