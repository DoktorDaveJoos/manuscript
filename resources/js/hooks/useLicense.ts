import { usePage } from '@inertiajs/react';
import type { License } from '@/types/models';

export function useLicense() {
    const { license } = usePage<{ license: License }>().props;

    return { isActive: license.active, isFree: !license.active };
}
