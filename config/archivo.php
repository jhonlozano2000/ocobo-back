<?php

return [
    /*
    | Decisión D4 (diseño de expedientes §3.4): meses sin incorporaciones tras los cuales un
    | expediente abierto se considera inactivo. Solo se avisa; el expediente no se cierra solo.
    */
    'meses_inactividad' => (int) env('ARCHIVO_MESES_INACTIVIDAD', 6),
];
