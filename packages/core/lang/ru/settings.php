<?php

declare(strict_types=1);

return [
    'general' => [
        'title' => 'Общие',
        'fields' => [
            'site_name' => ['label' => 'Название сайта', 'description' => 'Показывается в заголовке и письмах.'],
            'site_description' => ['label' => 'Описание сайта', 'description' => 'Краткое описание для поисковых систем.'],
            'default_language' => ['label' => 'Язык по умолчанию', 'description' => 'Используется, если язык пользователя не определён.'],
            'timezone' => ['label' => 'Часовой пояс', 'description' => 'Часовой пояс сайта по умолчанию.'],
            'home_page' => ['label' => 'Главная страница', 'description' => 'Ключ страницы, открываемой по адресу «/».'],
            'rules_markdown' => ['label' => 'Правила сайта', 'description' => 'Текст правил в формате Markdown.'],
        ],
    ],
    'registration' => [
        'title' => 'Регистрация',
        'fields' => [
            'open' => ['label' => 'Регистрация открыта', 'description' => 'Если выключено, новые пользователи не могут зарегистрироваться.'],
            'require_email_verification' => ['label' => 'Требовать подтверждение email', 'description' => 'Пока email не подтверждён, пользователь может только читать.'],
            'require_rules' => ['label' => 'Требовать согласие с правилами', 'description' => 'При регистрации нужно принять правила сайта.'],
            'invite_only' => ['label' => 'Регистрация по приглашениям', 'description' => 'Для регистрации нужен действующий код приглашения.'],
            'readonly_hours' => ['label' => 'Часов только для чтения', 'description' => 'Сколько часов после регистрации пользователь может только читать.'],
        ],
    ],
    'moderation' => [
        'title' => 'Модерация',
        'fields' => [
            'violation_codes' => ['label' => 'Коды нарушений', 'description' => 'Причины блокировок и допустимая длительность в днях.'],
            'password_confirm_minutes' => ['label' => 'Подтверждение пароля, минут', 'description' => 'Как долго подтверждённый пароль действует в админке.'],
        ],
        'codes' => [
            'code' => 'Код',
            'label' => 'Название',
            'min_days' => 'Мин. дней',
            'max_days' => 'Макс. дней',
        ],
    ],
    'appearance' => [
        'title' => 'Внешний вид',
        'fields' => [
            'accent_color' => ['label' => 'Акцентный цвет', 'description' => 'Цвет в формате #rrggbb.'],
            'logo_path' => ['label' => 'Путь к логотипу', 'description' => 'Необязательно.'],
            'default_theme' => ['label' => 'Тема по умолчанию', 'description' => 'system — следовать настройкам устройства.'],
        ],
    ],
];
