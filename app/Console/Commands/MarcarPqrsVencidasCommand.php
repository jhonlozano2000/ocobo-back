<?php

namespace App\Console\Commands;

use App\Jobs\MarcarPqrsVencidas;
use Illuminate\Console\Command;

class MarcarPqrsVencidasCommand extends Command
{
    protected $signature = 'pqrs:marcar-vencidas';

    protected $description = 'Marca las PQRS vencidas automáticamente';

    public function handle(): int
    {
        $this->info('Ejecutando job MarcarPqrsVencidas...');
        MarcarPqrsVencidas::dispatch();
        $this->info('Job en cola. Se ejecutará cuando el worker procese la cola.');

        return Command::SUCCESS;
    }
}
