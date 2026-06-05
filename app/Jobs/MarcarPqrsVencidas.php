<?php

namespace App\Jobs;

use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MarcarPqrsVencidas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        try {
            $actualizadas = VentanillaPqrs::whereIn('estado_tramite', ['Pendiente', 'En Tramite'])
                ->whereDate('fecha_vencimiento', '<', now()->toDateString())
                ->update(['estado_tramite' => 'Vencida']);

            Log::info("Job MarcarPqrsVencidas: {$actualizadas} PQRS marcadas como Vencidas");
        } catch (\Exception $e) {
            Log::error("Job MarcarPqrsVencidas error: " . $e->getMessage());
        }
    }
}