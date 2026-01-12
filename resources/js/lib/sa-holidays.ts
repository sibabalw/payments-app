/**
 * South Africa Holidays Utility
 * Provides functions to check for weekends and public/bank holidays
 */

/**
 * Calculate Easter date for a given year using the Computus algorithm
 */
function calculateEaster(year: number): Date {
    const a = year % 19;
    const b = Math.floor(year / 100);
    const c = year % 100;
    const d = Math.floor(b / 4);
    const e = b % 4;
    const f = Math.floor((b + 8) / 25);
    const g = Math.floor((b - f + 1) / 3);
    const h = (19 * a + b - d - g + 15) % 30;
    const i = Math.floor(c / 4);
    const k = c % 4;
    const l = (32 + 2 * e + 2 * i - h - k) % 7;
    const m = Math.floor((a + 11 * h + 22 * l) / 451);
    const month = Math.floor((h + l - 7 * m + 114) / 31);
    const day = ((h + l - 7 * m + 114) % 31) + 1;

    return new Date(year, month - 1, day);
}

/**
 * Get all South Africa public and bank holidays for a given year
 */
function getHolidays(year: number): Date[] {
    const holidays: Date[] = [];

    // Fixed date holidays
    holidays.push(new Date(year, 0, 1)); // New Year's Day
    holidays.push(new Date(year, 2, 21)); // Human Rights Day
    holidays.push(new Date(year, 3, 27)); // Freedom Day
    holidays.push(new Date(year, 4, 1)); // Workers' Day
    holidays.push(new Date(year, 5, 16)); // Youth Day
    holidays.push(new Date(year, 7, 9)); // National Women's Day
    holidays.push(new Date(year, 8, 24)); // Heritage Day
    holidays.push(new Date(year, 11, 16)); // Day of Reconciliation
    holidays.push(new Date(year, 11, 25)); // Christmas Day
    holidays.push(new Date(year, 11, 26)); // Day of Goodwill

    // Calculate Easter-based holidays
    const easter = calculateEaster(year);
    const goodFriday = new Date(easter);
    goodFriday.setDate(easter.getDate() - 2);
    holidays.push(goodFriday);

    const familyDay = new Date(easter);
    familyDay.setDate(easter.getDate() + 1);
    holidays.push(familyDay);

    return holidays;
}

/**
 * Check if a date is a weekend
 */
export function isWeekend(date: Date): boolean {
    const dayOfWeek = date.getDay(); // 0 = Sunday, 6 = Saturday
    return dayOfWeek === 0 || dayOfWeek === 6;
}

/**
 * Check if a date is a South Africa holiday
 */
export function isSouthAfricaHoliday(date: Date): boolean {
    const year = date.getFullYear();
    const holidays = getHolidays(year);

    return holidays.some(holiday => {
        return (
            holiday.getFullYear() === date.getFullYear() &&
            holiday.getMonth() === date.getMonth() &&
            holiday.getDate() === date.getDate()
        );
    });
}

/**
 * Check if a date is a valid business day (not weekend and not holiday)
 */
export function isBusinessDay(date: Date): boolean {
    return !isWeekend(date) && !isSouthAfricaHoliday(date);
}

/**
 * Get the next business day (not weekend, not holiday)
 */
export function getNextBusinessDay(date: Date): Date {
    const nextDay = new Date(date);
    nextDay.setDate(nextDay.getDate() + 1);

    while (!isBusinessDay(nextDay)) {
        nextDay.setDate(nextDay.getDate() + 1);
    }

    return nextDay;
}

/**
 * Get the name of a holiday if the date is a holiday
 */
export function getHolidayName(date: Date): string | null {
    if (!isSouthAfricaHoliday(date)) {
        return null;
    }

    const month = date.getMonth() + 1;
    const day = date.getDate();

    const holidayNames: Record<string, string> = {
        '1-1': "New Year's Day",
        '3-21': "Human Rights Day",
        '4-27': "Freedom Day",
        '5-1': "Workers' Day",
        '6-16': "Youth Day",
        '8-9': "National Women's Day",
        '9-24': "Heritage Day",
        '12-16': "Day of Reconciliation",
        '12-25': "Christmas Day",
        '12-26': "Day of Goodwill",
    };

    const key = `${month}-${day}`;
    if (holidayNames[key]) {
        return holidayNames[key];
    }

    // Check Easter-based holidays
    const year = date.getFullYear();
    const easter = calculateEaster(year);
    const goodFriday = new Date(easter);
    goodFriday.setDate(easter.getDate() - 2);
    const familyDay = new Date(easter);
    familyDay.setDate(easter.getDate() + 1);

    if (
        date.getFullYear() === goodFriday.getFullYear() &&
        date.getMonth() === goodFriday.getMonth() &&
        date.getDate() === goodFriday.getDate()
    ) {
        return "Good Friday";
    }

    if (
        date.getFullYear() === familyDay.getFullYear() &&
        date.getMonth() === familyDay.getMonth() &&
        date.getDate() === familyDay.getDate()
    ) {
        return "Family Day";
    }

    return "Public Holiday";
}
