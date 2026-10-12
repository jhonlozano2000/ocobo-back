<?php

namespace Tests\Feature\Firma;

use App\Mail\OtpFirmaMail;
use App\Models\User;
use App\Services\Firma\FirmaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * M03-19: al firmar un radicado interno se registra la firma en la fila del firmante
 * (firmado, fecha_firma, otp_utilizado) y el mismo firmante no puede firmar dos veces.
 */
class FirmaInternoFirmanteTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/archivo/firma-electronica';

    protected User $firmante;

    protected int $internoId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Mail::fake();

        $this->firmante = User::factory()->create(['estado' => 1]);

        // Clasificación y dependencia no son relevantes para estas pruebas
        Schema::disableForeignKeyConstraints();
        $this->internoId = DB::table('ventanilla_radica_internos')->insertGetId([
            'num_radicado' => 'M03-19-TEST-0001',
            'clasifica_documen_id' => 999999,
            'asunto' => 'Prueba M03-19',
            'usuario_crea' => $this->firmante->id,
            'archivo_digital' => 'm0319/interno.pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ventanilla_radica_internos_firmantes')->insert([
            'radica_interno_id' => $this->internoId,
            'users_id' => $this->firmante->id,
            'created_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();
    }

    private function filaFirmante(): object
    {
        return DB::table('ventanilla_radica_internos_firmantes')
            ->where('radica_interno_id', $this->internoId)
            ->where('users_id', $this->firmante->id)
            ->first();
    }

    public function test_marcar_firmante_registra_la_firma_una_sola_vez(): void
    {
        $service = app(FirmaService::class);

        $this->assertFalse($service->yaFirmo('radicado_interno', $this->internoId, $this->firmante->id));

        $service->marcarFirmante('radicado_interno', $this->internoId, $this->firmante->id, '123456');

        $fila = $this->filaFirmante();
        $this->assertSame(1, (int) $fila->firmado);
        $this->assertNotNull($fila->fecha_firma);
        $this->assertSame('***456', $fila->otp_utilizado);
        $this->assertTrue($service->yaFirmo('radicado_interno', $this->internoId, $this->firmante->id));

        // Una segunda marca no sobrescribe la fecha ni el OTP de la primera firma
        $service->marcarFirmante('radicado_interno', $this->internoId, $this->firmante->id, '999999');
        $this->assertSame('***456', $this->filaFirmante()->otp_utilizado);
    }

    public function test_firmar_por_endpoint_marca_al_firmante_y_rechaza_la_segunda_firma(): void
    {
        if (! Schema::hasColumn('firmas_eventos', 'timestamp_token')) {
            $this->markTestSkipped('BD de pruebas sin la migración add_timestamp_token_to_firmas_eventos_table.');
        }

        Storage::fake('radicados_internos');
        Http::fake(['*' => Http::response('', 503)]);

        $pdf = new \FPDF;
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(40, 10, 'Interno M03-19');
        $contenido = $pdf->Output('S');
        Storage::disk('radicados_internos')->put('m0319/interno.pdf', $contenido);
        DB::table('ventanilla_radica_internos')->where('id', $this->internoId)->update(['hash_sha256' => hash('sha256', $contenido)]);

        $payload = ['documentable_type' => 'radicado_interno', 'documentable_id' => $this->internoId];

        $this->actingAs($this->firmante)->postJson(self::URL.'/solicitar-otp', $payload)->assertOk();
        $otp = null;
        Mail::assertSent(OtpFirmaMail::class, function ($mail) use (&$otp) {
            $otp = (string) $mail->otp;

            return true;
        });

        $this->actingAs($this->firmante)->postJson(self::URL.'/firmar', $payload + ['otp' => $otp])->assertOk();

        $fila = $this->filaFirmante();
        $this->assertSame(1, (int) $fila->firmado);
        $this->assertSame('***'.substr($otp, -3), $fila->otp_utilizado);

        // Segunda firma del mismo firmante → 422 sin volver a estampar
        $hashTrasPrimera = hash('sha256', Storage::disk('radicados_internos')->get('m0319/interno.pdf'));
        $this->actingAs($this->firmante)->postJson(self::URL.'/firmar', $payload + ['otp' => '000000'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ya firmó este documento.');
        $this->assertSame($hashTrasPrimera, hash('sha256', Storage::disk('radicados_internos')->get('m0319/interno.pdf')));
    }
}
