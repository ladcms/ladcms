<?php

declare(strict_types=1);

return [
    'general' => [
        'title' => 'General',
        'fields' => [
            'site_name' => ['label' => 'Site name', 'description' => 'Shown in the title and in emails.'],
            'site_description' => ['label' => 'Site description', 'description' => 'Short description for search engines.'],
            'default_language' => ['label' => 'Default language', 'description' => 'Used when the user language is unknown.'],
            'timezone' => ['label' => 'Timezone', 'description' => 'Default site timezone.'],
            'home_page' => ['label' => 'Home page', 'description' => 'Key of the page served at "/".'],
            'rules_markdown' => ['label' => 'Site rules', 'description' => 'Rules text in Markdown.'],
        ],
    ],
    'registration' => [
        'title' => 'Registration',
        'fields' => [
            'open' => ['label' => 'Registration is open', 'description' => 'When off, new users cannot sign up.'],
            'require_email_verification' => ['label' => 'Require email verification', 'description' => 'Until the email is verified the user is read-only.'],
            'require_rules' => ['label' => 'Require acceptance of the rules', 'description' => 'Users must accept the site rules when signing up.'],
            'invite_only' => ['label' => 'Invite-only registration', 'description' => 'Signing up requires a valid invite code.'],
            'readonly_hours' => ['label' => 'Read-only hours', 'description' => 'How many hours after signing up the user stays read-only.'],
        ],
    ],
    'moderation' => [
        'title' => 'Moderation',
        'fields' => [
            'violation_codes' => ['label' => 'Violation codes', 'description' => 'Ban reasons and the allowed duration in days.'],
            'password_confirm_minutes' => ['label' => 'Password confirmation, minutes', 'description' => 'How long a confirmed password stays valid in the admin.'],
        ],
        'codes' => [
            'code' => 'Code',
            'label' => 'Label',
            'min_days' => 'Min days',
            'max_days' => 'Max days',
        ],
    ],
    'appearance' => [
        'title' => 'Appearance',
        'fields' => [
            'accent_color' => ['label' => 'Accent colour', 'description' => 'Colour in #rrggbb format.'],
            'logo_path' => ['label' => 'Logo path', 'description' => 'Optional.'],
            'default_theme' => ['label' => 'Default theme', 'description' => 'system follows the device setting.'],
        ],
    ],
];
