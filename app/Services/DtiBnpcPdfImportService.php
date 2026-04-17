<?php

namespace App\Services;

use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;

class DtiBnpcPdfImportService
{
    private const UNIT_RE = "/^(?:\d+(?:\.\d+)?\s*(?:g|kg|mL|ml|L)|\d+mL|#[\d\w.\/]+|AA|D)$/iu";

    public function extractItemsFromPdf(string $absolutePath): array
    {
        if (! is_readable($absolutePath)) {
            throw new \InvalidArgumentException("PDF not readable: {$absolutePath}");
        }
        $parser = new Parser;
        $document = $parser->parseFile($absolutePath);
        $text = $document->getText();
        $cut = stripos($text, 'NOTES:');
        if ($cut !== false) {
            $text = substr($text, 0, $cut);
        }
        preg_match_all("/\t([^\t\n]+?)\t([^\t\n]+)\t(\d+\.\d{2})\b/u", $text, $matches, PREG_OFFSET_CAPTURE);
        $out = [];
        $seen = [];
        for ($i = 0; $i < count($matches[0]); $i++) {
            $product = trim($matches[1][$i][0]);
            $unit = trim($matches[2][$i][0]);
            $srp = (float) $matches[3][$i][0];
            $offset = (int) $matches[0][$i][1];
            if ($product === '' || ! preg_match(self::UNIT_RE, $unit)) {
                continue;
            }
            if (preg_match("/^\d+\.\d{2}$/", $product)) {
                continue;
            }
            $nk = $this->normalizedItemKey($product, $unit, $srp);
            if (isset($seen[$nk])) {
                continue;
            }
            $seen[$nk] = true;
            $category = $this->guessCategoryBeforeOffset($text, $offset);
            $category = $this->sanitizeCategory($category, $product, $unit);
            if ($category === '') {
                continue;
            }
            $kind = $this->inferKind($category, $product);
            $out[] = ['category' => $category, 'product' => $product, 'unit' => $unit, 'srp' => $srp, 'kind' => $kind];
        }

        return $this->sortItems($out);
    }

    public function mergeIntoPayload(array $extracted, ?array $existingPayload): array
    {
        $base = is_array($existingPayload) ? $existingPayload : [];
        $existingItems = isset($base['items']) && is_array($base['items']) ? $base['items'] : [];
        $byKey = [];
        foreach ($existingItems as $row) {
            if (! is_array($row) || ! isset($row['product'], $row['unit'], $row['srp'])) {
                continue;
            }
            $k = $this->normalizedItemKey((string) $row['product'], (string) $row['unit'], (float) $row['srp']);
            $byKey[$k] = $row;
        }
        foreach ($extracted as $row) {
            $k = $this->normalizedItemKey($row['product'], $row['unit'], $row['srp']);
            if (! isset($byKey[$k])) {
                $byKey[$k] = [
                    'category' => $row['category'],
                    'product' => $row['product'],
                    'unit' => $row['unit'],
                    'srp' => $row['srp'],
                    'kind' => $row['kind'],
                ];
            }
        }
        $merged = array_values($byKey);
        usort($merged, function ($a, $b) {
            $ca = (string) ($a['category'] ?? '');
            $cb = (string) ($b['category'] ?? '');
            if ($ca !== $cb) {
                return strcmp($ca, $cb);
            }

            return strcmp((string) ($a['product'] ?? ''), (string) ($b['product'] ?? ''));
        });
        $base['items'] = $merged;

        return $base;
    }

    private function guessCategoryBeforeOffset(string $text, int $offset): string
    {
        $before = substr($text, 0, $offset);
        $tokens = preg_split("/[\n\t]+/", $before) ?: [];
        for ($i = count($tokens) - 1; $i >= 0; $i--) {
            $t = trim($tokens[$i]);
            if ($this->looksLikeCategoryHeader($t)) {
                return $t;
            }
        }

        return 'BNPC';
    }

    private function looksLikeCategoryHeader(string $t): bool
    {
        if (strlen($t) < 4 || strlen($t) > 140) {
            return false;
        }
        if (preg_match(self::UNIT_RE, $t) || preg_match("/^\d+\.\d{2}$/", $t)) {
            return false;
        }
        $letters = preg_replace('/[^A-Za-z]/', '', $t) ?? '';
        if ($letters === '') {
            return false;
        }
        $upper = preg_replace('/[^A-Z]/', '', $t) ?? '';

        return strlen($upper) >= strlen($letters) * 0.72;
    }

    private function inferKind(string $category, string $product): string
    {
        $blob = Str::upper($category.' '.$product);
        $prime = ['CORNED BEEF', 'BEEF LOAF', 'MEAT LOAF', 'LUNCHEON', 'VINEGAR', 'PATIS', 'SOY SAUCE', 'TOILET SOAP', 'BATTERY', 'EVEREADY', 'ENERGIZER'];
        foreach ($prime as $p) {
            if (str_contains($blob, $p)) {
                return 'prime';
            }
        }

        return 'basic';
    }

    private function sortItems(array $items): array
    {
        usort($items, function ($a, $b) {
            $c = strcmp($a['category'], $b['category']);

            return $c !== 0 ? $c : strcmp($a['product'], $b['product']);
        });

        return $items;
    }

    private function normalizedItemKey(string $product, string $unit, float $srp): string
    {
        $p = Str::lower(preg_replace("/\s+/u", ' ', trim($product)) ?? '');
        $u = Str::lower(preg_replace("/\s+/u", '', trim($unit)) ?? '');
        $s = number_format($srp, 2, '.', '');

        return "{$p}|{$u}|{$s}";
    }

    private function sanitizeCategory(string $category, string $product, string $unit): string
    {
        $catU = Str::upper($category);
        $prodL = Str::lower($product);
        if (str_contains($catU, 'CORNED') && str_contains($prodL, 'sardine')) {
            return 'Canned sardines in tomato sauce';
        }
        if (str_contains($catU, 'BATTERIES') && preg_match('/mL|ml|L$/i', $unit)) {
            return '';
        }

        return $category;
    }
}
