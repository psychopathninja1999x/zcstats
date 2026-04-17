<?php

namespace App\Http\Controllers;

use App\Services\DaPriceMonitoringService;
use App\Services\DtiBnpcSrpService;
use App\Services\GasmotoFuelService;
use App\Services\OpenWeatherService;
use App\Services\PhilippineHolidaysService;
use App\Services\PrayerTimesService;
use App\Services\ZamcelcoPowerService;
use App\Services\ZcwdWaterService;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected OpenWeatherService $openWeather,
        protected ZcwdWaterService $zcwdWater,
        protected ZamcelcoPowerService $zamcelcoPower,
        protected GasmotoFuelService $gasmotoFuel,
        protected DaPriceMonitoringService $daPrices,
        protected DtiBnpcSrpService $dtiBnpcSrp,
        protected PrayerTimesService $prayerTimes,
        protected PhilippineHolidaysService $philippineHolidays
    ) {}

    public function index(): View
    {
        return view('dashboard.index', [
            'weather' => $this->openWeather->getDashboardData(),
            'zcwd' => $this->zcwdWater->getReservoirData(),
            'zamcelco' => $this->zamcelcoPower->getDashboardData(),
            'fuel' => $this->gasmotoFuel->getDashboardData(),
            'prayer_times' => $this->prayerTimes->getDashboardData(),
            'da_prices' => $this->daPrices->getDashboardData(),
            'dti_bnpc' => $this->dtiBnpcSrp->getBulletinData(),
            'search_index' => $this->searchIndex(),
            'philippine_holidays' => $this->philippineHolidays->getDashboardData(),
        ]);
    }

    /**
     * Curated terms per section (current locale + common English aliases) for header search.
     *
     * @return list<array{id: string, terms: list<string>}>
     */
    private function searchIndex(): array
    {
        $norm = static function (array $parts): array {
            $out = [];
            foreach ($parts as $s) {
                if (! is_string($s)) {
                    continue;
                }
                $t = mb_strtolower(trim($s));
                if ($t !== '') {
                    $out[] = $t;
                }
            }

            return array_values(array_unique($out));
        };

        $index = [
            [
                'id' => 'overview',
                'terms' => $norm(array_merge(
                    [
                        __('zcstats.breadcrumb_dashboard'),
                        __('zcstats.breadcrumb_live'),
                        __('zcstats.app_title'),
                        __('zcstats.export_data'),
                        __('zcstats.ph_calendar_heading'),
                        __('zcstats.ph_holidays_heading'),
                    ],
                    ['dashboard', 'overview', 'export', 'download', 'json', 'status', 'live', 'zcstats', 'everything', 'zamboanga', 'todo', 'gaylingo', 'glg', 'beki', 'swardspeak', 'lodi', 'language', 'locale', 'holiday', 'holidays', 'philippines', 'proclamation', 'pista', 'regular holiday']
                )),
            ],
            [
                'id' => 'weather',
                'terms' => $norm(array_merge(
                    [
                        __('zcstats.live_weather'),
                        __('zcstats.condition'),
                        __('zcstats.air_quality'),
                        __('zcstats.feels_like'),
                        __('zcstats.humidity'),
                    ],
                    ['weather', 'temperature', 'openweather', 'humidity', 'aqi', 'climate', 'forecast', 'rain', 'wind', 'heat', 'panahon', 'bagyo']
                )),
            ],
            [
                'id' => 'electricity',
                'terms' => $norm(array_merge(
                    [
                        __('zcstats.zamcelco_title'),
                        __('zcstats.billing_month'),
                    ],
                    ['electricity', 'electric', 'power', 'zamcelco', 'kwh', 'bill', 'rate', 'kuryente', 'energy', 'utility']
                )),
            ],
            [
                'id' => 'water',
                'terms' => $norm(array_merge(
                    [
                        __('zcstats.zcwd_title'),
                        __('zcstats.zcwd_subtitle'),
                    ],
                    ['water', 'zcwd', 'reservoir', 'dam', 'turbidity', 'tubig', 'agua', 'lubog', 'district']
                )),
            ],
            [
                'id' => 'fuel',
                'terms' => $norm(array_merge(
                    [
                        __('zcstats.fuel_title'),
                        __('zcstats.fuel_subtitle'),
                        __('zcstats.brand'),
                    ],
                    ['fuel', 'gas', 'gasoline', 'diesel', 'gasmoto', 'petrol', 'station', 'doe', 'kerosene', 'gasolina', 'presyo']
                )),
            ],
            [
                'id' => 'prices',
                'terms' => $norm(array_merge(
                    [
                        __('zcstats.da_prices_title'),
                        __('zcstats.da_prices_subtitle'),
                        __('zcstats.dti_bnpc_title'),
                        __('zcstats.dti_bnpc_subtitle'),
                    ],
                    ['price', 'prices', 'agriculture', 'da', 'dti', 'trade', 'industry', 'bnpc', 'srp', 'suggested retail', 'basic necessities', 'prime commodities', 'food', 'rice', 'vegetables', 'meat', 'fish', 'commodity', 'presyo', 'pagkain', 'bigas', 'gulay', 'department of agriculture', 'price monitoring', 'bantay presyo']
                )),
            ],
            [
                'id' => 'emergency',
                'terms' => $norm(array_merge(
                    [
                        __('zcstats.emergency_title'),
                        __('zcstats.universal_emergency'),
                        __('zcstats.cdrrmo'),
                        __('zcstats.rescue_ems'),
                        __('zcstats.pnp'),
                        __('zcstats.bfp'),
                    ],
                    ['emergency', '911', 'hotline', 'police', 'fire', 'ambulance', 'rescue', 'drrm', 'saklolo', 'sunog']
                )),
            ],
            [
                'id' => 'hospitals',
                'terms' => $norm(array_merge(
                    [
                        __('zcstats.hospitals_title'),
                        __('zcstats.hospital_zcmc'),
                        __('zcstats.hospital_zrmc'),
                        __('zcstats.hospital_ciudad'),
                        __('zcstats.hospital_westmetro'),
                        __('zcstats.hospital_brent'),
                    ],
                    ['hospital', 'clinic', 'medical', 'zcmc', 'zrmc', 'mcsgh', 'west metro', 'ciudad', 'brent', 'doctor', 'er', 'health', 'ospital']
                )),
            ],
        ];

        if (config('services.prayer_times.enabled', true)) {
            array_splice($index, 5, 0, [[
                'id' => 'prayer',
                'terms' => $norm(array_merge(
                    [
                        __('zcstats.prayer_title'),
                    ],
                    ['prayer', 'salah', 'salat', 'fajr', 'dhuhr', 'zuhr', 'asr', 'maghrib', 'isha', 'islam', 'muslim', 'muslimpro', 'aladhan']
                )),
            ]]);
        }

        return $index;
    }

    public function export(): JsonResponse
    {
        $snapshot = [
            'app' => config('app.name'),
            'exported_at' => now(),
            'locale' => app()->getLocale(),
            'weather' => $this->openWeather->getDashboardData(),
            'zcwd' => $this->zcwdWater->getReservoirData(),
            'zamcelco' => $this->zamcelcoPower->getDashboardData(),
            'fuel' => $this->gasmotoFuel->getDashboardData(),
            'prayer_times' => $this->prayerTimes->getDashboardData(),
            'da_prices' => $this->daPrices->getDashboardData(),
            'dti_bnpc' => $this->dtiBnpcSrp->getBulletinData(),
        ];

        $filename = 'zcstats-snapshot-'.now()->format('Y-m-d-His').'.json';

        return response()->json(
            $this->normalizeForJsonExport($snapshot),
            200,
            [
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    private function normalizeForJsonExport($value)
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->normalizeForJsonExport($v);
            }

            return $out;
        }

        return $value;
    }
}
