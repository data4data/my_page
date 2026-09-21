<?php

namespace App\Support;

use App\Contracts\PortfolioSeedContent;

class DefaultPortfolioContent implements PortfolioSeedContent
{
    public function content(): array
    {
        return [
            'profile' => [
                // A placeholder. Change it in the workspace.
                'initials' => 'AB',
                'role' => ['en' => 'Full-Stack Developer', 'nl' => 'Full-stack ontwikkelaar'],
                'headline' => [
                    'en' => 'Building systems with precision that drive impact.',
                    'nl' => 'Systemen bouwen met precisie die impact maken.',
                ],
                // Both languages in one list; only the words on screen match.
                'headline_highlights' => [
                    ['text' => 'precision', 'tone' => 'blue'],
                    ['text' => 'precisie', 'tone' => 'blue'],
                    ['text' => 'impact', 'tone' => 'gold'],
                ],
                'summary' => [
                    'en' => 'I build digital systems that solve real business problems with clean code, automation, and measurable results.',
                    'nl' => 'Ik bouw digitale systemen die echte bedrijfsproblemen oplossen met heldere code, automatisering en meetbare resultaten.',
                ],
                'primary_cta_label' => ['en' => 'View my work', 'nl' => 'Bekijk mijn werk'],
                'primary_cta_url' => '#work',
                'secondary_cta_label' => ['en' => 'About me', 'nl' => 'Over mij'],
                'secondary_cta_url' => '#about',

                // Empty on purpose: the contact button hides itself until
                // the owner fills this in.
                'contact_email' => null,
                'social_image_url' => null,
                'footer_note_left' => ['en' => 'Based in Europe', 'nl' => 'Gevestigd in Europa'],
                // Free text; the column name is left over from when it held availability.
                'footer_note_right' => ['en' => 'Since 2024', 'nl' => 'Sinds 2024'],
                'quote' => [
                    'en' => 'Simplicity is the ultimate sophistication.',
                    'nl' => 'Eenvoud is de ultieme verfijning.',
                ],
                'quote_author' => [
                    'en' => 'Leonardo da Vinci',
                    'nl' => 'Leonardo da Vinci',
                ],
            ],
            'social_links' => [
                ['label' => 'GitHub', 'url' => 'https://github.com/', 'icon' => 'github', 'in_rail' => true, 'in_footer' => true],
                ['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/', 'icon' => 'linkedin', 'in_rail' => true, 'in_footer' => true],
                ['label' => 'Email', 'url' => 'mailto:hello@example.com', 'icon' => 'mail', 'in_rail' => true, 'in_footer' => true],
            ],
            'metrics' => [
                ['value' => '10+', 'label' => ['en' => 'Years of experience', 'nl' => 'Jaren ervaring']],
                ['value' => '20+', 'label' => ['en' => 'Projects completed', 'nl' => 'Projecten afgerond']],
                ['value' => '10+', 'label' => ['en' => 'Integrations built', 'nl' => 'Integraties gebouwd']],
                ['value' => '∞', 'label' => ['en' => 'Curiosity and learning', 'nl' => 'Nieuwsgierigheid en leren']],
            ],
            'expertise_items' => [
                [
                    'title' => ['en' => 'Web Development', 'nl' => 'Webontwikkeling'],
                    'description' => ['en' => 'Modern, scalable, and maintainable applications.', 'nl' => 'Moderne, schaalbare en onderhoudbare applicaties.'],
                    'icon' => 'code',
                    'category' => 'frontend',
                ],
                [
                    'title' => ['en' => 'APIs & Integrations', 'nl' => 'APIs & integraties'],
                    'description' => ['en' => 'RESTful APIs, third-party systems, webhooks, and data flows.', 'nl' => 'RESTful APIs, externe systemen, webhooks en datastromen.'],
                    'icon' => 'link',
                    'category' => 'backend',
                ],
                [
                    'title' => ['en' => 'Automation', 'nl' => 'Automatisering'],
                    'description' => ['en' => 'Workflow automation, scripts, and AI-assisted operational tools.', 'nl' => 'Workflowautomatisering, scripts en AI-ondersteunde operationele tools.'],
                    'icon' => 'settings',
                    'category' => 'systems',
                ],
                [
                    'title' => ['en' => 'Databases', 'nl' => 'Databases'],
                    'description' => ['en' => 'MySQL, relational design, query optimization, and reporting data.', 'nl' => 'MySQL, relationeel ontwerp, query-optimalisatie en rapportagedata.'],
                    'icon' => 'database',
                    'category' => 'data',
                ],
                [
                    'title' => ['en' => 'Cloud & DevOps', 'nl' => 'Cloud & DevOps'],
                    'description' => ['en' => 'CI/CD, deployments, hosting, queues, and maintainable infrastructure.', 'nl' => 'CI/CD, deployments, hosting, queues en onderhoudbare infrastructuur.'],
                    'icon' => 'cloud',
                    'category' => 'infrastructure',
                ],
                [
                    'title' => ['en' => 'Data & Analytics', 'nl' => 'Data & analytics'],
                    'description' => ['en' => 'Dashboards, reporting views, and business insight workflows.', 'nl' => 'Dashboards, rapportages en workflows voor bedrijfsinzicht.'],
                    'icon' => 'chart',
                    'category' => 'data',
                ],
            ],
            'projects' => [
                [
                    'title' => ['en' => 'Business Dashboard', 'nl' => 'Business dashboard'],
                    'summary' => ['en' => 'Real-time analytics dashboard for managing clients, invoices, and project performance.', 'nl' => 'Realtime dashboard voor klanten, facturen en projectprestaties.'],
                    'result' => ['en' => 'Clearer decisions from organized operational data.', 'nl' => 'Duidelijkere beslissingen door georganiseerde operationele data.'],
                    'tags' => ['Vue.js', 'Laravel', 'MySQL'],
                    'visual_style' => 'dashboard',
                ],
                [
                    'title' => ['en' => 'Workflow Automation', 'nl' => 'Workflowautomatisering'],
                    'summary' => ['en' => 'Automated document, notification, and approval flows that reduce repetitive manual work.', 'nl' => 'Geautomatiseerde document-, notificatie- en goedkeuringsflows die repetitief handwerk verminderen.'],
                    'result' => ['en' => 'Less manual handling and faster status visibility.', 'nl' => 'Minder handmatig werk en sneller inzicht in status.'],
                    'tags' => ['Laravel', 'Automation', 'API'],
                    'visual_style' => 'flow',
                ],
                [
                    'title' => ['en' => 'Internal CMS', 'nl' => 'Intern CMS'],
                    'summary' => ['en' => 'Custom content management system with roles, permissions, media, and activity logs.', 'nl' => 'Contentmanagementsysteem met rollen, rechten, media en activiteitenlogboeken.'],
                    'result' => ['en' => 'Content changes become structured and easy to control.', 'nl' => 'Contentwijzigingen worden gestructureerd en eenvoudig te beheren.'],
                    'tags' => ['Laravel', 'Vue.js', 'Tailwind'],
                    'visual_style' => 'cms',
                ],
            ],
            'process_steps' => [
                ['group' => 'input', 'title' => ['en' => 'Business Goals', 'nl' => 'Bedrijfsdoelen'], 'description' => ['en' => 'Purpose and priorities', 'nl' => 'Doel en prioriteiten'], 'icon' => 'target'],
                ['group' => 'input', 'title' => ['en' => 'User Needs', 'nl' => 'Gebruikersbehoeften'], 'description' => ['en' => 'Real workflows and friction', 'nl' => 'Echte workflows en knelpunten'], 'icon' => 'users'],
                ['group' => 'input', 'title' => ['en' => 'Data Sources', 'nl' => 'Databronnen'], 'description' => ['en' => 'Inputs, formats, and quality', 'nl' => 'Input, formats en kwaliteit'], 'icon' => 'database'],
                ['group' => 'input', 'title' => ['en' => 'Integrations', 'nl' => 'Integraties'], 'description' => ['en' => 'APIs, tools, and services', 'nl' => 'APIs, tools en services'], 'icon' => 'link'],
                ['group' => 'core', 'title' => ['en' => 'Backend', 'nl' => 'Backend'], 'description' => ['en' => 'Laravel', 'nl' => 'Laravel'], 'icon' => 'box'],
                ['group' => 'core', 'title' => ['en' => 'Frontend', 'nl' => 'Frontend'], 'description' => ['en' => 'Vue.js', 'nl' => 'Vue.js'], 'icon' => 'monitor'],
                ['group' => 'core', 'title' => ['en' => 'Database', 'nl' => 'Database'], 'description' => ['en' => 'MySQL', 'nl' => 'MySQL'], 'icon' => 'database'],
                ['group' => 'core', 'title' => ['en' => 'Automation', 'nl' => 'Automatisering'], 'description' => ['en' => 'Workflows', 'nl' => 'Workflows'], 'icon' => 'zap'],
                ['group' => 'core', 'title' => ['en' => 'Cloud', 'nl' => 'Cloud'], 'description' => ['en' => 'Deployments', 'nl' => 'Deployments'], 'icon' => 'cloud'],
                ['group' => 'output', 'title' => ['en' => 'Web Application', 'nl' => 'Webapplicatie'], 'description' => ['en' => 'Usable product', 'nl' => 'Bruikbaar product'], 'icon' => 'globe'],
                ['group' => 'output', 'title' => ['en' => 'Automations', 'nl' => 'Automatiseringen'], 'description' => ['en' => 'Less repetitive work', 'nl' => 'Minder repetitief werk'], 'icon' => 'settings'],
                ['group' => 'output', 'title' => ['en' => 'Dashboards', 'nl' => 'Dashboards'], 'description' => ['en' => 'Operational clarity', 'nl' => 'Operationele helderheid'], 'icon' => 'chart'],
                ['group' => 'output', 'title' => ['en' => 'Real Impact', 'nl' => 'Echte impact'], 'description' => ['en' => 'Measurable outcomes', 'nl' => 'Meetbare resultaten'], 'icon' => 'sparkles'],
            ],
        ];
    }
}
