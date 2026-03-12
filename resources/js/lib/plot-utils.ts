import type { Act, PlotPoint, Storyline } from '@/types/models';

type ChapterColumn = {
    id: number;
    title: string;
    reader_order: number;
    act_id: number | null;
    storyline_id: number;
    tension_score: number | null;
};

type ActGroup = Act & { chapters: ChapterColumn[] };

export type GridCell = {
    storylineId: number;
    actId: number;
    chapters: ChapterColumn[];
    plotPoints: PlotPoint[];
};

export function buildTimelineGrid(
    acts: ActGroup[],
    storylines: Storyline[],
    plotPoints: PlotPoint[],
): { grid: Map<string, GridCell>; allChapters: ChapterColumn[] } {
    const grid = new Map<string, GridCell>();
    const allChapters: ChapterColumn[] = [];

    const chapterActMap = new Map<number, number>();

    for (const act of acts) {
        for (const chapter of act.chapters) {
            allChapters.push(chapter);
            chapterActMap.set(chapter.id, act.id);
        }
    }

    for (const storyline of storylines) {
        for (const act of acts) {
            const key = cellKey(storyline.id, act.id);
            grid.set(key, {
                storylineId: storyline.id,
                actId: act.id,
                chapters: act.chapters.filter((ch) => ch.storyline_id === storyline.id),
                plotPoints: [],
            });
        }
    }

    for (const pp of plotPoints) {
        if (!pp.storyline_id) {
            continue;
        }

        let actId = pp.act_id;

        if (!actId && pp.intended_chapter_id) {
            actId = chapterActMap.get(pp.intended_chapter_id) ?? null;
        }

        if (actId) {
            const key = cellKey(pp.storyline_id, actId);
            const cell = grid.get(key);
            if (cell) {
                cell.plotPoints.push(pp);
            }
        }
    }

    return { grid, allChapters };
}

export function cellKey(storylineId: number, actId: number): string {
    return `${storylineId}-${actId}`;
}
