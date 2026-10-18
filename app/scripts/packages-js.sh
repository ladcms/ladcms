#!/usr/bin/env bash
# Прогоняет `vp <команда> …` по JS/TS пакетов LADCMS (vendor/ladcms/*/resources/js).
# vp не заходит в симлинки и .gitignore'нутый vendor при обходе каталогов, поэтому файлы передаются явно.
# components/ui/** и прочее отсекают ignorePatterns из vite.config.ts.
set -euo pipefail
cd "$(dirname "$0")/.."

# Массив, а не строка: пути с пробелами не разваливаются на части.
FILES=()
if [ -d vendor/ladcms ]; then
    while IFS= read -r -d '' file; do
        FILES+=("$file")
    done < <(find -L vendor/ladcms -path '*/resources/js/*' \( -name '*.ts' -o -name '*.tsx' \) \
        -not -path '*/node_modules/*' -not -path '*/tests/*' -not -path '*/vendor/*' -print0 | sort -z)
fi

# Без файлов `vp` обошёл бы весь проект (или ничего) и вернул 0 — молчаливый «зелёный» прогон.
if [ "${#FILES[@]}" -eq 0 ]; then
    echo "packages-js: no JS/TS files found under vendor/ladcms/*/resources/js (is composer install done?)" >&2
    exit 1
fi

exec vp "$@" "${FILES[@]}"
