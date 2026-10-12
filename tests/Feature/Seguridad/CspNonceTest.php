<?php

namespace Tests\Feature\Seguridad;

use Tests\TestCase;

/**
 * SES-006: la CSP de producción llevaba el literal 'nonce-{NONCE}', así que ningún <style> inline
 * podía pasar. Ahora el nonce es aleatorio por petición y coincide con el de la vista.
 */
class CspNonceTest extends TestCase
{
    private function nonceDeLaCsp(): string
    {
        $this->app['env'] = 'production';

        $respuesta = $this->get('/')->assertOk();
        $csp = $respuesta->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringNotContainsString('{NONCE}', $csp);
        $this->assertMatchesRegularExpression("/style-src [^;]*'nonce-([A-Za-z0-9+\\/=]{24})'/", $csp);
        preg_match("/'nonce-([^']+)'/", $csp, $m);
        $this->assertStringContainsString('<style nonce="'.$m[1].'">', $respuesta->getContent());

        return $m[1];
    }

    public function test_el_nonce_de_la_csp_es_real_y_coincide_con_la_vista(): void
    {
        $this->nonceDeLaCsp();
    }

    public function test_el_nonce_cambia_en_cada_peticion(): void
    {
        $this->assertNotSame($this->nonceDeLaCsp(), $this->nonceDeLaCsp());
    }
}
