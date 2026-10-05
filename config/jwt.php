<?php

return [
    'secret'      => env('APP_JWT_SECRET'),
    'access_ttl'  => env('JWT_ACCESS_TTL',  3600),   // 1h en secondes
    'refresh_ttl' => env('JWT_REFRESH_TTL', 2592000), // 30j en secondes
];
