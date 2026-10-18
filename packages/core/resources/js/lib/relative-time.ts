const units: [Intl.RelativeTimeFormatUnit, number][] = [
    ['day', 86_400],
    ['hour', 3_600],
    ['minute', 60],
];

/** «5 minutes ago» / «5 минут назад» для ISO-даты в заданной локали. */
export function relativeTime(iso: string, locale: string): string {
    const timestamp = new Date(iso).getTime();
    if (Number.isNaN(timestamp)) {
        return '';
    }

    const formatter = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
    const seconds = Math.round((timestamp - Date.now()) / 1000);

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return formatter.format(Math.round(seconds / size), unit);
        }
    }

    return formatter.format(0, 'second');
}
