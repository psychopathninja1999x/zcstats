<?php

namespace App\Services;

/**
 * Loads BNPC SRP rows from resources/data/dti_bnpc_srp.json.
 * That file is not read from the PDF at runtime. Refresh it with
 * {@see \App\Console\Commands\ImportDtiBnpcPdfCommand} after downloading the latest DTI bulletin, then spot-check against the PDF.
 */
class DtiBnpcSrpService
{
    private const DATA_FILE = 'data/dti_bnpc_srp.json';

    /**
     * @return array<string, mixed>|null
     */
    public function getBulletinData(): ?array
    {
        $path = resource_path(self::DATA_FILE);
        if (! is_readable($path)) {
            return null;
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        if (! is_array($data) || ! isset($data['items']) || ! is_array($data['items'])) {
            return null;
        }

        return $data;
    }
}
