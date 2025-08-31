import { LucideIcon } from 'lucide-vue-next';

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon | string;
    isActive?: boolean;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

// Extend the existing Inertia types
declare module '@inertiajs/core' {
    interface PageProps {
        navigation?: {
            mainNavItems: Array<{
                title: string;
                href: string;
                icon: string;
            }>;
        };
    }
}
