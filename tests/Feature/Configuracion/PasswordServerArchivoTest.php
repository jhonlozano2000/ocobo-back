<?php

namespace Tests\Feature\Configuracion;

use App\Models\Configuracion\ConfigServerArchivo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * M15-04: la contraseña del servidor de archivos se guardaba con bcrypt y no podía recuperarse
 * para conectarse. Ahora se cifra de forma reversible y nunca se guarda en claro.
 */
class PasswordServerArchivoTest extends TestCase
{
    use DatabaseTransactions;

    private function servidor(string $password): ConfigServerArchivo
    {
        // Sin catálogos en la BD de pruebas: proceso_id sin FK
        Schema::disableForeignKeyConstraints();
        $servidor = ConfigServerArchivo::create([
            'proceso_id' => 1, 'host' => '10.0.0.5', 'ruta' => '/docs', 'user' => 'ftpuser', 'password' => $password, 'estado' => 1,
        ]);
        Schema::enableForeignKeyConstraints();

        return $servidor;
    }

    public function test_la_contrasena_se_cifra_y_se_puede_recuperar(): void
    {
        $servidor = $this->servidor('S3cret-ftp');
        $guardado = DB::table('config_server_archivos')->where('id', $servidor->id)->value('password');

        $this->assertNotSame('S3cret-ftp', $guardado);
        $this->assertSame('S3cret-ftp', Crypt::decryptString($guardado));
        $this->assertSame('S3cret-ftp', $servidor->fresh()->password);
        $this->assertArrayNotHasKey('password', $servidor->fresh()->toArray());
    }

    public function test_actualizar_sin_contrasena_conserva_la_guardada(): void
    {
        $servidor = $this->servidor('S3cret-ftp');

        $servidor->update(['host' => '10.0.0.6', 'password' => '']);

        $this->assertSame('S3cret-ftp', $servidor->fresh()->password);
    }
}
