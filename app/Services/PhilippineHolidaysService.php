<?php

namespace App\Services;

use Carbon\Carbon;

class PhilippineHolidaysService
{
    /**
     * @return array{
     *     today: Carbon,
     *     today_label: string,
     *     today_holidays: list<array{date: Carbon, name: string, kind: string}>,
     *     upcoming: list<array{date: Carbon, name: string, kind: string}>,
     *     official_for_year: bool,
     *     note_key: string|null
     * }
     */
    public function getDashboardData(): array
    {
        $tz = (string) config('app.timezone');
        $today = now()->timezone($tz)->startOfDay();
        $year = (int) $today->format('Y');

        $holidays = $this->holidaysForYear($year, $tz);
        $todayStr = $today->format('Y-m-d');

        $todayHolidays = [];
        foreach ($holidays as $row) {
            if ($row['date']->format('Y-m-d') === $todayStr) {
                $todayHolidays[] = $row;
            }
        }

        $upcoming = [];
        foreach ($holidays as $row) {
            if ($row['date']->gte($today)) {
                $upcoming[] = $row;
            }
        }

        $official = $this->hasOfficialYearList($year);

        return [
            'today' => $today,
            'today_label' => $this->formatTodayLabel($today),
            'today_holidays' => $todayHolidays,
            'upcoming' => array_slice($upcoming, 0, 10),
            'official_for_year' => $official,
            'note_key' => $official ? null : 'ph_holidays_note_fallback',
        ];
    }

    /**
     * @return list<array{date: Carbon, name: string, kind: string}>
     */
    private function holidaysForYear(int $year, string $tz): array
    {
        $raw = config("philippine_holidays.years.{$year}");
        if (is_array($raw) && $raw !== []) {
            return $this->normalizeConfigYear($raw, $tz);
        }

        return $this->fallbackHolidaysForYear($year, $tz);
    }

    private function hasOfficialYearList(int $year): bool
    {
        $raw = config("philippine_holidays.years.{$year}");

        return is_array($raw) && $raw !== [];
    }

    /**
     * @param  list<array{date: string, name: string, kind: string}>  $rows
     * @return list<array{date: Carbon, name: string, kind: string}>
     */
    private function normalizeConfigYear(array $rows, string $tz): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $dateStr = $row['date'] ?? null;
            $name = $row['name'] ?? '';
            $kind = $row['kind'] ?? 'special';
            if (! is_string($dateStr) || ! is_string($name) || $dateStr === '' || $name === '') {
                continue;
            }
            $out[] = [
                'date' => Carbon::parse($dateStr, $tz)->startOfDay(),
                'name' => $name,
                'kind' => $kind === 'regular' ? 'regular' : 'special',
            ];
        }
        usort($out, fn (array $a, array $b): int => $a['date']->lt($b['date']) ? -1 : ($a['date']->gt($b['date']) ? 1 : 0));

        return $out;
    }

    /**
     * Core movable + fixed dates when no proclamation list exists yet (approximate).
     *
     * @return list<array{date: Carbon, name: string, kind: string}>
     */
    private function fallbackHolidaysForYear(int $year, string $tz): array
    {
        if (! function_exists('easter_date')) {
            return [];
        }

        $easter = Carbon::createFromTimestamp(easter_date($year), $tz)->startOfDay();

        $heroes = Carbon::create($year, 8, 31, 0, 0, 0, $tz);
        while (! $heroes->isMonday()) {
            $heroes = $heroes->subDay();
        }

        $rows = [
            ['date' => Carbon::create($year, 1, 1, 0, 0, 0, $tz), 'name' => 'ph_holiday_new_year', 'kind' => 'regular'],
            ['date' => $easter->copy()->subDays(3), 'name' => 'ph_holiday_maundy_thursday', 'kind' => 'regular'],
            ['date' => $easter->copy()->subDays(2), 'name' => 'ph_holiday_good_friday', 'kind' => 'regular'],
            ['date' => $easter->copy()->subDay(), 'name' => 'ph_holiday_black_saturday', 'kind' => 'special'],
            ['date' => Carbon::create($year, 4, 9, 0, 0, 0, $tz), 'name' => 'ph_holiday_araw_ng_kagitingan', 'kind' => 'regular'],
            ['date' => Carbon::create($year, 5, 1, 0, 0, 0, $tz), 'name' => 'ph_holiday_labor_day', 'kind' => 'regular'],
            ['date' => Carbon::create($year, 6, 12, 0, 0, 0, $tz), 'name' => 'ph_holiday_independence_day', 'kind' => 'regular'],
            ['date' => Carbon::create($year, 8, 21, 0, 0, 0, $tz), 'name' => 'ph_holiday_ninoy_aquino_day', 'kind' => 'special'],
            ['date' => $heroes->copy(), 'name' => 'ph_holiday_national_heroes_day', 'kind' => 'regular'],
            ['date' => Carbon::create($year, 11, 1, 0, 0, 0, $tz), 'name' => 'ph_holiday_all_saints_day', 'kind' => 'special'],
            ['date' => Carbon::create($year, 11, 30, 0, 0, 0, $tz), 'name' => 'ph_holiday_bonifacio_day', 'kind' => 'regular'],
            ['date' => Carbon::create($year, 12, 8, 0, 0, 0, $tz), 'name' => 'ph_holiday_immaculate_conception', 'kind' => 'special'],
            ['date' => Carbon::create($year, 12, 25, 0, 0, 0, $tz), 'name' => 'ph_holiday_christmas_day', 'kind' => 'regular'],
            ['date' => Carbon::create($year, 12, 30, 0, 0, 0, $tz), 'name' => 'ph_holiday_rizal_day', 'kind' => 'regular'],
            ['date' => Carbon::create($year, 12, 31, 0, 0, 0, $tz), 'name' => 'ph_holiday_last_day_of_year', 'kind' => 'special'],
        ];

        usort($rows, fn (array $a, array $b): int => $a['date']->lt($b['date']) ? -1 : ($a['date']->gt($b['date']) ? 1 : 0));

        return $rows;
    }

    private function formatTodayLabel(Carbon $today): string
    {
        $locale = match (app()->getLocale()) {
            'tl' => 'fil',
            'cbk' => 'es',
            default => 'en',
        };

        /** @var Carbon $localized */
        $localized = $today->copy()->locale($locale);

        return $localized->isoFormat('dddd, D MMMM YYYY');
    }
}
