<?php

namespace App\Console\Commands;

use App\Helpers\MailConfigHelper;
use App\Mail\RadicadoEnviadoNotification;
use App\Mail\RadicadoNotification;
use App\Models\VentanillaUnica\Pqrs\VentanillaPqrs;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;