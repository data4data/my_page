import {
    BarChart3,
    Box,
    Cloud,
    Code2,
    Database,
    ExternalLink,
    GitBranch,
    Globe2,
    Link as LinkIcon,
    Mail,
    Monitor,
    Settings,
    Sparkles,
    Target,
    Users,
    Zap,
} from '@lucide/vue';

// String key stored in the DB -> lucide component. Add new icon values here
// (both here and the admin "Icon" hint text) or they silently render nothing.
export const iconMap = {
    box: Box,
    chart: BarChart3,
    cloud: Cloud,
    code: Code2,
    database: Database,
    github: GitBranch,
    globe: Globe2,
    linkedin: ExternalLink,
    link: LinkIcon,
    mail: Mail,
    monitor: Monitor,
    settings: Settings,
    sparkles: Sparkles,
    target: Target,
    users: Users,
    zap: Zap,
};

export const resolveIcon = (name) => iconMap[name] ?? Sparkles;
