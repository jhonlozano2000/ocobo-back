<?php

namespace Tests\Feature\MiBandeja;

use App\Http\Requests\MiBandeja\StoreMiBandejaTempRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoreMiBandejaTempRequestTest extends TestCase
{
    protected function getRulesFor(array $data)
    {
        $request = new StoreMiBandejaTempRequest;
        $request->merge($data);

        return $request->rules();
    }

    #[Test]
    public function reglas_validan_contra_ventanilla_radica_reci_por_defecto()
    {
        $rules = $this->getRulesFor([
            'radicado_id' => 123,
            'radicado_tipo' => 'recibido', // o vacío
        ]);

        $this->assertContains('exists:ventanilla_radica_reci,id', $rules['radicado_id']);
    }

    #[Test]
    public function reglas_validan_contra_ventanilla_radica_internos()
    {
        $rules = $this->getRulesFor([
            'radicado_id' => 123,
            'radicado_tipo' => 'interno',
        ]);

        $this->assertContains('exists:ventanilla_radica_internos,id', $rules['radicado_id']);
    }

    #[Test]
    public function reglas_validan_contra_ventanilla_radica_enviados()
    {
        $rules = $this->getRulesFor([
            'radicado_id' => 123,
            'radicado_tipo' => 'enviado',
        ]);

        $this->assertContains('exists:ventanilla_radica_enviados,id', $rules['radicado_id']);
    }

    #[Test]
    public function no_pide_exists_si_radicado_id_es_nulo()
    {
        $rules = $this->getRulesFor([
            'radicado_id' => null,
            'radicado_tipo' => 'interno',
        ]);

        $this->assertNotContains('exists:ventanilla_radica_internos,id', $rules['radicado_id']);
        $this->assertContains('nullable', $rules['radicado_id']);
    }
}
