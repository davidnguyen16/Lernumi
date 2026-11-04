/** Convert a calendar wall-clock value to an instant in its configured time zone. */
export function calendarInstant(day: string, minutes: number, timeZone: string): string {
  const [year, month, date] = day.split('-').map(Number);
  const desired = Date.UTC(year, month - 1, date, 0, minutes);
  const formatter = new Intl.DateTimeFormat('en-GB', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });
  const wallClock = (instant: number) => {
    const parts = formatter.formatToParts(new Date(instant));
    const value = (key: string) => Number(parts.find(part => part.type === key)?.value ?? 0);
    return Date.UTC(value('year'), value('month') - 1, value('day'), value('hour'), value('minute'));
  };
  let instant = desired;
  for (let iteration = 0; iteration < 4; iteration++) instant += desired - wallClock(instant);
  if (wallClock(instant) !== desired) throw new Error('This local time does not exist because the clock changes. Choose another time.');
  return new Date(instant).toISOString();
}
