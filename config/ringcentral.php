<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Application Credentials
    |--------------------------------------------------------------------------
    |
    | Application credentials. Get your credentials from
    | https://developers.ringcentral.com | 'Credentials - Application Credentials'.
    |
    */
    'client_id' => function_exists('env') ? env('RINGCENTRAL_CLIENT_ID', '') : '',
    'client_secret' => function_exists('env') ? env('RINGCENTRAL_CLIENT_SECRET', '') : '',
    'server_url' => function_exists('env') ? env('RINGCENTRAL_SERVER_URL', '') : '',
    'token' => function_exists('env') ? env('RINGCENTRAL_TOKEN', '') : '',
];
