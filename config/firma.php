<?php

return [
    'tsa_url' => env('TSA_URL', 'https://timestamp.digicert.com'),
    'tsa_timeout' => env('TSA_TIMEOUT', 10),
    // Raíces de confianza (PEM) para verificar el certificado de la TSA; si está vacío
    // se usa openssl.cafile / curl.cainfo de PHP.
    'tsa_ca_file' => env('TSA_CA_FILE'),
];
