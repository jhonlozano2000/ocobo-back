<?php

namespace App\Http\Controllers\ControlAcceso;

use App\Helpers\ArchivoHelper;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\User;
use App\Models\Herramientas\LogGlobal;
use App\Models\ControlAcceso\UsersSession;
use App\Models\ControlAcceso\UserCargo;
use App\Http\Requests\ControlAcceso\StoreUserRequest;
use App\Http\Requests\ControlAcceso\UpdateUserRequest;
use App\Http\Requests\ControlAcceso\UpdateUserProfileRequest;
use App\Http\Requests\ControlAcceso\UpdatePasswordRequest;
use App\Http\Requests\ControlAcceso\ActivarInactivarRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Jenssegers\Agent\Agent;

class UserController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtiene un listado de usuarios del sistema con opciones de filtrado.
     *
     * Este método retorna usuarios con diferentes opciones de filtrado y carga de relaciones.
     * Incluye automáticamente el cargo activo del usuario junto con su dependencia/oficina
     * directamente relacionada. Puede incluir información detallada adicional, filtrar por
     * estado, búsqueda, etc. Es útil para diferentes interfaces que necesiten mostrar usuarios
     * con distintos niveles de detalle.
     *
     * @param Request $request La solicitud HTTP con filtros opcionales
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el listado de usuarios
     *
     * @queryParam incluir_cargos boolean optional Incluir información detallada del cargo activo. Example: true
     * @queryParam solo_activos boolean optional Solo incluir usuarios con estado activo (1). Example: true
     * @queryParam search string optional Buscar por nombre, apellido o email. Example: "Juan"
     * @queryParam con_oficina boolean optional Incluir información de oficina/sede activa. Example: true
     *
     * @response 200 {
     *   "status": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "nombres": "Juan",
     *       "apellidos": "Pérez",
     *       "email": "juan.perez@example.com",
     *       "estado": 1,
     *       "avatar_url": "http://example.com/avatars/user.jpg",
     *       "firma_url": "http://example.com/firmas/user.jpg",
     *       "cargo": {
     *         "id": 23,
     *         "nom_organico": "Gerente",
     *         "cod_organico": null,
     *         "tipo": "Cargo",
     *         "fecha_inicio": "2024-01-15",
     *         "observaciones": null
     *       },
     *       "oficina": null,
     *       "dependencia": {
     *         "id": 3,
     *         "nom_organico": "GERENCIA",
     *         "cod_organico": "100",
     *         "tipo": "Dependencia"
     *       },
     *       "pais": {
     *         "id": 1,
     *         "codigo": "CO",
     *         "nombre": "Colombia",
     *         "tipo": "Pais"
     *       },
     *       "departamento": {
     *         "id": 2,
     *         "codigo": "CUN",
     *         "nombre": "Cundinamarca",
     *         "tipo": "Departamento"
     *       },
     *       "municipio": {
     *         "id": 3,
     *         "codigo": "BOG",
     *         "nombre": "Bogotá D.C.",
     *         "tipo": "Municipio"
     *       },
     *       "roles": [
     *         {
     *           "id": 1,
     *           "name": "Administrador",
     *           "guard_name": "web",
     *           "created_at": "2024-01-15T10:00:00.000000Z",
     *           "updated_at": "2024-01-15T10:00:00.000000Z"
     *         }
     *       ]
     *     }
     *   ],
     *   "message": "Listado de usuarios obtenido exitosamente"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de usuarios",
     *   "error": "Error message"
     * }
     */
    public function index(Request $request)
    {
        try {
            // Si se solicita información de cargos o oficinas, usamos consulta con joins
            if ($request->boolean('incluir_cargos') || $request->boolean('con_oficina')) {
                return $this->indexConCargosYOficinas($request);
            }

            // Consulta estándar con relaciones de Eloquent
            $query = User::with([
                'roles',
                'cargoActivo.cargo',
                'divisionPolitica.padre.padre'
            ]);

            // Filtro opcional: solo usuarios activos
            if ($request->boolean('solo_activos')) {
                $query->where('estado', 1);
            }

            // Filtro opcional: búsqueda por nombre, apellido o email
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombres', 'like', "%{$search}%")
                        ->orWhere('apellidos', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $users = $query->orderBy('nombres')
                ->orderBy('apellidos')
                ->get()
                ->map(function ($user) {
                    $userData = $user->append(['avatar_url', 'firma_url'])->toArray();

                    // Eliminar campos redundantes
                    if (isset($userData['cargos'])) {
                        unset($userData['cargos']);
                    }
                    if (isset($userData['cargo_activo'])) {
                        unset($userData['cargo_activo']);
                    }
                    if (isset($userData['division_politica'])) {
                        unset($userData['division_politica']);
                    }

                    // Inicializar información de cargo, oficina, dependencia y división política como null
                    // Asegurar que siempre estén presentes en la respuesta
                    $userData = array_merge($userData, [
                        'cargo' => null,
                        'oficina' => null,
                        'dependencia' => null,
                        'pais' => null,
                        'departamento' => null,
                        'municipio' => null
                    ]);

                    // Obtener información de división política (país, departamento, municipio)
                    if ($user->divi_poli_id && $user->divisionPolitica) {
                        $diviPoli = $user->divisionPolitica;

                        // Cargar la relación padre si no está cargada
                        if (!$diviPoli->relationLoaded('padre')) {
                            $diviPoli->load('padre');
                        }

                        // Determinar qué tipo es y obtener la jerarquía completa
                        if ($diviPoli->tipo === 'Municipio') {
                            // Si es municipio, el padre es departamento y el padre del departamento es país
                            $departamento = $diviPoli->padre;

                            // Cargar el padre del departamento (país) si no está cargado
                            if ($departamento && !$departamento->relationLoaded('padre')) {
                                $departamento->load('padre');
                            }

                            $pais = $departamento ? $departamento->padre : null;

                            $userData['municipio'] = [
                                'id' => $diviPoli->id,
                                'codigo' => $diviPoli->codigo,
                                'nombre' => $diviPoli->nombre,
                                'tipo' => $diviPoli->tipo
                            ];

                            if ($departamento) {
                                $userData['departamento'] = [
                                    'id' => $departamento->id,
                                    'codigo' => $departamento->codigo,
                                    'nombre' => $departamento->nombre,
                                    'tipo' => $departamento->tipo
                                ];
                            }

                            if ($pais) {
                                $userData['pais'] = [
                                    'id' => $pais->id,
                                    'codigo' => $pais->codigo,
                                    'nombre' => $pais->nombre,
                                    'tipo' => $pais->tipo
                                ];
                            }
                        } elseif ($diviPoli->tipo === 'Departamento') {
                            // Si es departamento, el padre es país
                            // Cargar el padre si no está cargado
                            if (!$diviPoli->relationLoaded('padre')) {
                                $diviPoli->load('padre');
                            }

                            $pais = $diviPoli->padre;

                            $userData['departamento'] = [
                                'id' => $diviPoli->id,
                                'codigo' => $diviPoli->codigo,
                                'nombre' => $diviPoli->nombre,
                                'tipo' => $diviPoli->tipo
                            ];

                            if ($pais) {
                                $userData['pais'] = [
                                    'id' => $pais->id,
                                    'codigo' => $pais->codigo,
                                    'nombre' => $pais->nombre,
                                    'tipo' => $pais->tipo
                                ];
                            }
                        } elseif ($diviPoli->tipo === 'Pais') {
                            // Si es país, solo tenemos país
                            $userData['pais'] = [
                                'id' => $diviPoli->id,
                                'codigo' => $diviPoli->codigo,
                                'nombre' => $diviPoli->nombre,
                                'tipo' => $diviPoli->tipo
                            ];
                        }
                    }

                    if ($user->cargoActivo && $user->cargoActivo->cargo) {
                        $cargo = $user->cargoActivo->cargo;

                        // Información del cargo
                        $userData['cargo'] = [
                            'id' => $cargo->id,
                            'nom_organico' => $cargo->nom_organico,
                            'cod_organico' => $cargo->cod_organico,
                            'tipo' => $cargo->tipo,
                            'fecha_inicio' => $user->cargoActivo->fecha_inicio?->format('Y-m-d'),
                            'observaciones' => $user->cargoActivo->observaciones
                        ];

                        // Usar el método getJerarquiaCompleta() para obtener la jerarquía
                        $jerarquia = $cargo->getJerarquiaCompleta();

                        // Buscar la dependencia y oficina directamente relacionadas al cargo
                        $cargoIndex = -1;

                        // Encontrar la posición del cargo en la jerarquía
                        foreach ($jerarquia as $index => $nivel) {
                            if ($nivel['id'] === $cargo->id && $nivel['tipo'] === 'Cargo') {
                                $cargoIndex = $index;
                                break;
                            }
                        }

                        // Si encontramos el cargo, buscar su dependencia/oficina padre directa
                        if ($cargoIndex > 0) {
                            $parentDirecto = $jerarquia[$cargoIndex - 1]; // El elemento anterior es el padre directo

                            if ($parentDirecto['tipo'] === 'Oficina') {
                                $userData['oficina'] = [
                                    'id' => $parentDirecto['id'],
                                    'nom_organico' => $parentDirecto['nom_organico'],
                                    'cod_organico' => $parentDirecto['cod_organico'],
                                    'tipo' => $parentDirecto['tipo']
                                ];
                            } elseif ($parentDirecto['tipo'] === 'Dependencia') {
                                $userData['dependencia'] = [
                                    'id' => $parentDirecto['id'],
                                    'nom_organico' => $parentDirecto['nom_organico'],
                                    'cod_organico' => $parentDirecto['cod_organico'],
                                    'tipo' => $parentDirecto['tipo']
                                ];
                            }
                        }
                    }

                    return $userData;
                });

            return $this->successResponse($users, 'Listado de usuarios obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de usuarios', $e->getMessage(), 500);
        }
    }

    /**
     * Método privado para obtener usuarios con información detallada de cargos y oficinas.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    private function indexConCargosYOficinas(Request $request)
    {
        $selectFields = [
            'users.id',
            'users.nombres',
            'users.apellidos',
            'users.email',
            'users.num_docu',
            'users.estado',
            'users.avatar',
            'users.firma',
            'users.divi_poli_id'
        ];

        // Agregar campos de cargo si se solicita
        if ($request->boolean('incluir_cargos')) {
            $selectFields = array_merge($selectFields, [
                'calidad_organigrama.id as cargo_id',
                'calidad_organigrama.nom_organico as cargo_nombre',
                'calidad_organigrama.cod_organico as cargo_codigo',
                'calidad_organigrama.tipo as cargo_tipo',
                'users_cargos.fecha_inicio',
                'users_cargos.observaciones'
            ]);
        }

        // Agregar campos de oficina si se solicita
        if ($request->boolean('con_oficina')) {
            $selectFields = array_merge($selectFields, [
                'config_sedes.id as oficina_id',
                'config_sedes.nombre as oficina_nombre',
                'config_sedes.codigo as oficina_codigo',
                'config_sedes.direccion as oficina_direccion'
            ]);
        }

        $query = User::select($selectFields);

        // Left join con cargos si se solicita
        if ($request->boolean('incluir_cargos')) {
            $query->leftJoin('users_cargos', function ($join) {
                $join->on('users.id', '=', 'users_cargos.user_id')
                    ->where('users_cargos.estado', true)
                    ->whereNull('users_cargos.fecha_fin');
            })
                ->leftJoin('calidad_organigrama', 'users_cargos.cargo_id', '=', 'calidad_organigrama.id');
        }

        // Left join con oficinas si se solicita
        if ($request->boolean('con_oficina')) {
            $query->leftJoin('users_sedes', function ($join) {
                $join->on('users.id', '=', 'users_sedes.user_id')
                    ->where('users_sedes.estado', true);
            })
                ->leftJoin('config_sedes', 'users_sedes.sede_id', '=', 'config_sedes.id');
        }

        // Aplicar filtros
        if ($request->boolean('solo_activos')) {
            $query->where('users.estado', 1);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('users.nombres', 'like', "%{$search}%")
                    ->orWhere('users.apellidos', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        // Obtener los usuarios y cargar las relaciones de división política de forma eficiente
        $usuariosRaw = $query->orderBy('users.nombres')
            ->orderBy('users.apellidos')
            ->get();

        // Obtener todos los IDs únicos de división política
        $diviPoliIds = $usuariosRaw->pluck('divi_poli_id')->filter()->unique();

        // Cargar todas las divisiones políticas con sus padres de una vez
        $divisionesPoliticas = \App\Models\Configuracion\ConfigDiviPoli::with(['padre.padre'])
            ->whereIn('id', $diviPoliIds)
            ->get()
            ->keyBy('id');

        $usuarios = $usuariosRaw->map(function ($user) use ($request, $divisionesPoliticas) {
            $userData = [
                'id' => $user->id,
                'nombres' => $user->nombres,
                'apellidos' => $user->apellidos,
                'email' => $user->email,
                'num_docu' => $user->num_docu,
                'estado' => $user->estado,
                'avatar_url' => \App\Helpers\ArchivoHelper::obtenerUrl($user->avatar, 'avatars'),
                'firma_url' => \App\Helpers\ArchivoHelper::obtenerUrl($user->firma, 'firmas'),
                'pais' => null,
                'departamento' => null,
                'municipio' => null
            ];

            // Agregar información de división política (país, departamento, municipio)
            if (isset($user->divi_poli_id) && $user->divi_poli_id && isset($divisionesPoliticas[$user->divi_poli_id])) {
                $diviPoli = $divisionesPoliticas[$user->divi_poli_id];

                // Determinar qué tipo es y obtener la jerarquía completa
                if ($diviPoli->tipo === 'Municipio') {
                    $departamento = $diviPoli->padre;
                    $pais = $departamento ? $departamento->padre : null;

                    $userData['municipio'] = [
                        'id' => $diviPoli->id,
                        'codigo' => $diviPoli->codigo,
                        'nombre' => $diviPoli->nombre,
                        'tipo' => $diviPoli->tipo
                    ];

                    if ($departamento) {
                        $userData['departamento'] = [
                            'id' => $departamento->id,
                            'codigo' => $departamento->codigo,
                            'nombre' => $departamento->nombre,
                            'tipo' => $departamento->tipo
                        ];
                    }

                    if ($pais) {
                        $userData['pais'] = [
                            'id' => $pais->id,
                            'codigo' => $pais->codigo,
                            'nombre' => $pais->nombre,
                            'tipo' => $pais->tipo
                        ];
                    }
                } elseif ($diviPoli->tipo === 'Departamento') {
                    $pais = $diviPoli->padre;

                    $userData['departamento'] = [
                        'id' => $diviPoli->id,
                        'codigo' => $diviPoli->codigo,
                        'nombre' => $diviPoli->nombre,
                        'tipo' => $diviPoli->tipo
                    ];

                    if ($pais) {
                        $userData['pais'] = [
                            'id' => $pais->id,
                            'codigo' => $pais->codigo,
                            'nombre' => $pais->nombre,
                            'tipo' => $pais->tipo
                        ];
                    }
                } elseif ($diviPoli->tipo === 'Pais') {
                    $userData['pais'] = [
                        'id' => $diviPoli->id,
                        'codigo' => $diviPoli->codigo,
                        'nombre' => $diviPoli->nombre,
                        'tipo' => $diviPoli->tipo
                    ];
                }
            }

            // Agregar información de cargo si se solicita
            if ($request->boolean('incluir_cargos')) {
                $userData['cargo'] = isset($user->cargo_id) ? [
                    'id' => $user->cargo_id,
                    'nom_organico' => $user->cargo_nombre,
                    'cod_organico' => $user->cargo_codigo,
                    'tipo' => $user->cargo_tipo,
                    'fecha_inicio' => $user->fecha_inicio,
                    'observaciones' => $user->observaciones
                ] : null;
            }

            // Agregar información de oficina si se solicita
            if ($request->boolean('con_oficina')) {
                $userData['oficina'] = isset($user->oficina_id) ? [
                    'id' => $user->oficina_id,
                    'nombre' => $user->oficina_nombre,
                    'codigo' => $user->oficina_codigo,
                    'direccion' => $user->oficina_direccion
                ] : null;
            }

            return $userData;
        });

        return $this->successResponse($usuarios, 'Listado de usuarios obtenido exitosamente');
    }

    /**
     * Crea un nuevo usuario en el sistema.
     *
     * Este método permite crear un nuevo usuario con todos sus datos personales,
     * archivos (avatar y firma) y asignación de roles. El proceso se ejecuta
     * dentro de una transacción para garantizar la integridad de los datos.
     * Si algo falla, se eliminan los archivos subidos y se revierte la transacción.
     *
     * @param StoreUserRequest $request La solicitud HTTP validada con los datos del usuario
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el usuario creado
     *
     * @bodyParam divi_poli_id integer required ID de la división política. Example: 1
     * @bodyParam num_docu string required Número de documento único. Example: "12345678"
     * @bodyParam nombres string required Nombres del usuario. Example: "Juan Carlos"
     * @bodyParam apellidos string required Apellidos del usuario. Example: "Pérez García"
     * @bodyParam tel string Teléfono fijo. Example: "1234567"
     * @bodyParam movil string Teléfono móvil. Example: "3001234567"
     * @bodyParam dir string Dirección. Example: "Calle 123 #45-67"
     * @bodyParam email string required Email único. Example: "juan.perez@example.com"
     * @bodyParam password string required Contraseña. Example: "Password123!"
     * @bodyParam estado boolean Estado del usuario. Example: true
     * @bodyParam roles array required Array de nombres de roles. Example: ["admin", "editor"]
     * @bodyParam avatar file Archivo de avatar. Example: "user.jpg"
     * @bodyParam firma file Archivo de firma. Example: "signature.jpg"
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Usuario creado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "nombres": "Juan Carlos",
     *     "apellidos": "Pérez García",
     *     "email": "juan.perez@example.com",
     *     "avatar_url": "http://example.com/avatars/user.jpg",
     *     "firma_url": "http://example.com/firmas/signature.jpg",
     *     "roles": [
     *       {
     *         "id": 1,
     *         "name": "admin"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "email": ["El email ya se encuentra registrado."],
     *     "num_docu": ["El número de documento ya se encuentra registrado."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al crear el usuario",
     *   "error": "Error message"
     * }
     */
    public function store(StoreUserRequest $request)
    {
        $validatedData = $request->validated();

        DB::beginTransaction();

        try {
            // Procesar archivos si se proporcionan
            if ($request->hasFile('avatar')) {
                $validatedData['avatar'] = ArchivoHelper::guardarArchivo($request, 'avatar', 'avatars', null);
            }

            if ($request->hasFile('firma')) {
                $validatedData['firma'] = ArchivoHelper::guardarArchivo($request, 'firma', 'firmas', null);
            }

            // Cifrar contraseña
            $validatedData['password'] = Hash::make($validatedData['password']);

            // Convertir estado a booleano si se proporciona
            if (isset($validatedData['estado'])) {
                $validatedData['estado'] = filter_var($validatedData['estado'], FILTER_VALIDATE_BOOLEAN);
            }

            // Crear usuario
            $user = User::create($validatedData);

            // Asignar roles
            if ($request->has('roles')) {
                $user->syncRoles($request->roles);
            }

            // Asignar cargo si se proporciona
            if ($request->has('cargo_id') && $request->cargo_id) {
                $fechaInicio = $request->fecha_inicio_cargo ?? now()->format('Y-m-d');
                $observaciones = $request->observaciones_cargo;

                $user->asignarCargo(
                    $request->cargo_id,
                    $fechaInicio,
                    $observaciones
                );
            }

            DB::commit();

            return $this->successResponse(
                $user->load('roles')->append(['avatar_url', 'firma_url']),
                'Usuario creado exitosamente',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            // Limpiar archivos subidos en caso de error
            if (isset($validatedData['avatar'])) {
                Storage::disk('avatars')->delete($validatedData['avatar']);
            }
            if (isset($validatedData['firma'])) {
                Storage::disk('firmas')->delete($validatedData['firma']);
            }

            return $this->errorResponse('Error al crear el usuario', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un usuario específico por su ID.
     *
     * Este método permite obtener la información detallada de un usuario específico,
     * incluyendo sus URLs de archivos (avatar y firma), roles, y su cargo activo
     * junto con la dependencia/oficina directamente relacionada. Es útil para mostrar
     * los detalles de un usuario o para formularios de edición.
     *
     * @param string $id El ID del usuario a obtener
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el usuario
     *
     * @urlParam id integer required El ID del usuario a actualizar. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Usuario encontrado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "nombres": "Juan Carlos",
     *     "apellidos": "Pérez García",
     *     "email": "juan.perez@example.com",
     *     "avatar_url": "http://example.com/avatars/user.jpg",
     *     "firma_url": "http://example.com/firmas/signature.jpg",
     *     "cargo": {
     *       "id": 23,
     *       "nom_organico": "Gerente",
     *       "cod_organico": null,
     *       "tipo": "Cargo",
     *       "fecha_inicio": "2024-01-15",
     *       "observaciones": null
     *     },
     *     "oficina": null,
     *     "dependencia": {
     *       "id": 3,
     *       "nom_organico": "GERENCIA",
     *       "cod_organico": "100",
     *       "tipo": "Dependencia"
     *     },
     *     "roles": [
     *       {
     *         "id": 1,
     *         "name": "Administrador",
     *         "guard_name": "web"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Usuario no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el usuario",
     *   "error": "Error message"
     * }
     */
    public function show(string $id)
    {
        try {
            $user = User::with(['roles', 'cargoActivo.cargo', 'divisionPolitica.padre.padre'])->find($id);

            if (!$user) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            // Preparar datos del usuario
            $userData = $user->append(['avatar_url', 'firma_url'])->toArray();

            // Eliminar campos redundantes
            if (isset($userData['cargos'])) {
                unset($userData['cargos']);
            }
            if (isset($userData['cargo_activo'])) {
                unset($userData['cargo_activo']);
            }
            if (isset($userData['division_politica'])) {
                unset($userData['division_politica']);
            }

            // Agregar información de cargo, oficina, dependencia y división política
            $userData['cargo'] = null;
            $userData['oficina'] = null;
            $userData['dependencia'] = null;
            $userData['pais'] = null;
            $userData['departamento'] = null;
            $userData['municipio'] = null;

            // Obtener información de división política (país, departamento, municipio)
            if ($user->divi_poli_id && $user->divisionPolitica) {
                $diviPoli = $user->divisionPolitica;

                // Cargar la relación padre si no está cargada
                if (!$diviPoli->relationLoaded('padre')) {
                    $diviPoli->load('padre');
                }

                // Determinar qué tipo es y obtener la jerarquía completa
                if ($diviPoli->tipo === 'Municipio') {
                    // Si es municipio, el padre es departamento y el padre del departamento es país
                    $departamento = $diviPoli->padre;

                    // Cargar el padre del departamento (país) si no está cargado
                    if ($departamento && !$departamento->relationLoaded('padre')) {
                        $departamento->load('padre');
                    }

                    $pais = $departamento ? $departamento->padre : null;

                    $userData['municipio'] = [
                        'id' => $diviPoli->id,
                        'codigo' => $diviPoli->codigo,
                        'nombre' => $diviPoli->nombre,
                        'tipo' => $diviPoli->tipo
                    ];

                    if ($departamento) {
                        $userData['departamento'] = [
                            'id' => $departamento->id,
                            'codigo' => $departamento->codigo,
                            'nombre' => $departamento->nombre,
                            'tipo' => $departamento->tipo
                        ];
                    }

                    if ($pais) {
                        $userData['pais'] = [
                            'id' => $pais->id,
                            'codigo' => $pais->codigo,
                            'nombre' => $pais->nombre,
                            'tipo' => $pais->tipo
                        ];
                    }
                } elseif ($diviPoli->tipo === 'Departamento') {
                    // Si es departamento, el padre es país
                    $pais = $diviPoli->padre;

                    $userData['departamento'] = [
                        'id' => $diviPoli->id,
                        'codigo' => $diviPoli->codigo,
                        'nombre' => $diviPoli->nombre,
                        'tipo' => $diviPoli->tipo
                    ];

                    if ($pais) {
                        $userData['pais'] = [
                            'id' => $pais->id,
                            'codigo' => $pais->codigo,
                            'nombre' => $pais->nombre,
                            'tipo' => $pais->tipo
                        ];
                    }
                } elseif ($diviPoli->tipo === 'Pais') {
                    // Si es país, solo tenemos país
                    $userData['pais'] = [
                        'id' => $diviPoli->id,
                        'codigo' => $diviPoli->codigo,
                        'nombre' => $diviPoli->nombre,
                        'tipo' => $diviPoli->tipo
                    ];
                }
            }

            if ($user->cargoActivo && $user->cargoActivo->cargo) {
                $cargo = $user->cargoActivo->cargo;

                // Información del cargo
                $userData['cargo'] = [
                    'id' => $cargo->id,
                    'nom_organico' => $cargo->nom_organico,
                    'cod_organico' => $cargo->cod_organico,
                    'tipo' => $cargo->tipo,
                    'fecha_inicio' => $user->cargoActivo->fecha_inicio?->format('Y-m-d'),
                    'observaciones' => $user->cargoActivo->observaciones
                ];

                // Usar el método getJerarquiaCompleta() para obtener la jerarquía
                $jerarquia = $cargo->getJerarquiaCompleta();

                // Buscar la dependencia y oficina directamente relacionadas al cargo
                $cargoIndex = -1;

                // Encontrar la posición del cargo en la jerarquía
                foreach ($jerarquia as $index => $nivel) {
                    if ($nivel['id'] === $cargo->id && $nivel['tipo'] === 'Cargo') {
                        $cargoIndex = $index;
                        break;
                    }
                }

                // Si encontramos el cargo, buscar su dependencia/oficina padre directa
                if ($cargoIndex > 0) {
                    $parentDirecto = $jerarquia[$cargoIndex - 1]; // El elemento anterior es el padre directo

                    if ($parentDirecto['tipo'] === 'Oficina') {
                        $userData['oficina'] = [
                            'id' => $parentDirecto['id'],
                            'nom_organico' => $parentDirecto['nom_organico'],
                            'cod_organico' => $parentDirecto['cod_organico'],
                            'tipo' => $parentDirecto['tipo']
                        ];
                    } elseif ($parentDirecto['tipo'] === 'Dependencia') {
                        $userData['dependencia'] = [
                            'id' => $parentDirecto['id'],
                            'nom_organico' => $parentDirecto['nom_organico'],
                            'cod_organico' => $parentDirecto['cod_organico'],
                            'tipo' => $parentDirecto['tipo']
                        ];
                    }
                }
            }

            return $this->successResponse(
                $userData,
                'Usuario encontrado exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el usuario', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza un usuario existente en el sistema.
     *
     * Este método permite modificar los datos de un usuario existente, incluyendo
     * la actualización de archivos (avatar y firma), roles y cargos. El proceso se ejecuta
     * dentro de una transacción para garantizar la integridad de los datos.
     * Los archivos antiguos se eliminan solo después de que la actualización sea exitosa.
     *
     * COMPORTAMIENTO DE CARGOS:
     * - Si se envía 'cargo_id' con un valor: Se asigna ese cargo al usuario (finalizando cargo anterior)
     * - Si se envía 'cargo_id' como null/vacío: Se deshabilitan TODOS los cargos activos del usuario
     * - Si NO se envía 'cargo_id': Los cargos del usuario NO se modifican
     *
     * @param UpdateUserRequest $request La solicitud HTTP validada con los datos actualizados
     * @param int $id El ID del usuario a actualizar
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el usuario actualizado
     *
     * @bodyParam divi_poli_id integer ID de la división política. Example: 1
     * @bodyParam num_docu string Número de documento único. Example: "12345678"
     * @bodyParam nombres string Nombres del usuario. Example: "Juan Carlos"
     * @bodyParam apellidos string Apellidos del usuario. Example: "Pérez García"
     * @bodyParam tel string Teléfono fijo. Example: "1234567"
     * @bodyParam movil string Teléfono móvil. Example: "3001234567"
     * @bodyParam dir string Dirección. Example: "Calle 123 #45-67"
     * @bodyParam email string Email único. Example: "juan.perez@example.com"
     * @bodyParam password string Nueva contraseña (opcional). Example: "NewPassword123!"
     * @bodyParam estado boolean Estado del usuario. Example: true
     * @bodyParam roles array Array de nombres de roles. Example: ["admin", "editor"]
     * @bodyParam avatar file Archivo de avatar. Example: "new_user.jpg"
     * @bodyParam firma file Archivo de firma. Example: "new_signature.jpg"
     * @bodyParam cargo_id integer optional ID del cargo a asignar (null/vacío deshabilita todos los cargos). Example: 123
     * @bodyParam fecha_inicio_cargo string optional Fecha de inicio del cargo. Example: "2024-01-15"
     * @bodyParam observaciones_cargo string optional Observaciones del cargo. Example: "Cargo temporal"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Usuario actualizado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "nombres": "Juan Carlos",
     *     "apellidos": "Pérez García",
     *     "email": "juan.perez@example.com",
     *     "avatar_url": "http://example.com/avatars/new_user.jpg",
     *     "firma_url": "http://example.com/firmas/new_signature.jpg",
     *     "roles": [
     *       {
     *         "id": 1,
     *         "name": "admin"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "email": ["El email ya se encuentra registrado."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar el usuario",
     *   "error": "Error message"
     * }
     */
    public function update(UpdateUserRequest $request, $id)
    {
        $validatedData = [];

        try {
            DB::beginTransaction();

            // Buscar el modelo por ID
            $user = User::findOrFail($id);

            // Guardar rutas de archivos antiguos
            $oldAvatarPath = $user->avatar;
            $oldFirmaPath = $user->firma;

            // Obtener solo los campos que están presentes en la petición y validados
            $validatedData = $request->validated();

            // Procesar archivos si se proporcionan
            $user->avatar = ArchivoHelper::guardarArchivo($request, 'avatar', 'avatars', $user->avatar);
            $user->firma = ArchivoHelper::guardarArchivo($request, 'firma', 'firmas', $user->firma);

            // Procesar contraseña si se proporciona
            if (!empty($validatedData['password'])) {
                $validatedData['password'] = Hash::make($validatedData['password']);
            } else {
                unset($validatedData['password']);
            }

            // Convertir estado a booleano si se proporciona
            if (isset($validatedData['estado'])) {
                $validatedData['estado'] = filter_var($validatedData['estado'], FILTER_VALIDATE_BOOLEAN);
            }

            // Actualizar el modelo con los datos validados
            if (!empty($validatedData)) {
                $user->fill($validatedData);
                $user->save();
            }

            // Sincronizar roles
            if ($request->has('roles')) {
                $user->syncRoles($request->roles);
            }

            // Manejo de cargos
            if ($request->has('cargo_id')) {
                if ($request->cargo_id) {
                    // Si se envía un cargo_id, asignar ese cargo
                    $fechaInicio = $request->fecha_inicio_cargo ?? now()->format('Y-m-d');
                    $observaciones = $request->observaciones_cargo;

                    $user->asignarCargo(
                        $request->cargo_id,
                        $fechaInicio,
                        $observaciones
                    );
                } else {
                    // Si cargo_id es null o vacío, deshabilitar todos los cargos del usuario
                    $this->deshabilitarTodosCargosUsuario($user);
                }
            }

            // Refrescar el modelo para obtener los datos actualizados
            $user->refresh();

            DB::commit();

            // Eliminar archivos antiguos después de confirmar la transacción
            if ($request->hasFile('avatar') && $oldAvatarPath) {
                Storage::disk('avatars')->delete($oldAvatarPath);
            }
            if ($request->hasFile('firma') && $oldFirmaPath) {
                Storage::disk('firmas')->delete($oldFirmaPath);
            }

            return $this->successResponse(
                $user->load('roles')->append(['avatar_url', 'firma_url']),
                'Usuario actualizado exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            // Limpiar archivos nuevos en caso de error
            if (isset($validatedData['avatar'])) {
                Storage::disk('avatars')->delete($validatedData['avatar']);
            }
            if (isset($validatedData['firma'])) {
                Storage::disk('firmas')->delete($validatedData['firma']);
            }

            return $this->errorResponse('Error al actualizar el usuario', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un usuario del sistema.
     *
     * Este método permite eliminar un usuario del sistema junto con todos sus
     * archivos asociados (avatar y firma). El proceso se ejecuta dentro de una
     * transacción para garantizar la integridad de los datos.
     *
     * @param string $id El ID del usuario a eliminar
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @urlParam id integer required El ID del usuario a eliminar. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Usuario eliminado exitosamente"
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Usuario no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al eliminar el usuario",
     *   "error": "Error message"
     * }
     */
    public function destroy(string $id)
    {
        DB::beginTransaction();

        try {
            $user = User::find($id);

            if (!$user) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            // Eliminar archivos
            ArchivoHelper::eliminarArchivo($user->avatar, 'avatars');
            ArchivoHelper::eliminarArchivo($user->firma, 'firmas');

            // Eliminar usuario
            $user->delete();

            DB::commit();

            return $this->successResponse(null, 'Usuario eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el usuario', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene estadísticas de usuarios del sistema.
     *
     * Este método proporciona estadísticas generales sobre los usuarios del sistema,
     * incluyendo el total de usuarios, usuarios activos/inactivos y sesiones.
     * Es útil para dashboards de administración y reportes.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las estadísticas
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Estadísticas obtenidas exitosamente",
     *   "data": {
     *     "total_users": 150,
     *     "total_users_activos": 120,
     *     "total_users_inactivos": 30,
     *     "total_sesiones": 45
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener las estadísticas",
     *   "error": "Error message"
     * }
     */
    public function estadisticas()
    {
        try {
            $totalUsers = User::count();
            $totalUsersActivos = User::where('estado', 1)->count();
            $totalUsersInactivos = User::where('estado', 0)->count();
            $totalSesiones = DB::table('users_sessions')->count();

            $estadisticas = [
                'total_users' => $totalUsers,
                'total_users_activos' => $totalUsersActivos,
                'total_users_inactivos' => $totalUsersInactivos,
                'total_sesiones' => $totalSesiones,
            ];

            return $this->successResponse($estadisticas, 'Estadísticas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las estadísticas', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza la información del perfil del usuario autenticado.
     *
     * Este método permite al usuario autenticado actualizar su información
     * personal básica (nombres y apellidos). Es útil para que los usuarios
     * puedan mantener sus datos actualizados.
     *
     * @param UpdateUserProfileRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el usuario actualizado
     *
     * @bodyParam nombres string required Nombres del usuario. Example: "Juan Carlos"
     * @bodyParam apellidos string required Apellidos del usuario. Example: "Pérez García"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Perfil actualizado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "nombres": "Juan Carlos",
     *     "apellidos": "Pérez García",
     *     "avatar_url": "http://example.com/avatars/user.jpg",
     *     "firma_url": "http://example.com/firmas/signature.jpg"
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "nombres": ["Los nombres son obligatorios."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar el perfil",
     *   "error": "Error message"
     * }
     */
    public function updateUserProfile(UpdateUserProfileRequest $request)
    {
        try {
            $user = Auth::user();
            $user->update($request->validated());

            return $this->successResponse(
                $user->append(['avatar_url', 'firma_url']),
                'Perfil actualizado exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar el perfil', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza la contraseña del usuario autenticado.
     *
     * Este método permite al usuario autenticado cambiar su contraseña.
     * Requiere la contraseña actual para verificar la identidad del usuario
     * y valida que la nueva contraseña cumpla con los requisitos de seguridad.
     *
     * @param UpdatePasswordRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando el cambio
     *
     * @bodyParam current_password string required Contraseña actual. Example: "OldPassword123!"
     * @bodyParam password string required Nueva contraseña. Example: "NewPassword123!"
     * @bodyParam password_confirmation string required Confirmación de nueva contraseña. Example: "NewPassword123!"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Contraseña actualizada exitosamente"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "current_password": ["La contraseña actual que ingresaste no es correcta."],
     *     "password": ["La nueva contraseña debe contener al menos un símbolo."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar la contraseña",
     *   "error": "Error message"
     * }
     */
    public function updatePassword(UpdatePasswordRequest $request)
    {
        try {
            $user = Auth::user();

            // Verificar contraseña actual
            if (!Hash::check($request->validated('current_password'), $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'La contraseña actual que ingresaste no es correcta.',
                ]);
            }

            // Actualizar contraseña
            $user->forceFill([
                'password' => Hash::make($request->validated('password')),
            ])->save();

            return $this->successResponse(null, 'Contraseña actualizada exitosamente');
        } catch (ValidationException $e) {
            return $this->errorResponse('Datos de validación incorrectos', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar la contraseña', $e->getMessage(), 500);
        }
    }

    /**
     * Lista usuarios activos con su respectiva oficina y dependencia.
     *
     * Este método retorna todos los usuarios que tienen estado activo junto con
     * la información de su oficina (sede) activa y dependencia (cargo) activa.
     * Es útil para reportes y consultas administrativas donde se necesita ver
     * la estructura organizacional actual.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el listado de usuarios activos
     *
     * @response 200 {
     *   "status": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "nombres": "Juan",
     *       "apellidos": "Pérez",
     *       "email": "juan.perez@example.com",
     *       "num_docu": "12345678",
     *       "oficina": {
     *         "id": 2,
     *         "nom_organico": "Oficina Principal",
     *         "cod_organico": "OP001",
     *         "tipo": "Oficina"
     *       },
     *       "dependencia": {
     *         "id": 1,
     *         "nom_organico": "Dirección de Sistemas",
     *         "cod_organico": "DS001",
     *         "tipo": "Dependencia"
     *       },
     *       "cargo": {
     *         "id": 3,
     *         "nom_organico": "Analista de Sistemas",
     *         "cod_organico": "AS001",
     *         "tipo": "Cargo",
     *         "fecha_inicio": "2024-01-15",
     *         "observaciones": "Cargo principal"
     *       }
     *     }
     *   ],
     *   "message": "Listado de usuarios activos con oficina y dependencia obtenido exitosamente"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de usuarios activos",
     *   "error": "Error message"
     * }
     */
    public function usuariosActivosConOficinaYDependencia()
    {
        try {
            // Obtener usuarios activos con sus cargos activos y relaciones
            $usuarios = User::with([
                'cargoActivo.cargo.parent.parent', // Cargo -> Oficina -> Dependencia
                'cargoActivo.cargo.parent' // Cargo -> Oficina (si existe)
            ])
                ->where('estado', 1)
                ->orderBy('nombres')
                ->orderBy('apellidos')
                ->get()
                ->map(function ($user) {
                    $cargoActivo = $user->cargoActivo;

                    // Inicializar datos base del usuario
                    $usuarioData = [
                        'id' => $user->id,
                        'nombres' => $user->nombres,
                        'apellidos' => $user->apellidos,
                        'email' => $user->email,
                        'num_docu' => $user->num_docu,
                        'oficina' => null,
                        'dependencia' => null,
                        'cargo' => null
                    ];

                    // Si el usuario tiene cargo activo
                    if ($cargoActivo && $cargoActivo->cargo) {
                        $cargo = $cargoActivo->cargo;

                        // Información del cargo
                        $usuarioData['cargo'] = [
                            'id' => $cargoActivo->id, // ID de users_cargos, no del organigrama
                            'organigrama_id' => $cargo->id, // ID del organigrama
                            'nom_organico' => $cargo->nom_organico,
                            'cod_organico' => $cargo->cod_organico,
                            'tipo' => $cargo->tipo,
                            'fecha_inicio' => $cargoActivo->fecha_inicio?->format('Y-m-d'),
                            'observaciones' => $cargoActivo->observaciones
                        ];

                        // Buscar la oficina (padre del cargo) - Cargar explícitamente la relación
                        $oficina = null;
                        if ($cargo->parent) {
                            // Si parent es un ID, cargar el modelo completo
                            if (is_numeric($cargo->parent)) {
                                $oficina = \App\Models\Calidad\CalidadOrganigrama::with('parent')->find($cargo->parent);
                            } elseif (is_object($cargo->parent)) {
                                $oficina = $cargo->parent;
                            }
                        }


                        if ($oficina && is_object($oficina) && isset($oficina->tipo)) {
                            if ($oficina->tipo === 'Oficina') {
                                $usuarioData['oficina'] = [
                                    'id' => $oficina->id,
                                    'nom_organico' => $oficina->nom_organico,
                                    'cod_organico' => $oficina->cod_organico,
                                    'tipo' => $oficina->tipo
                                ];

                                // Buscar la dependencia (padre de la oficina) - Verificar que sea un objeto
                                $dependencia = $oficina->parent;
                                if ($dependencia && is_object($dependencia) && isset($dependencia->tipo) && $dependencia->tipo === 'Dependencia') {
                                    $usuarioData['dependencia'] = [
                                        'id' => $dependencia->id,
                                        'nom_organico' => $dependencia->nom_organico,
                                        'cod_organico' => $dependencia->cod_organico,
                                        'tipo' => $dependencia->tipo
                                    ];
                                }
                            } elseif ($oficina->tipo === 'Dependencia') {
                                // Si el cargo está directamente bajo una dependencia (sin oficina intermedia)
                                $usuarioData['dependencia'] = [
                                    'id' => $oficina->id,
                                    'nom_organico' => $oficina->nom_organico,
                                    'cod_organico' => $oficina->cod_organico,
                                    'tipo' => $oficina->tipo
                                ];
                            }
                        }
                    }

                    return $usuarioData;
                });

            return $this->successResponse(
                $usuarios,
                'Listado de usuarios activos con oficina y dependencia obtenido exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error al obtener el listado de usuarios activos',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Lista todos los usuarios con sus respectivos cargos (incluye usuarios sin cargo).
     *
     * Este método retorna todos los usuarios del sistema junto con la información
     * de su cargo activo si lo tienen. Los usuarios que no tienen cargo asignado
     * aparecerán con cargo = null. Es útil para obtener una vista completa de
     * todos los usuarios y su estado de asignación de cargos.
     *
     * @param Request $request La solicitud HTTP con filtros opcionales
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el listado de usuarios y sus cargos
     *
     * @queryParam solo_activos boolean optional Solo incluir usuarios con estado activo (1). Example: true
     * @queryParam search string optional Buscar por nombre, apellido o email. Example: "Juan"
     *
     * @response 200 {
     *   "status": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "nombres": "Juan",
     *       "apellidos": "Pérez",
     *       "email": "juan.perez@example.com",
     *       "num_docu": "12345678",
     *       "estado": 1,
     *       "cargo": {
     *         "id": 1,
     *         "nom_organico": "Jefe de Sistemas",
     *         "cod_organico": "JS001",
     *         "tipo": "Cargo",
     *         "fecha_inicio": "2024-01-15",
     *         "observaciones": "Cargo principal"
     *       }
     *     },
     *     {
     *       "id": 2,
     *       "nombres": "María",
     *       "apellidos": "García",
     *       "email": "maria.garcia@example.com",
     *       "num_docu": "87654321",
     *       "estado": 1,
     *       "cargo": null
     *     }
     *   ],
     *   "message": "Listado de usuarios con sus respectivos cargos obtenido exitosamente"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de usuarios",
     *   "error": "Error message"
     * }
     */
    public function usuariosConCargosActivos(Request $request)
    {
        try {
            $query = User::select([
                'users.id',
                'users.nombres',
                'users.apellidos',
                'users.email',
                'users.num_docu',
                'users.estado',
                'calidad_organigrama.id as cargo_id',
                'calidad_organigrama.nom_organico as cargo_nombre',
                'calidad_organigrama.cod_organico as cargo_codigo',
                'calidad_organigrama.tipo as cargo_tipo',
                'users_cargos.fecha_inicio',
                'users_cargos.observaciones'
            ])
                // Left join para incluir TODOS los usuarios, tengan o no cargo
                ->leftJoin('users_cargos', function ($join) {
                    $join->on('users.id', '=', 'users_cargos.user_id')
                        ->where('users_cargos.estado', true)
                        ->whereNull('users_cargos.fecha_fin');
                })
                ->leftJoin('calidad_organigrama', 'users_cargos.cargo_id', '=', 'calidad_organigrama.id');

            // Filtro opcional: solo usuarios activos
            if ($request->filled('solo_activos') && $request->boolean('solo_activos')) {
                $query->where('users.estado', 1);
            }

            // Filtro opcional: búsqueda por nombre, apellido o email
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('users.nombres', 'like', "%{$search}%")
                        ->orWhere('users.apellidos', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%");
                });
            }

            $usuarios = $query->orderBy('users.nombres')
                ->orderBy('users.apellidos')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'nombres' => $user->nombres,
                        'apellidos' => $user->apellidos,
                        'email' => $user->email,
                        'num_docu' => $user->num_docu,
                        'estado' => $user->estado,
                        'cargo' => $user->cargo_id ? [
                            'id' => $user->cargo_id,
                            'nom_organico' => $user->cargo_nombre,
                            'cod_organico' => $user->cargo_codigo,
                            'tipo' => $user->cargo_tipo,
                            'fecha_inicio' => $user->fecha_inicio,
                            'observaciones' => $user->observaciones
                        ] : null
                    ];
                });

            return $this->successResponse(
                $usuarios,
                'Listado de usuarios con sus respectivos cargos obtenido exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error al obtener el listado de usuarios',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Desactiva la cuenta del usuario autenticado.
     *
     * Este método permite al usuario autenticado desactivar su cuenta.
     * Requiere la contraseña para confirmar la acción y elimina todos
     * los tokens de sesión activos del usuario.
     *
     * @param ActivarInactivarRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la desactivación
     *
     * @bodyParam password string required Contraseña para confirmar la acción. Example: "MyPassword123!"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Cuenta desactivada exitosamente"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "password": ["La contraseña proporcionada no es correcta."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al desactivar la cuenta",
     *   "error": "Error message"
     * }
     */
    public function activarInactivar(ActivarInactivarRequest $request)
    {
        DB::beginTransaction();

        try {
            $user = Auth::user();

            // Verificar contraseña
            if (!Hash::check($request->validated('password'), $user->password)) {
                throw ValidationException::withMessages([
                    'password' => 'La contraseña proporcionada no es correcta.',
                ]);
            }

            // Eliminar tokens de sesión
            $user->tokens()->delete();

            // Desactivar cuenta
            $user->estado = 0;
            $user->save();

            DB::commit();

            return $this->successResponse(null, 'Cuenta desactivada exitosamente');
        } catch (ValidationException $e) {
            return $this->errorResponse('Datos de validación incorrectos', $e->errors(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al desactivar la cuenta', $e->getMessage(), 500);
        }
    }

    /**
     * Lista todos los usuarios con sus respectivos cargos activos.
     *
     * Este método retorna todos los usuarios del sistema junto con la información
     * de su cargo activo si lo tienen. Los usuarios que no tienen cargo asignado
     * aparecerán con cargo = null. Es útil para obtener una vista completa de
     * todos los usuarios y su estado de asignación de cargos.
     *
     * @param Request $request La solicitud HTTP con filtros opcionales
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el listado de usuarios y sus cargos
     *
     * @queryParam solo_activos boolean optional Solo incluir usuarios con estado activo (1). Example: true
     * @queryParam search string optional Buscar por nombre, apellido o email. Example: "Juan"
     * @queryParam incluir_roles boolean optional Incluir roles del usuario. Example: true
     *
     * @response 200 {
     *   "status": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "nombres": "Juan",
     *       "apellidos": "Pérez",
     *       "email": "juan.perez@example.com",
     *       "num_docu": "12345678",
     *       "estado": 1,
     *       "avatar_url": "http://example.com/avatars/user.jpg",
     *       "firma_url": "http://example.com/firmas/user.jpg",
     *       "cargo": {
     *         "id": 1,
     *         "nom_organico": "Jefe de Sistemas",
     *         "cod_organico": "JS001",
     *         "tipo": "Cargo",
     *         "fecha_inicio": "2024-01-15",
     *         "observaciones": "Cargo principal"
     *       },
     *       "roles": [
     *         {
     *           "id": 1,
     *           "name": "admin"
     *         }
     *       ]
     *     }
     *   ],
     *   "message": "Listado de usuarios con sus respectivos cargos obtenido exitosamente"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de usuarios",
     *   "error": "Error message"
     * }
     */
    public function listarUsuariosConCargos(Request $request)
    {
        try {
            $query = User::select([
                'users.id',
                'users.nombres',
                'users.apellidos',
                'users.email',
                'users.num_docu',
                'users.estado',
                'users.avatar',
                'users.firma',
                'calidad_organigrama.id as cargo_id',
                'calidad_organigrama.nom_organico as cargo_nombre',
                'calidad_organigrama.cod_organico as cargo_codigo',
                'calidad_organigrama.tipo as cargo_tipo',
                'users_cargos.fecha_inicio',
                'users_cargos.observaciones'
            ])
                // Left join para incluir TODOS los usuarios, tengan o no cargo
                ->leftJoin('users_cargos', function ($join) {
                    $join->on('users.id', '=', 'users_cargos.user_id')
                        ->where('users_cargos.estado', true)
                        ->whereNull('users_cargos.fecha_fin');
                })
                ->leftJoin('calidad_organigrama', 'users_cargos.cargo_id', '=', 'calidad_organigrama.id');

            // Filtro opcional: solo usuarios activos
            if ($request->filled('solo_activos') && $request->boolean('solo_activos')) {
                $query->where('users.estado', 1);
            }

            // Filtro opcional: búsqueda por nombre, apellido o email
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('users.nombres', 'like', "%{$search}%")
                        ->orWhere('users.apellidos', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%");
                });
            }

            $usuarios = $query->orderBy('users.nombres')
                ->orderBy('users.apellidos')
                ->get()
                ->map(function ($user) use ($request) {
                    $userData = [
                        'id' => $user->id,
                        'nombres' => $user->nombres,
                        'apellidos' => $user->apellidos,
                        'email' => $user->email,
                        'num_docu' => $user->num_docu,
                        'estado' => $user->estado,
                        'avatar_url' => ArchivoHelper::obtenerUrl($user->avatar, 'avatars'),
                        'firma_url' => ArchivoHelper::obtenerUrl($user->firma, 'firmas'),
                        'cargo' => $user->cargo_id ? [
                            'id' => $user->cargo_id,
                            'nom_organico' => $user->cargo_nombre,
                            'cod_organico' => $user->cargo_codigo,
                            'tipo' => $user->cargo_tipo,
                            'fecha_inicio' => $user->fecha_inicio,
                            'observaciones' => $user->observaciones
                        ] : null
                    ];

                    // Incluir roles si se solicita
                    if ($request->boolean('incluir_roles')) {
                        $userModel = User::find($user->id);
                        $userData['roles'] = $userModel->roles->map(function ($role) {
                            return [
                                'id' => $role->id,
                                'name' => $role->name,
                                'guard_name' => $role->guard_name
                            ];
                        });
                    }

                    return $userData;
                });

            return $this->successResponse(
                $usuarios,
                'Listado de usuarios con sus respectivos cargos obtenido exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error al obtener el listado de usuarios',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Método de depuración para verificar relaciones de usuarios.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function debugUsuariosRelaciones()
    {
        try {
            $debug = [
                'usuarios_activos' => User::where('estado', 1)->count(),
                'total_users_sedes' => DB::table('users_sedes')->count(),
                'total_users_cargos' => DB::table('users_cargos')->count(),
                'total_config_sedes' => DB::table('config_sedes')->count(),
                'total_calidad_organigrama' => DB::table('calidad_organigrama')->count(),
                'users_sedes_activas' => DB::table('users_sedes')->where('estado', true)->count(),
                'users_cargos_activos' => DB::table('users_cargos')->where('estado', true)->whereNull('fecha_fin')->count(),
                'config_sedes_activas' => DB::table('config_sedes')->where('estado', true)->count(),
            ];

            // Verificar relaciones específicas del usuario admin
            $adminUser = User::where('email', 'admin@admin.com')->first();
            if ($adminUser) {
                $debug['admin_user'] = [
                    'id' => $adminUser->id,
                    'estado' => $adminUser->estado,
                    'sedes_asignadas' => DB::table('users_sedes')->where('user_id', $adminUser->id)->get(),
                    'cargos_asignados' => DB::table('users_cargos')->where('user_id', $adminUser->id)->get(),
                ];
            }

            return $this->successResponse($debug, 'Información de depuración obtenida');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener información de depuración', $e->getMessage(), 500);
        }
    }

    /**
     * Método de depuración específico para oficinas y cargos.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function debugOficinasYCargos()
    {
        try {
            $debug = [];

            // Verificar todas las oficinas disponibles
            $debug['oficinas_disponibles'] = DB::table('config_sedes')
                ->where('estado', true)
                ->select('id', 'nombre', 'codigo', 'direccion', 'estado')
                ->get();

            // Verificar todas las asignaciones de usuarios a oficinas
            $debug['asignaciones_oficinas'] = DB::table('users_sedes')
                ->join('users', 'users_sedes.user_id', '=', 'users.id')
                ->join('config_sedes', 'users_sedes.sede_id', '=', 'config_sedes.id')
                ->select(
                    'users.id as user_id',
                    'users.nombres',
                    'users.apellidos',
                    'users.email',
                    'config_sedes.id as sede_id',
                    'config_sedes.nombre as sede_nombre',
                    'users_sedes.estado as asignacion_estado'
                )
                ->get();

            // Verificar todas las asignaciones de usuarios a cargos
            $debug['asignaciones_cargos'] = DB::table('users_cargos')
                ->join('users', 'users_cargos.user_id', '=', 'users.id')
                ->join('calidad_organigrama', 'users_cargos.cargo_id', '=', 'calidad_organigrama.id')
                ->select(
                    'users.id as user_id',
                    'users.nombres',
                    'users.apellidos',
                    'users.email',
                    'calidad_organigrama.id as cargo_id',
                    'calidad_organigrama.nom_organico',
                    'users_cargos.fecha_inicio',
                    'users_cargos.estado as asignacion_estado'
                )
                ->get();

            return $this->successResponse($debug, 'Información de oficinas y cargos obtenida');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener información de oficinas y cargos', $e->getMessage(), 500);
        }
    }

    /**
     * Método de depuración para verificar la estructura del organigrama.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function debugOrganigramaEstructura()
    {
        try {
            $debug = [];

            // Verificar estructura del organigrama
            $debug['organigrama_tipos'] = DB::table('calidad_organigrama')
                ->select('tipo', DB::raw('COUNT(*) as cantidad'))
                ->groupBy('tipo')
                ->get();

            // Verificar jerarquía del organigrama
            $debug['organigrama_jerarquia'] = DB::table('calidad_organigrama')
                ->select('id', 'tipo', 'nom_organico', 'cod_organico', 'parent')
                ->orderBy('parent')
                ->orderBy('tipo')
                ->get();

            // Verificar usuario admin específico
            $adminUser = User::where('email', 'admin@admin.com')->first();
            if ($adminUser) {
                $debug['admin_cargo_activo'] = $adminUser->cargoActivo;
                if ($adminUser->cargoActivo) {
                    $debug['admin_cargo_info'] = $adminUser->cargoActivo->cargo;
                    if ($adminUser->cargoActivo->cargo) {
                        $debug['admin_cargo_parent'] = $adminUser->cargoActivo->cargo->parent;
                        if ($adminUser->cargoActivo->cargo->parent) {
                            $debug['admin_cargo_parent_parent'] = $adminUser->cargoActivo->cargo->parent->parent;
                        }
                    }
                }
            }

            return $this->successResponse($debug, 'Información de estructura del organigrama obtenida');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener información del organigrama', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene el perfil completo de un usuario específico.
     *
     * Este método retorna toda la información consolidada del perfil del usuario,
     * incluyendo datos básicos, cargo activo, oficina, dependencia, sedes asignadas,
     * roles, y división política (país, departamento, municipio).
     *
     * @param int $userId El ID del usuario
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el perfil completo del usuario
     *
     * @urlParam userId integer required El ID del usuario. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Perfil completo del usuario obtenido exitosamente",
     *   "data": {
     *     "id": 1,
     *     "nombres": "Juan Carlos",
     *     "apellidos": "Pérez García",
     *     "email": "juan.perez@example.com",
     *     "num_docu": "12345678",
     *     "tel": "1234567",
     *     "movil": "3001234567",
     *     "dir": "Calle 123 #45-67",
     *     "estado": true,
     *     "avatar_url": "http://example.com/avatars/user.jpg",
     *     "firma_url": "http://example.com/firmas/signature.jpg",
     *     "cargo": {
     *       "id": 23,
     *       "nom_organico": "Gerente",
     *       "cod_organico": "GER001",
     *       "tipo": "Cargo",
     *       "fecha_inicio": "2024-01-15",
     *       "observaciones": null
     *     },
     *     "oficina": {
     *       "id": 3,
     *       "nom_organico": "Oficina Principal",
     *       "cod_organico": "OFI001",
     *       "tipo": "Oficina"
     *     },
     *     "dependencia": {
     *       "id": 2,
     *       "nom_organico": "GERENCIA",
     *       "cod_organico": "100",
     *       "tipo": "Dependencia"
     *     },
     *     "sedes": [
     *       {
     *         "id": 1,
     *         "nombre": "Sede Principal",
     *         "codigo": "SEDE001",
     *         "pivot": {
     *           "estado": true,
     *           "observaciones": "Asignación principal"
     *         }
     *       }
     *     ],
     *     "roles": [
     *       {
     *         "id": 1,
     *         "name": "Administrador",
     *         "guard_name": "web"
     *       }
     *     ],
     *     "pais": {
     *       "id": 1,
     *       "codigo": "COD",
     *       "nombre": "Colombia",
     *       "tipo": "Pais"
     *     },
     *     "departamento": {
     *       "id": 982,
     *       "codigo": "73",
     *       "nombre": "Tolima",
     *       "tipo": "Departamento"
     *     },
     *     "municipio": {
     *       "id": 998,
     *       "codigo": "73268",
     *       "nombre": "ESPINAL",
     *       "tipo": "Municipio"
     *     }
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Usuario no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el perfil completo del usuario",
     *   "error": "Error message"
     * }
     */
    public function getPerfilCompleto(int $userId)
    {
        try {
            $user = User::with([
                'roles',
                'cargoActivo.cargo',
                'divisionPolitica.padre.padre',
                'sedes'
            ])->find($userId);

            if (!$user) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            // Preparar datos básicos del usuario
            $userData = $user->append(['avatar_url', 'firma_url'])->toArray();

            // Eliminar campos redundantes
            if (isset($userData['cargos'])) {
                unset($userData['cargos']);
            }
            if (isset($userData['cargo_activo'])) {
                unset($userData['cargo_activo']);
            }
            if (isset($userData['division_politica'])) {
                unset($userData['division_politica']);
            }

            // Inicializar campos estructurados
            $userData['cargo'] = null;
            $userData['oficina'] = null;
            $userData['dependencia'] = null;
            $userData['pais'] = null;
            $userData['departamento'] = null;
            $userData['municipio'] = null;
            $userData['sedes'] = [];

            // Obtener información de división política (país, departamento, municipio)
            if ($user->divi_poli_id && $user->divisionPolitica) {
                $diviPoli = $user->divisionPolitica;

                // Cargar la relación padre si no está cargada
                if (!$diviPoli->relationLoaded('padre')) {
                    $diviPoli->load('padre');
                }

                if ($diviPoli->tipo === 'Municipio') {
                    $departamento = $diviPoli->padre;

                    if ($departamento && !$departamento->relationLoaded('padre')) {
                        $departamento->load('padre');
                    }

                    $pais = $departamento ? $departamento->padre : null;

                    $userData['municipio'] = [
                        'id' => $diviPoli->id,
                        'codigo' => $diviPoli->codigo,
                        'nombre' => $diviPoli->nombre,
                        'tipo' => $diviPoli->tipo
                    ];

                    if ($departamento) {
                        $userData['departamento'] = [
                            'id' => $departamento->id,
                            'codigo' => $departamento->codigo,
                            'nombre' => $departamento->nombre,
                            'tipo' => $departamento->tipo
                        ];
                    }

                    if ($pais) {
                        $userData['pais'] = [
                            'id' => $pais->id,
                            'codigo' => $pais->codigo,
                            'nombre' => $pais->nombre,
                            'tipo' => $pais->tipo
                        ];
                    }
                } elseif ($diviPoli->tipo === 'Departamento') {
                    $pais = $diviPoli->padre;

                    $userData['departamento'] = [
                        'id' => $diviPoli->id,
                        'codigo' => $diviPoli->codigo,
                        'nombre' => $diviPoli->nombre,
                        'tipo' => $diviPoli->tipo
                    ];

                    if ($pais) {
                        $userData['pais'] = [
                            'id' => $pais->id,
                            'codigo' => $pais->codigo,
                            'nombre' => $pais->nombre,
                            'tipo' => $pais->tipo
                        ];
                    }
                } elseif ($diviPoli->tipo === 'Pais') {
                    $userData['pais'] = [
                        'id' => $diviPoli->id,
                        'codigo' => $diviPoli->codigo,
                        'nombre' => $diviPoli->nombre,
                        'tipo' => $diviPoli->tipo
                    ];
                }
            }

            // Obtener información de cargo, oficina y dependencia
            if ($user->cargoActivo && $user->cargoActivo->cargo) {
                $cargo = $user->cargoActivo->cargo;

                $userData['cargo'] = [
                    'id' => $cargo->id,
                    'nom_organico' => $cargo->nom_organico,
                    'cod_organico' => $cargo->cod_organico,
                    'tipo' => $cargo->tipo,
                    'fecha_inicio' => $user->cargoActivo->fecha_inicio?->format('Y-m-d'),
                    'observaciones' => $user->cargoActivo->observaciones
                ];

                // Obtener jerarquía completa del cargo
                $jerarquia = $cargo->getJerarquiaCompleta();

                // Encontrar la posición del cargo en la jerarquía
                $cargoIndex = -1;
                foreach ($jerarquia as $index => $nivel) {
                    if ($nivel['id'] === $cargo->id && $nivel['tipo'] === 'Cargo') {
                        $cargoIndex = $index;
                        break;
                    }
                }

                // Buscar oficina y dependencia
                if ($cargoIndex > 0) {
                    $parentDirecto = $jerarquia[$cargoIndex - 1];

                    if ($parentDirecto['tipo'] === 'Oficina') {
                        $userData['oficina'] = [
                            'id' => $parentDirecto['id'],
                            'nom_organico' => $parentDirecto['nom_organico'],
                            'cod_organico' => $parentDirecto['cod_organico'],
                            'tipo' => $parentDirecto['tipo']
                        ];

                        // Buscar dependencia (padre de la oficina)
                        if ($cargoIndex > 1) {
                            $dependencia = $jerarquia[$cargoIndex - 2];
                            if ($dependencia['tipo'] === 'Dependencia') {
                                $userData['dependencia'] = [
                                    'id' => $dependencia['id'],
                                    'nom_organico' => $dependencia['nom_organico'],
                                    'cod_organico' => $dependencia['cod_organico'],
                                    'tipo' => $dependencia['tipo']
                                ];
                            }
                        }
                    } elseif ($parentDirecto['tipo'] === 'Dependencia') {
                        $userData['dependencia'] = [
                            'id' => $parentDirecto['id'],
                            'nom_organico' => $parentDirecto['nom_organico'],
                            'cod_organico' => $parentDirecto['cod_organico'],
                            'tipo' => $parentDirecto['tipo']
                        ];
                    }
                }
            }

            // Obtener sedes asignadas
            $userData['sedes'] = $user->sedes->map(function ($sede) {
                return [
                    'id' => $sede->id,
                    'nombre' => $sede->nombre,
                    'codigo' => $sede->codigo,
                    'direccion' => $sede->direccion,
                    'telefono' => $sede->telefono,
                    'estado' => $sede->pivot->estado ?? true,
                    'observaciones' => $sede->pivot->observaciones ?? null
                ];
            })->toArray();

            return $this->successResponse($userData, 'Perfil completo del usuario obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el perfil completo del usuario', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene el timeline de actividades y auditoría de un usuario específico.
     *
     * Este método retorna un timeline consolidado de todas las actividades del usuario,
     * incluyendo logs globales, sesiones de login, cambios de cargos y cambios de sedes,
     * ordenadas cronológicamente de más reciente a más antigua.
     *
     * @param int $userId El ID del usuario
     * @param Request $request La solicitud HTTP con parámetros opcionales
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el timeline de actividades
     *
     * @urlParam userId integer required El ID del usuario. Example: 1
     * @queryParam limit integer Número máximo de actividades a retornar (por defecto: 50). Example: 20
     * @queryParam tipo string Filtrar por tipo de actividad (log, sesion, cargo, sede). Example: "cargo"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Timeline de actividades obtenido exitosamente",
     *   "data": {
     *     "usuario": {
     *       "id": 1,
     *       "nombres": "Juan Carlos",
     *       "apellidos": "Pérez García"
     *     },
     *     "actividades": [
     *       {
     *         "id": 1,
     *         "tipo": "cargo",
     *         "accion": "Cargo asignado",
     *         "descripcion": "Se asignó el cargo 'Gerente'",
     *         "fecha": "2024-01-15T10:30:00.000000Z",
     *         "detalles": {
     *           "cargo_id": 23,
     *           "cargo_nombre": "Gerente",
     *           "fecha_inicio": "2024-01-15",
     *           "estado": "activo"
     *         }
     *       },
     *       {
     *         "id": 2,
     *         "tipo": "sesion",
     *         "accion": "Inicio de sesión",
     *         "descripcion": "Sesión iniciada desde Chrome on Windows",
     *         "fecha": "2024-01-15T09:00:00.000000Z",
     *         "detalles": {
     *           "ip_address": "192.168.1.1",
     *           "user_agent": "Mozilla/5.0..."
     *         }
     *       },
     *       {
     *         "id": 3,
     *         "tipo": "log",
     *         "accion": "CREAR_RADICADO",
     *         "descripcion": "Se creó un nuevo radicado",
     *         "fecha": "2024-01-14T14:20:00.000000Z",
     *         "detalles": {
     *           "radicado_id": 123,
     *           "ip": "192.168.1.1"
     *         }
     *       }
     *     ],
     *     "total": 3
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Usuario no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el timeline de actividades",
     *   "error": "Error message"
     * }
     */
    public function getActividad(int $userId, Request $request)
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            $limit = $request->input('limit', 50);
            $tipoFiltro = $request->input('tipo');

            $actividades = collect();

            // 1. Obtener logs globales
            if (!$tipoFiltro || $tipoFiltro === 'log') {
                $logs = LogGlobal::where('user_id', $userId)
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function ($log) {
                        return [
                            'id' => $log->id,
                            'tipo' => 'log',
                            'accion' => $log->accion,
                            'descripcion' => $log->accion . ($log->detalles ? ': ' . $log->detalles : ''),
                            'fecha' => $log->created_at,
                            'detalles' => [
                                'detalles' => $log->detalles,
                                'ip' => $log->ip,
                                'user_agent' => $log->user_agent
                            ]
                        ];
                    });

                $actividades = $actividades->merge($logs);
            }

            // 2. Obtener sesiones de login
            if (!$tipoFiltro || $tipoFiltro === 'sesion') {
                $sesiones = UsersSession::where('user_id', $userId)
                    ->orderBy('last_login_at', 'desc')
                    ->get()
                    ->map(function ($sesion) {
                        return [
                            'id' => $sesion->id,
                            'tipo' => 'sesion',
                            'accion' => 'Inicio de sesión',
                            'descripcion' => 'Sesión iniciada desde ' . ($sesion->user_agent ?? 'desconocido'),
                            'fecha' => $sesion->last_login_at,
                            'detalles' => [
                                'ip_address' => $sesion->ip_address,
                                'user_agent' => $sesion->user_agent
                            ]
                        ];
                    });

                $actividades = $actividades->merge($sesiones);
            }

            // 3. Obtener cambios de cargos
            if (!$tipoFiltro || $tipoFiltro === 'cargo') {
                $cargos = UserCargo::where('user_id', $userId)
                    ->with('cargo')
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function ($cargo) {
                        $accion = $cargo->estado && !$cargo->fecha_fin ? 'Cargo asignado' : 'Cargo finalizado';
                        $descripcion = $cargo->cargo
                            ? "Se {$accion} el cargo '{$cargo->cargo->nom_organico}'"
                            : "Se {$accion} un cargo";

                        return [
                            'id' => $cargo->id,
                            'tipo' => 'cargo',
                            'accion' => $accion,
                            'descripcion' => $descripcion,
                            'fecha' => $cargo->created_at,
                            'detalles' => [
                                'cargo_id' => $cargo->cargo_id,
                                'cargo_nombre' => $cargo->cargo?->nom_organico,
                                'fecha_inicio' => $cargo->fecha_inicio?->format('Y-m-d'),
                                'fecha_fin' => $cargo->fecha_fin?->format('Y-m-d'),
                                'estado' => $cargo->estado && !$cargo->fecha_fin ? 'activo' : 'finalizado',
                                'observaciones' => $cargo->observaciones
                            ]
                        ];
                    });

                $actividades = $actividades->merge($cargos);
            }

            // 4. Obtener cambios de sedes (desde la tabla pivot)
            if (!$tipoFiltro || $tipoFiltro === 'sede') {
                $sedes = DB::table('users_sedes')
                    ->where('user_id', $userId)
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function ($sede) {
                        $sedeModel = \App\Models\Configuracion\ConfigSede::find($sede->sede_id);
                        $accion = $sede->estado ? 'Sede asignada' : 'Sede desasignada';
                        $descripcion = $sedeModel
                            ? "Se {$accion} la sede '{$sedeModel->nombre}'"
                            : "Se {$accion} una sede";

                        return [
                            'id' => $sede->id ?? null,
                            'tipo' => 'sede',
                            'accion' => $accion,
                            'descripcion' => $descripcion,
                            'fecha' => $sede->created_at,
                            'detalles' => [
                                'sede_id' => $sede->sede_id,
                                'sede_nombre' => $sedeModel?->nombre,
                                'estado' => $sede->estado ? 'activa' : 'inactiva',
                                'observaciones' => $sede->observaciones
                            ]
                        ];
                    });

                $actividades = $actividades->merge($sedes);
            }

            // Ordenar todas las actividades por fecha (más reciente primero)
            $actividades = $actividades->sortByDesc(function ($actividad) {
                return $actividad['fecha']->timestamp;
            })->values();

            // Aplicar límite
            $total = $actividades->count();
            $actividades = $actividades->take($limit);

            $data = [
                'usuario' => [
                    'id' => $user->id,
                    'nombres' => $user->nombres,
                    'apellidos' => $user->apellidos
                ],
                'actividades' => $actividades->map(function ($actividad) {
                    return [
                        'id' => $actividad['id'],
                        'tipo' => $actividad['tipo'],
                        'accion' => $actividad['accion'],
                        'descripcion' => $actividad['descripcion'],
                        'fecha' => $actividad['fecha']->toISOString(),
                        'fecha_formateada' => $actividad['fecha']->format('d M Y, H:i'),
                        'detalles' => $actividad['detalles']
                    ];
                }),
                'total' => $total,
                'mostrando' => $actividades->count()
            ];

            return $this->successResponse($data, 'Timeline de actividades obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el timeline de actividades', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene los roles y permisos detallados de un usuario específico.
     *
     * Este método retorna información completa sobre los roles asignados al usuario,
     * los permisos asociados a cada rol, los permisos directos del usuario (si los hay),
     * y un listado consolidado de todos los permisos que el usuario tiene.
     *
     * @param int $userId El ID del usuario
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con roles y permisos detallados
     *
     * @urlParam userId integer required El ID del usuario. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Roles y permisos del usuario obtenidos exitosamente",
     *   "data": {
     *     "usuario": {
     *       "id": 1,
     *       "nombres": "Juan Carlos",
     *       "apellidos": "Pérez García",
     *       "email": "juan.perez@example.com"
     *     },
     *     "roles": [
     *       {
     *         "id": 1,
     *         "name": "Administrador",
     *         "guard_name": "web",
     *         "created_at": "2024-01-01T00:00:00.000000Z",
     *         "updated_at": "2024-01-01T00:00:00.000000Z",
     *         "permissions": [
     *           {
     *             "id": 1,
     *             "name": "user.create",
     *             "guard_name": "web"
     *           },
     *           {
     *             "id": 2,
     *             "name": "user.edit",
     *             "guard_name": "web"
     *           }
     *         ]
     *       }
     *     ],
     *     "permisos_directos": [
     *       {
     *         "id": 5,
     *         "name": "special.permission",
     *         "guard_name": "web"
     *       }
     *     ],
     *     "todos_los_permisos": [
     *       {
     *         "id": 1,
     *         "name": "user.create",
     *         "guard_name": "web",
     *         "origen": "rol:Administrador"
     *       },
     *       {
     *         "id": 2,
     *         "name": "user.edit",
     *         "guard_name": "web",
     *         "origen": "rol:Administrador"
     *       },
     *       {
     *         "id": 5,
     *         "name": "special.permission",
     *         "guard_name": "web",
     *         "origen": "directo"
     *       }
     *     ],
     *     "resumen": {
     *       "total_roles": 1,
     *       "total_permisos_directos": 1,
     *       "total_permisos_totales": 3,
     *       "nombres_roles": ["Administrador"],
     *       "nombres_permisos": ["user.create", "user.edit", "special.permission"]
     *     }
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Usuario no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener los roles y permisos del usuario",
     *   "error": "Error message"
     * }
     */
    public function getPermisos(int $userId)
    {
        try {
            // Limpiar cache de permisos para obtener datos actualizados
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            $user = User::with(['roles.permissions', 'permissions'])->find($userId);

            if (!$user) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            // Obtener roles con sus permisos
            $roles = $user->roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                    'created_at' => $role->created_at?->toISOString(),
                    'updated_at' => $role->updated_at?->toISOString(),
                    'permissions' => $role->permissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'guard_name' => $permission->guard_name
                        ];
                    })
                ];
            });

            // Obtener permisos directos del usuario
            $permisosDirectos = $user->permissions->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name
                ];
            });

            // Obtener todos los permisos (directos + de roles) con información de origen
            $todosLosPermisos = collect();

            // Agregar permisos de roles
            foreach ($user->roles as $role) {
                foreach ($role->permissions as $permission) {
                    $todosLosPermisos->push([
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'guard_name' => $permission->guard_name,
                        'origen' => "rol:{$role->name}"
                    ]);
                }
            }

            // Agregar permisos directos
            foreach ($user->permissions as $permission) {
                // Evitar duplicados si el permiso ya está en los roles
                $existe = $todosLosPermisos->contains(function ($perm) use ($permission) {
                    return $perm['id'] === $permission->id;
                });

                if (!$existe) {
                    $todosLosPermisos->push([
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'guard_name' => $permission->guard_name,
                        'origen' => 'directo'
                    ]);
                } else {
                    // Si existe, agregar el origen directo también
                    $index = $todosLosPermisos->search(function ($perm) use ($permission) {
                        return $perm['id'] === $permission->id;
                    });
                    if ($index !== false) {
                        $todosLosPermisos[$index]['origen'] = $todosLosPermisos[$index]['origen'] . ', directo';
                    }
                }
            }

            // Ordenar permisos por nombre
            $todosLosPermisos = $todosLosPermisos->sortBy('name')->values();

            // Crear resumen
            $resumen = [
                'total_roles' => $roles->count(),
                'total_permisos_directos' => $permisosDirectos->count(),
                'total_permisos_totales' => $todosLosPermisos->count(),
                'nombres_roles' => $roles->pluck('name')->toArray(),
                'nombres_permisos' => $todosLosPermisos->pluck('name')->toArray()
            ];

            $data = [
                'usuario' => [
                    'id' => $user->id,
                    'nombres' => $user->nombres,
                    'apellidos' => $user->apellidos,
                    'email' => $user->email
                ],
                'roles' => $roles,
                'permisos_directos' => $permisosDirectos,
                'todos_los_permisos' => $todosLosPermisos,
                'resumen' => $resumen
            ];

            return $this->successResponse($data, 'Roles y permisos del usuario obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener los roles y permisos del usuario', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene el historial completo de cambios de un usuario específico.
     *
     * Este método retorna un historial consolidado de todos los cambios realizados
     * en el perfil del usuario, incluyendo cambios en datos personales, cargos, sedes,
     * roles, logs de actividad y sesiones, ordenados cronológicamente.
     *
     * @param int $userId El ID del usuario
     * @param Request $request La solicitud HTTP con parámetros opcionales
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el historial completo de cambios
     *
     * @urlParam userId integer required El ID del usuario. Example: 1
     * @queryParam limit integer Número máximo de cambios a retornar (por defecto: 100). Example: 50
     * @queryParam tipo string Filtrar por tipo de cambio (perfil, cargo, sede, rol, log, sesion). Example: "cargo"
     * @queryParam desde string Fecha de inicio para filtrar (formato: Y-m-d). Example: "2024-01-01"
     * @queryParam hasta string Fecha de fin para filtrar (formato: Y-m-d). Example: "2024-12-31"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Historial completo de cambios obtenido exitosamente",
     *   "data": {
     *     "usuario": {
     *       "id": 1,
     *       "nombres": "Juan Carlos",
     *       "apellidos": "Pérez García",
     *       "email": "juan.perez@example.com",
     *       "fecha_creacion": "2024-01-01T00:00:00.000000Z"
     *     },
     *     "cambios": [
     *       {
     *         "id": 1,
     *         "tipo": "cargo",
     *         "accion": "Cargo asignado",
     *         "descripcion": "Se asignó el cargo 'Gerente'",
     *         "fecha": "2024-01-15T10:30:00.000000Z",
     *         "fecha_formateada": "15 Ene 2024, 10:30",
     *         "detalles": {
     *           "cargo_id": 23,
     *           "cargo_nombre": "Gerente",
     *           "fecha_inicio": "2024-01-15",
     *           "estado": "activo"
     *         }
     *       },
     *       {
     *         "id": 2,
     *         "tipo": "perfil",
     *         "accion": "Perfil actualizado",
     *         "descripcion": "Se actualizó la información del perfil",
     *         "fecha": "2024-01-14T14:20:00.000000Z",
     *         "fecha_formateada": "14 Ene 2024, 14:20",
     *         "detalles": {
     *           "campos_modificados": ["nombres", "email"]
     *         }
     *       }
     *     ],
     *     "resumen": {
     *       "total_cambios": 25,
     *       "por_tipo": {
     *         "perfil": 5,
     *         "cargo": 3,
     *         "sede": 4,
     *         "rol": 2,
     *         "log": 8,
     *         "sesion": 3
     *       },
     *       "fecha_primera_actividad": "2024-01-01T00:00:00.000000Z",
     *       "fecha_ultima_actividad": "2024-01-15T10:30:00.000000Z"
     *     }
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Usuario no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el historial de cambios",
     *   "error": "Error message"
     * }
     */
    public function getHistorial(int $userId, Request $request)
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            $limit = $request->input('limit', 100);
            $tipoFiltro = $request->input('tipo');
            $desde = $request->input('desde');
            $hasta = $request->input('hasta');

            $cambios = collect();
            $resumenPorTipo = [
                'perfil' => 0,
                'cargo' => 0,
                'sede' => 0,
                'rol' => 0,
                'log' => 0,
                'sesion' => 0
            ];

            // 1. Cambios en el perfil del usuario (usando updated_at)
            if (!$tipoFiltro || $tipoFiltro === 'perfil') {
                if ($user->updated_at && $user->updated_at->notEqualTo($user->created_at)) {
                    $cambios->push([
                        'id' => $user->id,
                        'tipo' => 'perfil',
                        'accion' => 'Perfil actualizado',
                        'descripcion' => 'Se actualizó la información del perfil del usuario',
                        'fecha' => $user->updated_at,
                        'detalles' => [
                            'fecha_creacion' => $user->created_at?->toISOString(),
                            'fecha_actualizacion' => $user->updated_at->toISOString()
                        ]
                    ]);
                    $resumenPorTipo['perfil']++;
                }
            }

            // 2. Cambios de cargos
            if (!$tipoFiltro || $tipoFiltro === 'cargo') {
                $cargos = UserCargo::where('user_id', $userId)
                    ->with('cargo')
                    ->orderBy('created_at', 'desc')
                    ->get();

                foreach ($cargos as $cargo) {
                    // Cambio de creación/asignación
                    $accion = $cargo->estado && !$cargo->fecha_fin ? 'Cargo asignado' : 'Cargo finalizado';
                    $descripcion = $cargo->cargo
                        ? "Se {$accion} el cargo '{$cargo->cargo->nom_organico}'"
                        : "Se {$accion} un cargo";

                    $cambios->push([
                        'id' => $cargo->id,
                        'tipo' => 'cargo',
                        'accion' => $accion,
                        'descripcion' => $descripcion,
                        'fecha' => $cargo->created_at,
                        'detalles' => [
                            'cargo_id' => $cargo->cargo_id,
                            'cargo_nombre' => $cargo->cargo?->nom_organico,
                            'fecha_inicio' => $cargo->fecha_inicio?->format('Y-m-d'),
                            'fecha_fin' => $cargo->fecha_fin?->format('Y-m-d'),
                            'estado' => $cargo->estado && !$cargo->fecha_fin ? 'activo' : 'finalizado',
                            'observaciones' => $cargo->observaciones
                        ]
                    ]);
                    $resumenPorTipo['cargo']++;

                    // Si fue actualizado después de creado, agregar también el cambio de actualización
                    if ($cargo->updated_at && $cargo->updated_at->notEqualTo($cargo->created_at)) {
                        $cambios->push([
                            'id' => $cargo->id . '_update',
                            'tipo' => 'cargo',
                            'accion' => 'Cargo actualizado',
                            'descripcion' => "Se actualizó información del cargo '{$cargo->cargo?->nom_organico}'",
                            'fecha' => $cargo->updated_at,
                            'detalles' => [
                                'cargo_id' => $cargo->cargo_id,
                                'cargo_nombre' => $cargo->cargo?->nom_organico,
                                'fecha_actualizacion' => $cargo->updated_at->toISOString()
                            ]
                        ]);
                        $resumenPorTipo['cargo']++;
                    }
                }
            }

            // 3. Cambios de sedes
            if (!$tipoFiltro || $tipoFiltro === 'sede') {
                $sedes = DB::table('users_sedes')
                    ->where('user_id', $userId)
                    ->orderBy('created_at', 'desc')
                    ->get();

                foreach ($sedes as $sede) {
                    $sedeModel = \App\Models\Configuracion\ConfigSede::find($sede->sede_id);
                    $accion = $sede->estado ? 'Sede asignada' : 'Sede desasignada';
                    $descripcion = $sedeModel
                        ? "Se {$accion} la sede '{$sedeModel->nombre}'"
                        : "Se {$accion} una sede";

                    $cambios->push([
                        'id' => $sede->id ?? null,
                        'tipo' => 'sede',
                        'accion' => $accion,
                        'descripcion' => $descripcion,
                        'fecha' => \Carbon\Carbon::parse($sede->created_at),
                        'detalles' => [
                            'sede_id' => $sede->sede_id,
                            'sede_nombre' => $sedeModel?->nombre,
                            'estado' => $sede->estado ? 'activa' : 'inactiva',
                            'observaciones' => $sede->observaciones
                        ]
                    ]);
                    $resumenPorTipo['sede']++;

                    // Si fue actualizado después de creado
                    $createdAt = \Carbon\Carbon::parse($sede->created_at);
                    $updatedAt = \Carbon\Carbon::parse($sede->updated_at);
                    if ($updatedAt && $updatedAt->notEqualTo($createdAt)) {
                        $cambios->push([
                            'id' => ($sede->id ?? '') . '_update',
                            'tipo' => 'sede',
                            'accion' => 'Sede actualizada',
                            'descripcion' => "Se actualizó información de la sede '{$sedeModel?->nombre}'",
                            'fecha' => $updatedAt,
                            'detalles' => [
                                'sede_id' => $sede->sede_id,
                                'sede_nombre' => $sedeModel?->nombre,
                                'fecha_actualizacion' => $updatedAt->toISOString()
                            ]
                        ]);
                        $resumenPorTipo['sede']++;
                    }
                }
            }

            // 4. Cambios de roles (inferidos de los roles actuales)
            if (!$tipoFiltro || $tipoFiltro === 'rol') {
                $rolesActuales = $user->roles;
                foreach ($rolesActuales as $role) {
                    $cambios->push([
                        'id' => 'rol_' . $role->id,
                        'tipo' => 'rol',
                        'accion' => 'Rol asignado',
                        'descripcion' => "Se asignó el rol '{$role->name}'",
                        'fecha' => $user->created_at, // Usamos fecha de creación del usuario como aproximación
                        'detalles' => [
                            'rol_id' => $role->id,
                            'rol_nombre' => $role->name,
                            'guard_name' => $role->guard_name
                        ]
                    ]);
                    $resumenPorTipo['rol']++;
                }
            }

            // 5. Logs globales
            if (!$tipoFiltro || $tipoFiltro === 'log') {
                $logs = LogGlobal::where('user_id', $userId)
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function ($log) {
                        return [
                            'id' => $log->id,
                            'tipo' => 'log',
                            'accion' => $log->accion,
                            'descripcion' => $log->accion . ($log->detalles ? ': ' . $log->detalles : ''),
                            'fecha' => $log->created_at,
                            'detalles' => [
                                'detalles' => $log->detalles,
                                'ip' => $log->ip,
                                'user_agent' => $log->user_agent
                            ]
                        ];
                    });

                $cambios = $cambios->merge($logs);
                $resumenPorTipo['log'] += $logs->count();
            }

            // 6. Sesiones de login
            if (!$tipoFiltro || $tipoFiltro === 'sesion') {
                $sesiones = UsersSession::where('user_id', $userId)
                    ->orderBy('last_login_at', 'desc')
                    ->get()
                    ->map(function ($sesion) {
                        return [
                            'id' => $sesion->id,
                            'tipo' => 'sesion',
                            'accion' => 'Inicio de sesión',
                            'descripcion' => 'Sesión iniciada desde ' . ($sesion->user_agent ?? 'desconocido'),
                            'fecha' => $sesion->last_login_at,
                            'detalles' => [
                                'ip_address' => $sesion->ip_address,
                                'user_agent' => $sesion->user_agent
                            ]
                        ];
                    });

                $cambios = $cambios->merge($sesiones);
                $resumenPorTipo['sesion'] += $sesiones->count();
            }

            // Aplicar filtros de fecha si se proporcionan
            if ($desde) {
                $cambios = $cambios->filter(function ($cambio) use ($desde) {
                    return $cambio['fecha'] >= \Carbon\Carbon::parse($desde)->startOfDay();
                });
            }

            if ($hasta) {
                $cambios = $cambios->filter(function ($cambio) use ($hasta) {
                    return $cambio['fecha'] <= \Carbon\Carbon::parse($hasta)->endOfDay();
                });
            }

            // Ordenar todos los cambios por fecha (más reciente primero)
            $cambios = $cambios->sortByDesc(function ($cambio) {
                return $cambio['fecha']->timestamp;
            })->values();

            // Aplicar límite
            $total = $cambios->count();
            $cambios = $cambios->take($limit);

            // Obtener fechas de primera y última actividad
            $fechaPrimera = $cambios->last()['fecha'] ?? null;
            $fechaUltima = $cambios->first()['fecha'] ?? null;

            $data = [
                'usuario' => [
                    'id' => $user->id,
                    'nombres' => $user->nombres,
                    'apellidos' => $user->apellidos,
                    'email' => $user->email,
                    'fecha_creacion' => $user->created_at?->toISOString()
                ],
                'cambios' => $cambios->map(function ($cambio) {
                    return [
                        'id' => $cambio['id'],
                        'tipo' => $cambio['tipo'],
                        'accion' => $cambio['accion'],
                        'descripcion' => $cambio['descripcion'],
                        'fecha' => $cambio['fecha']->toISOString(),
                        'fecha_formateada' => $cambio['fecha']->format('d M Y, H:i'),
                        'detalles' => $cambio['detalles']
                    ];
                }),
                'resumen' => [
                    'total_cambios' => $total,
                    'por_tipo' => $resumenPorTipo,
                    'fecha_primera_actividad' => $fechaPrimera?->toISOString(),
                    'fecha_ultima_actividad' => $fechaUltima?->toISOString()
                ]
            ];

            return $this->successResponse($data, 'Historial completo de cambios obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el historial de cambios', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene el cargo activo de un usuario específico.
     *
     * Este método retorna el cargo activo actual del usuario, incluyendo
     * información detallada del cargo, oficina, dependencia y jerarquía completa.
     *
     * @param int $userId El ID del usuario
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el cargo activo del usuario
     *
     * @urlParam userId integer required El ID del usuario. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Cargo activo del usuario obtenido exitosamente",
     *   "data": {
     *     "cargo": {
     *       "id": 23,
     *       "nom_organico": "Gerente",
     *       "cod_organico": "GER001",
     *       "tipo": "Cargo",
     *       "fecha_inicio": "2024-01-15",
     *       "observaciones": null
     *     },
     *     "oficina": {
     *       "id": 3,
     *       "nom_organico": "Oficina Principal",
     *       "cod_organico": "OFI001",
     *       "tipo": "Oficina"
     *     },
     *     "dependencia": {
     *       "id": 2,
     *       "nom_organico": "GERENCIA",
     *       "cod_organico": "100",
     *       "tipo": "Dependencia"
     *     },
     *     "tiene_cargo_activo": true
     *   }
     * }
     *
     * @response 200 {
     *   "status": true,
     *   "message": "El usuario no tiene cargo activo actualmente",
     *   "data": {
     *     "cargo": null,
     *     "oficina": null,
     *     "dependencia": null,
     *     "tiene_cargo_activo": false
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Usuario no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el cargo del usuario",
     *   "error": "Error message"
     * }
     */
    public function getUserCargo(int $userId)
    {
        try {
            $user = User::with(['cargoActivo.cargo'])->find($userId);

            if (!$user) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            $data = [
                'cargo' => null,
                'oficina' => null,
                'dependencia' => null,
                'tiene_cargo_activo' => false
            ];

            if ($user->cargoActivo && $user->cargoActivo->cargo) {
                $cargo = $user->cargoActivo->cargo;

                // Información del cargo
                $data['cargo'] = [
                    'id' => $cargo->id,
                    'nom_organico' => $cargo->nom_organico,
                    'cod_organico' => $cargo->cod_organico,
                    'tipo' => $cargo->tipo,
                    'fecha_inicio' => $user->cargoActivo->fecha_inicio?->format('Y-m-d'),
                    'observaciones' => $user->cargoActivo->observaciones
                ];

                // Usar el método getJerarquiaCompleta() para obtener la jerarquía
                $jerarquia = $cargo->getJerarquiaCompleta();

                // Buscar la dependencia y oficina directamente relacionadas al cargo
                $cargoIndex = -1;

                // Encontrar la posición del cargo en la jerarquía
                foreach ($jerarquia as $index => $nivel) {
                    if ($nivel['id'] === $cargo->id && $nivel['tipo'] === 'Cargo') {
                        $cargoIndex = $index;
                        break;
                    }
                }

                // Si encontramos el cargo, buscar su dependencia/oficina padre directa
                if ($cargoIndex > 0) {
                    $padreDirecto = $jerarquia[$cargoIndex - 1];

                    if ($padreDirecto['tipo'] === 'Oficina') {
                        $data['oficina'] = [
                            'id' => $padreDirecto['id'],
                            'nom_organico' => $padreDirecto['nom_organico'],
                            'cod_organico' => $padreDirecto['cod_organico'],
                            'tipo' => $padreDirecto['tipo']
                        ];

                        // Buscar la dependencia (padre de la oficina)
                        if ($cargoIndex > 1) {
                            $dependencia = $jerarquia[$cargoIndex - 2];
                            if ($dependencia['tipo'] === 'Dependencia') {
                                $data['dependencia'] = [
                                    'id' => $dependencia['id'],
                                    'nom_organico' => $dependencia['nom_organico'],
                                    'cod_organico' => $dependencia['cod_organico'],
                                    'tipo' => $dependencia['tipo']
                                ];
                            }
                        }
                    } elseif ($padreDirecto['tipo'] === 'Dependencia') {
                        $data['dependencia'] = [
                            'id' => $padreDirecto['id'],
                            'nom_organico' => $padreDirecto['nom_organico'],
                            'cod_organico' => $padreDirecto['cod_organico'],
                            'tipo' => $padreDirecto['tipo']
                        ];
                    }
                }

                $data['tiene_cargo_activo'] = true;
            }

            $message = $data['tiene_cargo_activo']
                ? 'Cargo activo del usuario obtenido exitosamente'
                : 'El usuario no tiene cargo activo actualmente';

            return $this->successResponse($data, $message);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el cargo del usuario', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene el historial completo de cambios de cargos de un usuario específico.
     *
     * Este método retorna un historial detallado de todos los cambios relacionados
     * con los cargos del usuario, incluyendo asignaciones, finalizaciones y actualizaciones.
     *
     * @param int $userId El ID del usuario
     * @param Request $request La solicitud HTTP con parámetros opcionales
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el historial de cargos
     *
     * @urlParam userId integer required El ID del usuario. Example: 1
     * @queryParam limit integer Número máximo de registros a retornar (por defecto: 50). Example: 20
     * @queryParam desde string Fecha de inicio para filtrar (formato: Y-m-d). Example: "2024-01-01"
     * @queryParam hasta string Fecha de fin para filtrar (formato: Y-m-d). Example: "2024-12-31"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Historial de cargos obtenido exitosamente",
     *   "data": {
     *     "usuario": {
     *       "id": 1,
     *       "nombres": "Juan Carlos",
     *       "apellidos": "Pérez García"
     *     },
     *     "cargos": [
     *       {
     *         "id": 1,
     *         "accion": "Cargo asignado",
     *         "descripcion": "Se asignó el cargo 'Gerente'",
     *         "fecha": "2024-01-15T10:30:00.000000Z",
     *         "fecha_formateada": "15 Ene 2024, 10:30",
     *         "detalles": {
     *           "cargo_id": 23,
     *           "cargo_nombre": "Gerente",
     *           "fecha_inicio": "2024-01-15",
     *           "estado": "activo",
     *           "observaciones": null
     *         }
     *       }
     *     ],
     *     "total": 3,
     *     "resumen": {
     *       "total_asignaciones": 2,
     *       "total_finalizaciones": 1,
     *       "cargo_activo_actual": "Gerente"
     *     }
     *   }
     * }
     */
    public function getHistorialCargos(int $userId, Request $request)
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            $limit = $request->input('limit', 50);
            $desde = $request->input('desde');
            $hasta = $request->input('hasta');

            $query = UserCargo::where('user_id', $userId)
                ->with('cargo')
                ->orderBy('created_at', 'desc');

            if ($desde) {
                $query->whereDate('created_at', '>=', $desde);
            }

            if ($hasta) {
                $query->whereDate('created_at', '<=', $hasta);
            }

            $cargos = $query->get();

            $historial = collect();
            $totalAsignaciones = 0;
            $totalFinalizaciones = 0;

            foreach ($cargos as $cargo) {
                $accion = $cargo->estado && !$cargo->fecha_fin ? 'Cargo asignado' : 'Cargo finalizado';
                $descripcion = $cargo->cargo
                    ? "Se {$accion} el cargo '{$cargo->cargo->nom_organico}'"
                    : "Se {$accion} un cargo";

                if ($accion === 'Cargo asignado') {
                    $totalAsignaciones++;
                } else {
                    $totalFinalizaciones++;
                }

                $historial->push([
                    'id' => $cargo->id,
                    'accion' => $accion,
                    'descripcion' => $descripcion,
                    'fecha' => $cargo->created_at,
                    'detalles' => [
                        'cargo_id' => $cargo->cargo_id,
                        'cargo_nombre' => $cargo->cargo?->nom_organico,
                        'cargo_codigo' => $cargo->cargo?->cod_organico,
                        'fecha_inicio' => $cargo->fecha_inicio?->format('Y-m-d'),
                        'fecha_fin' => $cargo->fecha_fin?->format('Y-m-d'),
                        'estado' => $cargo->estado && !$cargo->fecha_fin ? 'activo' : 'finalizado',
                        'observaciones' => $cargo->observaciones,
                        'duracion_dias' => $cargo->fecha_fin
                            ? $cargo->fecha_inicio->diffInDays($cargo->fecha_fin)
                            : ($cargo->fecha_inicio ? $cargo->fecha_inicio->diffInDays(now()) : null)
                    ]
                ]);

                // Si fue actualizado después de creado
                if ($cargo->updated_at && $cargo->updated_at->notEqualTo($cargo->created_at)) {
                    $historial->push([
                        'id' => $cargo->id . '_update',
                        'accion' => 'Cargo actualizado',
                        'descripcion' => "Se actualizó información del cargo '{$cargo->cargo?->nom_organico}'",
                        'fecha' => $cargo->updated_at,
                        'detalles' => [
                            'cargo_id' => $cargo->cargo_id,
                            'cargo_nombre' => $cargo->cargo?->nom_organico,
                            'fecha_actualizacion' => $cargo->updated_at->toISOString()
                        ]
                    ]);
                }
            }

            // Ordenar por fecha (más reciente primero)
            $historial = $historial->sortByDesc(function ($item) {
                return $item['fecha']->timestamp;
            })->values();

            $total = $historial->count();
            $historial = $historial->take($limit);

            $cargoActivo = $user->cargoActivo;

            $data = [
                'usuario' => [
                    'id' => $user->id,
                    'nombres' => $user->nombres,
                    'apellidos' => $user->apellidos
                ],
                'cargos' => $historial->map(function ($item) {
                    return [
                        'id' => $item['id'],
                        'accion' => $item['accion'],
                        'descripcion' => $item['descripcion'],
                        'fecha' => $item['fecha']->toISOString(),
                        'fecha_formateada' => $item['fecha']->format('d M Y, H:i'),
                        'detalles' => $item['detalles']
                    ];
                }),
                'total' => $total,
                'resumen' => [
                    'total_asignaciones' => $totalAsignaciones,
                    'total_finalizaciones' => $totalFinalizaciones,
                    'cargo_activo_actual' => $cargoActivo && $cargoActivo->cargo
                        ? $cargoActivo->cargo->nom_organico
                        : null
                ]
            ];

            return $this->successResponse($data, 'Historial de cargos obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el historial de cargos', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene el historial completo de cambios de sedes de un usuario específico.
     *
     * Este método retorna un historial detallado de todos los cambios relacionados
     * con las sedes del usuario, incluyendo asignaciones, desasignaciones y actualizaciones.
     *
     * @param int $userId El ID del usuario
     * @param Request $request La solicitud HTTP con parámetros opcionales
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el historial de sedes
     *
     * @urlParam userId integer required El ID del usuario. Example: 1
     * @queryParam limit integer Número máximo de registros a retornar (por defecto: 50). Example: 20
     * @queryParam desde string Fecha de inicio para filtrar (formato: Y-m-d). Example: "2024-01-01"
     * @queryParam hasta string Fecha de fin para filtrar (formato: Y-m-d). Example: "2024-12-31"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Historial de sedes obtenido exitosamente",
     *   "data": {
     *     "usuario": {
     *       "id": 1,
     *       "nombres": "Juan Carlos",
     *       "apellidos": "Pérez García"
     *     },
     *     "sedes": [
     *       {
     *         "id": 1,
     *         "accion": "Sede asignada",
     *         "descripcion": "Se asignó la sede 'Sede Principal'",
     *         "fecha": "2024-01-15T10:30:00.000000Z",
     *         "fecha_formateada": "15 Ene 2024, 10:30",
     *         "detalles": {
     *           "sede_id": 1,
     *           "sede_nombre": "Sede Principal",
     *           "estado": "activa",
     *           "observaciones": null
     *         }
     *       }
     *     ],
     *     "total": 3,
     *     "resumen": {
     *       "total_asignaciones": 2,
     *       "total_desasignaciones": 1,
     *       "sedes_activas_actuales": 1
     *     }
     *   }
     * }
     */
    public function getHistorialSedes(int $userId, Request $request)
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            $limit = $request->input('limit', 50);
            $desde = $request->input('desde');
            $hasta = $request->input('hasta');

            $query = DB::table('users_sedes')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc');

            if ($desde) {
                $query->whereDate('created_at', '>=', $desde);
            }

            if ($hasta) {
                $query->whereDate('created_at', '<=', $hasta);
            }

            $sedes = $query->get();

            $historial = collect();
            $totalAsignaciones = 0;
            $totalDesasignaciones = 0;

            foreach ($sedes as $sede) {
                $sedeModel = \App\Models\Configuracion\ConfigSede::find($sede->sede_id);
                $accion = $sede->estado ? 'Sede asignada' : 'Sede desasignada';
                $descripcion = $sedeModel
                    ? "Se {$accion} la sede '{$sedeModel->nombre}'"
                    : "Se {$accion} una sede";

                if ($accion === 'Sede asignada') {
                    $totalAsignaciones++;
                } else {
                    $totalDesasignaciones++;
                }

                $historial->push([
                    'id' => $sede->id,
                    'accion' => $accion,
                    'descripcion' => $descripcion,
                    'fecha' => \Carbon\Carbon::parse($sede->created_at),
                    'detalles' => [
                        'sede_id' => $sede->sede_id,
                        'sede_nombre' => $sedeModel?->nombre,
                        'sede_codigo' => $sedeModel?->codigo,
                        'sede_direccion' => $sedeModel?->direccion,
                        'estado' => $sede->estado ? 'activa' : 'inactiva',
                        'observaciones' => $sede->observaciones
                    ]
                ]);

                // Si fue actualizado después de creado
                $createdAt = \Carbon\Carbon::parse($sede->created_at);
                $updatedAt = \Carbon\Carbon::parse($sede->updated_at);
                if ($updatedAt && $updatedAt->notEqualTo($createdAt)) {
                    $historial->push([
                        'id' => $sede->id . '_update',
                        'accion' => 'Sede actualizada',
                        'descripcion' => "Se actualizó información de la sede '{$sedeModel?->nombre}'",
                        'fecha' => $updatedAt,
                        'detalles' => [
                            'sede_id' => $sede->sede_id,
                            'sede_nombre' => $sedeModel?->nombre,
                            'fecha_actualizacion' => $updatedAt->toISOString(),
                            'estado_actual' => $sede->estado ? 'activa' : 'inactiva'
                        ]
                    ]);
                }
            }

            // Ordenar por fecha (más reciente primero)
            $historial = $historial->sortByDesc(function ($item) {
                return $item['fecha']->timestamp;
            })->values();

            $total = $historial->count();
            $historial = $historial->take($limit);

            $sedesActivas = $user->sedesActivas()->count();

            $data = [
                'usuario' => [
                    'id' => $user->id,
                    'nombres' => $user->nombres,
                    'apellidos' => $user->apellidos
                ],
                'sedes' => $historial->map(function ($item) {
                    return [
                        'id' => $item['id'],
                        'accion' => $item['accion'],
                        'descripcion' => $item['descripcion'],
                        'fecha' => $item['fecha']->toISOString(),
                        'fecha_formateada' => $item['fecha']->format('d M Y, H:i'),
                        'detalles' => $item['detalles']
                    ];
                }),
                'total' => $total,
                'resumen' => [
                    'total_asignaciones' => $totalAsignaciones,
                    'total_desasignaciones' => $totalDesasignaciones,
                    'sedes_activas_actuales' => $sedesActivas
                ]
            ];

            return $this->successResponse($data, 'Historial de sedes obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el historial de sedes', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene el historial de cambios de roles de un usuario específico.
     *
     * Este método retorna información sobre los roles asignados al usuario.
     * Nota: Spatie Laravel-Permission no almacena historial de cambios de roles
     * por defecto, por lo que este método muestra los roles actuales del usuario.
     *
     * @param int $userId El ID del usuario
     * @param Request $request La solicitud HTTP con parámetros opcionales
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el historial de roles
     *
     * @urlParam userId integer required El ID del usuario. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Historial de roles obtenido exitosamente",
     *   "data": {
     *     "usuario": {
     *       "id": 1,
     *       "nombres": "Juan Carlos",
     *       "apellidos": "Pérez García"
     *     },
     *     "roles": [
     *       {
     *         "id": 1,
     *         "name": "Administrador",
     *         "guard_name": "web",
     *         "permissions": [
     *           {
     *             "id": 1,
     *             "name": "user.create",
     *             "guard_name": "web"
     *           }
     *         ]
     *       }
     *     ],
     *     "resumen": {
     *       "total_roles": 1,
     *       "nombres_roles": ["Administrador"]
     *     },
     *     "nota": "Spatie Laravel-Permission no almacena historial de cambios de roles. Se muestran los roles actuales del usuario."
     *   }
     * }
     */
    public function getHistorialRoles(int $userId, Request $request)
    {
        try {
            // Limpiar cache de permisos para obtener datos actualizados
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            $user = User::with(['roles.permissions'])->find($userId);

            if (!$user) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            $roles = $user->roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                    'created_at' => $role->created_at?->toISOString(),
                    'updated_at' => $role->updated_at?->toISOString(),
                    'permissions' => $role->permissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'guard_name' => $permission->guard_name
                        ];
                    })
                ];
            });

            $data = [
                'usuario' => [
                    'id' => $user->id,
                    'nombres' => $user->nombres,
                    'apellidos' => $user->apellidos,
                    'email' => $user->email
                ],
                'roles' => $roles,
                'resumen' => [
                    'total_roles' => $roles->count(),
                    'nombres_roles' => $roles->pluck('name')->toArray()
                ],
                'nota' => 'Spatie Laravel-Permission no almacena historial de cambios de roles. Se muestran los roles actuales del usuario.'
            ];

            return $this->successResponse($data, 'Historial de roles obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el historial de roles', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene información completa de conexiones de un usuario específico.
     *
     * Este método retorna información consolidada sobre las conexiones del usuario,
     * incluyendo sesiones recientes, estadísticas de conexiones, dispositivos únicos,
     * IPs utilizadas y resumen de actividad de conexión.
     *
     * @param int $userId El ID del usuario
     * @param Request $request La solicitud HTTP con parámetros opcionales
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con información de conexiones
     *
     * @urlParam userId integer required El ID del usuario. Example: 1
     * @queryParam limit integer Número máximo de sesiones recientes a retornar (por defecto: 10). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Información de conexiones obtenida exitosamente",
     *   "data": {
     *     "usuario": {
     *       "id": 1,
     *       "nombres": "Juan Carlos",
     *       "apellidos": "Pérez García",
     *       "email": "juan.perez@example.com"
     *     },
     *     "sesiones_recientes": [
     *       {
     *         "id": 1,
     *         "browserName": "Chrome on Windows",
     *         "device": "Desktop",
     *         "location": "192.168.1.100",
     *         "date": "15 Jan 2024, 14:30",
     *         "browserIcon": "tabler-brand-windows",
     *         "ip_address": "192.168.1.100",
     *         "last_login_at": "2024-01-15T14:30:00.000000Z"
     *       }
     *     ],
     *     "estadisticas": {
     *       "total_conexiones": 25,
     *       "conexiones_ultimos_30_dias": 8,
     *       "conexiones_ultimos_7_dias": 3,
     *       "dispositivos_unicos": 4,
     *       "ips_unicas": 5,
     *       "primera_conexion": "2024-01-01T00:00:00.000000Z",
     *       "ultima_conexion": "2024-01-15T14:30:00.000000Z"
     *     },
     *     "dispositivos": [
     *       {
     *         "tipo": "Desktop",
     *         "plataforma": "Windows",
     *         "navegador": "Chrome",
     *         "conexiones": 15
     *       }
     *     ],
     *     "ips_utilizadas": [
     *       {
     *         "ip": "192.168.1.100",
     *         "conexiones": 10,
     *         "ultima_conexion": "2024-01-15T14:30:00.000000Z"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Usuario no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener la información de conexiones",
     *   "error": "Error message"
     * }
     */
    public function getConexiones(int $userId, Request $request)
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            $limit = $request->input('limit', 10);

            // Obtener todas las sesiones del usuario
            $todasLasSesiones = $user->sessions()
                ->orderBy('last_login_at', 'desc')
                ->get();

            $agent = new Agent();

            // Formatear sesiones recientes
            $sesionesRecientes = $todasLasSesiones->take($limit)->map(function ($session) use ($agent) {
                $agent->setUserAgent($session->user_agent);

                return [
                    'id' => $session->id,
                    'browserName' => $agent->browser() . ' on ' . $agent->platform(),
                    'device' => $agent->device(),
                    'location' => $session->ip_address,
                    'date' => $session->last_login_at->format('d M Y, H:i'),
                    'browserIcon' => $this->getDeviceIcon($agent),
                    'user_agent' => $session->user_agent,
                    'ip_address' => $session->ip_address,
                    'last_login_at' => $session->last_login_at->toISOString(),
                ];
            });

            // Calcular estadísticas
            $totalConexiones = $todasLasSesiones->count();
            $hace30Dias = now()->subDays(30);
            $hace7Dias = now()->subDays(7);

            $conexionesUltimos30Dias = $todasLasSesiones->filter(function ($session) use ($hace30Dias) {
                return $session->last_login_at >= $hace30Dias;
            })->count();

            $conexionesUltimos7Dias = $todasLasSesiones->filter(function ($session) use ($hace7Dias) {
                return $session->last_login_at >= $hace7Dias;
            })->count();

            // Dispositivos únicos
            $dispositivos = [];
            foreach ($todasLasSesiones as $session) {
                $agent->setUserAgent($session->user_agent);
                $key = $agent->device() . '|' . $agent->platform() . '|' . $agent->browser();

                if (!isset($dispositivos[$key])) {
                    $dispositivos[$key] = [
                        'tipo' => $agent->device() ?: 'Desconocido',
                        'plataforma' => $agent->platform() ?: 'Desconocido',
                        'navegador' => $agent->browser() ?: 'Desconocido',
                        'conexiones' => 0
                    ];
                }
                $dispositivos[$key]['conexiones']++;
            }
            $dispositivos = array_values($dispositivos);

            // IPs únicas
            $ips = [];
            foreach ($todasLasSesiones as $session) {
                $ip = $session->ip_address;
                if ($ip) {
                    if (!isset($ips[$ip])) {
                        $ips[$ip] = [
                            'ip' => $ip,
                            'conexiones' => 0,
                            'ultima_conexion' => $session->last_login_at
                        ];
                    } else {
                        $ips[$ip]['conexiones']++;
                        if ($session->last_login_at > $ips[$ip]['ultima_conexion']) {
                            $ips[$ip]['ultima_conexion'] = $session->last_login_at;
                        }
                    }
                }
            }

            // Ordenar IPs por última conexión (más reciente primero)
            usort($ips, function ($a, $b) {
                return $b['ultima_conexion'] <=> $a['ultima_conexion'];
            });

            $ips = array_map(function ($ip) {
                return [
                    'ip' => $ip['ip'],
                    'conexiones' => $ip['conexiones'],
                    'ultima_conexion' => $ip['ultima_conexion']->toISOString()
                ];
            }, $ips);

            // Primera y última conexión
            $primeraConexion = $todasLasSesiones->last()?->last_login_at;
            $ultimaConexion = $todasLasSesiones->first()?->last_login_at;

            $data = [
                'usuario' => [
                    'id' => $user->id,
                    'nombres' => $user->nombres,
                    'apellidos' => $user->apellidos,
                    'email' => $user->email
                ],
                'sesiones_recientes' => $sesionesRecientes,
                'estadisticas' => [
                    'total_conexiones' => $totalConexiones,
                    'conexiones_ultimos_30_dias' => $conexionesUltimos30Dias,
                    'conexiones_ultimos_7_dias' => $conexionesUltimos7Dias,
                    'dispositivos_unicos' => count($dispositivos),
                    'ips_unicas' => count($ips),
                    'primera_conexion' => $primeraConexion?->toISOString(),
                    'ultima_conexion' => $ultimaConexion?->toISOString()
                ],
                'dispositivos' => $dispositivos,
                'ips_utilizadas' => $ips
            ];

            return $this->successResponse($data, 'Información de conexiones obtenida exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la información de conexiones', $e->getMessage(), 500);
        }
    }

    /**
     * Función de ayuda para obtener el ícono del dispositivo.
     *
     * @param Agent $agent Instancia del agente de usuario
     * @return string Nombre del ícono
     */
    private function getDeviceIcon(Agent $agent): string
    {
        if ($agent->isDesktop()) {
            if (str_contains(strtolower($agent->platform()), 'windows')) {
                return 'tabler-brand-windows';
            }
            if (str_contains(strtolower($agent->platform()), 'mac')) {
                return 'tabler-brand-apple';
            }
            return 'tabler-device-desktop';
        }

        if ($agent->isMobile()) {
            if (str_contains(strtolower($agent->platform()), 'android')) {
                return 'tabler-brand-android';
            }
            if (str_contains(strtolower($agent->platform()), 'ios')) {
                return 'tabler-device-mobile';
            }
            return 'tabler-device-mobile';
        }

        return 'tabler-device-desktop';
    }

    /**
     * Deshabilita todos los cargos activos de un usuario.
     *
     * Este método privado finaliza todos los cargos activos del usuario,
     * estableciendo estado = false y fecha_fin = fecha actual.
     *
     * @param User $user El usuario al que se le deshabilitarán los cargos
     * @return void
     */
    private function deshabilitarTodosCargosUsuario(User $user): void
    {
        // Actualizar todos los cargos activos del usuario
        DB::table('users_cargos')
            ->where('user_id', $user->id)
            ->where('estado', true)
            ->whereNull('fecha_fin')
            ->update([
                'estado' => false,
                'fecha_fin' => now()->format('Y-m-d'),
                'updated_at' => now()
            ]);
    }
}
