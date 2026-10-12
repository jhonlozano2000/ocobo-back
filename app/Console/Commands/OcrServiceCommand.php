<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class OcrServiceCommand extends Command
{
    protected $signature = 'ocr {action : start|stop|status}';

    protected $description = 'Gestiona el servicio OCR (PaddleOCR)';

    private const PID_FILE = 'ocr-service.pid';

    private const PORT = 5000;

    private const STARTUP_TIMEOUT = 180;

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'start' => $this->start(),
            'stop' => $this->stop(),
            'status' => $this->status(),
            default => $this->invalidAction(),
        };
    }

    private function start(): int
    {
        if ($this->isRunning()) {
            $this->error('El servicio OCR ya está corriendo en puerto '.self::PORT);

            return 1;
        }

        $servicePath = storage_path('app/ocr-service');
        $pidFile = storage_path('app/'.self::PID_FILE);

        if (! file_exists($servicePath.'/main.py')) {
            $this->error("No se encontró el servicio OCR en: {$servicePath}");

            return 1;
        }

        // M12-02: sin secreto el microservicio rechaza las extracciones; solo escucha en loopback
        // salvo que OCR_SERVICE_HOST indique otra interfaz
        $token = (string) config('services.ocr.token');
        if ($token === '') {
            $this->error('Defina OCR_SERVICE_TOKEN en el .env antes de iniciar el servicio OCR');

            return 1;
        }
        putenv('OCR_SERVICE_TOKEN='.$token);
        $host = escapeshellarg((string) config('services.ocr.host', '127.0.0.1'));

        // Las dependencias viven en el entorno virtual que crea start.bat (o `python -m venv .venv`)
        $python = PHP_OS_FAMILY === 'Windows' ? $servicePath.'\.venv\Scripts\python.exe' : $servicePath.'/.venv/bin/python';
        if (! file_exists($python)) {
            $this->error('Falta el entorno virtual del servicio OCR. Instálelo con:');
            $this->line(PHP_OS_FAMILY === 'Windows'
                ? "  {$servicePath}\\start.bat install"
                : "  cd {$servicePath} && python3 -m venv .venv && .venv/bin/pip install -r requirements.txt");

            return 1;
        }

        $log = storage_path('logs/ocr-service.log');
        $this->info('Iniciando servicio OCR (log: '.$log.')...');

        if (PHP_OS_FAMILY === 'Windows') {
            $command = "cd /d \"{$servicePath}\" && start /B \"\" \"{$python}\" -m uvicorn main:app --host {$host} --port ".self::PORT." > \"{$log}\" 2>&1";
            pclose(popen($command, 'r'));
        } else {
            $command = "cd \"{$servicePath}\" && \"{$python}\" -m uvicorn main:app --host {$host} --port ".self::PORT." > \"{$log}\" 2>&1 & echo $!";
            $pid = shell_exec($command);
            if ($pid) {
                file_put_contents($pidFile, trim($pid));
            }
        }

        // El puerto abre después de cargar PaddleOCR; el primer arranque además descarga los modelos
        for ($espera = 0; $espera < self::STARTUP_TIMEOUT; $espera += 2) {
            if ($this->isRunning()) {
                if (PHP_OS_FAMILY === 'Windows' && $pid = $this->pidEscuchando()) {
                    file_put_contents($pidFile, $pid);
                }
                $this->info('✓ Servicio OCR iniciado en puerto '.self::PORT);

                return 0;
            }
            sleep(2);
        }

        $this->error('El servicio OCR no respondió en '.self::STARTUP_TIMEOUT.' s. Revise '.$log);

        return 1;
    }

    private function stop(): int
    {
        $pidFile = storage_path('app/'.self::PID_FILE);

        // Sin PID file (p. ej. iniciado con start.bat) se busca el proceso que escucha en el puerto
        $pid = file_exists($pidFile) ? (int) file_get_contents($pidFile) : (int) $this->pidEscuchando();

        if (! $pid || ! $this->isProcessRunning($pid)) {
            $this->warn('No hay servicio OCR corriendo');
            @unlink($pidFile);

            return 0;
        }

        $this->info("Deteniendo servicio OCR (PID: {$pid})...");

        if (PHP_OS_FAMILY === 'Windows') {
            exec("taskkill /PID {$pid} /F");
        } else {
            exec("kill {$pid}");
        }

        @unlink($pidFile);

        $this->info('✓ Servicio OCR detenido');

        return 0;
    }

    private function status(): int
    {
        if (! $this->isRunning()) {
            $this->warn('Servicio OCR: Detenido');

            return 0;
        }

        $pidFile = storage_path('app/'.self::PID_FILE);
        $pid = file_exists($pidFile) ? (int) file_get_contents($pidFile) : '?';
        $this->info("Servicio OCR: Corriendo (PID: {$pid}, Puerto: ".self::PORT.')');

        return 0;
    }

    private function invalidAction(): int
    {
        $this->error('Acción inválida. Use: start, stop, status');

        return 1;
    }

    private function isRunning(): bool
    {
        // Se prueba la conexión: filtrar `netstat | findstr :5000` también casaba puertos
        // como 50001 y daba el servicio por iniciado sin estarlo
        $host = (string) config('services.ocr.host', '127.0.0.1');
        $host = in_array($host, ['0.0.0.0', '::', ''], true) ? '127.0.0.1' : $host;

        $socket = @fsockopen($host, self::PORT, $errno, $errstr, 1);
        if ($socket === false) {
            return false;
        }
        fclose($socket);

        return true;
    }

    /**
     * PID del proceso que escucha en el puerto del servicio (solo Windows; en Linux se usa el PID file).
     */
    private function pidEscuchando(): ?int
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return null;
        }

        // Columnas: Proto, Local, Remota (x:0 cuando escucha), Estado, PID; el estado va traducido
        $pattern = '/^\s*TCP\s+\S+:'.self::PORT.'\s+\S+:0\s+\S+\s+(\d+)\s*$/m';

        return preg_match($pattern, (string) shell_exec('netstat -ano -p TCP'), $m) ? (int) $m[1] : null;
    }

    private function isProcessRunning(int $pid): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec("tasklist /FI \"PID eq {$pid}\"");

            return str_contains($output ?? '', (string) $pid);
        }

        return shell_exec("kill -0 {$pid} 2>/dev/null && echo 1") !== null;
    }
}
