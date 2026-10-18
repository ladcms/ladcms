<?php

declare(strict_types=1);

namespace Ladcms\Core\Settings\Schema;

enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Markdown = 'markdown';
    case Number = 'number';
    case Boolean = 'boolean';
    case Select = 'select';
    case Color = 'color';
    case Json = 'json';
    case ViolationCodes = 'violation_codes';
}
