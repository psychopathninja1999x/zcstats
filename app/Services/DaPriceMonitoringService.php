<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches the Department of Agriculture price-monitoring page
 * (https://www.da.gov.ph/price-monitoring/) and extracts the latest
 * Weekly Average Prices and Daily Price Index PDF links.
 *
 * If the live scrape fails (403 / timeout / WAF), falls back to
 * deterministic URL generation based on the DA's predictable upload path.
 */
class DaPriceMonitoringService
{
    private const BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    private const DA_UPLOAD_BASE = 'https://www.da.gov.ph/wp-content/uploads';

    /**
     * @return array<string, mixed>|null
     */
    public function getDashboardData(): ?array
    {
        $ttl = (int) config('services.da_prices.cache_ttl', 3600);

        return Cache::remember('da_price_monitoring_v2', max(60, $ttl), function () {
            return $this->fetchData();
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchData(): ?array
    {
        $scraped = $this->tryScrape();

        if ($scraped !== null) {
            return $scraped;
        }

        return $this->generateFromKnownPattern();
    }

    /**
     * Primary strategy: scrape the live page.
     *
     * @return array<string, mixed>|null
     */
    private function tryScrape(): ?array
    {
        $url = (string) config('services.da_prices.url', 'https://www.da.gov.ph/price-monitoring/');

        if ($url === '') {
            return null;
        }

        try {
            $options = [];
            if (! config('services.da_prices.verify_ssl', true)) {
                $options['verify'] = false;
            }

            $response = Http::timeout(25)
                ->connectTimeout(10)
                ->withOptions($options)
                ->withHeaders([
                    'User-Agent' => self::BROWSER_UA,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Referer' => 'https://www.da.gov.ph/',
                    'DNT' => '1',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1',
                    'Sec-Fetch-Dest' => 'document',
                    'Sec-Fetch-Mode' => 'navigate',
                    'Sec-Fetch-Site' => 'same-origin',
                    'Sec-Fetch-User' => '?1',
                    'Cache-Control' => 'max-age=0',
                ])
                ->get($url);

            if (! $response->successful()) {
                Log::info('DA price monitoring scrape returned non-200', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $this->parseHtml($response->body());
        } catch (\Throwable $e) {
            Log::info('DA price monitoring scrape failed, will use fallback', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseHtml(string $html): ?array
    {
        $maxDaily = (int) config('services.da_prices.max_daily', 7);
        $maxWeekly = (int) config('services.da_prices.max_weekly', 4);

        $daily = $this->extractSection($html, 'Daily Price Index', $maxDaily);
        $weekly = $this->extractSection($html, 'Weekly Average Prices', $maxWeekly);

        if ($daily === [] && $weekly === []) {
            return null;
        }

        return [
            'daily' => $daily,
            'weekly' => $weekly,
            'source_url' => (string) config('services.da_prices.url', 'https://www.da.gov.ph/price-monitoring/'),
            'updated_at' => now(),
        ];
    }

    /**
     * @return list<array{date: string, url: string, size: string}>
     */
    private function extractSection(string $html, string $sectionName, int $limit): array
    {
        $results = [];

        $year = (int) date('Y');
        $marker = $year . ' ' . $sectionName;

        $pos = stripos($html, $marker);
        if ($pos === false) {
            $pos = stripos($html, $sectionName);
            if ($pos === false) {
                return [];
            }
        }

        $chunk = substr($html, $pos, 20000);

        if (! preg_match_all(
            '/<a\s[^>]*href\s*=\s*"([^"]+\.pdf)"[^>]*>([^<]+)<\/a>/i',
            $chunk,
            $matches,
            PREG_SET_ORDER
        )) {
            return [];
        }

        foreach ($matches as $m) {
            if (count($results) >= $limit) {
                break;
            }

            $pdfUrl = trim($m[1]);
            $dateLabel = trim(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            $size = $this->extractSizeAfterLink($chunk, $m[0]);

            $results[] = [
                'date' => $dateLabel,
                'url' => $pdfUrl,
                'size' => $size,
            ];
        }

        return $results;
    }

    private function extractSizeAfterLink(string $html, string $linkHtml): string
    {
        $pos = strpos($html, $linkHtml);
        if ($pos === false) {
            return '';
        }

        $after = substr($html, $pos + strlen($linkHtml), 500);

        if (preg_match('/<td[^>]*>\s*([\d.,]+\s*(?:KB|MB|GB))\s*<\/td>/i', $after, $m)) {
            return trim($m[1]);
        }

        return '';
    }

    /**
     * Fallback: build recent daily + weekly URLs from the DA's predictable
     * upload path pattern and verify them with lightweight HEAD requests.
     *
     * Daily pattern:  /uploads/{YYYY}/{MM}/Daily-Price-Index-{Month}-{D}-{YYYY}.pdf
     * Weekly pattern: /uploads/{YYYY}/{MM}/Weekly-Average-Prices-{Month}-{D1}-{D2}-{YYYY}.pdf
     *
     * @return array<string, mixed>|null
     */
    private function generateFromKnownPattern(): ?array
    {
        $maxDaily = (int) config('services.da_prices.max_daily', 7);

        $daily = $this->generateDailyUrls($maxDaily);
        $weekly = $this->generateWeeklyUrls();

        if ($daily === [] && $weekly === []) {
            return null;
        }

        return [
            'daily' => $daily,
            'weekly' => $weekly,
            'source_url' => (string) config('services.da_prices.url', 'https://www.da.gov.ph/price-monitoring/'),
            'updated_at' => now(),
        ];
    }

    /**
     * @return list<array{date: string, url: string, size: string}>
     */
    private function generateDailyUrls(int $count): array
    {
        $results = [];
        $today = Carbon::today(config('app.timezone', 'Asia/Manila'));
        $checked = 0;

        for ($i = 0; $i < $count + 10 && count($results) < $count && $checked < 20; $i++) {
            $date = $today->copy()->subDays($i);
            $checked++;

            $month = $date->format('F');
            $day = (int) $date->format('j');
            $year = $date->format('Y');
            $uploadMonth = $date->format('m');

            $filename = "Daily-Price-Index-{$month}-{$day}-{$year}.pdf";
            $url = self::DA_UPLOAD_BASE . "/{$year}/{$uploadMonth}/{$filename}";

            $dateLabel = $date->format('F j, Y');

            if ($this->urlExists($url)) {
                $results[] = [
                    'date' => $dateLabel,
                    'url' => $url,
                    'size' => '',
                ];
                continue;
            }

            $revisedFilename = "Revised-Daily-Price-Index-{$month}-{$day}-{$year}.pdf";
            $revisedUrl = self::DA_UPLOAD_BASE . "/{$year}/{$uploadMonth}/{$revisedFilename}";

            if ($this->urlExists($revisedUrl)) {
                $results[] = [
                    'date' => $dateLabel,
                    'url' => $revisedUrl,
                    'size' => '',
                ];
                continue;
            }

            $nextMonth = $date->copy()->addMonth();
            $crossMonthUrl = self::DA_UPLOAD_BASE . "/{$nextMonth->format('Y')}/{$nextMonth->format('m')}/{$filename}";
            if ($this->urlExists($crossMonthUrl)) {
                $results[] = [
                    'date' => $dateLabel,
                    'url' => $crossMonthUrl,
                    'size' => '',
                ];
            }
        }

        return $results;
    }

    /**
     * Generate the last few weekly average URLs.
     * Weekly reports cover Mon–Sat of each week, uploaded in the month folder
     * of the end date or the following month.
     *
     * @return list<array{date: string, url: string, size: string}>
     */
    private function generateWeeklyUrls(): array
    {
        $maxWeekly = (int) config('services.da_prices.max_weekly', 4);
        $results = [];
        $today = Carbon::today(config('app.timezone', 'Asia/Manila'));

        $saturdayThisWeek = $today->copy()->endOfWeek(Carbon::SATURDAY)->startOfDay();
        if ($saturdayThisWeek->gt($today)) {
            $saturdayThisWeek->subWeek();
        }

        for ($w = 0; $w < $maxWeekly + 4 && count($results) < $maxWeekly; $w++) {
            $end = $saturdayThisWeek->copy()->subWeeks($w);
            $start = $end->copy()->subDays(5);

            $dateLabel = $this->formatWeeklyDateLabel($start, $end);
            $url = $this->buildWeeklyUrl($start, $end);

            if ($url !== null) {
                $results[] = [
                    'date' => $dateLabel,
                    'url' => $url,
                    'size' => '',
                ];
            }
        }

        return $results;
    }

    private function formatWeeklyDateLabel(Carbon $start, Carbon $end): string
    {
        if ($start->month === $end->month && $start->year === $end->year) {
            return $start->format('F') . ' ' . $start->day . '-' . $end->day . ', ' . $end->format('Y');
        }

        if ($start->year === $end->year) {
            return $start->format('F') . ' ' . $start->day . '-' . $end->format('F') . ' ' . $end->day . ', ' . $end->format('Y');
        }

        return $start->format('F j, Y') . ' - ' . $end->format('F j, Y');
    }

    /**
     * Try common URL patterns for the weekly PDF.
     */
    private function buildWeeklyUrl(Carbon $start, Carbon $end): ?string
    {
        $startMonth = $start->format('F');
        $endMonth = $end->format('F');
        $year = $end->format('Y');
        $uploadMonth = $end->format('m');
        $uploadYear = $end->format('Y');

        if ($start->month === $end->month) {
            $filename = "Weekly-Average-Prices-{$startMonth}-{$start->day}-{$end->day}-{$year}.pdf";
        } else {
            $filename = "Weekly-Average-Prices-{$startMonth}-{$start->day}-{$endMonth}-{$end->day}-{$year}.pdf";
        }

        $url = self::DA_UPLOAD_BASE . "/{$uploadYear}/{$uploadMonth}/{$filename}";
        if ($this->urlExists($url)) {
            return $url;
        }

        $nextMonth = $end->copy()->addMonth();
        $crossUrl = self::DA_UPLOAD_BASE . "/{$nextMonth->format('Y')}/{$nextMonth->format('m')}/{$filename}";
        if ($this->urlExists($crossUrl)) {
            return $crossUrl;
        }

        return null;
    }

    /**
     * Lightweight HEAD check to see if a PDF URL exists on the DA server.
     */
    private function urlExists(string $url): bool
    {
        try {
            $options = [];
            if (! config('services.da_prices.verify_ssl', true)) {
                $options['verify'] = false;
            }

            $response = Http::timeout(8)
                ->connectTimeout(5)
                ->withOptions($options)
                ->withHeaders([
                    'User-Agent' => self::BROWSER_UA,
                    'Accept' => '*/*',
                    'Referer' => 'https://www.da.gov.ph/price-monitoring/',
                ])
                ->head($url);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
