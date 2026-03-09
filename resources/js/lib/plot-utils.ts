import type { Act, PlotPoint, Storyline } from '@/types/models';

type ChapterColumn = {
    id: number;
    title: string;
    reader_order: number;
    act_id: number;
    storyline_id: number;
    tension_score: number | null;
};

type ActGroup = Act & { chapters: ChapterColumn[] };

export type GridCell = {
    storylineId: number;
    chapterId: number;
    plotPoints: PlotPoint[];
};

export function buildTimelineGrid(
    acts: ActGroup[],
    storylines: Storyline[],
    plotPoints: PlotPoint[],
): { grid: Map<string, GridCell>; allChapters: ChapterColumn[] } {
    const grid = new Map<string, GridCell>();
    const allChapters: ChapterColumn[] = [];

    for (const act of acts) {
        for (const chapter of act.chapters) {
            allChapters.push(chapter);
        }
    }

    for (const storyline of storylines) {
        for (const chapter of allChapters) {
            const key = `${storyline.id}-${chapter.id}`;
            grid.set(key, {
                storylineId: storyline.id,
                chapterId: chapter.id,
                plotPoints: [],
            });
        }
    }

    for (const pp of plotPoints) {
        if (pp.storyline_id && pp.intended_chapter_id) {
            const key = `${pp.storyline_id}-${pp.intended_chapter_id}`;
            const cell = grid.get(key);
            if (cell) {
                cell.plotPoints.push(pp);
            }
        }
    }

    return { grid, allChapters };
}

export function cellKey(storylineId: number, chapterId: number): string {
    return `${storylineId}-${chapterId}`;
}
