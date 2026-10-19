<?php

return [
    'tsa_url' => env('TSA_URL', 'https://timestamp.digicert.com'),
    'tsa_timeout' => env('TSA_TIMEOUT', 10),
    // Raíces de confianza (PEM) para verificar el certificado de la TSA; si está vacío
    // se usa openssl.cafile / curl.cainfo de PHP.
    'tsa_ca_file' => env('TSA_CA_FILE'),
    // B10: sellar con la TSA cada versión firmada del índice (llamada de red al cerrar). Respaldo de la
    // clave archivo_indice_tsa de config_varias, que administra cada entidad.
    'tsa_indice_expediente' => (bool) env('ARCHIVO_INDICE_TSA', false),
];
