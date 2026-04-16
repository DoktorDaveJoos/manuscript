import type { Auth } from '@/types/auth';
import type { Storyline } from '@/types/models';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            app_version: string;
            auth: Auth;
            sidebarOpen: boolean;
            locale: string;
            sidebar_storylines: Storyline[] | null;
            boot_error?: boolean;
            database_repaired?: boolean;
            repair_details?: {
                recovered: string[];
                failed: string[];
            };
            ai_configured?: boolean;
            ai_key_recovery_needed?: boolean;
            [key: string]: unknown;
        };
    }
}
