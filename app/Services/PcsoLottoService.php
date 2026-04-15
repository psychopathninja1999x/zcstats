<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PcsoLottoService
{
    private const BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36';

    /**
     * @return array{updated_at: Carbon, source_url: string, rows: array<int, array{game: string, combination: string, draw_date: string, jackpot: string, winners: string}>}|null
     */
    public function getDashboardData(): ?array
    {
        if (! config('services.pcso.enabled', true)) {
            return null;
        }

        $ttl = (int) config('services.pcso.cache_ttl', 3600);

        return Cache::remember('pcso_lotto_search_results', max(120, $ttl), function () {
            return $this->fetchRecentResults();
        });
    }

    /**
     * @return array{updated_at: Carbon, source_url: string, rows: array<int, array{game: string, combination: string, draw_date: string, jackpot: string, winners: string}>}|null
     */
    private function fetchRecentResults(): ?array
    {
        $pageUrl = (string) config('services.pcso.page_url', 'https://www.lottopcso.com/');
        $pcsoOfficial = (string) config('services.pcso.pcso_official_url', 'https://www.pcso.gov.ph/SearchLottoResult.aspx');
        $apiUrl = config('services.pcso.api_url');
        if (is_string($apiUrl) && $apiUrl !== '') {
            $fromApi = $this->fetchFromJsonApi($apiUrl, $pcsoOfficial);
            if ($fromApi !== null) {
                return $fromApi;
            }
        }

        if ($pageUrl === '') {
            return null;
        }

        try {
            $res = $this->httpClientForPage($pageUrl)->get($pageUrl);
            if (! $res->successful()) {
                Log::warning('PCSO lotto: results page GET failed', ['status' => $res->status(), 'url' => $pageUrl]);

                return null;
            }

            $rows = $this->parseLottopcsoHomepage($res->body());
            $maxSlides = max(1, min(40, (int) config('services.pcso.carousel_max', 15)));
            $rows = array_slice($rows, 0, $maxSlides);

            if ($rows === []) {
                return null;
            }

            return [
                'updated_at' => Carbon::now(),
                'source_url' => $pageUrl,
                'rows' => $rows,
            ];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * LottoPCSO (WordPress): first “Lotto Results Today” post uses TablePress-style two-column tables.
     *
     * @return array<int, array{game: string, combination: string, draw_date: string, jackpot: string, winners: string}>
     */
    private function parseLottopcsoHomepage(string $html): array
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        if (! $loaded) {
            return [];
        }

        $xpath = new \DOMXPath($dom);
        $tables = $xpath->query("//article[contains(@class,'post_box')][1]//table[contains(@class,'has-fixed-layout')]");
        if ($tables === false || $tables->length === 0) {
            $tables = $xpath->query("//table[contains(@class,'has-fixed-layout')]");
        }
        if ($tables === false) {
            return [];
        }

        $out = [];
        $seen = [];
        foreach ($tables as $table) {
            if (! $table instanceof \DOMElement) {
                continue;
            }
            $row = $this->parseLottopcsoTable($table);
            if ($row === null) {
                continue;
            }
            $key = mb_strtolower($row['game'], 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return array{game: string, combination: string, draw_date: string, jackpot: string, winners: string}|null
     */
    private function parseLottopcsoTable(\DOMElement $table): ?array
    {
        $thead = $table->getElementsByTagName('thead')->item(0);
        if (! $thead instanceof \DOMElement) {
            return null;
        }
        $headTr = $thead->getElementsByTagName('tr')->item(0);
        if (! $headTr instanceof \DOMElement) {
            return null;
        }
        $ths = $headTr->getElementsByTagName('th');
        if ($ths->length !== 2) {
            return null;
        }

        $h0 = $this->cellText($ths->item(0));
        $h1 = $this->cellText($ths->item(1));

        if ($h0 === '' || $this->isLottopcsoSkipHeader($h0)) {
            return null;
        }

        $tbody = $table->getElementsByTagName('tbody')->item(0);
        if (! $tbody instanceof \DOMElement) {
            return null;
        }

        $game = $h0;
        $drawDate = $h1;

        if ($this->tbodyHasWinningCombinationRow($tbody)) {
            return $this->parseLottopcsoMajorTable($tbody, $game, $drawDate);
        }

        return $this->parseLottopcsoFlatTable($tbody, $game, $drawDate);
    }

    private function isLottopcsoSkipHeader(string $h0): bool
    {
        $x = mb_strtolower($h0, 'UTF-8');

        return $x === 'prize amount'
            || $x === 'major lotto draw'
            || $x === 'date';
    }

    private function tbodyHasWinningCombinationRow(\DOMElement $tbody): bool
    {
        foreach ($tbody->getElementsByTagName('tr') as $tr) {
            if (! $tr instanceof \DOMElement) {
                continue;
            }
            $td = $tr->getElementsByTagName('td')->item(0);
            if ($td === null) {
                continue;
            }
            $k = mb_strtolower($this->cellText($td), 'UTF-8');
            if (str_contains($k, 'winning combination')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{game: string, combination: string, draw_date: string, jackpot: string, winners: string}|null
     */
    private function parseLottopcsoMajorTable(\DOMElement $tbody, string $game, string $drawDate): ?array
    {
        $combination = '';
        $jackpot = '';
        $winners = '';

        foreach ($tbody->getElementsByTagName('tr') as $tr) {
            if (! $tr instanceof \DOMElement) {
                continue;
            }
            $cells = $tr->getElementsByTagName('td');
            if ($cells->length < 2) {
                continue;
            }
            $k = $this->cellText($cells->item(0));
            $v = $this->cellText($cells->item(1));
            $kl = mb_strtolower($k, 'UTF-8');

            if (str_contains($kl, 'winning combination')) {
                $combination = $v;
            } elseif (str_contains($kl, 'jackpot prize') && ! str_contains($kl, 'winner')) {
                $jackpot = $v;
            } elseif (str_contains($kl, 'jackpot winner') && str_contains($kl, '6 out of 6')) {
                $winners = $v;
            }
        }

        if ($combination === '') {
            return null;
        }

        return [
            'game' => $game,
            'combination' => $combination,
            'draw_date' => $drawDate,
            'jackpot' => $jackpot,
            'winners' => $winners,
        ];
    }

    /**
     * @return array{game: string, combination: string, draw_date: string, jackpot: string, winners: string}|null
     */
    private function parseLottopcsoFlatTable(\DOMElement $tbody, string $game, string $drawDate): ?array
    {
        $segments = [];
        foreach ($tbody->getElementsByTagName('tr') as $tr) {
            if (! $tr instanceof \DOMElement) {
                continue;
            }
            $cells = $tr->getElementsByTagName('td');
            if ($cells->length < 2) {
                continue;
            }
            $k = $this->cellText($cells->item(0));
            $v = $this->cellText($cells->item(1));
            if ($k === '' && $v === '') {
                continue;
            }
            $segments[] = trim($k.' '.$v);
        }

        if ($segments === []) {
            return null;
        }

        return [
            'game' => $game,
            'combination' => implode('; ', $segments),
            'draw_date' => $drawDate,
            'jackpot' => '',
            'winners' => '',
        ];
    }

    private function cellText(?\DOMNode $node): string
    {
        if ($node === null) {
            return '';
        }
        $t = $node->textContent ?? '';
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace('/\s+/u', ' ', trim($t)) ?? '';
    }

    /**
     * GET JSON from a third-party or self-hosted PCSO-compatible feed.
     *
     * @return array{updated_at: Carbon, source_url: string, rows: array<int, array{game: string, combination: string, draw_date: string, jackpot: string, winners: string}>}|null
     */
    private function fetchFromJsonApi(string $apiUrl, string $officialPcsoUrl): ?array
    {
        try {
            $res = $this->httpClientForUrl($apiUrl)->get($apiUrl);
            if (! $res->successful()) {
                Log::warning('PCSO lotto: JSON API request failed', ['status' => $res->status(), 'url' => $apiUrl]);

                return null;
            }

            $json = $res->json();
            $list = $this->extractJsonResultList($json);
            if ($list === null) {
                Log::warning('PCSO lotto: JSON API response shape not recognized', ['url' => $apiUrl]);

                return null;
            }

            $rows = [];
            foreach ($list as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $row = $this->normalizeJsonRow($item);
                if ($row !== null) {
                    $rows[] = $row;
                }
            }

            $maxSlides = max(1, min(40, (int) config('services.pcso.carousel_max', 15)));
            $rows = array_slice($rows, 0, $maxSlides);

            if ($rows === []) {
                return null;
            }

            return [
                'updated_at' => Carbon::now(),
                'source_url' => $officialPcsoUrl !== '' ? $officialPcsoUrl : 'https://www.pcso.gov.ph/SearchLottoResult.aspx',
                'rows' => $rows,
            ];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @param  mixed  $json
     * @return array<int, mixed>|null
     */
    private function extractJsonResultList(mixed $json): ?array
    {
        if (is_array($json) && array_is_list($json)) {
            return $json;
        }
        if (! is_array($json)) {
            return null;
        }
        foreach (['data', 'results', 'items', 'records', 'draws'] as $key) {
            if (isset($json[$key]) && is_array($json[$key]) && array_is_list($json[$key])) {
                return $json[$key];
            }
        }
        if (isset($json['result']) && is_array($json['result']) && array_is_list($json['result'])) {
            return $json['result'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{game: string, combination: string, draw_date: string, jackpot: string, winners: string}|null
     */
    private function normalizeJsonRow(array $item): ?array
    {
        $game = $this->stringFromKeys($item, ['game', 'game_name', 'lotto_game', 'name', 'title', 'type', 'lottery']);
        $combination = $item['combination'] ?? $item['numbers'] ?? $item['result'] ?? $item['winning_numbers'] ?? $item['winningNumbers'] ?? null;
        if (is_array($combination)) {
            $combination = implode(' ', array_map(static fn ($v) => (string) $v, $combination));
        }
        $combination = is_string($combination) ? preg_replace('/\s+/u', ' ', trim($combination)) ?? '' : '';
        if ($game === '' || $combination === '' || str_starts_with($combination, '-')) {
            return null;
        }

        $drawDate = $this->stringFromKeys($item, ['draw_date', 'drawDate', 'date', 'draw', 'drawn_at']);
        $jackpot = $this->stringFromKeys($item, ['jackpot', 'prize', 'prize_pool', 'amount']);
        $winners = $this->stringFromKeys($item, ['winners', 'winner_count', 'winnerCount', 'no_of_winners']);

        return [
            'game' => $game,
            'combination' => $combination,
            'draw_date' => $drawDate,
            'jackpot' => $jackpot,
            'winners' => $winners,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $keys
     */
    private function stringFromKeys(array $item, array $keys): string
    {
        foreach ($keys as $k) {
            if (! array_key_exists($k, $item)) {
                continue;
            }
            $v = $item[$k];
            if ($v === null) {
                continue;
            }
            if (is_scalar($v) || $v instanceof \Stringable) {
                return preg_replace('/\s+/u', ' ', trim((string) $v)) ?? '';
            }
        }

        return '';
    }

    private function httpClientForPage(string $url): PendingRequest
    {
        $options = [];
        if (! config('services.pcso.verify_ssl', true)) {
            $options['verify'] = false;
        }

        $parsed = parse_url($url);
        $origin = '';
        if (is_array($parsed) && isset($parsed['scheme'], $parsed['host'])) {
            $origin = $parsed['scheme'].'://'.$parsed['host'];
        }

        return Http::timeout(35)
            ->withOptions($options)
            ->withHeaders([
                'User-Agent' => self::BROWSER_UA,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Origin' => $origin,
                'Referer' => $url,
            ]);
    }

    private function httpClientForUrl(string $url): PendingRequest
    {
        $options = [];
        if (! config('services.pcso.verify_ssl', true)) {
            $options['verify'] = false;
        }

        $parsed = parse_url($url);
        $origin = '';
        if (is_array($parsed) && isset($parsed['scheme'], $parsed['host'])) {
            $origin = $parsed['scheme'].'://'.$parsed['host'];
        }

        return Http::timeout(25)
            ->withOptions($options)
            ->withHeaders(array_filter([
                'User-Agent' => self::BROWSER_UA,
                'Accept' => 'application/json, text/plain, */*',
                'Origin' => $origin !== '' ? $origin : null,
                'Referer' => $url,
            ]));
    }
}
