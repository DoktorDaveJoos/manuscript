export const menuShadow = 'border border-border-light shadow-lg';

export const menuContentBase =
    'z-50 rounded-lg bg-surface-card p-1';

export const menuItemBase =
    'flex w-full cursor-default items-center gap-2.5 rounded-md px-3 py-2 text-[13px] leading-[18px] outline-none transition-colors';

export const menuItemVariants = {
    default: 'text-ink-soft data-[highlighted]:bg-neutral-bg',
    danger: 'font-medium text-delete data-[highlighted]:bg-neutral-bg',
} as const;

export const menuSeparatorBase = 'mx-2 my-1 h-px bg-border';

export const menuLabelBase =
    'text-[11px] font-medium tracking-[0.06em] text-ink-faint uppercase';
