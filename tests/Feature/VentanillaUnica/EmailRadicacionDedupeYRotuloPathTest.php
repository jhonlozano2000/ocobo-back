<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\Configuracion\ConfigLista;
use App\Models\Configuracion\ConfigListaDetalle;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\VentanillaUnica\EmailRadicacionService;
use App\Services\VentanillaUnica\RotuloPngService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EmailRadicacionDedupeYRotuloPathTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::first() ?? User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_rotulo_png_sanitiza_directorio_con_caracteres_especiales(): void
    {
        $service = app(RotuloPngService::class);

        $path = $service->generarRotulo([
            'num_radicado' => '../TRAVERSAL-1234',
            'fecha_radicado' => now()->format('Y-m-d H:i:s'),
            'remitente_nombre' => 'Test Remitente',
            'remitente_email' => 'test@example.com',
            'asunto' => 'Prueba rotulo seguro',
            'clasificacion' => 'General',
            'codigo_verificacion' => 'OCOBO-1234',
            'hash_sha256' => hash('sha256', 'test'),
        ]);

        $this->assertStringNotContainsString('../', $path);
        $this->assertStringStartsWith('radicados_recibidos/___TRAVERSAL-1234/', $path);
    }

    public function test_email_radicacion_rechaza_uid_duplicado(): void
    {
        $medio = ConfigListaDetalle::where('nombre', 'Correo Electrónico')->first();
        if (! $medio) {
            $lista = ConfigLista::firstOrCreate(
                ['cod' => 'MedRec'],
                ['nombre' => 'Medio de Recepción', 'estado' => 1]
            );
            $medio = ConfigListaDetalle::create([
                'lista_id' => $lista->id,
                'codigo' => 'EMAIL',
                'nombre' => 'Correo Electrónico',
                'estado' => 1,
            ]);
        }

        $uniqueUid = 'TEST_UID_'.uniqid();

        VentanillaRadicaReci::create([
            'num_radicado' => 'RAD-PREV-'.uniqid(),
            'imap_uid' => $uniqueUid,
            'medio_recep_id' => $medio->id,
            'usuario_crea' => $this->user->id,
            'asunto' => 'Correo previo',
            'fec_docu' => now()->toDateString(),
            'cod_verifica' => 'TESTCOD123',
            'hash_sha256' => hash('sha256', 'previo'),
        ]);

        $service = app(EmailRadicacionService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("El correo (UID {$uniqueUid}) ya fue radicado previamente");

        $service->radicarFromEmailData([
            'uid' => $uniqueUid,
            'asunto' => 'Intento duplicado',
            'remitente_nombre' => 'Remitente',
            'remitente_email' => 'remitente@test.com',
            'cuerpo_texto' => 'Mensaje',
        ]);
    }
}
