<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\VentanillaRadicaInterno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentanillaRadicaInternoController extends Controller
{
    use ApiResponseTrait;

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $radicado = new VentanillaRadicaInterno($request->only([
                "asunto", "clasifica_documen_id", "num_folios", "num_anexos", "descrip_anexos", "fec_venci"
            ]));
            
            $radicado->num_radicado = "INT-" . date("Ymd") . "-" . rand(100, 999);
            $radicado->usuario_crea = auth()->id();
            $radicado->save();

            // 2. Destinatarios (users_cargos_id)
            if ($request->has("destinatarios")) {
                foreach ($request->destinatarios as $id) {
                    DB::table("ventanilla_radica_internos_destina")->insert([
                        "radica_interno_id" => $radicado->id,
                        "users_cargos_id" => $id,
                        "created_at" => now()
                    ]);
                }
            }

            // 3. Responsables (users_cargos_id)
            if ($request->has("responsables")) {
                foreach ($request->responsables as $resp) {
                    DB::table("ventanilla_radica_interno_responsa")->insert([
                        "radica_interno_id" => $radicado->id,
                        "users_cargos_id" => $resp["user_id"],
                        "custodio" => $resp["custodio"],
                        "created_at" => now()
                    ]);
                }
            }

            // 4. Proyectores (users_cargos_id)
            if ($request->has("proyectores")) {
                foreach ($request->proyectores as $id) {
                    DB::table("ventanilla_radica_interno_proyectores")->insert([
                        "radica_interno_id" => $radicado->id,
                        "users_cargos_id" => $id,
                        "created_at" => now()
                    ]);
                }
            }

            // 5. Firmantes (users_id)
            if ($request->has("firmantes")) {
                foreach ($request->firmantes as $id) {
                    DB::table("ventanilla_radica_internos_firmantes")->insert([
                        "radica_interno_id" => $radicado->id,
                        "users_id" => $id,
                        "created_at" => now()
                    ]);
                }
            }

            DB::commit();
            return $this->successResponse($radicado, "Radicación interna completada exitosamente", 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse("Error al procesar la radicación interna", $e->getMessage(), 500);
        }
    }
}
