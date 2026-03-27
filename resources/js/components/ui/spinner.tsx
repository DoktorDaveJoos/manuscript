import { Loader2Icon } from 'lucide-react';
import { cn } from '@/lib/utils';

function Spinner({ className, ...props }: React.ComponentProps<'svg'>) {
    return (
        <Loader2Icon
            role="img"
            aria-label="Loading"
            className={cn('animate-spin', className)}
            {...props}
        />
    );
}

export { Spinner };
