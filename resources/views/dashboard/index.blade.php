@extends('layouts.civic')

@section('title', __('zcstats.app_title'))

@section('body')
    <div class="flex flex-col flex-1 min-w-0 w-full">
        <header class="sticky top-0 w-full z-40 bg-[#f8f9fe]/85 backdrop-blur-xl border-b border-outline-variant/15">
            <div class="flex justify-between items-center px-6 h-16 w-full max-w-screen-2xl mx-auto">
                <div class="flex items-center gap-4 flex-1 min-w-0">
                    <a href="#overview" class="flex items-center gap-2 shrink-0 rounded-xl hover:bg-surface-container-high/60 transition-colors -ml-1 px-1 py-0.5" title="{{ __('zcstats.app_title') }}">
                        <img src="{{ asset('images/zcstatslogo.png') }}" alt="{{ __('zcstats.app_title') }}" width="160" height="48" decoding="async" class="h-9 sm:h-10 w-auto max-h-10 object-contain object-left">
                    </a>
                    <div class="relative flex-1 min-w-0 max-w-[13rem] sm:max-w-md">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-xl pointer-events-none z-[1]">search</span>
                        <input id="zc-dashboard-search" class="w-full bg-surface-container rounded-full py-2 pl-10 pr-4 border-none shadow-[inset_0_0_0_1px_rgba(193,199,209,0.15)] focus:ring-2 focus:ring-primary/20 focus:bg-white text-sm" placeholder="{{ __('zcstats.search_placeholder') }}" type="search" autocomplete="off" data-min-length="2" aria-describedby="zc-search-hint" enterkeyhint="search">
                        <p id="zc-search-hint" class="sr-only">{{ __('zcstats.search_hint') }}</p>
                        <div id="zc-search-feedback" class="hidden absolute left-0 right-0 top-full mt-2 z-50 rounded-2xl border border-outline-variant/20 bg-surface-container-lowest p-4 text-xs text-on-surface shadow-[0_12px_40px_rgba(25,28,32,0.12)]" role="status" aria-live="polite" aria-hidden="true">
                            <p class="font-semibold text-on-surface">{{ __('zcstats.search_no_match') }}</p>
                            <p class="mt-2 text-on-surface-variant leading-relaxed">{{ __('zcstats.search_suggestion') }} <a href="mailto:mvitem5@gmail.com" class="font-bold text-primary underline decoration-primary/40 hover:decoration-primary">mvitem5@gmail.com</a></p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                    <div class="flex items-center gap-1 rounded-full border border-outline-variant/25 bg-surface-container-high/60 px-2 sm:px-2.5 py-1 shadow-[inset_0_0_0_1px_rgba(193,199,209,0.08)]" title="{{ __('zcstats.header_clock_timezone') }}">
                        <span class="material-symbols-outlined text-on-surface-variant text-base sm:text-lg shrink-0" aria-hidden="true">schedule</span>
                        <time id="zc-header-clock" class="text-[10px] sm:text-xs font-bold tabular-nums text-on-surface whitespace-nowrap" datetime="" aria-label="{{ __('zcstats.header_clock_timezone') }}"></time>
                    </div>
                    <div class="inline-flex items-center rounded-full border border-outline-variant/25 bg-surface-container-high/60 p-0.5 shadow-[inset_0_0_0_1px_rgba(193,199,209,0.08)]" role="group" aria-label="{{ __('zcstats.language') }}">
                        <a href="{{ route('locale.switch', 'en') }}" class="px-2 sm:px-2.5 py-1 rounded-full text-[10px] sm:text-xs font-bold transition-colors {{ app()->getLocale() === 'en' ? 'bg-primary text-white shadow-sm' : 'text-on-surface-variant hover:bg-surface-container-high' }}">{{ __('zcstats.lang_en') }}</a>
                        <a href="{{ route('locale.switch', 'tl') }}" class="px-2 sm:px-2.5 py-1 rounded-full text-[10px] sm:text-xs font-bold transition-colors {{ app()->getLocale() === 'tl' ? 'bg-primary text-white shadow-sm' : 'text-on-surface-variant hover:bg-surface-container-high' }}">{{ __('zcstats.lang_tl') }}</a>
                        <a href="{{ route('locale.switch', 'cbk') }}" title="{{ __('zcstats.lang_cbk_title') }}" class="px-2 sm:px-2.5 py-1 rounded-full text-[10px] sm:text-xs font-bold transition-colors {{ app()->getLocale() === 'cbk' ? 'bg-primary text-white shadow-sm' : 'text-on-surface-variant hover:bg-surface-container-high' }}">{{ __('zcstats.lang_cbk') }}</a>
                    </div>
                </div>
            </div>
        </header>

      

        <main id="overview" class="flex-1 px-6 py-8 max-w-screen-2xl mx-auto w-full">
            <section class="mb-8">
                <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                    <div>
                        <nav class="flex items-center gap-2 text-xs font-bold text-secondary uppercase tracking-widest mb-3" aria-label="Breadcrumb">
                            <span>{{ __('zcstats.breadcrumb_dashboard') }}</span>
                            <span class="material-symbols-outlined text-[10px]">chevron_right</span>
                            <span>{{ __('zcstats.breadcrumb_live') }}</span>
                        </nav>
                        @php
                            $greetHour = (int) now()->timezone(config('app.timezone'))->format('G');
                            if ($greetHour >= 5 && $greetHour < 12) {
                                $timeGreeting = __('zcstats.greeting_morning');
                            } elseif ($greetHour >= 12 && $greetHour < 18) {
                                $timeGreeting = __('zcstats.greeting_afternoon');
                            } else {
                                $timeGreeting = __('zcstats.greeting_evening');
                            }
                        @endphp
                        <h1 class="text-3xl md:text-5xl font-extrabold tracking-tight text-primary leading-tight font-display">{{ $timeGreeting }}</h1>
                    
                        <div class="flex flex-wrap items-center gap-4 mt-3 text-on-surface-variant">
                            <div class="flex items-center gap-1.5 bg-surface-container-high/50 px-3 py-1 rounded-full">
                                <span class="material-symbols-outlined text-sm">schedule</span>
                                <span class="text-xs font-medium">{{ __('zcstats.last_updated') }} @if($weather){{ $weather['updated_at']->format('M j, g:i A') }}@else{{ __('zcstats.weather_unavailable') }}@endif</span>
                            </div>
                            <div class="flex items-center gap-2 text-on-surface-variant">
                                <div class="w-2 h-2 rounded-full @if($weather) bg-green-600 @else bg-outline-variant @endif" aria-hidden="true"></div>
                                <span class="font-bold text-xs uppercase">{{ __('zcstats.overall_status') }} @if($weather){{ __('zcstats.status_live_weather') }}@else{{ __('zcstats.status_pending') }}@endif</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <div id="weather" class="md:col-span-8 min-h-[380px] md:h-[420px] rounded-3xl overflow-hidden relative group shadow-[0_8px_48px_rgba(25,28,32,0.06)] scroll-mt-24">
                    <div class="absolute inset-0 hero-placeholder-bg"></div>
                    <div class="absolute inset-0 bg-gradient-to-t from-primary/95 via-primary/25 to-transparent flex flex-col justify-end p-8 md:p-10">
                        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                            <div>
                                <div class="inline-flex items-center gap-2 bg-white/15 backdrop-blur-md px-3 py-1 rounded-full text-white text-xs font-bold uppercase mb-4 border border-white/10">
                                    <span class="material-symbols-outlined text-sm">thermostat</span>
                                    {{ __('zcstats.live_weather') }}
                                </div>
                                <h2 class="text-white text-3xl md:text-4xl font-extrabold mb-2">@if($weather){{ $weather['location'] }}@if($weather['country']), {{ $weather['country'] }}@endif @else{{ __('zcstats.station_tbd') }}@endif</h2>
                                <p class="text-white/85 font-medium max-w-md text-sm md:text-base">@if($weather){{ __('zcstats.weather_ok') }}@else{{ __('zcstats.weather_placeholder') }}@endif</p>
                            </div>
                            <div class="text-right text-white">
                                <span class="text-5xl md:text-6xl font-black">@if($weather && $weather['temp'] !== null){{ $weather['temp'] }}°C @else —°C @endif</span>
                                <p class="text-sm font-bold opacity-90 uppercase tracking-widest mt-1">{{ __('zcstats.condition') }} @if($weather && $weather['description'] !== ''){{ $weather['description'] }} @else — @endif</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="electricity" class="md:col-span-4 bg-surface-container-lowest rounded-3xl p-8 shadow-[0_8px_32px_rgba(25,28,32,0.04)] flex flex-col scroll-mt-24">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h3 class="text-lg font-bold text-on-surface mb-1">{{ __('zcstats.zamcelco_title') }}</h3>
                            @if($zamcelco && ! empty($zamcelco['current_month']))
                                <p class="text-on-surface-variant/80 text-[11px] mt-1 flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">calendar_month</span>
                                    {{ __('zcstats.billing_month') }} {{ $zamcelco['current_month'] }}
                                </p>
                            @endif
                        </div>
                        <div class="bg-yellow-50 p-4 rounded-2xl">
                            <span class="material-symbols-outlined text-yellow-600 text-3xl" style="font-variation-settings: 'FILL' 1">bolt</span>
                        </div>
                    </div>
                    <div class="mb-6 flex-1 space-y-4">
                        @if($zamcelco && $zamcelco['residential'] && $zamcelco['residential']['current_total'] !== null)
                            <div>
                                <div class="flex items-center gap-2 text-on-surface-variant mb-2">
                                    <span class="material-symbols-outlined text-sm">home</span>
                                    <span class="text-xs font-bold uppercase tracking-wide">{{ $zamcelco['residential']['description'] }}</span>
                                    @if($zamcelco['residential']['change'] !== null)
                                        @php $ch = $zamcelco['residential']['change']; @endphp
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $ch < 0 ? 'bg-green-100 text-green-800' : ($ch > 0 ? 'bg-red-100 text-red-800' : 'bg-surface-container-high text-on-surface-variant') }}">
                                            {{ $ch < 0 ? '' : ($ch > 0 ? '+' : '') }}{{ number_format($ch, 4) }} {{ __('zcstats.vs_prior') }}
                                        </span>
                                    @endif
                                </div>
                                <div class="text-4xl sm:text-5xl font-extrabold tracking-tighter text-on-surface">
                                    <span class="text-lg font-bold text-on-surface-variant mr-0.5">₱</span>{{ number_format($zamcelco['residential']['current_total'], 4) }}<span class="text-base font-medium text-on-surface-variant ml-1">/kWh</span>
                                </div>
                                @if($zamcelco['residential']['previous_total'] !== null && ! empty($zamcelco['previous_month']))
                                    <p class="text-[11px] text-on-surface-variant mt-1">{{ __('zcstats.prior_kwh', ['month' => $zamcelco['previous_month'], 'price' => number_format($zamcelco['residential']['previous_total'], 4)]) }}</p>
                                @endif
                            </div>
                        @elseif($zamcelco && ! empty($zamcelco['rates']))
                            @php $first = $zamcelco['rates'][0]; @endphp
                            <div class="text-4xl font-extrabold tracking-tighter text-on-surface">
                                <span class="text-lg font-bold text-on-surface-variant mr-0.5">₱</span>{{ number_format($first['current_total'] ?? 0, 4) }}<span class="text-base font-medium text-on-surface-variant ml-1">/kWh</span>
                                <span class="block text-xs font-semibold text-on-surface-variant mt-1">{{ $first['description'] ?? '' }}</span>
                            </div>
                        @else
                            <p class="text-sm text-on-surface-variant">{{ __('zcstats.power_unavailable') }}</p>
                        @endif

                        @if($zamcelco && ! empty($zamcelco['rates']) && count($zamcelco['rates']) > 1)
                            <div class="space-y-2 pt-2 border-t border-outline-variant/15">
                                @foreach($zamcelco['rates'] as $row)
                                    @continue($row['code'] === 'R')
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-on-surface-variant font-medium">{{ $row['description'] }}</span>
                                        <span class="font-bold text-on-surface">₱{{ number_format($row['current_total'] ?? 0, 4) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div id="water" class="md:col-span-6 bg-surface-container-lowest rounded-3xl px-8 pt-8 pb-36 md:pb-40 min-h-[20rem] shadow-[0_8px_32px_rgba(25,28,32,0.04)] relative overflow-hidden isolate scroll-mt-24">
                    <div class="zcwd-water-fx" aria-hidden="true"></div>
                    <div class="zcwd-water-waves" aria-hidden="true">
                        <div class="zcwd-water-wave zcwd-water-wave-a"></div>
                        <div class="zcwd-water-wave zcwd-water-wave-b"></div>
                        <div class="zcwd-water-wave zcwd-water-wave-c"></div>
                    </div>
                    <div class="absolute right-0 top-0 w-32 h-32 bg-primary/5 rounded-bl-full -mr-10 -mt-10 z-[1]" aria-hidden="true"></div>
                    <div class="relative z-10">
                        <div class="flex justify-between items-start mb-8">
                            <div>
                                <h3 class="text-xl font-bold text-on-surface mb-1">{{ __('zcstats.zcwd_title') }}</h3>
                                <p class="text-on-surface-variant text-sm font-medium">{{ __('zcstats.zcwd_subtitle') }}</p>
                                @if($zcwd && $zcwd['as_of'])
                                    <p class="text-on-surface-variant/80 text-xs mt-1">{{ __('zcstats.as_of') }} {{ $zcwd['as_of'] }} · <a href="https://zcwd.gov.ph/production_new_bak.php" class="text-primary hover:underline font-semibold" target="_blank" rel="noopener noreferrer">zcwd.gov.ph</a></p>
                                @endif
                            </div>
                            <div class="bg-blue-50 p-4 rounded-2xl">
                                <span class="material-symbols-outlined text-primary text-3xl" style="font-variation-settings: 'FILL' 1">water_drop</span>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-8 mb-8">
                            <div>
                                <span class="text-5xl md:text-6xl font-extrabold tracking-tighter text-primary">@if($zcwd){{ number_format($zcwd['current_m'], 2) }}m @else —.——m @endif</span>
                                <div class="mt-2 inline-flex px-4 py-1.5 bg-surface-container-high text-on-surface-variant rounded-full text-xs font-bold uppercase tracking-wider">
                                    {{ __('zcstats.status') }} @if($zcwd){{ $zcwd['status'] }} @else — @endif
                                </div>
                            </div>
                            <div class="flex-1 space-y-4 min-w-0">
                                <div class="flex justify-between text-xs font-bold text-on-surface-variant uppercase tracking-widest">
                                    <span>{{ __('zcstats.vs_normal') }}</span>
                                    <span>@if($zcwd && $zcwd['capacity_percent'] !== null){{ $zcwd['capacity_percent'] }}% @else —% @endif</span>
                                </div>
                                <div
                                    class="h-4 w-full bg-surface-container-highest rounded-full overflow-hidden relative z-[1]"
                                    role="progressbar"
                                    aria-valuenow="{{ $zcwd && $zcwd['capacity_percent'] !== null ? min(100, $zcwd['capacity_percent']) : 0 }}"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    aria-label="{{ __('zcstats.reservoir_aria') }}"
                                >
                                    <div
                                        class="h-full bg-gradient-to-r from-secondary-fixed-dim to-secondary rounded-full transition-[width] duration-700 ease-out"
                                        style="width: {{ $zcwd && $zcwd['capacity_percent'] !== null ? min(100, $zcwd['capacity_percent']) : 0 }}%"
                                    ></div>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 bg-surface-container-low rounded-2xl text-center">
                                <p class="text-[10px] font-bold text-on-surface-variant uppercase mb-1">{{ __('zcstats.previous_reading') }}</p>
                                <p class="text-lg font-bold text-secondary">@if($zcwd && $zcwd['previous_m'] !== null){{ number_format($zcwd['previous_m'], 2) }}m @else — @endif</p>
                                @if($zcwd && $zcwd['previous_when'])
                                    <p class="text-[10px] text-on-surface-variant mt-1 leading-tight">{{ $zcwd['previous_when'] }}</p>
                                @endif
                            </div>
                            <div class="p-4 bg-surface-container-low rounded-2xl text-center">
                                <p class="text-[10px] font-bold text-on-surface-variant uppercase mb-1">{{ __('zcstats.normal_ref') }}</p>
                                <p class="text-lg font-bold">@if($zcwd && $zcwd['normal_m'] !== null){{ number_format($zcwd['normal_m'], 1) }}m @else —.——m @endif</p>
                            </div>
                        </div>
                        @if($zcwd && $zcwd['turbidity_line'])
                            <p class="mt-4 text-xs font-medium text-on-surface-variant leading-relaxed bg-surface-container-low/90 p-3 rounded-2xl border border-outline-variant/10 relative z-[1]">
                                {{ $zcwd['turbidity_line'] }}
                            </p>
                        @endif
                    </div>
                </div>

                <div class="md:col-span-6 grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div class="bg-surface-container-lowest rounded-3xl p-6 flex flex-col justify-between shadow-[0_8px_32px_rgba(25,28,32,0.04)]">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-12 h-12 rounded-2xl bg-tertiary-fixed flex items-center justify-center">
                                <span class="material-symbols-outlined text-on-tertiary-fixed text-2xl">air</span>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-on-surface-variant uppercase tracking-tighter">{{ __('zcstats.air_quality') }}</p>
                                <p class="text-xl font-extrabold text-on-surface">@if($weather && $weather['aqi'] !== null){{ $weather['aqi'] }}/5 @else{{ __('zcstats.aqi_na') }}@endif</p>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="h-2 w-full bg-surface-container-highest rounded-full overflow-hidden">
                                <div class="h-full bg-secondary rounded-full opacity-50" style="width: {{ $weather && $weather['aqi'] !== null ? min(100, $weather['aqi'] * 20) : 0 }}%"></div>
                            </div>
                            <p class="text-xs font-bold text-on-surface-variant uppercase tracking-widest">@if($weather && $weather['aqi_label']){{ $weather['aqi_label'] }}@else{{ __('zcstats.label_prefix') }} —@endif</p>
                        </div>
                    </div>
                    <div class="bg-surface-container-lowest rounded-3xl p-6 flex flex-col justify-between shadow-[0_8px_32px_rgba(25,28,32,0.04)]">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-12 h-12 rounded-2xl bg-secondary-fixed flex items-center justify-center">
                                <span class="material-symbols-outlined text-on-secondary-container text-2xl">thermostat</span>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-on-surface-variant uppercase tracking-tighter">{{ __('zcstats.feels_like') }}</p>
                                <p class="text-xl font-extrabold text-on-surface">@if($weather && $weather['feels_like'] !== null){{ $weather['feels_like'] }}°C @else —°C @endif</p>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="h-2 w-full bg-surface-container-highest rounded-full overflow-hidden">
                                <div class="h-full bg-orange-400/70 rounded-full" style="width: {{ $weather && $weather['humidity'] !== null ? min(100, $weather['humidity']) : 0 }}%"></div>
                            </div>
                            <p class="text-xs font-bold text-orange-600/90 uppercase tracking-widest">{{ __('zcstats.humidity') }} @if($weather && $weather['humidity'] !== null){{ $weather['humidity'] }}%@else —%@endif</p>
                        </div>
                    </div>
                    <div id="fuel" class="sm:col-span-2 bg-surface-container-lowest rounded-3xl p-6 shadow-[0_8px_32px_rgba(25,28,32,0.04)] border border-outline-variant/15 scroll-mt-24">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-4">
                            <div class="flex items-start gap-4 min-w-0">
                                <div class="w-12 h-12 rounded-2xl bg-amber-50 flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-amber-800 text-2xl" style="font-variation-settings: 'FILL' 1">local_gas_station</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-on-surface-variant uppercase tracking-tighter">{{ __('zcstats.fuel_title') }}</p>
                                    <p class="text-lg font-extrabold text-on-surface leading-tight">{{ __('zcstats.fuel_subtitle') }}</p>
                                    @if($fuel !== null)
                                        <p class="text-xs text-on-surface-variant mt-1 font-medium">{{ $fuel['region_label'] }}</p>
                                        @if($fuel['disclaimer'] !== '')
                                            <p class="text-[11px] text-on-surface-variant/90 mt-2 leading-relaxed">{{ $fuel['disclaimer'] }}</p>
                                        @endif
                                    @endif
                                </div>
                            </div>
                            @if($fuel !== null)
                                <div class="flex flex-col items-end gap-1 shrink-0 text-right">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-50 text-green-800 uppercase tracking-wide">{{ __('zcstats.live_data') }}</span>
                                    <span class="text-[10px] text-on-surface-variant">{{ $fuel['updated_at']->format('M j, g:i A') }}</span>
                                    <span class="text-[10px] text-on-surface-variant">{{ __('zcstats.stations_in_view', ['count' => number_format($fuel['station_count'])]) }}</span>
                                </div>
                            @endif
                        </div>
                        @if($fuel === null)
                            <p class="text-sm text-on-surface-variant">{{ __('zcstats.fuel_unavailable') }}</p>
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                                <div class="p-4 bg-surface-container-low rounded-2xl">
                                    <p class="text-[10px] font-bold text-on-surface-variant uppercase mb-1">{{ __('zcstats.lowest_diesel_doe') }}</p>
                                    @if($fuel['cheapest_diesel'] !== null)
                                        <p class="text-2xl font-extrabold text-on-surface">₱{{ number_format($fuel['cheapest_diesel']['price'], 2) }}<span class="text-xs font-semibold text-on-surface-variant ml-1">/L</span></p>
                                        <p class="text-xs font-medium text-on-surface-variant mt-1 truncate" title="{{ $fuel['cheapest_diesel']['station'] }}">{{ $fuel['cheapest_diesel']['brand'] }} · {{ $fuel['cheapest_diesel']['fuel_label'] }}</p>
                                    @else
                                        <p class="text-sm text-on-surface-variant">{{ __('zcstats.no_diesel_doe') }}</p>
                                    @endif
                                </div>
                                <div class="p-4 bg-surface-container-low rounded-2xl">
                                    <p class="text-[10px] font-bold text-on-surface-variant uppercase mb-1">{{ __('zcstats.lowest_gasoline') }}</p>
                                    @if($fuel['cheapest_gasoline'] !== null)
                                        <p class="text-2xl font-extrabold text-on-surface">₱{{ number_format($fuel['cheapest_gasoline']['price'], 2) }}<span class="text-xs font-semibold text-on-surface-variant ml-1">/L</span></p>
                                        <p class="text-xs font-medium text-on-surface-variant mt-1 truncate" title="{{ $fuel['cheapest_gasoline']['station'] }}">{{ $fuel['cheapest_gasoline']['fuel_label'] }} · {{ $fuel['cheapest_gasoline']['brand'] }}</p>
                                    @else
                                        <p class="text-sm text-on-surface-variant">{{ __('zcstats.no_gasoline') }}</p>
                                    @endif
                                </div>
                            </div>
                            @if($fuel['doe_rows'] !== [])
                                <p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider mb-2">{{ __('zcstats.city_rates_header') }}</p>
                                <div class="overflow-x-auto rounded-xl border border-outline-variant/10">
                                    <table class="w-full text-left text-xs min-w-[52rem]">
                                        <thead>
                                            <tr class="bg-surface-container-high/80 text-on-surface-variant font-bold uppercase tracking-wider">
                                                <th class="px-3 py-2 sticky left-0 bg-surface-container-high/95 z-[1] shadow-[1px_0_0_rgba(193,199,209,0.2)]">{{ __('zcstats.brand') }}</th>
                                                @foreach($fuel['doe_columns'] as $col)
                                                    <th class="px-2 py-2 text-right whitespace-nowrap">{{ $col['label'] }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-outline-variant/10">
                                            @foreach($fuel['doe_rows'] as $row)
                                                <tr class="hover:bg-surface-container-high/40">
                                                    <td class="px-3 py-2 font-medium text-on-surface sticky left-0 bg-surface-container-lowest z-[1] shadow-[1px_0_0_rgba(193,199,209,0.15)]">{{ $row['brand'] }}</td>
                                                    @foreach($fuel['doe_columns'] as $col)
                                                        @php $cell = $row['cells'][$col['key']] ?? null; @endphp
                                                        <td class="px-2 py-2 text-right whitespace-nowrap" @if($cell !== null) title="{{ $cell['product'] }}" @endif>
                                                            @if($cell !== null)
                                                                <span class="font-bold text-on-surface">₱{{ number_format($cell['price'], 2) }}</span>
                                                            @else
                                                                <span class="text-on-surface-variant font-normal">—</span>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                            <a href="{{ $fuel['source_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex items-center gap-2 text-xs font-bold text-primary hover:underline">{{ __('zcstats.open_gasmoto') }}<span class="material-symbols-outlined text-sm">open_in_new</span></a>
                        @endif
                    </div>
                </div>

                <div class="md:col-span-12 grid grid-cols-1 md:grid-cols-2 gap-6 min-w-0">
                    <section id="emergency" class="min-w-0 bg-surface-container-lowest rounded-3xl p-6 md:p-8 shadow-[0_8px_32px_rgba(25,28,32,0.04)] border border-red-200/40 scroll-mt-24 h-full flex flex-col" aria-labelledby="emergency-hotlines-heading">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
                        <div class="flex items-start gap-4 min-w-0">
                            <div class="w-12 h-12 rounded-2xl bg-red-50 flex items-center justify-center shrink-0 ring-1 ring-red-100">
                                <span class="material-symbols-outlined text-red-700 text-2xl" style="font-variation-settings: 'FILL' 1">emergency</span>
                            </div>
                            <div>
                                <h2 id="emergency-hotlines-heading" class="text-xl md:text-2xl font-extrabold text-on-surface leading-tight">{{ __('zcstats.emergency_title') }}</h2>
                                <p class="text-xs text-on-surface-variant font-medium mt-1 uppercase tracking-wide">{{ __('zcstats.emergency_subtitle', ['code' => __('zcstats.area_code')]) }}</p>
                            </div>
                        </div>
                        <a href="tel:911" class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl bg-red-600 text-white text-sm font-extrabold shadow-md hover:bg-red-700 transition-colors shrink-0">
                            <span class="material-symbols-outlined text-xl">call</span>
                            {{ __('zcstats.dial_911') }}
                        </a>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 flex-1">
                        <div class="p-4 rounded-2xl bg-surface-container-low border border-outline-variant/10">
                            <h3 class="text-xs font-bold text-red-800 uppercase tracking-wider mb-3">{{ __('zcstats.universal_emergency') }}</h3>
                            <p class="text-sm text-on-surface-variant mb-2">{{ __('zcstats.universal_emergency_desc') }}</p>
                            <a href="tel:911" class="text-lg font-extrabold text-primary hover:underline">911</a>
                        </div>
                        <div class="p-4 rounded-2xl bg-surface-container-low border border-outline-variant/10">
                            <h3 class="text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-3">{{ __('zcstats.cdrrmo') }}</h3>
                            <ul class="space-y-2 text-sm font-medium text-on-surface">
                                <li><a href="tel:+63629901171" class="hover:text-primary hover:underline">(062) 990-1171</a></li>
                                <li><a href="tel:+63629261848" class="hover:text-primary hover:underline">(062) 926-1848</a></li>
                                <li><a href="tel:+63629559601" class="hover:text-primary hover:underline">(062) 955-9601</a></li>
                                <li class="pt-1 text-on-surface-variant text-xs font-normal">{{ __('zcstats.mobile') }} <a href="tel:+639177113536" class="font-medium text-on-surface hover:text-primary hover:underline">+63 917 711 3536</a> · <a href="tel:+639176560891" class="font-medium text-on-surface hover:text-primary hover:underline">+63 917 656 0891</a></li>
                            </ul>
                        </div>
                        <div class="p-4 rounded-2xl bg-surface-container-low border border-outline-variant/10">
                            <h3 class="text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-3">{{ __('zcstats.rescue_ems') }}</h3>
                            <p class="text-sm text-on-surface mb-1"><a href="tel:+63629261849" class="font-bold hover:text-primary hover:underline">(062) 926-1849</a></p>
                            <p class="text-xs text-on-surface-variant">{{ __('zcstats.rescue_ems_desc') }}</p>
                        </div>
                        <div class="p-4 rounded-2xl bg-surface-container-low border border-outline-variant/10">
                            <h3 class="text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-3">{{ __('zcstats.pnp') }}</h3>
                            <ul class="space-y-2 text-sm font-medium text-on-surface">
                                <li><a href="tel:+63629924385" class="hover:text-primary hover:underline">(062) 992-4385</a></li>
                                <li><a href="tel:+63629922300" class="hover:text-primary hover:underline">(062) 992-2300</a></li>
                                <li><a href="tel:+63629913000" class="hover:text-primary hover:underline">(062) 991-3000</a></li>
                            </ul>
                        </div>
                        <div class="p-4 rounded-2xl bg-surface-container-low border border-outline-variant/10">
                            <h3 class="text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-3">{{ __('zcstats.bfp') }}</h3>
                            <ul class="space-y-2 text-sm font-medium text-on-surface">
                                <li><a href="tel:+63629912267" class="hover:text-primary hover:underline">(062) 991-2267</a></li>
                                <li><a href="tel:+63629915320" class="hover:text-primary hover:underline">(062) 991-5320</a></li>
                            </ul>
                        </div>
                        <div class="p-4 rounded-2xl bg-surface-container-low border border-outline-variant/10 sm:col-span-2">
                            <h3 class="text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-3">{{ __('zcstats.official_ref') }}</h3>
                            <p class="text-xs text-on-surface-variant leading-relaxed">{{ __('zcstats.official_ref_desc') }}</p>
                            <a href="https://www.zamboangacity.gov.ph/citydisasterriskreduction/operations-and-warning-services/" class="inline-flex items-center gap-1 mt-3 text-xs font-bold text-primary hover:underline" target="_blank" rel="noopener noreferrer">{{ __('zcstats.cdrrmo_link') }}<span class="material-symbols-outlined text-sm">open_in_new</span></a>
                        </div>
                    </div>
                </section>

                <section id="hospitals" class="min-w-0 bg-surface-container-lowest rounded-3xl p-6 md:p-8 shadow-[0_8px_32px_rgba(25,28,32,0.04)] border border-primary/15 scroll-mt-24 h-full flex flex-col" aria-labelledby="hospitals-heading">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
                        <div class="flex items-start gap-4 min-w-0">
                            <div class="w-12 h-12 rounded-2xl bg-primary/10 flex items-center justify-center shrink-0 ring-1 ring-primary/15">
                                <span class="material-symbols-outlined text-primary text-2xl" style="font-variation-settings: 'FILL' 1">local_hospital</span>
                            </div>
                            <div>
                                <h2 id="hospitals-heading" class="text-xl md:text-2xl font-extrabold text-on-surface leading-tight">{{ __('zcstats.hospitals_title') }}</h2>
                                <p class="text-xs text-on-surface-variant font-medium mt-1 uppercase tracking-wide">{{ __('zcstats.hospitals_subtitle', ['code' => __('zcstats.area_code')]) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 flex-1">
                        <div class="p-4 rounded-2xl bg-surface-container-low border border-outline-variant/10">
                            <h3 class="text-xs font-bold text-primary uppercase tracking-wider mb-3">{{ __('zcstats.hospital_zcmc') }}</h3>
                            <ul class="space-y-2 text-sm font-medium text-on-surface">
                                <li><span class="text-on-surface-variant text-xs font-semibold uppercase block mb-0.5">{{ __('zcstats.hospital_main_line') }}</span><a href="tel:+63629912934" class="hover:text-primary hover:underline">(062) 991-2934</a></li>
                                <li><span class="text-on-surface-variant text-xs font-semibold uppercase block mb-0.5">{{ __('zcstats.hospital_mobile') }}</span><a href="tel:+639155365583" class="hover:text-primary hover:underline">0915 536 5583</a></li>
                                <li class="text-xs text-on-surface-variant pt-1"><span class="font-semibold text-on-surface uppercase tracking-wide">{{ __('zcstats.hospital_chat') }}</span><br><a href="tel:+639772016640" class="font-medium text-on-surface hover:text-primary hover:underline">0977 201 6640</a> · <a href="tel:+639397845513" class="font-medium text-on-surface hover:text-primary hover:underline">0939 784 5513</a></li>
                            </ul>
                            <a href="https://zcmc.doh.gov.ph/" class="inline-flex items-center gap-1 mt-3 text-xs font-bold text-primary hover:underline" target="_blank" rel="noopener noreferrer">zcmc.doh.gov.ph<span class="material-symbols-outlined text-sm">open_in_new</span></a>
                        </div>
                        <div class="p-4 rounded-2xl bg-surface-container-low border border-outline-variant/10">
                            <h3 class="text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-3">{{ __('zcstats.hospital_zrmc') }}</h3>
                            <ul class="space-y-2 text-sm font-medium text-on-surface">
                                <li><a href="tel:+63629571494" class="hover:text-primary hover:underline">(062) 957-1494</a></li>
                                <li><a href="tel:+63629759533" class="hover:text-primary hover:underline">(062) 975-9533</a></li>
                            </ul>
                            <a href="https://mcs.doh.gov.ph/contact-us/" class="inline-flex items-center gap-1 mt-3 text-xs font-bold text-primary hover:underline" target="_blank" rel="noopener noreferrer">mcs.doh.gov.ph<span class="material-symbols-outlined text-sm">open_in_new</span></a>
                        </div>
                        <div class="p-4 rounded-2xl bg-surface-container-low border border-outline-variant/10">
                            <h3 class="text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-3">{{ __('zcstats.hospital_ciudad') }}</h3>
                            <p class="text-xs text-on-surface-variant mb-1 font-semibold uppercase">{{ __('zcstats.hospital_customer_care') }}</p>
                            <p class="text-sm font-medium text-on-surface"><a href="tel:+639178546329" class="hover:text-primary hover:underline">(0917) 854-6329</a></p>
                            <a href="https://ciudadmedicalzamboanga.com.ph/contact-us/" class="inline-flex items-center gap-1 mt-3 text-xs font-bold text-primary hover:underline" target="_blank" rel="noopener noreferrer">ciudadmedicalzamboanga.com.ph<span class="material-symbols-outlined text-sm">open_in_new</span></a>
                        </div>
                        <div class="p-4 rounded-2xl bg-surface-container-low border border-outline-variant/10">
                            <h3 class="text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-3">{{ __('zcstats.hospital_westmetro') }}</h3>
                            <ul class="space-y-2 text-sm font-medium text-on-surface">
                                <li><span class="text-on-surface-variant text-xs font-semibold uppercase block mb-0.5">{{ __('zcstats.hospital_main_line') }}</span><a href="tel:+63629912506" class="hover:text-primary hover:underline">(062) 991-2506</a></li>
                                <li><span class="text-on-surface-variant text-xs font-semibold uppercase block mb-0.5">{{ __('zcstats.hospital_emergency_line') }}</span><a href="tel:+639989783820" class="hover:text-primary hover:underline">0998 978 3820</a></li>
                            </ul>
                            <a href="https://new.westmetro.com.ph/connect-with-us/" class="inline-flex items-center gap-1 mt-3 text-xs font-bold text-primary hover:underline" target="_blank" rel="noopener noreferrer">westmetro.com.ph<span class="material-symbols-outlined text-sm">open_in_new</span></a>
                        </div>
                        <div class="p-4 rounded-2xl bg-surface-container-low border border-outline-variant/10 sm:col-span-2">
                            <h3 class="text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-3">{{ __('zcstats.hospital_brent') }}</h3>
                            <ul class="space-y-2 text-sm font-medium text-on-surface">
                                <li><a href="tel:+63629901963" class="hover:text-primary hover:underline">(062) 990-1963</a></li>
                                <li><a href="tel:+63629925996" class="hover:text-primary hover:underline">(062) 992-5996</a></li>
                            </ul>
                            <a href="https://healthspace.ph/facility/brent-hospital-and-colleges-inc-FCD01417/details" class="inline-flex items-center gap-1 mt-3 text-xs font-bold text-primary hover:underline" target="_blank" rel="noopener noreferrer">{{ __('zcstats.hospital_brent_ref') }}<span class="material-symbols-outlined text-sm">open_in_new</span></a>
                        </div>
                        <div class="p-4 rounded-2xl bg-surface-container-low border border-outline-variant/10 sm:col-span-2">
                            <p class="text-xs text-on-surface-variant leading-relaxed">{{ __('zcstats.hospitals_note') }}</p>
                        </div>
                    </div>
                </section>
                </div>
            </div>
        </main>

    </div>

    {{-- Floating dock (replaces sidebar + full-width mobile bar) --}}
    <nav class="fixed bottom-[max(1rem,env(safe-area-inset-bottom))] left-0 right-0 z-50 px-4 sm:px-6 pointer-events-none" aria-label="{{ __('zcstats.nav_primary') }}">
        <div class="pointer-events-auto mx-auto max-w-3xl flex items-stretch gap-2 rounded-[1.35rem] border border-outline-variant/15 bg-white/95 backdrop-blur-xl shadow-[0_12px_48px_rgba(25,28,32,0.14),0_4px_16px_rgba(25,28,32,0.06)] pl-1 pr-2 py-2 sm:pl-2 sm:pr-3">
            <div class="flex flex-1 min-w-0 items-center justify-between gap-0.5 sm:gap-1 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <a class="dock-nav-item flex flex-col items-center justify-center gap-0.5 rounded-xl px-2 sm:px-3 py-1.5 text-primary min-w-[3.25rem] shrink-0 active:scale-95 transition-transform" href="#overview">
                    <span class="material-symbols-outlined text-[22px] sm:text-2xl" style="font-variation-settings: 'FILL' 1">dashboard</span>
                    <span class="font-sans text-[9px] sm:text-[10px] font-bold leading-tight text-center">{{ __('zcstats.dock_status') }}</span>
                </a>
                <a class="dock-nav-item flex flex-col items-center justify-center gap-0.5 rounded-xl px-2 sm:px-3 py-1.5 text-on-surface-variant hover:text-primary hover:bg-surface-container-high/80 min-w-[3.25rem] shrink-0 active:scale-95 transition-all" href="#water">
                    <span class="material-symbols-outlined text-[22px] sm:text-2xl">water_drop</span>
                    <span class="font-sans text-[9px] sm:text-[10px] font-semibold leading-tight text-center">{{ __('zcstats.dock_water') }}</span>
                </a>
                <a class="dock-nav-item flex flex-col items-center justify-center gap-0.5 rounded-xl px-2 sm:px-3 py-1.5 text-on-surface-variant hover:text-primary hover:bg-surface-container-high/80 min-w-[3.25rem] shrink-0 active:scale-95 transition-all" href="#electricity">
                    <span class="material-symbols-outlined text-[22px] sm:text-2xl">bolt</span>
                    <span class="font-sans text-[9px] sm:text-[10px] font-semibold leading-tight text-center">{{ __('zcstats.dock_power') }}</span>
                </a>
                <a class="dock-nav-item flex flex-col items-center justify-center gap-0.5 rounded-xl px-2 sm:px-3 py-1.5 text-on-surface-variant hover:text-primary hover:bg-surface-container-high/80 min-w-[3.25rem] shrink-0 active:scale-95 transition-all" href="#fuel">
                    <span class="material-symbols-outlined text-[22px] sm:text-2xl">local_gas_station</span>
                    <span class="font-sans text-[9px] sm:text-[10px] font-semibold leading-tight text-center">{{ __('zcstats.dock_fuel') }}</span>
                </a>
                <a class="dock-nav-item flex flex-col items-center justify-center gap-0.5 rounded-xl px-2 sm:px-3 py-1.5 text-on-surface-variant hover:text-primary hover:bg-surface-container-high/80 min-w-[3.25rem] shrink-0 active:scale-95 transition-all" href="#emergency">
                    <span class="material-symbols-outlined text-[22px] sm:text-2xl">emergency</span>
                    <span class="font-sans text-[9px] sm:text-[10px] font-semibold leading-tight text-center">{{ __('zcstats.dock_911') }}</span>
                </a>
                <a class="dock-nav-item flex flex-col items-center justify-center gap-0.5 rounded-xl px-2 sm:px-3 py-1.5 text-on-surface-variant hover:text-primary hover:bg-surface-container-high/80 min-w-[3.25rem] shrink-0 active:scale-95 transition-all" href="#hospitals">
                    <span class="material-symbols-outlined text-[22px] sm:text-2xl">local_hospital</span>
                    <span class="font-sans text-[9px] sm:text-[10px] font-semibold leading-tight text-center">{{ __('zcstats.dock_hospitals') }}</span>
                </a>
            </div>
            <div class="hidden sm:flex w-px self-stretch bg-outline-variant/25 shrink-0" aria-hidden="true"></div>
            <button type="button" class="flex flex-col items-center justify-center gap-0.5 rounded-xl px-2.5 sm:px-3 py-1.5 bg-gradient-to-br from-primary to-primary-container text-white text-[9px] sm:text-[10px] font-bold shadow-[0_6px_20px_rgba(0,66,109,0.25)] hover:opacity-95 active:scale-95 transition-all shrink-0 min-w-[3.5rem] sm:min-w-[4.5rem]">
                <span class="material-symbols-outlined text-xl">flag</span>
                <span class="leading-tight">{{ __('zcstats.dock_report') }}</span>
            </button>
        </div>
    </nav>

    <script type="application/json" id="zc-search-index">@json($search_index ?? [])</script>
@endsection
