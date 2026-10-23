<?php

declare(strict_types=1);

return [
    'nav' => [
        'home' => 'Home',
        'users' => 'Users',
        'rules' => 'Rules',
    ],
    'header' => [
        'main_nav' => 'Main navigation',
        'search' => 'Search',
        'search_placeholder' => 'Search users…',
    ],
    'user_menu' => [
        'menu' => 'User menu',
        'profile' => 'My profile',
        'settings' => 'Settings',
        'admin' => 'Admin panel',
        'logout' => 'Log out',
        'login' => 'Log in',
        'register' => 'Register',
    ],
    'roles' => [
        'creator' => 'Creator',
        'main_admin' => 'Chief administrator',
        'admin' => 'Administrator',
        'super_moderator' => 'Senior moderator',
        'moderator' => 'Moderator',
        'vip' => 'VIP',
        'user' => 'User',
    ],
    'readonly' => [
        'title' => 'Read-only mode',
        'text' => 'You cannot publish new content while the restriction is active.',
    ],
    'footer' => [
        'copyright' => '© :year :name',
        'rules' => 'Rules',
        'language' => 'Language',
    ],
    'tabbar' => [
        'label' => 'Bottom navigation',
        'me' => 'Me',
    ],
    'sidebar' => [
        'more' => 'More',
    ],
    'home' => [
        'heading' => 'Welcome to :name',
        'empty' => 'Nothing here yet',
        'empty_text' => 'The home page has no content yet.',
    ],
    'widgets' => [
        'welcome' => [
            'title' => 'Welcome',
            'rules' => 'Read the rules',
        ],
        'online' => [
            'title' => 'Online now',
            'empty' => 'Nobody is online',
        ],
        'latest_users' => [
            'title' => 'Newest users',
            'empty' => 'Nobody yet',
        ],
    ],
    'users' => [
        'title' => 'Users',
        'total' => 'Total',
        'search' => 'Search by login or name',
        'sort' => 'Sort',
        'sort_options' => [
            'created_at_desc' => 'Newest first',
            'created_at_asc' => 'Oldest first',
            'login_asc' => 'Login A–Z',
            'login_desc' => 'Login Z–A',
            'last_activity_at_desc' => 'Recently active',
            'last_activity_at_asc' => 'Least recently active',
        ],
        'online_only' => 'Online only',
        'online' => 'Online',
        'joined' => 'Joined :date',
        'empty' => 'No users found',
        'empty_text' => 'Try changing your search.',
    ],
];
