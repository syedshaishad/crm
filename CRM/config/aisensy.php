<?php

/*
|--------------------------------------------------------------------------
| AiSensy (WhatsApp Business API) campaign settings
|--------------------------------------------------------------------------
|
| The API key and campaign names are normally saved from Settings →
| Integrations (the key is encrypted at rest in the settings table). These
| env values are fallbacks for installs that prefer to configure by .env.
|
*/

return [
    'endpoint' => env('AISENSY_ENDPOINT', 'https://backend.aisensy.com/campaign/t1/api/v2'),
    'api_key' => env('AISENSY_API_KEY', ''),
    'source' => env('AISENSY_SOURCE', 'CRM'),

    'campaigns' => [
        'reminder' => env('AISENSY_CAMPAIGN_REMINDER', ''),
        'report' => env('AISENSY_CAMPAIGN_REPORT', ''),
        'test' => env('AISENSY_CAMPAIGN_TEST', ''),
    ],
];
