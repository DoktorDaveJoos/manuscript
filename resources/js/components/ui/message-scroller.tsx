import {
    MessageScroller as MessageScrollerPrimitive,
    useMessageScroller,
    useMessageScrollerScrollable,
    useMessageScrollerVisibility,
} from '@shadcn/react/message-scroller';
import { cva } from 'class-variance-authority';
import type { VariantProps } from 'class-variance-authority';
import { ArrowDown } from 'lucide-react';
import { useLayoutEffect, useRef } from 'react';
import type { ComponentProps } from 'react';
import Button from '@/components/ui/Button';
import { cn } from '@/lib/utils';

function MessageScrollerProvider(
    props: ComponentProps<typeof MessageScrollerPrimitive.Provider>,
) {
    return <MessageScrollerPrimitive.Provider {...props} />;
}

const messageScrollerVariants = cva(
    'group/message-scroller relative flex size-full min-h-0 flex-col overflow-hidden [--message-scroller-composer-height:0px]',
    {
        variants: {
            canvas: {
                editor: 'bg-surface',
                panel: 'bg-surface-sidebar',
            },
        },
        defaultVariants: {
            canvas: 'editor',
        },
    },
);

function MessageScroller({
    canvas = 'editor',
    className,
    ...props
}: ComponentProps<typeof MessageScrollerPrimitive.Root> &
    VariantProps<typeof messageScrollerVariants>) {
    return (
        <MessageScrollerPrimitive.Root
            data-slot="message-scroller"
            data-canvas={canvas}
            className={cn(messageScrollerVariants({ canvas }), className)}
            {...props}
        />
    );
}

function MessageScrollerViewport({
    className,
    ...props
}: ComponentProps<typeof MessageScrollerPrimitive.Viewport>) {
    return (
        <MessageScrollerPrimitive.Viewport
            data-slot="message-scroller-viewport"
            className={cn(
                'size-full min-h-0 min-w-0 overflow-y-auto overscroll-contain data-autoscrolling:scrollbar-none',
                className,
            )}
            {...props}
        />
    );
}

function MessageScrollerContent({
    className,
    ...props
}: ComponentProps<typeof MessageScrollerPrimitive.Content>) {
    return (
        <MessageScrollerPrimitive.Content
            data-slot="message-scroller-content"
            className={cn(
                "flex h-max min-h-full flex-col gap-4 after:block after:h-[var(--message-scroller-composer-height)] after:shrink-0 after:content-['']",
                className,
            )}
            {...props}
        />
    );
}

function MessageScrollerItem({
    className,
    scrollAnchor = false,
    ...props
}: ComponentProps<typeof MessageScrollerPrimitive.Item>) {
    return (
        <MessageScrollerPrimitive.Item
            data-slot="message-scroller-item"
            scrollAnchor={scrollAnchor}
            className={cn('min-w-0 shrink-0', className)}
            {...props}
        />
    );
}

function MessageScrollerComposer({
    className,
    ...props
}: Omit<ComponentProps<'div'>, 'ref'>) {
    const composerRef = useRef<HTMLDivElement>(null);

    useLayoutEffect(() => {
        const composer = composerRef.current;
        const scroller = composer?.closest<HTMLElement>(
            '[data-slot="message-scroller"]',
        );

        if (!composer || !scroller) {
            return;
        }

        const updateComposerHeight = () => {
            scroller.style.setProperty(
                '--message-scroller-composer-height',
                `${composer.getBoundingClientRect().height}px`,
            );
        };

        updateComposerHeight();

        if (typeof ResizeObserver === 'undefined') {
            return () => {
                scroller.style.removeProperty(
                    '--message-scroller-composer-height',
                );
            };
        }

        const observer = new ResizeObserver(updateComposerHeight);
        observer.observe(composer);

        return () => {
            observer.disconnect();
            scroller.style.removeProperty(
                '--message-scroller-composer-height',
            );
        };
    }, []);

    return (
        <div
            ref={composerRef}
            data-slot="message-scroller-composer"
            className={cn(
                'pointer-events-none absolute inset-x-0 bottom-0',
                className,
            )}
            {...props}
        />
    );
}

function MessageScrollerButton({
    direction = 'end',
    className,
    children,
    render,
    ...props
}: ComponentProps<typeof MessageScrollerPrimitive.Button>) {
    return (
        <MessageScrollerPrimitive.Button
            data-slot="message-scroller-button"
            data-direction={direction}
            direction={direction}
            className={cn(
                'absolute left-1/2 size-8 -translate-x-1/2 rounded-full shadow-sm transition-[translate,scale,opacity] duration-200 [&_svg]:size-3.5 data-[active=false]:pointer-events-none data-[active=false]:scale-95 data-[active=false]:opacity-0 data-[active=true]:translate-y-0 data-[active=true]:scale-100 data-[active=true]:opacity-100 data-[direction=end]:bottom-[calc(var(--message-scroller-composer-height)+1rem)] data-[direction=end]:data-[active=false]:translate-y-full data-[direction=start]:top-4 data-[direction=start]:data-[active=false]:-translate-y-full data-[direction=start]:[&_svg]:rotate-180',
                className,
            )}
            render={
                render ?? <Button variant="secondary" size="icon" />
            }
            {...props}
        >
            {children ?? (
                <>
                    <ArrowDown />
                    <span className="sr-only">
                        {direction === 'end'
                            ? 'Scroll to end'
                            : 'Scroll to start'}
                    </span>
                </>
            )}
        </MessageScrollerPrimitive.Button>
    );
}

export {
    MessageScrollerProvider,
    MessageScroller,
    MessageScrollerViewport,
    MessageScrollerContent,
    MessageScrollerItem,
    MessageScrollerComposer,
    MessageScrollerButton,
    useMessageScroller,
    useMessageScrollerScrollable,
    useMessageScrollerVisibility,
};
