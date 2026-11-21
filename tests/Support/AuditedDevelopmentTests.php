<?php

use Tests\Feature\Archivo\AdjuntosCorreoDepuracionTest;
use Tests\Feature\Auth\CambioPasswordObligatorioTest;
use Tests\Feature\Auth\EnumeracionUsuariosTest;
use Tests\Feature\Auth\RevocacionSesionTest;
use Tests\Feature\Auth\SesionConcurrenteTest;
use Tests\Feature\Calendario\ZonaHorariaNegocioTest;
use Tests\Feature\Calidad\ExportOrganigramaTest;
use Tests\Feature\Calidad\OrganigramaCiclosTest;
use Tests\Feature\Calidad\PermisosCalidadSeederTest;
use Tests\Feature\ClasificacionDocumental\AprobacionConcurrenteTRDTest;
use Tests\Feature\ClasificacionDocumental\AprobacionTVDTest;
use Tests\Feature\ClasificacionDocumental\ExportTVDTest;
use Tests\Feature\ClasificacionDocumental\FondosAcumuladosTest;
use Tests\Feature\ClasificacionDocumental\ImportacionTRDTemporalTest;
use Tests\Feature\ClasificacionDocumental\ImportarTRDTest;
use Tests\Feature\ClasificacionDocumental\InmutabilidadTRDTest;
use Tests\Feature\ClasificacionDocumental\IntegridadEstructuraTRDTest;
use Tests\Feature\ClasificacionDocumental\LimiteImportacionTVDTest;
use Tests\Feature\ClasificacionDocumental\TRDScopingYRetencionExpedienteTest;
use Tests\Feature\ClasificacionDocumental\TVDEstadisticasTest;
use Tests\Feature\Configuracion\CanalesNotificacionTest;
use Tests\Feature\Configuracion\ConfigNumRadicadoTest;
use Tests\Feature\Configuracion\ConfigVariasSecretosTest;
use Tests\Feature\Configuracion\FestivosColombiaTest;
use Tests\Feature\Configuracion\NotificacionTiempoRealTest;
use Tests\Feature\Configuracion\NotificarRadicadoAlTerceroTest;
use Tests\Feature\Configuracion\PasswordServerArchivoTest;
use Tests\Feature\Configuracion\PreferenciasNotificacionTest;
use Tests\Feature\Configuracion\SchedulerComandosTest;
use Tests\Feature\Configuracion\TiposPlantillaSeedTest;
use Tests\Feature\ControlAcceso\CargosDisponiblesRendimientoTest;
use Tests\Feature\ControlAcceso\MiAvatarTest;
use Tests\Feature\ControlAcceso\PoliticaContrasenaUsuariosTest;
use Tests\Feature\ControlAcceso\UserAuthorizationTest;
use Tests\Feature\Dashboard\AcademyDashboardTest;
use Tests\Feature\Dashboard\AnalyticsDashboardTest;
use Tests\Feature\Dashboard\CrmDashboardTest;
use Tests\Feature\Dashboard\DashboardGlobalVencimientosTest;
use Tests\Feature\Dashboard\EcommerceDashboardTest;
use Tests\Feature\Dashboard\LogisticsDashboardTest;
use Tests\Feature\Firma\FirmaEventosAccesoTest;
use Tests\Feature\Firma\FirmaInternoFirmanteTest;
use Tests\Feature\Firma\FirmaMasivaLoteTest;
use Tests\Feature\Firma\FirmaMasivaPdfTest;
use Tests\Feature\Firma\FirmaOtpFirmanteTest;
use Tests\Feature\Firma\FirmaVariosFirmantesTest;
use Tests\Feature\Firma\TsaServiceTest;
use Tests\Feature\Gestion\ConsultaIntegralTerceroTest;
use Tests\Feature\Gestion\CorreoTelefonoUnicosTest;
use Tests\Feature\Gestion\HistorialTerceroTest;
use Tests\Feature\Gestion\TercerosAuthorizationTest;
use Tests\Feature\GestionTerceros\HabeasDataYDvNitTest;
use Tests\Feature\Metricas\ArchivosMetricasTest;
use Tests\Feature\MiBandeja\ClasificacionGrupoTest;
use Tests\Feature\MiBandeja\CombinacionCorrespondenciaTest;
use Tests\Feature\MiBandeja\ComentariosDocumentoTest;
use Tests\Feature\MiBandeja\DisenoPaginaTest;
use Tests\Feature\MiBandeja\DocumentoColaborativoRadicadoTest;
use Tests\Feature\MiBandeja\EspaciosContenidoTest;
use Tests\Feature\MiBandeja\EstilosDocumentoTest;
use Tests\Feature\MiBandeja\ExportarDocumentoTest;
use Tests\Feature\MiBandeja\FirmaDocumentoTest;
use Tests\Feature\MiBandeja\FirmaGrupoDocumentoTest;
use Tests\Feature\MiBandeja\GrupoColaborativoFirmaTest;
use Tests\Feature\MiBandeja\GrupoDocumentoTest;
use Tests\Feature\MiBandeja\GrupoFirmantesAutorizacionTest;
use Tests\Feature\MiBandeja\ImagenExportTest;
use Tests\Feature\MiBandeja\ImportarDocumentoWordTest;
use Tests\Feature\MiBandeja\LogoEncabezadoDocumentoTest;
use Tests\Feature\MiBandeja\MiBandejaEstadisticasTest;
use Tests\Feature\MiBandeja\MiBandejaFirmantesTest;
use Tests\Feature\MiBandeja\MiBandejaInternosPuedeFirmarTest;
use Tests\Feature\MiBandeja\MiDiscoCarpetaAjenaTest;
use Tests\Feature\MiBandeja\MisDocumentosEstadisticasTest;
use Tests\Feature\MiBandeja\MisDocumentosListadoTest;
use Tests\Feature\MiBandeja\NotaAlPieExportTest;
use Tests\Feature\MiBandeja\NotasTiempoRealTest;
use Tests\Feature\MiBandeja\PlantillasDocumentoTest;
use Tests\Feature\MiBandeja\PlantillasGeneradasTest;
use Tests\Feature\MiBandeja\RadicacionGrupoDocumentoTest;
use Tests\Feature\MiBandeja\SincronizarDocumentoTamanoTest;
use Tests\Feature\MiBandeja\StoreMiBandejaTempRequestTest;
use Tests\Feature\MiBandeja\TablaContenidoExportTest;
use Tests\Feature\MiBandeja\TablaEstiloExportTest;
use Tests\Feature\MiBandeja\VersionDocumentoColaborativoTest;
use Tests\Feature\OfiArchivo\AccesoExpedientesTest;
use Tests\Feature\OfiArchivo\CierreExpedienteTest;
use Tests\Feature\OfiArchivo\ClasificacionAccesoExpedienteTest;
use Tests\Feature\OfiArchivo\ConfiguracionArchivoTest;
use Tests\Feature\OfiArchivo\DepuracionDigitalTest;
use Tests\Feature\OfiArchivo\DisposicionFinalTest;
use Tests\Feature\OfiArchivo\EliminacionDocumentalTest;
use Tests\Feature\OfiArchivo\EstadisticasArchivoAlcanceTest;
use Tests\Feature\OfiArchivo\ExportExpedientesTest;
use Tests\Feature\OfiArchivo\ExportTransferenciasTest;
use Tests\Feature\OfiArchivo\FasesExpedienteTest;
use Tests\Feature\OfiArchivo\FiltroEstadoExpedientesTest;
use Tests\Feature\OfiArchivo\FoliacionNumeracionExpedienteTest;
use Tests\Feature\OfiArchivo\HistorialExpedienteTest;
use Tests\Feature\OfiArchivo\HojaControlTest;
use Tests\Feature\OfiArchivo\IndiceEncadenadoExpedienteTest;
use Tests\Feature\OfiArchivo\InmutabilidadExpedienteTest;
use Tests\Feature\OfiArchivo\IntegridadArchivoTest;
use Tests\Feature\OfiArchivo\LimitesRutasArchivoTest;
use Tests\Feature\OfiArchivo\LotesTransferenciaTest;
use Tests\Feature\OfiArchivo\PaquetePreservacionTest;
use Tests\Feature\OfiArchivo\PendientesArchivoTest;
use Tests\Feature\OfiArchivo\PrestamoConsultaElectronicaTest;
use Tests\Feature\OfiArchivo\PrestamoExpedienteTest;
use Tests\Feature\OfiArchivo\PrestamosVencidosTest;
use Tests\Feature\OfiArchivo\ReaperturaExpedienteTest;
use Tests\Feature\OfiArchivo\ResumenReporteExpedientesTest;
use Tests\Feature\OfiArchivo\SnapshotDependenciaTest;
use Tests\Feature\OfiArchivo\SugerenciaExpedienteTest;
use Tests\Feature\OfiArchivo\TercerosExpedienteTest;
use Tests\Feature\OfiArchivo\TiposDocumentalesExpedienteTest;
use Tests\Feature\OfiArchivo\TransaccionesExpedienteTest;
use Tests\Feature\OfiArchivo\TransferenciaEliminacionTest;
use Tests\Feature\OfiArchivo\UbicacionTopograficaTest;
use Tests\Feature\OfiArchivo\VerificacionCierrePqrsTest;
use Tests\Feature\Reportes\ExportVolumenTest;
use Tests\Feature\Reportes\ReportesAccesoTest;
use Tests\Feature\Reportes\ReportesExportDisenoTest;
use Tests\Feature\Reportes\ReportesFiltrosTest;
use Tests\Feature\Seguridad\ArchivoLegibleTest;
use Tests\Feature\Seguridad\CargaAdjuntosSegurosTest;
use Tests\Feature\Seguridad\CombinedSecurityMiddlewareTest;
use Tests\Feature\Seguridad\CspNonceTest;
use Tests\Feature\Seguridad\ExportSinFormulasTest;
use Tests\Feature\Seguridad\FirmaEventoWormTest;
use Tests\Feature\Seguridad\InspeccionCargasTest;
use Tests\Feature\Seguridad\InspectorArchivosTest;
use Tests\Feature\Seguridad\JefeDeArchivoHabilidadTest;
use Tests\Feature\Seguridad\LimitesSolicitudesTest;
use Tests\Feature\Transversal\HistorialNotificacionesTest;
use Tests\Feature\VentanillaUnica\AccesoAsignacionesTest;
use Tests\Feature\VentanillaUnica\AccesoPqrsTest;
use Tests\Feature\VentanillaUnica\AccesoRadicadosEnviadosTest;
use Tests\Feature\VentanillaUnica\AccesoRadicadosInternosTest;
use Tests\Feature\VentanillaUnica\AccesoRadicadosRecibidosTest;
use Tests\Feature\VentanillaUnica\AdjuntoImapInspeccionTest;
use Tests\Feature\VentanillaUnica\AdjuntoImapNombreSeguroTest;
use Tests\Feature\VentanillaUnica\AdjuntosYRespuestasLimitesTest;
use Tests\Feature\VentanillaUnica\AlertasVencimientoYBadgeTest;
use Tests\Feature\VentanillaUnica\ArchivoHelperEnviadosTest;
use Tests\Feature\VentanillaUnica\BorradoArchivosIndividualTest;
use Tests\Feature\VentanillaUnica\BorradoArchivosRadicadoTest;
use Tests\Feature\VentanillaUnica\CambioClasificacionAtomicoTest;
use Tests\Feature\VentanillaUnica\CamposInmutablesRadicadoTest;
use Tests\Feature\VentanillaUnica\CodigoDependenciaRadicadoTest;
use Tests\Feature\VentanillaUnica\ConsecutivoEnviadosTest;
use Tests\Feature\VentanillaUnica\DetalleEnviadoDesdeMiBandejaTest;
use Tests\Feature\VentanillaUnica\EmailRadicacionDedupeYRotuloPathTest;
use Tests\Feature\VentanillaUnica\ExportarMetadataAlcanceTest;
use Tests\Feature\VentanillaUnica\ExportPendientesRadicarTest;
use Tests\Feature\VentanillaUnica\ExportRadicadosEnviadosTest;
use Tests\Feature\VentanillaUnica\ExportRadicadosRecibidosTest;
use Tests\Feature\VentanillaUnica\FiltrosRadicadosEnviadosTest;
use Tests\Feature\VentanillaUnica\FiltrosRadicadosInternosTest;
use Tests\Feature\VentanillaUnica\FiltrosRadicadosRecibidosTest;
use Tests\Feature\VentanillaUnica\FirmaPqrsOtpTest;
use Tests\Feature\VentanillaUnica\FirmaPqrsTransaccionTest;
use Tests\Feature\VentanillaUnica\InternosEstadoYHistorialTest;
use Tests\Feature\VentanillaUnica\MetadataAccesoRadicadoTest;
use Tests\Feature\VentanillaUnica\MetadataArchivosPqrsTest;
use Tests\Feature\VentanillaUnica\MetadataDigitalTest;
use Tests\Feature\VentanillaUnica\MetadataListadoTest;
use Tests\Feature\VentanillaUnica\MetadataSubidaEnviadosInternosTest;
use Tests\Feature\VentanillaUnica\OcrSegundoPlanoTest;
use Tests\Feature\VentanillaUnica\OcrServicioTokenTest;
use Tests\Feature\VentanillaUnica\PermisoArchivosPqrsTest;
use Tests\Feature\VentanillaUnica\PermisoPaseCompartirRecibidosTest;
use Tests\Feature\VentanillaUnica\ProrrogaPqrsTest;
use Tests\Feature\VentanillaUnica\ProximoAVencerDiasHabilesTest;
use Tests\Feature\VentanillaUnica\RadicadoInternoDependenciaOrigenTest;
use Tests\Feature\VentanillaUnica\RadicadoRespuestaEliminarTest;
use Tests\Feature\VentanillaUnica\RadicarEmailDesdeImapTest;
use Tests\Feature\VentanillaUnica\ReservaIdentidadPqrsTest;
use Tests\Feature\VentanillaUnica\RespuestaRadicadoEmailTest;
use Tests\Feature\VentanillaUnica\RespuestasEnviadoTest;
use Tests\Feature\VentanillaUnica\RespuestasRadicadoAccesoTest;
use Tests\Feature\VentanillaUnica\RutasRecibidosAutorizadasTest;
use Tests\Feature\VentanillaUnica\StoreRadicadoInternoTest;
use Tests\Feature\VentanillaUnica\SugerirClasificacionTest;
use Tests\Feature\VentanillaUnica\TransicionEstadoPqrsTest;
use Tests\Feature\VentanillaUnica\VerDocumentoAutorizacionTest;
use Tests\Feature\Workflows\ArchivoFlujoEnExpedienteTest;
use Tests\Feature\Workflows\ArchivosNodoTest;
use Tests\Feature\Workflows\AsignarUsuarioInactivoTest;
use Tests\Feature\Workflows\AuditoriaDisenoTest;
use Tests\Feature\Workflows\AutorizacionAnidadaTest;
use Tests\Feature\Workflows\AvanceConTareasTest;
use Tests\Feature\Workflows\ChecklistPlantillaTest;
use Tests\Feature\Workflows\ChecklistTareaLecturaTest;
use Tests\Feature\Workflows\ChecklistTareaTest;
use Tests\Feature\Workflows\CicloDeVidaWorkflowTest;
use Tests\Feature\Workflows\CondicionManualTest;
use Tests\Feature\Workflows\ConfiguracionNodoTest;
use Tests\Feature\Workflows\DevolucionTest;
use Tests\Feature\Workflows\EditarTareaNodoTest;
use Tests\Feature\Workflows\EliminarWorkflowConInstanciasTest;
use Tests\Feature\Workflows\ErroresSinDetalleTest;
use Tests\Feature\Workflows\EstrategiaAsignacionTest;
use Tests\Feature\Workflows\EvidenciaTareaTest;
use Tests\Feature\Workflows\FiltroTareasTest;
use Tests\Feature\Workflows\GrafoDelWorkflowTest;
use Tests\Feature\Workflows\GuardarCanvasTest;
use Tests\Feature\Workflows\IniciarInstanciaTest;
use Tests\Feature\Workflows\IniciarTareaTest;
use Tests\Feature\Workflows\InstanciaDeOtroWorkflowTest;
use Tests\Feature\Workflows\InstanciasEnCursoDetalleTest;
use Tests\Feature\Workflows\MarcarTareasVencidasCommandTest;
use Tests\Feature\Workflows\MatrizAutorizacionWorkflowTest;
use Tests\Feature\Workflows\NodosConInstanciasEnCursoTest;
use Tests\Feature\Workflows\NodoSinSalidaTest;
use Tests\Feature\Workflows\OrigenInstanciaTest;
use Tests\Feature\Workflows\PaginacionTableroTest;
use Tests\Feature\Workflows\PerfilesWorkflowsTest;
use Tests\Feature\Workflows\PerPageConTopeTest;
use Tests\Feature\Workflows\PlantillasNodoDisenoTest;
use Tests\Feature\Workflows\PlazoDiasHabilesTest;
use Tests\Feature\Workflows\RamasParalelasTest;
use Tests\Feature\Workflows\TareasDelWorkflowTest;
use Tests\Feature\Workflows\TareasDeNodoTest;
use Tests\Feature\Workflows\TextoPlanoDisenoTest;
use Tests\Feature\Workflows\VencimientoTareasTest;
use Tests\Feature\Workflows\VisibilidadInstanciasTest;
use Tests\Feature\Workflows\WorkflowReportesTest;

/** Inventario explícito de certificación: rollback y barreras SQL obligatorios. */
return [
    AdjuntosCorreoDepuracionTest::class,
    CambioPasswordObligatorioTest::class,
    EnumeracionUsuariosTest::class,
    RevocacionSesionTest::class,
    SesionConcurrenteTest::class,
    ZonaHorariaNegocioTest::class,
    ExportOrganigramaTest::class,
    OrganigramaCiclosTest::class,
    PermisosCalidadSeederTest::class,
    AprobacionConcurrenteTRDTest::class,
    AprobacionTVDTest::class,
    ExportTVDTest::class,
    FondosAcumuladosTest::class,
    ImportacionTRDTemporalTest::class,
    ImportarTRDTest::class,
    InmutabilidadTRDTest::class,
    IntegridadEstructuraTRDTest::class,
    LimiteImportacionTVDTest::class,
    TRDScopingYRetencionExpedienteTest::class,
    TVDEstadisticasTest::class,
    CanalesNotificacionTest::class,
    ConfigNumRadicadoTest::class,
    ConfigVariasSecretosTest::class,
    FestivosColombiaTest::class,
    NotificacionTiempoRealTest::class,
    NotificarRadicadoAlTerceroTest::class,
    PasswordServerArchivoTest::class,
    PreferenciasNotificacionTest::class,
    SchedulerComandosTest::class,
    TiposPlantillaSeedTest::class,
    CargosDisponiblesRendimientoTest::class,
    MiAvatarTest::class,
    PoliticaContrasenaUsuariosTest::class,
    UserAuthorizationTest::class,
    AcademyDashboardTest::class,
    AnalyticsDashboardTest::class,
    CrmDashboardTest::class,
    DashboardGlobalVencimientosTest::class,
    EcommerceDashboardTest::class,
    LogisticsDashboardTest::class,
    FirmaEventosAccesoTest::class,
    FirmaInternoFirmanteTest::class,
    FirmaMasivaLoteTest::class,
    FirmaMasivaPdfTest::class,
    FirmaOtpFirmanteTest::class,
    FirmaVariosFirmantesTest::class,
    TsaServiceTest::class,
    ConsultaIntegralTerceroTest::class,
    CorreoTelefonoUnicosTest::class,
    HistorialTerceroTest::class,
    TercerosAuthorizationTest::class,
    HabeasDataYDvNitTest::class,
    ArchivosMetricasTest::class,
    ClasificacionGrupoTest::class,
    CombinacionCorrespondenciaTest::class,
    ComentariosDocumentoTest::class,
    DisenoPaginaTest::class,
    DocumentoColaborativoRadicadoTest::class,
    EspaciosContenidoTest::class,
    EstilosDocumentoTest::class,
    ExportarDocumentoTest::class,
    FirmaDocumentoTest::class,
    FirmaGrupoDocumentoTest::class,
    GrupoColaborativoFirmaTest::class,
    GrupoDocumentoTest::class,
    GrupoFirmantesAutorizacionTest::class,
    ImagenExportTest::class,
    ImportarDocumentoWordTest::class,
    LogoEncabezadoDocumentoTest::class,
    MiBandejaEstadisticasTest::class,
    MiBandejaFirmantesTest::class,
    MiBandejaInternosPuedeFirmarTest::class,
    MiDiscoCarpetaAjenaTest::class,
    MisDocumentosEstadisticasTest::class,
    MisDocumentosListadoTest::class,
    NotaAlPieExportTest::class,
    NotasTiempoRealTest::class,
    PlantillasDocumentoTest::class,
    PlantillasGeneradasTest::class,
    RadicacionGrupoDocumentoTest::class,
    SincronizarDocumentoTamanoTest::class,
    StoreMiBandejaTempRequestTest::class,
    TablaContenidoExportTest::class,
    TablaEstiloExportTest::class,
    VersionDocumentoColaborativoTest::class,
    AccesoExpedientesTest::class,
    CierreExpedienteTest::class,
    ClasificacionAccesoExpedienteTest::class,
    ConfiguracionArchivoTest::class,
    DepuracionDigitalTest::class,
    DisposicionFinalTest::class,
    EliminacionDocumentalTest::class,
    EstadisticasArchivoAlcanceTest::class,
    ExportExpedientesTest::class,
    ExportTransferenciasTest::class,
    FasesExpedienteTest::class,
    FiltroEstadoExpedientesTest::class,
    FoliacionNumeracionExpedienteTest::class,
    HistorialExpedienteTest::class,
    HojaControlTest::class,
    IndiceEncadenadoExpedienteTest::class,
    InmutabilidadExpedienteTest::class,
    IntegridadArchivoTest::class,
    LimitesRutasArchivoTest::class,
    LotesTransferenciaTest::class,
    PaquetePreservacionTest::class,
    PendientesArchivoTest::class,
    PrestamoConsultaElectronicaTest::class,
    PrestamoExpedienteTest::class,
    PrestamosVencidosTest::class,
    ReaperturaExpedienteTest::class,
    ResumenReporteExpedientesTest::class,
    SnapshotDependenciaTest::class,
    SugerenciaExpedienteTest::class,
    TercerosExpedienteTest::class,
    TiposDocumentalesExpedienteTest::class,
    TransaccionesExpedienteTest::class,
    TransferenciaEliminacionTest::class,
    UbicacionTopograficaTest::class,
    VerificacionCierrePqrsTest::class,
    ExportVolumenTest::class,
    ReportesAccesoTest::class,
    ReportesExportDisenoTest::class,
    ReportesFiltrosTest::class,
    ArchivoLegibleTest::class,
    CargaAdjuntosSegurosTest::class,
    CombinedSecurityMiddlewareTest::class,
    CspNonceTest::class,
    ExportSinFormulasTest::class,
    FirmaEventoWormTest::class,
    InspeccionCargasTest::class,
    InspectorArchivosTest::class,
    JefeDeArchivoHabilidadTest::class,
    LimitesSolicitudesTest::class,
    HistorialNotificacionesTest::class,
    AccesoAsignacionesTest::class,
    AccesoPqrsTest::class,
    AccesoRadicadosEnviadosTest::class,
    AccesoRadicadosInternosTest::class,
    AccesoRadicadosRecibidosTest::class,
    AdjuntoImapInspeccionTest::class,
    AdjuntoImapNombreSeguroTest::class,
    AdjuntosYRespuestasLimitesTest::class,
    AlertasVencimientoYBadgeTest::class,
    ArchivoHelperEnviadosTest::class,
    BorradoArchivosIndividualTest::class,
    BorradoArchivosRadicadoTest::class,
    CambioClasificacionAtomicoTest::class,
    CamposInmutablesRadicadoTest::class,
    CodigoDependenciaRadicadoTest::class,
    ConsecutivoEnviadosTest::class,
    DetalleEnviadoDesdeMiBandejaTest::class,
    EmailRadicacionDedupeYRotuloPathTest::class,
    ExportarMetadataAlcanceTest::class,
    ExportPendientesRadicarTest::class,
    ExportRadicadosEnviadosTest::class,
    ExportRadicadosRecibidosTest::class,
    FiltrosRadicadosEnviadosTest::class,
    FiltrosRadicadosInternosTest::class,
    FiltrosRadicadosRecibidosTest::class,
    FirmaPqrsOtpTest::class,
    FirmaPqrsTransaccionTest::class,
    InternosEstadoYHistorialTest::class,
    MetadataAccesoRadicadoTest::class,
    MetadataArchivosPqrsTest::class,
    MetadataDigitalTest::class,
    MetadataListadoTest::class,
    MetadataSubidaEnviadosInternosTest::class,
    OcrSegundoPlanoTest::class,
    OcrServicioTokenTest::class,
    PermisoArchivosPqrsTest::class,
    PermisoPaseCompartirRecibidosTest::class,
    ProrrogaPqrsTest::class,
    ProximoAVencerDiasHabilesTest::class,
    RadicadoInternoDependenciaOrigenTest::class,
    RadicadoRespuestaEliminarTest::class,
    RadicarEmailDesdeImapTest::class,
    ReservaIdentidadPqrsTest::class,
    RespuestaRadicadoEmailTest::class,
    RespuestasEnviadoTest::class,
    RespuestasRadicadoAccesoTest::class,
    RutasRecibidosAutorizadasTest::class,
    StoreRadicadoInternoTest::class,
    SugerirClasificacionTest::class,
    TransicionEstadoPqrsTest::class,
    VerDocumentoAutorizacionTest::class,
    ArchivoFlujoEnExpedienteTest::class,
    ArchivosNodoTest::class,
    AsignarUsuarioInactivoTest::class,
    AuditoriaDisenoTest::class,
    AutorizacionAnidadaTest::class,
    AvanceConTareasTest::class,
    ChecklistPlantillaTest::class,
    ChecklistTareaLecturaTest::class,
    ChecklistTareaTest::class,
    CicloDeVidaWorkflowTest::class,
    CondicionManualTest::class,
    ConfiguracionNodoTest::class,
    DevolucionTest::class,
    EditarTareaNodoTest::class,
    EliminarWorkflowConInstanciasTest::class,
    ErroresSinDetalleTest::class,
    EstrategiaAsignacionTest::class,
    EvidenciaTareaTest::class,
    FiltroTareasTest::class,
    GrafoDelWorkflowTest::class,
    GuardarCanvasTest::class,
    IniciarInstanciaTest::class,
    IniciarTareaTest::class,
    InstanciaDeOtroWorkflowTest::class,
    InstanciasEnCursoDetalleTest::class,
    MarcarTareasVencidasCommandTest::class,
    MatrizAutorizacionWorkflowTest::class,
    NodosConInstanciasEnCursoTest::class,
    NodoSinSalidaTest::class,
    OrigenInstanciaTest::class,
    PaginacionTableroTest::class,
    PerfilesWorkflowsTest::class,
    PerPageConTopeTest::class,
    PlantillasNodoDisenoTest::class,
    PlazoDiasHabilesTest::class,
    RamasParalelasTest::class,
    TareasDelWorkflowTest::class,
    TareasDeNodoTest::class,
    TextoPlanoDisenoTest::class,
    VencimientoTareasTest::class,
    VisibilidadInstanciasTest::class,
    WorkflowReportesTest::class,
];
