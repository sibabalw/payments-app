/**
 * Convert cron expression to human-readable text
 */
export function cronToHumanReadable(cronExpression: string): string {
    const parts = cronExpression.trim().split(/\s+/);
    
    if (parts.length !== 5) {
        return cronExpression; // Return as-is if invalid
    }

    const [minute, hour, day, month, weekday] = parts;

    // Format time
    const hourNum = parseInt(hour);
    const minuteNum = parseInt(minute);
    const timeStr = formatTime(hourNum, minuteNum);

    // Daily: * * * * *
    if (day === '*' && month === '*' && weekday === '*') {
        return `Daily at ${timeStr}`;
    }

    // Weekly: * * * * 0-6 (specific weekday)
    if (day === '*' && month === '*' && weekday !== '*') {
        const dayName = getWeekdayName(parseInt(weekday));
        return `Every ${dayName} at ${timeStr}`;
    }

    // Monthly: * * 1-31 * *
    if (day !== '*' && month === '*' && weekday === '*') {
        const dayNum = parseInt(day);
        const daySuffix = getDaySuffix(dayNum);
        return `Monthly on the ${dayNum}${daySuffix} at ${timeStr}`;
    }

    // Yearly: * * 1-31 1-12 *
    if (day !== '*' && month !== '*' && weekday === '*') {
        const dayNum = parseInt(day);
        const monthName = getMonthName(parseInt(month));
        const daySuffix = getDaySuffix(dayNum);
        return `Yearly on ${monthName} ${dayNum}${daySuffix} at ${timeStr}`;
    }

    // Complex expressions - try to make it readable
    if (day !== '*' && month === '*') {
        const dayNum = parseInt(day);
        const daySuffix = getDaySuffix(dayNum);
        return `Monthly on the ${dayNum}${daySuffix} at ${timeStr}`;
    }

    // Fallback: return formatted time with cron
    return `${timeStr} (${cronExpression})`;
}

function formatTime(hour: number, minute: number): string {
    const period = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour === 0 ? 12 : hour > 12 ? hour - 12 : hour;
    const displayMinute = minute.toString().padStart(2, '0');
    return `${displayHour}:${displayMinute} ${period}`;
}

function getWeekdayName(day: number): string {
    const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    return days[day] || `Day ${day}`;
}

function getMonthName(month: number): string {
    const months = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    return months[month - 1] || `Month ${month}`;
}

function getDaySuffix(day: number): string {
    if (day >= 11 && day <= 13) {
        return 'th';
    }
    switch (day % 10) {
        case 1: return 'st';
        case 2: return 'nd';
        case 3: return 'rd';
        default: return 'th';
    }
}
