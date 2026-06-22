<?php

namespace App\Http\Resources\VentanillaUnica;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class RadicadoPqrsCollection extends ResourceCollection
{
    public $collects = RadicadoPqrsResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}
