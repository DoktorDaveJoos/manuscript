import type { Storyline } from '@/types/models';
import { usePage } from '@inertiajs/react';

export function useSidebarStorylines(): Storyline[] {
    const { sidebar_storylines } = usePage<{ sidebar_storylines: Storyline[] | null }>().props;
    return sidebar_storylines ?? [];
}
