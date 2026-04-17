<?php

namespace App\Console\Commands;

use App\Services\DtiBnpcPdfImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportDtiBnpcPdfCommand extends Command
{
    protected $signature = 'dti:import-bnpc-pdf {path? : Path to the DTI BNPC SRP bulletin PDF} {--dry-run : Show counts without writing} {--force : Write resources/data/dti_bnpc_srp.json}';

    protected $description = 'Extract BNPC SRP rows from a DTI bulletin PDF and merge into resources/data/dti_bnpc_srp.json';

    public function handle(DtiBnpcPdfImportService $import): int
    {
        $path = $this->argument('path');
        if ($path === null || $path === '') {
            $path = storage_path('app/dti-bnpc-bulletin.pdf');
        } elseif (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:\\\\/', $path)) {
            $path = base_path($path);
        }
        if (! is_readable($path)) {
            $this->error("PDF not found or not readable: {$path}");
            $this->line('Download the latest DTI bulletin, save as storage/app/dti-bnpc-bulletin.pdf, or pass the full path.');

            return self::FAILURE;
        }
        $this->info('Parsing PDF...');
        $extracted = $import->extractItemsFromPdf($path);
        $this->line('Extracted rows (best-effort): '.count($extracted));
        $jsonPath = resource_path('data/dti_bnpc_srp.json');
        $existing = is_readable($jsonPath) ? json_decode(File::get($jsonPath), true) : null;
        $merged = $import->mergeIntoPayload($extracted, is_array($existing) ? $existing : null);
        $finalCount = is_array($merged['items'] ?? null) ? count($merged['items']) : 0;
        $this->line('Total items after merge: '.$finalCount);
        if ($this->option('dry-run') || ! $this->option('force')) {
            $this->warn('No file written. Use --force to write resources/data/dti_bnpc_srp.json.');

            return self::SUCCESS;
        }
        File::put($jsonPath, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
        $this->info("Wrote {$jsonPath}");

        return self::SUCCESS;
    }
}
