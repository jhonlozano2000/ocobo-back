<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have a
    | conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tesseract OCR Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración para el servicio de OCR local usando Tesseract.
    | Requiere Tesseract 5.x instalado con idiomas español e inglés.
    |
    */

    'tesseract' => [
        'path' => env('TESSERACT_PATH', 'tesseract'),
        'language' => env('TESSERACT_LANG', 'spa+eng'),
        'enabled' => env('TESSERACT_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | ImageMagick Configuration
    |--------------------------------------------------------------------------
    |
    | Ruta al ejecutable de ImageMagick para convertir PDFs a imágenes.
    |
    */

    'imagemagick' => [
        'path' => env('IMAGEMAGICK_PATH', 'C:\\Program Files\\ImageMagick-7.1.2-Q16-HDRI\\magick.exe'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OCR Service (Microservicio Python)
    |--------------------------------------------------------------------------
    |
    | Configuración del servicio OCR externo basado en PaddleOCR.
    |
    */

    'ocr' => [
        'url' => env('OCR_SERVICE_URL', 'http://localhost:5000'),
        'enabled' => env('OCR_ENABLED', false),
        'timeout' => env('OCR_TIMEOUT', 60),
    ],

];
