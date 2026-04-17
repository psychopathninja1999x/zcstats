<?php

namespace App\Services;

use DateTimeInterface;

class LiveDigestService
{
    public function __construct(
        protected OpenWeatherService $openWeather,
        protected ZcwdWaterService $zcwdWater,
        protected ZamcelcoPowerService $zamcelcoPower,
        protected GasmotoFuelService $gasmotoFuel,
        protected PrayerTimesService $prayerTimes,
        protected DaPriceMonitoringService $daPrices,
        protected DtiBnpcSrpService $dtiBnpcSrp,
        protected EarthquakeUsgsService $earthquakeUsgs,
        protected TyphoonGdacsService $typhoonGdacs,
        protected PhilippineHolidaysService $philippineHolidays
    ) {}

    public function hash(): string
    {
        return hash('sha256', json_encode($this->components(), JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, mixed>
     */
    public function components(): array
    {
        $ts = static function ($d): ?int {
            if ($d instanceof DateTimeInterface) {
                return $d->getTimestamp();
            }

            return null;
        };

        $weather = $this->openWeather->getDashboardData();
        $zcwd = $this->zcwdWater->getReservoirData();
        $zamcelco = $this->zamcelcoPower->getDashboardData();
        $fuel = $this->gasmotoFuel->getDashboardData();
        $prayer = $this->prayerTimes->getDashboardData();
        $da = $this->daPrices->getDashboardData();
        $dti = $this->dtiBnpcSrp->getBulletinData();
        $earthquakes = $this->earthquakeUsgs->getDashboardData();
        $typhoons = $this->typhoonGdacs->getDashboardData();
        $holidays = $this->philippineHolidays->getDashboardData();

        $eqSig = null;
        if (is_array($earthquakes) && isset($earthquakes['events']) && is_array($earthquakes['events'])) {
            $eqSig = array_map(static function (array $e) use ($ts): array {
                return [
                    $ts($e['at'] ?? null),
                    $e['mag'] ?? null,
                    (string) ($e['url'] ?? ''),
                ];
            }, array_slice($earthquakes['events'], 0, 10));
        }

        $tySig = null;
        if (is_array($typhoons) && isset($typhoons['storms']) && is_array($typhoons['storms'])) {
            $tySig = array_map(static function (array $s) use ($ts): array {
                return [
                    (string) ($s['eventname'] ?? $s['name'] ?? ''),
                    (string) ($s['alertlevel'] ?? ''),
                    $ts($s['datemodified'] ?? null),
                ];
            }, array_slice($typhoons['storms'], 0, 8));
        }

        $holidaySig = [];
        if (isset($holidays['today']) && $holidays['today'] instanceof DateTimeInterface) {
            $holidaySig[] = $holidays['today']->format('Y-m-d');
        }
        if (isset($holidays['upcoming']) && is_array($holidays['upcoming'])) {
            foreach (array_slice($holidays['upcoming'], 0, 6) as $row) {
                if (is_array($row) && isset($row['date'], $row['name']) && $row['date'] instanceof DateTimeInterface) {
                    $holidaySig[] = (string) $row['name'].$row['date']->format('Y-m-d');
                }
            }
        }

        return [
            'weather' => $weather === null ? null : [
                $weather['temp'] ?? null,
                $weather['feels_like'] ?? null,
                $weather['aqi'] ?? null,
                (string) ($weather['description'] ?? ''),
                $ts($weather['updated_at'] ?? null),
            ],
            'zcwd' => $zcwd === null ? null : [
                (string) ($zcwd['as_of'] ?? ''),
                $zcwd['current_m'] ?? null,
                $zcwd['turbidity_ntu'] ?? null,
            ],
            'zamcelco' => $zamcelco === null ? null : hash('sha256', json_encode($zamcelco['rates'] ?? [], JSON_UNESCAPED_UNICODE)),
            'fuel' => $fuel === null ? null : [
                $ts($fuel['updated_at'] ?? null),
                $fuel['station_count'] ?? null,
                hash('sha256', json_encode($fuel['doe_rows'] ?? [], JSON_UNESCAPED_UNICODE)),
            ],
            'prayer' => $prayer === null ? null : [
                (string) ($prayer['date_readable'] ?? ''),
                $ts($prayer['fetched_at'] ?? null),
            ],
            'da' => $da === null ? null : [
                (string) (($da['daily'][0]['url'] ?? '') ?: ''),
                (string) (($da['daily'][0]['date'] ?? '') ?: ''),
                (string) (($da['weekly'][0]['url'] ?? '') ?: ''),
                count($da['daily'] ?? []),
                $ts($da['updated_at'] ?? null),
            ],
            'dti' => $dti === null ? null : [
                (string) ($dti['effective_period'] ?? ''),
                count($dti['items'] ?? []),
            ],
            'earthquakes' => $eqSig,
            'typhoons' => $tySig,
            'holidays' => $holidaySig,
        ];
    }
}
