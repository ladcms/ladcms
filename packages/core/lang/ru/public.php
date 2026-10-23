<?php

declare(strict_types=1);

return [
    'nav' => [
        'home' => 'Главная',
        'users' => 'Пользователи',
        'rules' => 'Правила',
    ],
    'header' => [
        'main_nav' => 'Основная навигация',
        'search' => 'Поиск',
        'search_placeholder' => 'Поиск пользователей…',
    ],
    'user_menu' => [
        'menu' => 'Меню пользователя',
        'profile' => 'Мой профиль',
        'settings' => 'Настройки',
        'admin' => 'Админка',
        'logout' => 'Выйти',
        'login' => 'Войти',
        'register' => 'Регистрация',
    ],
    'roles' => [
        'creator' => 'Создатель',
        'main_admin' => 'Главный администратор',
        'admin' => 'Администратор',
        'super_moderator' => 'Старший модератор',
        'moderator' => 'Модератор',
        'vip' => 'VIP',
        'user' => 'Пользователь',
    ],
    'readonly' => [
        'title' => 'Режим «только чтение»',
        'text' => 'Вы не можете публиковать новый контент, пока действует ограничение.',
    ],
    'footer' => [
        'copyright' => '© :year :name',
        'rules' => 'Правила',
        'language' => 'Язык',
    ],
    'tabbar' => [
        'label' => 'Нижняя навигация',
        'me' => 'Я',
    ],
    'sidebar' => [
        'more' => 'Ещё',
    ],
    'home' => [
        'heading' => 'Добро пожаловать на :name',
        'empty' => 'Здесь пока пусто',
        'empty_text' => 'На главной странице пока нет содержимого.',
    ],
    'widgets' => [
        'welcome' => [
            'title' => 'Добро пожаловать',
            'rules' => 'Читать правила',
        ],
        'online' => [
            'title' => 'Сейчас на сайте',
            'empty' => 'Сейчас никого нет',
        ],
        'latest_users' => [
            'title' => 'Новые пользователи',
            'empty' => 'Пока никого нет',
        ],
    ],
    'users' => [
        'title' => 'Пользователи',
        'total' => 'Всего',
        'search' => 'Поиск по логину или имени',
        'sort' => 'Сортировка',
        'sort_options' => [
            'created_at_desc' => 'Сначала новые',
            'created_at_asc' => 'Сначала старые',
            'login_asc' => 'Логин А–Я',
            'login_desc' => 'Логин Я–А',
            'last_activity_at_desc' => 'Недавно были на сайте',
            'last_activity_at_asc' => 'Давно не были на сайте',
        ],
        'online_only' => 'Только онлайн',
        'online' => 'Онлайн',
        'joined' => 'Регистрация: :date',
        'empty' => 'Никого не найдено',
        'empty_text' => 'Измените параметры поиска.',
    ],
];
