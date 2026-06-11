import { cn } from '@/lib/utils';
import NavItem from './NavItem';
import SectionLabel from './SectionLabel';

export type SideNavItem = {
    key: string;
    label: string;
    href: string;
};

export default function SideNav({
    items,
    activeKey,
    label,
    className,
}: {
    items: SideNavItem[];
    activeKey: string;
    label?: string;
    className?: string;
}) {
    return (
        <nav
            className={cn(
                'flex w-56 shrink-0 flex-col gap-0.5 overflow-y-auto border-r border-border-light px-2.5 py-4',
                className,
            )}
        >
            {label && (
                <SectionLabel variant="section" className="mb-1.5 block px-2.5">
                    {label}
                </SectionLabel>
            )}
            {items.map((item) => (
                <NavItem
                    key={item.key}
                    label={item.label}
                    href={item.href}
                    isActive={item.key === activeKey}
                />
            ))}
        </nav>
    );
}
