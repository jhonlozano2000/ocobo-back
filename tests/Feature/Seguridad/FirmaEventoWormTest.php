<?php

namespace Tests\Feature\Seguridad;

use App\Models\Transversal\FirmaEvento;
use App\Models\User;
use App\Models\VentanillaUnica\VentanillaRadicaReci;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * M06-10: Inmutabilidad WORM en firmas_eventos (Eloquent y BD Trigger).
 */
class FirmaEventoWormTest extends TestCase
{
    use DatabaseTransactions;

    public function test_firma_evento_rechaza_actualizaciones_eloquent(): void
    {
        $user = User::factory()->create(['estado' => 1]);
        $evento = FirmaEvento::create([
            'documentable_id' => 1,
            'documentable_type' => VentanillaRadicaReci::class,
            'user_id' => $user->id,
            'hash_original' => hash('sha256', 'original'),
            'hash_firmado' => hash('sha256', 'firmado'),
            'otp_utilizado' => '123456',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'fecha_firma' => now(),
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('WORM');

        $evento->update(['hash_firmado' => hash('sha256', 'alterado')]);
    }

    public function test_firma_evento_rechaza_eliminacion_eloquent(): void
    {
        $user = User::factory()->create(['estado' => 1]);
        $evento = FirmaEvento::create([
            'documentable_id' => 1,
            'documentable_type' => VentanillaRadicaReci::class,
            'user_id' => $user->id,
            'hash_original' => hash('sha256', 'original'),
            'hash_firmado' => hash('sha256', 'firmado'),
            'otp_utilizado' => '123456',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'fecha_firma' => now(),
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('WORM');

        $evento->delete();
    }

    public function test_firma_evento_trigger_bd_rechaza_update_directo(): void
    {
        $user = User::factory()->create(['estado' => 1]);
        $id = DB::table('firmas_eventos')->insertGetId([
            'documentable_id' => 1,
            'documentable_type' => VentanillaRadicaReci::class,
            'user_id' => $user->id,
            'hash_original' => hash('sha256', 'original'),
            'hash_firmado' => hash('sha256', 'firmado'),
            'otp_utilizado' => '123456',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('WORM');

        DB::table('firmas_eventos')->where('id', $id)->update(['hash_firmado' => 'hacked']);
    }

    public function test_firma_evento_trigger_bd_rechaza_delete_directo(): void
    {
        $user = User::factory()->create(['estado' => 1]);
        $id = DB::table('firmas_eventos')->insertGetId([
            'documentable_id' => 1,
            'documentable_type' => VentanillaRadicaReci::class,
            'user_id' => $user->id,
            'hash_original' => hash('sha256', 'original'),
            'hash_firmado' => hash('sha256', 'firmado'),
            'otp_utilizado' => '123456',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('WORM');

        DB::table('firmas_eventos')->where('id', $id)->delete();
    }
}
