import type { LucideIcon } from 'lucide-react';
import {
    Bell,
    Box,
    ChevronRight,
    Circle,
    Gavel,
    LayoutDashboard,
    LogOut,
    Menu,
    Puzzle,
    ScrollText,
    Settings,
    Shield,
    User,
    Users,
} from 'lucide-react';

const icons: Record<string, LucideIcon> = {
    'layout-dashboard': LayoutDashboard,
    users: Users,
    shield: Shield,
    gavel: Gavel,
    settings: Settings,
    puzzle: Puzzle,
    'scroll-text': ScrollText,
    box: Box,
    bell: Bell,
    'log-out': LogOut,
    menu: Menu,
    user: User,
    'chevron-right': ChevronRight,
};

/** Иконка lucide по kebab-имени из меню; неизвестные — `Circle`. */
export function resolveIcon(name: string | null | undefined): LucideIcon {
    return (name ? icons[name] : undefined) ?? Circle;
}
