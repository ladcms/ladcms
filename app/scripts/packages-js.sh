#!/bin/sh
# Прогоняет `vp <команда> …` по JS/TS пакетов LADCMS (vendor/ladcms/*/resources/js).
# vp не заходит в симлинки и .gitignore'нутый vendor при обходе каталогов, поэтому файлы передаются явно.
# components/ui/** и прочее отсекают ignorePatterns из vite.config.ts.
set -e
cd "$(dirname "$0")/.."
FILES=$(find -L vendor/ladcms -path '*/resources/js/*' \( -name '*.ts' -o -name '*.tsx' \) \
    -not -path '*/node_modules/*' -not -path '*/tests/*' -not -path '*/vendor/*' | sort)
# shellcheck disable=SC2086
exec vp "$@" $FILES
