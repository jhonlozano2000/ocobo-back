<?php

// Respaldo: cada entidad los administra en config_varias (Otras configuraciones → Archivo);
// ver App\Services\OfiArchivo\ConfiguracionArchivo. Estos valores aplican sin la clave o vacía.
return [
    /*
    | Decisión D4 (diseño de expedientes §3.4): meses sin incorporaciones tras los cuales un
    | expediente abierto se considera inactivo. Solo se avisa; el expediente no se cierra solo.
    */
    'meses_inactividad' => (int) env('ARCHIVO_MESES_INACTIVIDAD', 6),

    /*
    | Decisión D3 (diseño de expedientes §3.3): folios por tomo físico. Si el documento siguiente
    | no cabe en el tomo actual, se abre el siguiente con la foliación continua.
    */
    'folios_por_tomo' => (int) env('ARCHIVO_FOLIOS_POR_TOMO', 200),
];
