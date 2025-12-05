<?php

namespace App\Services;

use Google_Client;
use Google_Service_Sheets;

class GoogleSheetsImporter
{
    public function getData(string $spreadsheetId, string $range = 'A:D'): array
    {
        $client = new Google_Client();
        $client->setAuthConfig(storage_path('app/google/service-account.json'));
        $client->addScope(Google_Service_Sheets::SPREADSHEETS_READONLY);

        $service = new Google_Service_Sheets($client);

        $response = $service->spreadsheets_values->get($spreadsheetId, $range);

        return $response->getValues() ?? [];
    }
}
