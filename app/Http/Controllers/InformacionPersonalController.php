<?php

namespace App\Http\Controllers;

use App\Models\informacionpersonal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class InformacionPersonalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request) {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Aplica paginación al resultado del filtro

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }



    public function listarEstudiantesConFoto()
    {
        try {
            $carrerasExcluidas = ['056', '122', '124', '197', '206', '601', '602', '603'];

            $sql = '
                SELECT 
                    ip.CIInfPer,
                    ip.NombInfPer,
                    ip.ApellInfPer,
                    ip.ApellMatInfPer,
                    ip.mailInst,
                    c.NombCarr
                FROM informacionpersonal ip
                INNER JOIN ingreso i ON i.CIInfPer = ip.CIInfPer
                INNER JOIN carrera c ON c.idCarr = i.idcarr
                INNER JOIN factura f ON f.cedula = ip.CIInfPer
                WHERE 
                    f.idper = 125
                    AND i.idper = (
                        SELECT MAX(i2.idper)
                        FROM ingreso i2
                        INNER JOIN carrera c2 ON c2.idCarr = i2.idcarr
                        WHERE i2.CIInfPer = ip.CIInfPer
                          AND c2.idCarr NOT IN (' . implode(',', array_fill(0, count($carrerasExcluidas), '?')) . ")
                          AND c2.NombCarr NOT LIKE '%TRABAJO DE INTEGRACIÓN CURRICULAR%'
                    )
                    AND c.idCarr NOT IN (" . implode(',', array_fill(0, count($carrerasExcluidas), '?')) . ")
                    AND c.NombCarr NOT LIKE '%TRABAJO DE INTEGRACIÓN CURRICULAR%'
                    AND ip.fotografia IS NOT NULL
                GROUP BY 
                    ip.CIInfPer,
                    ip.NombInfPer,
                    ip.ApellInfPer,
                    ip.ApellMatInfPer,
                    ip.mailInst,
                    c.NombCarr
            ";

            // Bind parameters dos veces (subquery + query principal)
            $bindings = array_merge($carrerasExcluidas, $carrerasExcluidas);

            $estudiantes = DB::select($sql, $bindings);

            return response()->json([
                'message' => 'Estudiantes con fotografía obtenidos correctamente.',
                'data' => $estudiantes,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => true,
                'message' => 'Error al obtener los datos.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function estudiantesfoto(Request $request)
    {
        try {
            // 1. Capturar parámetros para la llave de caché
            $page = $request->input('page', 1);
            $perPage = min($request->input('per_page', 20), 50);
            $searchQuery = $request->input('search_query', '');
            $carreraFilter = $request->input('carrera_name', 'Todos');

            // 2. Crear una llave única basada en los parámetros (MD5 para la búsqueda por seguridad/longitud)
            $cacheKey = "estudiantes_foto_page_{$page}_limit_{$perPage}_search_" . md5($searchQuery) . "_carrera_" . str_replace(' ', '_', $carreraFilter);

            // 3. Intentar obtener de caché o ejecutar la consulta (10 minutos de vida)
            $responseData = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($perPage, $searchQuery, $carreraFilter) {

                $carrerasAExcluir = ['056', '122', '124', '197', '206', '601', '602', '603'];

                $query = informacionpersonal::select(
                    'informacionpersonal.CIInfPer',
                    'informacionpersonal.NombInfPer',
                    'informacionpersonal.ApellInfPer',
                    'informacionpersonal.ApellMatInfPer',
                    'informacionpersonal.mailInst',
                    'carrera.NombCarr',
                    'carrera.idCarr'
                )
                    ->join('factura', 'factura.cedula', '=', 'informacionpersonal.CIInfPer')
                    ->join('ingreso', 'ingreso.CIInfPer', '=', 'informacionpersonal.CIInfPer')
                    ->join('carrera', 'carrera.idCarr', '=', 'ingreso.idcarr')
                    ->where('factura.idper', 126)
                    ->where('carrera.StatusCarr', 1)
                    ->whereIn('ingreso.idper', function ($sub) use ($carrerasAExcluir) {
                        $sub->from('ingreso as i2')
                            ->selectRaw('MAX(i2.idper)')
                            ->join('carrera as c2', 'c2.idCarr', '=', 'i2.idcarr')
                            ->whereColumn('i2.CIInfPer', 'ingreso.CIInfPer')
                            ->whereNotIn('c2.idCarr', $carrerasAExcluir)
                            ->where('c2.NombCarr', 'NOT LIKE', '%TRABAJO DE INTEGRACIÓN CURRICULAR%')
                            ->groupBy('i2.CIInfPer');
                    })
                    ->whereNotIn('carrera.idCarr', $carrerasAExcluir)
                    ->where('carrera.NombCarr', 'NOT LIKE', '%TRABAJO DE INTEGRACIÓN CURRICULAR%')
                    ->whereNotNull('informacionpersonal.fotografia');

                // Filtrado por búsqueda (Cédula o Nombres)
                if (!empty($searchQuery)) {
                    $query->where(function ($q) use ($searchQuery) {
                        $q->where('informacionpersonal.CIInfPer', 'LIKE', "%{$searchQuery}%")
                            ->orWhere('informacionpersonal.NombInfPer', 'LIKE', "%{$searchQuery}%")
                            ->orWhere('informacionpersonal.ApellInfPer', 'LIKE', "%{$searchQuery}%")
                            ->orWhere('informacionpersonal.ApellMatInfPer', 'LIKE', "%{$searchQuery}%");
                    });
                }

                // Filtrado por Carrera
                if (!empty($carreraFilter) && $carreraFilter !== 'Todos') {
                    $query->where('carrera.idCarr', $carreraFilter);
                }

                $query->groupBy(
                    'informacionpersonal.CIInfPer',
                    'informacionpersonal.NombInfPer',
                    'informacionpersonal.ApellInfPer',
                    'informacionpersonal.ApellMatInfPer',
                    'informacionpersonal.mailInst',
                    'carrera.NombCarr',
                    'carrera.idCarr'
                );

                $data = $query->paginate($perPage);

                if ($data->isEmpty()) {
                    return ['data' => [], 'pagination' => null];
                }

                // Transformar la colección para el frontend
                $items = $data->getCollection()->map(function ($item) {
                    // Verificamos si ya existe el estado de HikCentral en la caché individual
                    // Esto es opcional, pero ayuda a que si ya se verificó, el valor persista
                    $ci = $item->CIInfPer;
                    $statusHC = Cache::get("hik_status_est_{$ci}");

                    return [
                        'CIInfPer'         => $item->CIInfPer,
                        'NombInfPer'       => $item->NombInfPer,
                        'ApellInfPer'      => $item->ApellInfPer,
                        'ApellMatInfPer'   => $item->ApellMatInfPer,
                        'mailInst'         => $item->mailInst,
                        'NombCarr'         => $item->NombCarr,
                        'hasPhoto'         => true,
                        'estaRegistradoHC' => $statusHC // null, true o false
                    ];
                });

                return [
                    'data' => $items,
                    'pagination' => [
                        'current_page' => $data->currentPage(),
                        'per_page'     => $data->perPage(),
                        'total'        => $data->total(),
                        'last_page'    => $data->lastPage(),
                    ]
                ];
            });

            // 4. Retornar respuesta
            if (empty($responseData['data'])) {
                return response()->json(['data' => [], 'message' => 'No se encontraron estudiantes'], 200);
            }

            return response()->json($responseData, 200);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => true,
                'message' => 'Error interno: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function getEstudianteByCI(Request $request, $ci)
    {
        try {
            // 1. Crear una llave de caché específica para este CI
            $cacheKey = "estudiante_individual_{$ci}";
            $idperidod = $request->input('idper');
            // 2. Intentar recuperar de caché o buscar en la DB
            $estudiante = Cache::remember($cacheKey, now()->addMinutes(1), function () use ($ci, $idperidod) {
                $carrerasAExcluir = ['056', '122', '124', '197', '206', '601', '602', '603'];

                $item = informacionpersonal::select(
                    'informacionpersonal.*',
                    'carrera.idCarr',
                    'carrera.codihicenter',
                    'carrera.NombCarr',
                    'carrera.StatusCarr'
                )
                    ->join('factura', 'factura.cedula', '=', 'informacionpersonal.CIInfPer')
                    ->join('ingreso', 'ingreso.CIInfPer', '=', 'informacionpersonal.CIInfPer')
                    ->join('carrera', 'carrera.idCarr', '=', 'ingreso.idcarr')
                    ->where('informacionpersonal.CIInfPer', $ci) // Filtro por el CI solicitado
                    ->where('factura.idper', $idperidod) // Solo periodo vigente
                    ->where('carrera.StatusCarr', 1)
                    ->whereIn('ingreso.idper', function ($sub) use ($carrerasAExcluir) {
                        $sub->from('ingreso as i2')
                            ->selectRaw('MAX(i2.idper)')
                            ->join('carrera as c2', 'c2.idCarr', '=', 'i2.idcarr')
                            ->whereColumn('i2.CIInfPer', 'ingreso.CIInfPer')
                            ->whereNotIn('c2.idCarr', $carrerasAExcluir)
                            ->where('c2.NombCarr', 'NOT LIKE', '%TRABAJO DE INTEGRACIÓN CURRICULAR%')
                            ->groupBy('i2.CIInfPer');
                    })
                    ->whereNotIn('carrera.idCarr', $carrerasAExcluir)
                    ->where('carrera.NombCarr', 'NOT LIKE', '%TRABAJO DE INTEGRACIÓN CURRICULAR%')
                    ->first();

                if (!$item) {
                    return null;
                }

                // 3. Transformar el modelo a un array plano (limpio para el front)
                return [
                    'CIInfPer'         => $item->CIInfPer,
                    'NombInfPer'       => $item->NombInfPer,
                    'ApellInfPer'      => $item->ApellInfPer,
                    'ApellMatInfPer'   => $item->ApellMatInfPer,
                    'mailInst'         => $item->mailInst,
                    'NombCarr'       => $item->NombCarr,
                    'hasPhoto'         => true,
                    'estaRegistradoHC' => null // Cruce con el estado de HikCentral
                ];
            });

            // 4. Validar si se encontró el docente
            if (!$estudiante) {
                return response()->json([
                    'error' => true,
                    'message' => "No se encontró el docente con CI: {$ci}"
                ], 404);
            }

            return response()->json($estudiante, 200);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => true,
                'message' => 'Error al obtener docente: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getFotografia2($ci)
    {
        try {
            // Cacheamos la foto por 60 minutos para ahorrar lecturas a la DB
            $fotoData = Cache::remember("foto_blob_{$ci}", 120, function () use ($ci) {
                $persona = informacionpersonal::where('CIInfPer', $ci)
                    ->select('fotografia')
                    ->first();
                return $persona ? $persona->fotografia : null;
            });

            if (!$fotoData) {
                return response()->json(['error' => 'No encontrada'], 404);
            }

            $mime = 'image/jpeg';
            if (extension_loaded('fileinfo')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_buffer($finfo, $fotoData);
                finfo_close($finfo);
            }

            return response($fotoData, 200)
                ->header('Content-Type', $mime)
                ->header('Cache-Control', 'public, max-age=3600'); // Cache de navegador

        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    //Estudiantes del pre
    public function getEstudiantesPre(Request $request)
    {
        try {
            // 1. Capturar parámetros para la llave de caché
            $page = $request->input('page', 1);
            $perPage = min($request->input('per_page', 20), 50);
            $searchQuery = $request->input('search_query', '');
            $carreraFilter = $request->input('carrera_name', 'Todos');
            // 2. Crear una llave única basada en los parámetros (MD5 para la búsqueda por seguridad/longitud)
            $cacheKey = "estudiantes_pre_page_{$page}_limit_{$perPage}_search_" . md5($searchQuery) . "_carrera_" . str_replace(' ', '_', $carreraFilter);
            // 3. Intentar obtener de caché o ejecutar la consulta (10 minutos de vida)
            // 3. Intentar obtener de caché o ejecutar la consulta (10 minutos de vida)
            $responseData = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($perPage, $searchQuery, $carreraFilter) {



                $query = informacionpersonal::select(
                    'informacionpersonal.CIInfPer',
                    'informacionpersonal.NombInfPer',
                    'informacionpersonal.ApellInfPer',
                    'informacionpersonal.ApellMatInfPer',
                    'informacionpersonal.mailInst',
                    'ingreso_inscripcionsnnas.idCarreraSeleccionada',
                    'ingreso_inscripcionsnnas.idper',
                    'ingreso_inscripcionsnnas.NombCarr',
                    'ingreso_inscripcionsnnas.laboratorio_examen',

                )
                    ->join('ingreso_inscripcionsnnas', 'ingreso_inscripcionsnnas.CIInfPer', '=', 'informacionpersonal.CIInfPer')
                    ->where('ingreso_inscripcionsnnas.idper', 126)
                    ->whereNotNull('informacionpersonal.fotografia')
                    ->whereNotNull('ingreso_inscripcionsnnas.laboratorio_examen');

                // Filtrado por búsqueda (Cédula o Nombres)
                if (!empty($searchQuery)) {
                    $query->where(function ($q) use ($searchQuery) {
                        $q->where('informacionpersonal.CIInfPer', 'LIKE', "%{$searchQuery}%")
                            ->orWhere('informacionpersonal.NombInfPer', 'LIKE', "%{$searchQuery}%")
                            ->orWhere('informacionpersonal.ApellInfPer', 'LIKE', "%{$searchQuery}%")
                            ->orWhere('informacionpersonal.ApellMatInfPer', 'LIKE', "%{$searchQuery}%");
                    });
                }

                // Filtrado por Carrera
                if (!empty($carreraFilter) && $carreraFilter !== 'Todos') {
                    $query->where('ingreso_inscripcionsnnas.idCarreraSeleccionada', $carreraFilter);
                }

                $query->groupBy(
                    'informacionpersonal.CIInfPer',
                    'informacionpersonal.NombInfPer',
                    'informacionpersonal.ApellInfPer',
                    'informacionpersonal.ApellMatInfPer',
                    'informacionpersonal.mailInst',
                    'ingreso_inscripcionsnnas.idCarreraSeleccionada',
                    'ingreso_inscripcionsnnas.idper',
                    'ingreso_inscripcionsnnas.NombCarr',
                    'ingreso_inscripcionsnnas.laboratorio_examen',
                );

                $data = $query->paginate($perPage);

                if ($data->isEmpty()) {
                    return ['data' => [], 'pagination' => null];
                }

                // Transformar la colección para el frontend
                $items = $data->getCollection()->map(function ($item) {
                    // Verificamos si ya existe el estado de HikCentral en la caché individual
                    // Esto es opcional, pero ayuda a que si ya se verificó, el valor persista
                    $ci = $item->CIInfPer;
                    $statusHC = Cache::get("hik_status_pre_est_{$ci}");

                    return [
                        'CIInfPer'         => $item->CIInfPer,
                        'NombInfPer'       => $item->NombInfPer,
                        'ApellInfPer'      => $item->ApellInfPer,
                        'ApellMatInfPer'   => $item->ApellMatInfPer,
                        'mailInst'         => $item->mailInst,
                        'NombCarr'         => $item->NombCarr,
                        'hasPhoto'         => true,
                        'estaRegistradoHC' => $statusHC // null, true o false
                    ];
                });

                return [
                    'data' => $items,
                    'pagination' => [
                        'current_page' => $data->currentPage(),
                        'per_page'     => $data->perPage(),
                        'total'        => $data->total(),
                        'last_page'    => $data->lastPage(),
                    ]
                ];
            });

            // 4. Retornar respuesta
            if (empty($responseData['data'])) {
                return response()->json(['data' => [], 'message' => 'No se encontraron estudiantes'], 200);
            }

            return response()->json($responseData, 200);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => true,
                'message' => 'Error interno: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function getEstudiantesPreCI(Request $request, $ci)
    {
        try {
            // 1. Crear una llave de caché específica para este CI
            $cacheKey = "estudiante_pre_individual_{$ci}";
            // 2. Intentar recuperar de caché o buscar en la DB
            $estudiante = Cache::remember($cacheKey, now()->addMinutes(1), function () use ($ci) {

                $item = informacionpersonal::select(
                    'informacionpersonal.CIInfPer',
                    'informacionpersonal.NombInfPer',
                    'informacionpersonal.ApellInfPer',
                    'informacionpersonal.ApellMatInfPer',
                    'informacionpersonal.mailInst',
                    'ingreso_inscripcionsnnas.idCarreraSeleccionada',
                    'ingreso_inscripcionsnnas.idper',
                    'ingreso_inscripcionsnnas.NombCarr',
                    'ingreso_inscripcionsnnas.laboratorio_examen',
                )
                    ->join('ingreso_inscripcionsnnas', 'ingreso_inscripcionsnnas.CIInfPer', '=', 'informacionpersonal.CIInfPer')
                    ->where('ingreso_inscripcionsnnas.idper', 126)
                    ->whereNotNull('informacionpersonal.fotografia')
                    ->whereNotNull('ingreso_inscripcionsnnas.laboratorio_examen')
                    ->where('informacionpersonal.CIInfPer', $ci) 
                    ->first();

                if (!$item) {
                    return null;
                }

                // 3. Transformar el modelo a un array plano (limpio para el front)
                return [
                    'CIInfPer'         => $item->CIInfPer,
                    'NombInfPer'       => $item->NombInfPer,
                    'ApellInfPer'      => $item->ApellInfPer,
                    'ApellMatInfPer'   => $item->ApellMatInfPer,
                    'mailInst'         => $item->mailInst,
                    'NombCarr'       => $item->NombCarr,
                    'hasPhoto'         => true,
                    'estaRegistradoHC' => null // Cruce con el estado de HikCentral
                ];
            });

            // 4. Validar si se encontró el docente
            if (!$estudiante) {
                return response()->json([
                    'error' => true,
                    'message' => "No se encontró el estudiante con CI: {$ci}"
                ], 404);
            }

            return response()->json($estudiante, 200);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => true,
                'message' => 'Error al obtener estudiante: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function getPreFotografia2($ci)
    {
        try {
            // Cacheamos la foto por 60 minutos para ahorrar lecturas a la DB
            $fotoData = Cache::remember("foto_pre_blob_{$ci}", 420, function () use ($ci) {
                $persona = informacionpersonal::where('CIInfPer', $ci)
                    ->select('fotografia')
                    ->first();
                return $persona ? $persona->fotografia : null;
            });

            if (!$fotoData) {
                return response()->json(['error' => 'No encontrada'], 404);
            }

            $mime = 'image/jpeg';
            if (extension_loaded('fileinfo')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_buffer($finfo, $fotoData);
                finfo_close($finfo);
            }

            return response($fotoData, 200)
                ->header('Content-Type', $mime)
                ->header('Cache-Control', 'public, max-age=3600'); // Cache de navegador

        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function descargarFotosMasiva(Request $request)
    {
        // Aumentar el tiempo límite de ejecución para esta petición pesada
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        try {
            $carrerasAExcluir = ['056', '122', '124', '197', '206', '601', '602', '603'];

            // Usamos la misma lógica de filtro que 'estudiantesfoto', pero pedimos 'fotografia'
            // y NO usamos paginación.
            $query = informacionpersonal::select(
                'informacionpersonal.CIInfPer',
                'informacionpersonal.NombInfPer',
                'informacionpersonal.ApellInfPer',
                'informacionpersonal.ApellMatInfPer',
                'informacionpersonal.mailInst',
                'informacionpersonal.fotografia', // 👈 ¡Incluimos el dato binario de la foto!
                'carrera.NombCarr'
            )
                ->join('factura', 'factura.cedula', '=', 'informacionpersonal.CIInfPer')
                ->join('ingreso', 'ingreso.CIInfPer', '=', 'informacionpersonal.CIInfPer')
                ->join('carrera', 'carrera.idCarr', '=', 'ingreso.idcarr')

                // WHERE factura.idper = 125
                ->where('factura.idper', 125)

                // FILTRAR EL ÚLTIMO i.idper
                ->whereIn('ingreso.idper', function ($sub) use ($carrerasAExcluir) {
                    $sub->from('ingreso as i2')
                        ->selectRaw('MAX(i2.idper)')
                        ->join('carrera as c2', 'c2.idCarr', '=', 'i2.idcarr')
                        ->whereColumn('i2.CIInfPer', 'ingreso.CIInfPer')
                        ->whereNotIn('c2.idCarr', $carrerasAExcluir)
                        ->where('c2.NombCarr', 'NOT LIKE', '%TRABAJO DE INTEGRACIÓN CURRICULAR%')
                        ->groupBy('i2.CIInfPer');
                })

                // Excluir carreras
                ->whereNotIn('carrera.idCarr', $carrerasAExcluir)
                ->where('carrera.NombCarr', 'NOT LIKE', '%TRABAJO DE INTEGRACIÓN CURRICULAR%')

                // Foto válida
                ->whereNotNull('informacionpersonal.fotografia')
                ->whereRaw('LENGTH(informacionpersonal.fotografia) > 0')

                // GROUP BY
                ->groupBy(
                    'informacionpersonal.CIInfPer',
                    'informacionpersonal.NombInfPer',
                    'informacionpersonal.ApellInfPer',
                    'informacionpersonal.ApellMatInfPer',
                    'informacionpersonal.mailInst',
                    'informacionpersonal.fotografia', // 👈 Agregado al GROUP BY
                    'carrera.NombCarr'
                );

            // Obtenemos todos los resultados sin paginación
            $data = $query->get();

            // Convertir el dato binario (fotografia) a Base64 para enviarlo por JSON.
            // Esto aumenta el tamaño de la respuesta, pero reduce las peticiones de 8000+ a 1.
            $data->transform(function ($item) {
                $itemArray = $item->toArray();
                if (isset($itemArray['fotografia']) && $itemArray['fotografia'] !== null) {
                    // Convertir el dato binario BLOB/TEXT a Base64
                    $itemArray['fotografia'] = base64_encode($itemArray['fotografia']);
                } else {
                    // Asegurar que no hay problemas si el dato es NULL
                    $itemArray['fotografia'] = null;
                }

                return $itemArray;
            });

            if ($data->isEmpty()) {
                return response()->json(['data' => [], 'message' => 'No se encontraron estudiantes con fotografía para descarga masiva'], 200);
            }

            return response()->json(['data' => $data], 200);
        } catch (\Throwable $e) {
            // En caso de fallo (ej. timeout de BD, memoria), es mejor retornar error 500
            return response()->json([
                'error' => true,
                'message' => 'Error interno del servidor en descarga masiva: ' . $e->getMessage(),
            ], 500);
        }
    }
}
