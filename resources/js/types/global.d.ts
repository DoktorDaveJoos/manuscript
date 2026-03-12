import type { Auth } from '@/types/auth';
import type { Storyline } from '@/types/models';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            locale: string;
            sidebar_storylines: Storyline[] | null;
            [key: string]: unknown;
        };
    }
}
