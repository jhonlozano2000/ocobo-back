<?php

/**
 * Concurrencia real en desarrollo: php tests/Concurrency/workflows.php
 * Los procesos necesitan fixtures confirmados. Solo crea y limpia registros con marcador propio;
 * no migra, trunca ni crea bases. No se ejecuta dentro de la suite transaccional PHPUnit.
 */
require __DIR__.'/../../vendor/autoload.php';

use App\Exceptions\Workflows\WorkflowException;
use App\Models\User;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\Workflow;
use App\Services\Workflows\TareaService;
use App\Services\Workflows\WorkflowExecutionService;
use App\Services\Workflows\WorkflowService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Process\Process;

foreach (['APP_ENV' => 'testing', 'DB_DATABASE' => 'ocobo_back', 'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array', 'BROADCAST_DRIVER' => 'null', 'BCRYPT_ROUNDS' => '4'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || DB::connection()->getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'ocobo_back') {
    throw new RuntimeException('Esta prueba requiere MySQL de desarrollo ocobo_back.');
}
config(['mail.default' => 'array', 'broadcasting.default' => 'null', 'queue.default' => 'sync', 'cache.default' => 'array', 'session.driver' => 'array']);

/** Cada worker valida la identidad de la fixture antes de tocar filas. */
function worker(array $context): array
{
    $flow = Workflow::findOrFail($context['workflow']);
    $user = User::findOrFail($context['user']);
    if ($flow->nombre !== $context['marker'] || $flow->creador_user_id !== $user->id
        || $user->email !== $context['marker'].'@concurrency.invalid') {
        throw new RuntimeException('La fixture no pertenece a esta ejecución.');
    }
    auth()->setUser($user);
    $task = $context['action'] === 'complete' ? Tarea::where('workflow_id', $flow->id)->findOrFail($context['task']) : null;
    $probed = 0;
    DB::listen(function ($query) use ($context, &$probed) {
        // Fuerza la ventana de una lectura sin bloqueo; una lectura FOR UPDATE no se retrasa.
        if ($context['action'] === 'complete' && str_starts_with(strtolower($query->sql), 'select * from `workflow_tareas`')
            && ! str_contains(strtolower($query->sql), 'for update')
            && in_array($context['task'], $query->bindings)) {
            $probed++;
            usleep(350000);
        }
        if ($context['action'] === 'save' && str_starts_with(strtolower($query->sql), 'select * from `workflows`')
            && ! str_contains(strtolower($query->sql), 'for update')) {
            $probed++;
            usleep(350000);
        }
    });
    file_put_contents($context['directory'].'/ready-'.$context['index'], 'ready');
    $deadline = microtime(true) + 20;
    while (! is_file($context['directory'].'/go')) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Timeout de barrera.');
        }
        usleep(10000);
    }
    if (($context['mixed'] ?? false) && $context['action'] === 'activate') {
        usleep(100000);
    }
    try {
        if ($context['action'] === 'activate') {
            app(WorkflowService::class)->cambiarEstado($flow->id, 'activo');
        } elseif ($context['action'] === 'save') {
            app(WorkflowService::class)->guardarNodosYconexiones($flow->id, $context['nodes'], $context['connections']);
        } else {
            app(TareaService::class)->completar($task);
        }

        return ['status' => 'success', 'unlocked_reads' => $probed];
    } catch (WorkflowException $e) {
        return ['status' => 'conflict', 'exception' => get_class($e), 'unlocked_reads' => $probed];
    } catch (HttpExceptionInterface $e) {
        return ['status' => $e->getStatusCode() === 422 ? 'conflict' : 'error', 'exception' => get_class($e), 'unlocked_reads' => $probed];
    } catch (Throwable $e) {
        return ['status' => 'error', 'exception' => get_class($e), 'unlocked_reads' => $probed];
    }
}

if (($argv[1] ?? '') === 'worker') {
    echo json_encode(worker(json_decode(base64_decode($argv[2]), true, 512, JSON_THROW_ON_ERROR))), PHP_EOL;
    exit;
}

/** Huella de esquema y filas, sin guardar ni imprimir contenido de documentos. */
function snapshot(): array
{
    $result = [];
    foreach (DB::select("SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME", ['ocobo_back']) as $table) {
        $name = '`'.str_replace('`', '``', $table->name).'`';
        $ddl = array_values((array) DB::selectOne('SHOW CREATE TABLE '.$name))[1];
        $checksum = array_values((array) DB::selectOne('CHECKSUM TABLE '.$name.' EXTENDED'))[1];
        $result[$table->name] = [hash('sha256', preg_replace('/AUTO_INCREMENT=\d+\s*/', '', $ddl)), DB::table($table->name)->count(), $checksum];
    }

    return $result;
}

/** Ocho conexiones independientes arrancan desde la misma barrera. */
function concurrent(array $context, array $tasks = []): array
{
    mkdir($context['directory'], 0700);
    $processes = [];
    try {
        for ($i = 0; $i < 8; $i++) {
            $payload = $context + ['index' => $i, 'task' => $tasks ? $tasks[$i % count($tasks)] : null];
            if ($context['action'] === 'mixed') {
                $payload['action'] = $i === 7 ? 'activate' : 'save';
                $payload['mixed'] = true;
            }
            $process = new Process([PHP_BINARY, __FILE__, 'worker', base64_encode(json_encode($payload))], base_path(), null, null, 30);
            $process->start();
            $processes[] = $process;
        }
        $deadline = microtime(true) + 20;
        while (count(glob($context['directory'].'/ready-*')) !== 8) {
            foreach ($processes as $process) {
                if (! $process->isRunning()) {
                    throw new RuntimeException('Worker terminó antes de la barrera.');
                }
            }
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Timeout preparando procesos.');
            }
            usleep(10000);
        }
        file_put_contents($context['directory'].'/go', 'go');
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('Worker finalizó sin resultado válido.');
            }
            $results[] = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        }

        return $results;
    } finally {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
        foreach (glob($context['directory'].'/*') as $file) {
            unlink($file);
        }
        rmdir($context['directory']);
    }
}

$before = snapshot();
$marker = 'ocobo-race-'.bin2hex(random_bytes(8));
$directory = sys_get_temp_dir().'/'.$marker;
mkdir($directory, 0700);
$user = null;
$flowIds = [];
$report = [];
$exit = 0;
try {
    $user = User::factory()->create(['email' => $marker.'@concurrency.invalid', 'estado' => 1]);
    auth()->setUser($user);
    foreach (['activation' => 1, 'same_task' => 1, 'sibling_tasks' => 2, 'canvas' => 1, 'save_vs_activate' => 1] as $case => $count) {
        [$flow, $first, $next] = DB::transaction(function () use ($marker, $user, $count, &$flowIds) {
            $flow = Workflow::create(['nombre' => $marker, 'estado' => 'borrador', 'creador_user_id' => $user->id]);
            $flowIds[] = $flow->id;
            $nodes = [];
            foreach (['inicio', 'tarea', 'tarea', 'fin'] as $i => $type) {
                $nodes[] = $flow->nodos()->create(['titulo' => $type.' '.$i, 'tipo' => $type, 'orden_ejecucion' => $i]);
            }
            for ($i = 0; $i < 3; $i++) {
                $flow->conexiones()->create(['nodo_origen_id' => $nodes[$i]->id, 'nodo_destino_id' => $nodes[$i + 1]->id]);
            }
            for ($i = 0; $i < $count; $i++) {
                $nodes[1]->tareas()->create(['titulo' => 'Actividad '.$i, 'responsable_usuario_id' => $user->id]);
            }
            $nodes[2]->tareas()->create(['titulo' => 'Paso siguiente', 'responsable_usuario_id' => $user->id]);

            return [$flow, $nodes[1], $nodes[2]];
        });
        $tasks = [];
        if (! in_array($case, ['activation', 'canvas', 'save_vs_activate'], true)) {
            $flow->update(['estado' => 'activo']);
            app(WorkflowExecutionService::class)->iniciarInstancia($flow->id, $user->id);
            $tasks = $flow->tareasModulo()->where('nodo_id', $first->id)->pluck('id')->all();
            Tarea::whereIn('id', $tasks)->update(['estado' => 'en_curso']);
        }
        $context = ['action' => match ($case) {
            'activation' => 'activate', 'canvas' => 'save', 'save_vs_activate' => 'mixed', default => 'complete'
        }, 'workflow' => $flow->id, 'user' => $user->id, 'marker' => $marker, 'directory' => $directory.'/'.$case];
        if (in_array($case, ['canvas', 'save_vs_activate'], true)) {
            $context['nodes'] = $flow->nodos()->get()->map(fn ($n) => ['client_id' => 'nodo_'.$n->id, 'tipo' => $n->tipo, 'titulo' => $n->titulo, 'posicion_x' => 0, 'posicion_y' => 0, 'configuracion_json' => []])->all();
            $context['connections'] = $flow->conexiones()->get()->map(fn ($c) => ['nodo_origen_id' => 'nodo_'.$c->nodo_origen_id, 'nodo_destino_id' => 'nodo_'.$c->nodo_destino_id])->all();
        }
        $results = concurrent($context, $tasks);
        $successes = count(array_filter($results, fn ($r) => $r['status'] === 'success'));
        $errors = count(array_filter($results, fn ($r) => $r['status'] === 'error'));
        $instances = $flow->instancias()->count();
        $completedAudits = $flow->audits()->where('action', 'tarea.completada')->count();
        $nextTasks = $flow->tareasModulo()->where('nodo_id', $next->id)->count();
        $passed = $instances === 1 && $errors === 0 && $successes === ($case === 'activation' ? 1 : $count)
            && $completedAudits === ($case === 'activation' ? 0 : $count) && $nextTasks === ($case === 'activation' ? 0 : 1);
        if ($case === 'canvas') {
            $passed = $successes === 8 && $errors === 0 && $instances === 0 && $flow->nodos()->count() === 4
                && $flow->conexiones()->count() === 3 && $flow->tareas()->count() === 2;
        }
        $lateSaves = 0;
        if ($case === 'save_vs_activate') {
            $activationAudit = $flow->audits()->where('action', 'workflow.cambio_estado')->value('id');
            $lateSaves = $activationAudit ? $flow->audits()->where('action', 'workflow.canvas_guardado')->where('id', '>', $activationAudit)->count() : -1;
            $passed = $instances === 1 && $errors === 0 && $lateSaves === 0 && $flow->tareasModulo()->count() === 1;
        }
        $report[$case] = ['passed' => $passed, 'workers' => 8, 'successes' => $successes, 'errors' => $errors, 'instances' => $instances, 'completed_audits' => $completedAudits, 'next_tasks' => $nextTasks, 'saves_after_activation' => $lateSaves, 'results' => $results];
        echo $case.': '.json_encode($report[$case]), PHP_EOL;
        if (! $passed) {
            $exit = 1;
        }
    }
} catch (Throwable $e) {
    $report['error'] = get_class($e).': '.$e->getMessage();
    echo $report['error'], PHP_EOL;
    $exit = 1;
} finally {
    // Solo registros de esta ejecución. Los audits de usuarios/flujo reales nunca se eliminan.
    if ($user) {
        DB::transaction(function () use ($user, $marker, $flowIds) {
            $owned = DB::table('workflows')->whereIn('id', $flowIds)->where('nombre', $marker)->where('creador_user_id', $user->id)->pluck('id');
            DB::table('workflow_audits')->whereIn('workflow_id', $owned)->where('user_id', $user->id)->delete();
            DB::table('notifications')->where('user_id', $user->id)->delete();
            DB::table('workflows')->whereIn('id', $owned)->delete();
            DB::table('users')->where('id', $user->id)->where('email', $marker.'@concurrency.invalid')->delete();
        });
    }
    rmdir($directory);
    $after = snapshot();
    $report['database_unchanged'] = $before === $after;
    if ($before !== $after) {
        $exit = 1;
    }
    echo 'database_unchanged='.json_encode($report['database_unchanged']), PHP_EOL;
    file_put_contents(storage_path('logs/workflows-concurrency.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
exit($exit);
