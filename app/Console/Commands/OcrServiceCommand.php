<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class OcrServiceCommand extends Command
{
    protected $signature = 'ocr {action : start|stop|status}';
    protected $description = 'Gestiona el servicio OCR (PaddleOCR)';

    private const PID_FILE = 'ocr-service.pid';
    private const PORT = 5000;

    public function handle(): int
    {
        $action = $this->argument('action');

        return match($action) {
            'start' => $this->start(),
            'stop' => $this->stop(),
            'status' => $this->status(),
            default => $this->invalidAction(),
        };
    }

    private function start(): int
    {
        if ($this->isRunning()) {
            $this->error("El servicio OCR ya está corriendo en puerto " . self::PORT);
            return 1;
        }

        $servicePath = storage_path('app/ocr-service');
        $pidFile = storage_path('app/' . self::PID_FILE);

        if (!file_exists($servicePath . '/main.py')) {
            $this->error("No se encontró el servicio OCR en: {$servicePath}");
            return 1;
        }

        $this->info("Iniciando servicio OCR...");

        if (PHP_OS_FAMILY === 'Windows') {
            $command = "cd /d \"{$servicePath}\" && start /B python -m uvicorn main:app --host 0.0.0.0 --port " . self::PORT;
            pclose(popen($command, 'r'));
        } else {
            $command = "cd \"{$servicePath}\" && python -m uvicorn main:app --host 0.0.0.0 --port " . self::PORT . " > /dev/null 2>&1 & echo $!";
            $pid = shell_exec($command);
            if ($pid) {
                file_put_contents($pidFile, trim($pid));
            }
        }

        sleep(3);

        if ($this->isRunning()) {
            $this->info("✓ Servicio OCR iniciado en puerto " . self::PORT);
            return 0;
        }

        $this->error("Error al iniciar el servicio OCR");
        return 1;
    }

    private function stop(): int
    {
        $pidFile = storage_path('app/' . self::PID_FILE);

        if (!file_exists($pidFile)) {
            $this->warn("No hay servicio OCR corriendo (sin PID file)");
            return 0;
        }

        $pid = (int) file_get_contents($pidFile);

        if (!$this->isProcessRunning($pid)) {
            $this->warn("Proceso OCR no está corriendo");
            unlink($pidFile);
            return 0;
        }

        $this->info("Deteniendo servicio OCR (PID: {$pid})...");

        if (PHP_OS_FAMILY === 'Windows') {
            exec("taskkill /PID {$pid} /F");
        } else {
            exec("kill {$pid}");
        }

        unlink($pidFile);

        $this->info("✓ Servicio OCR detenido");
        return 0;
    }

    private function status(): int
    {
        if (!$this->isRunning()) {
            $this->warn("Servicio OCR: Detenido");
            return 0;
        }

        $pidFile = storage_path('app/' . self::PID_FILE);
        $pid = file_exists($pidFile) ? (int) file_get_contents($pidFile) : '?';
        $this->info("Servicio OCR: Corriendo (PID: {$pid}, Puerto: " . self::PORT . ")");
        return 0;
    }

    private function invalidAction(): int
    {
        $this->error("Acción inválida. Use: start, stop, status");
        return 1;
    }

    private function isRunning(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec("netstat -ano | findstr :" . self::PORT);
        } else {
            $output = shell_exec("lsof -i :" . self::PORT);
        }

        return str_contains($output ?? '', 'LISTENING') || str_contains($output ?? '', (string) self::PORT);
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
