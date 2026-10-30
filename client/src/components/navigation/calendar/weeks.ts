import type { CalendarAssessment,CalendarPeriod,CalendarTerm } from './types';
export type DayKey = string;
export function dayKeyOf(value: string | Date, timeZone: string): DayKey {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date(value));
    const get = (type: string) => parts.find((part) => part.type === type)?.value ?? '';
    return `${get('year')}-${get('month')}-${get('day')}`;
}
export function localTimeOf(value: string, timeZone: string): {
    hour: number;
    minute: number;
} {
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone,
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(new Date(value));
    const get = (type: string) => Number(parts.find((part) => part.type === type)?.value ?? '0');
    return { hour: get('hour'), minute: get('minute') };
}
function toUtc(key: DayKey): Date {
    const [year, month, day] = key.split('-').map(Number);
    return new Date(Date.UTC(year, month - 1, day));
}
function fromUtc(date: Date): DayKey {
    return date.toISOString().slice(0, 10);
}
export function addDays(key: DayKey, days: number): DayKey {
    const date = toUtc(key);
    date.setUTCDate(date.getUTCDate() + days);
    return fromUtc(date);
}
export function diffDays(from: DayKey, to: DayKey): number {
    return Math.round((toUtc(to).getTime() - toUtc(from).getTime()) / 86400000);
}
export function mondayOf(key: DayKey): DayKey {
    const weekday = toUtc(key).getUTCDay();
    return addDays(key, weekday === 0 ? -6 : 1 - weekday);
}
export function weekDays(monday: DayKey): DayKey[] {
    return Array.from({ length: 7 }, (_, index) => addDays(monday, index));
}
export function formatDay(key: DayKey, options: Intl.DateTimeFormatOptions): string {
    return new Intl.DateTimeFormat('en-AU', { timeZone: 'UTC', ...options }).format(toUtc(key));
}
export type TermWeek = {
    start: DayKey;
    end: DayKey;
    type: CalendarPeriod['type'] | 'GAP';
    label: string;
    teachingWeek: number | null;
    periodName: string | null;
};
function periodContaining(periods: CalendarPeriod[], key: DayKey, timeZone: string) {
    return periods.find((period) => dayKeyOf(period.startAt, timeZone) <= key && key <= dayKeyOf(period.endAt, timeZone));
}
export function buildTermWeeks(term: CalendarTerm, timeZone: string): TermWeek[] {
    if (term.periods.length === 0)
        return [];
    const sorted = [...term.periods].sort((a, b) => a.startAt.localeCompare(b.startAt));
    const first = mondayOf(dayKeyOf(sorted[0].startAt, timeZone));
    const last = mondayOf(dayKeyOf(sorted[sorted.length - 1].endAt, timeZone));
    const weeks: TermWeek[] = [];
    let teaching = 0;
    for (let monday = first; monday <= last; monday = addDays(monday, 7)) {
        const period = periodContaining(sorted, addDays(monday, 2), timeZone) ??
            periodContaining(sorted, monday, timeZone);
        const type = period?.type ?? 'GAP';
        const teachingWeek = type === 'TEACHING' ? ++teaching : null;
        weeks.push({
            start: monday,
            end: addDays(monday, 6),
            type,
            teachingWeek,
            periodName: period?.name ?? null,
            label: type === 'TEACHING'
                ? `Wk ${teachingWeek}`
                : type === 'BREAK'
                    ? 'Recess'
                    : type === 'EXAM'
                        ? 'Exams'
                        : '—',
        });
    }
    return weeks;
}
export function weekIndexOf(assessment: CalendarAssessment, weeks: TermWeek[], timeZone: string): number | null {
    if (assessment.dueAt) {
        const key = dayKeyOf(assessment.dueAt, timeZone);
        const index = weeks.findIndex((week) => week.start <= key && key <= week.end);
        return index >= 0 ? index : null;
    }
    const match = assessment.expectedDue?.match(/\bweek\s*(\d{1,2})\b/i);
    if (match) {
        const index = weeks.findIndex((week) => week.teachingWeek === Number(match[1]));
        return index >= 0 ? index : null;
    }
    if (/\bexam/i.test(assessment.expectedDue ?? '')) {
        const index = weeks.findIndex((week) => week.type === 'EXAM');
        return index >= 0 ? index : null;
    }
    return null;
}
export function minutesOf(value: string, timeZone: string): number {
    const { hour, minute } = localTimeOf(value, timeZone);
    return hour * 60 + minute;
}
export function wallTimeToIso(day: DayKey, minutes: number, timeZone: string): string {
    const [year, month, date] = day.split('-').map(Number);
    let guess = Date.UTC(year, month - 1, date, Math.floor(minutes / 60), minutes % 60);
    for (let pass = 0; pass < 2; pass += 1) {
        const parts = new Intl.DateTimeFormat('en-CA', {
            timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        }).formatToParts(new Date(guess));
        const get = (type: string) => Number(parts.find((part) => part.type === type)?.value ?? '0');
        const wallAsUtc = Date.UTC(get('year'), get('month') - 1, get('day'), get('hour'), get('minute'));
        const target = Date.UTC(year, month - 1, date, Math.floor(minutes / 60), minutes % 60);
        guess -= wallAsUtc - target;
    }
    return new Date(guess).toISOString();
}
export function formatMinutes(minutes: number): string {
    const clamped = Math.max(0, Math.min(24 * 60, minutes));
    return `${String(Math.floor(clamped / 60)).padStart(2, '0')}:${String(clamped % 60).padStart(2, '0')}`;
}
