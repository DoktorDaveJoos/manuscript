import { formatCompactCount } from '@/lib/utils';
import type { Scene } from '@/types/models';

export default function SceneListItem({ scene, onClick }: { scene: Scene; onClick: () => void }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="flex w-full items-center gap-2 rounded-[5px] py-1 pl-[52px] pr-2.5 text-left text-[12px] text-ink-faint transition-colors hover:bg-ink/5 hover:text-ink-soft"
        >
            <span className="min-w-0 flex-1 truncate">{scene.title}</span>
            <span className="shrink-0 text-[11px]">{formatCompactCount(scene.word_count)}</span>
        </button>
    );
}
