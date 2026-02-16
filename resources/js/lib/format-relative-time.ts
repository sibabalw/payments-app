/**
 * Format a date for chat-style display (e.g. WhatsApp): "Just now", "2 min ago", "1 hr ago", "Yesterday", "Jan 15", etc.
 * Pass a Date, ISO string, or timestamp. Returns a short relative string; use the same value for title for full datetime.
 */
export function formatRelativeTime(input: Date | string | number): string {
    const date = typeof input === 'object' && input instanceof Date ? input : new Date(input);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
    const diffHr = Math.floor(diffMin / 60);
    const diffDay = Math.floor(diffHr / 24);

    const sameCalendarDay = (a: Date, b: Date) =>
        a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    const yesterday = new Date(now);
    yesterday.setDate(yesterday.getDate() - 1);

    if (diffSec < 60) {
        return 'Just now';
    }
    if (diffMin < 60) {
        return diffMin === 1 ? '1 min ago' : `${diffMin} min ago`;
    }
    if (diffHr < 24 && sameCalendarDay(date, now)) {
        return diffHr === 1 ? '1 hr ago' : `${diffHr} hr ago`;
    }
    if (sameCalendarDay(date, yesterday)) {
        return 'Yesterday';
    }
    if (diffDay < 7) {
        return `${diffDay} days ago`;
    }
    if (date.getFullYear() === now.getFullYear()) {
        return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    }
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}
