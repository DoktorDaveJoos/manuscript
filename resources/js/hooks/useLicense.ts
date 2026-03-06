import type { License } from '@/types/models';
import { usePage } from '@inertiajs/react';

export function useLicense() {
    const { license } = usePage<{ license: License }>().props;

    return { isActive: license.active, isFree: !license.active };
}
