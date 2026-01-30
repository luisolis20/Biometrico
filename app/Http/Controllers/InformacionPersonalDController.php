<?php

namespace App\Http\Controllers;

use App\Models\informacionpersonal_D;
use App\Models\informacionpersonal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;

class InformacionPersonalDController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            // 🔹 Controlar el número de registros por página
            $perPage = $request->input('per_page', 20);
            $perPage = min($perPage, 50); // No permitir más de 50 por página

            // 🔹 Consulta optimizada: solo columnas necesarias
            $data = informacionpersonal_D::select('CIInfPer', 'NombInfPer', 'ApellInfPer', 'ApellMatInfPer', 'mailInst', 'TipoInfPer', 'fotografia')
                ->where('StatusPer', 1)
                ->whereNotNull('fotografia')
                ->whereRaw("LENGTH(fotografia) > 0")
                ->paginate($perPage);

            if ($data->isEmpty()) {
                return response()->json(['data' => [], 'message' => 'No se encontraron datos con fotografía'], 200);
            }

            // 🔹 Solo convertir fotografía si el cliente lo solicita
            $withPhotos = $request->boolean('withPhotos', true);

            $data->getCollection()->transform(function ($item) use ($withPhotos) {
                $attributes = $item->getAttributes();

                if ($withPhotos && !empty($attributes['fotografia'])) {
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->buffer($attributes['fotografia']);
                    $attributes['fotografia'] = [
                        'mime' => $mimeType,
                        'data' => base64_encode($attributes['fotografia']),
                    ];
                } else {
                    // Si no se pide, enviamos solo una bandera
                    unset($attributes['fotografia']);
                    $attributes['hasPhoto'] = true;
                }

                return $attributes;
            });

            return response()->json([
                'data' => $data->items(),
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                ]
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => true,
                'message' => 'Error interno del servidor: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function getdocentes(Request $request)
    {
        try {
            // 1. Capturar parámetros para la llave de caché
            $page = $request->input('page', 1);
            $perPage = min($request->input('per_page', 20), 50);
            $searchQuery = $request->input('search_query', '');
            $tipoFilter = $request->input('tipoFilter', 'Todos');

            // 2. Crear una llave única basada en la consulta
            // Ejemplo: docentes_page_1_search_123_tipo_Todos
            $cacheKey = "docentes_page_{$page}_limit_{$perPage}_search_" . md5($searchQuery) . "_tipo_{$tipoFilter}";

            // 3. Intentar obtener de caché o ejecutar la consulta
            $responseData = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($perPage, $searchQuery, $tipoFilter) {

                $query = informacionpersonal_D::select('CIInfPer', 'NombInfPer', 'ApellInfPer', 'ApellMatInfPer', 'mailInst', 'TipoInfPer')
                    ->where('StatusPer', 1)
                    ->whereNotNull('fotografia');

                // Filtrado por búsqueda
                if (!empty($searchQuery)) {
                    $query->where(function ($q) use ($searchQuery) {
                        $q->where('CIInfPer', 'LIKE', "%{$searchQuery}%")
                            ->orWhere('NombInfPer', 'LIKE', "%{$searchQuery}%")
                            ->orWhere('ApellInfPer', 'LIKE', "%{$searchQuery}%")
                            ->orWhere('ApellMatInfPer', 'LIKE', "%{$searchQuery}%");
                    });
                }

                // Filtrado por tipo
                if (!empty($tipoFilter) && $tipoFilter !== 'Todos') {
                    $query->where('TipoInfPer', $tipoFilter);
                }

                $data = $query->paginate($perPage);

                if ($data->isEmpty()) {
                    return ['data' => [], 'pagination' => null];
                }

                // Transformar la colección
                $items = $data->getCollection()->map(function ($item) {
                    return [
                        'CIInfPer'        => $item->CIInfPer,
                        'NombInfPer'      => $item->NombInfPer,
                        'ApellInfPer'     => $item->ApellInfPer,
                        'ApellMatInfPer'  => $item->ApellMatInfPer,
                        'mailInst'         => $item->mailInst,
                        'TipoInfPer'      => $item->TipoInfPer,
                        'hasPhoto'        => true,
                        'estaRegistradoHC' => null // Estado inicial para el front
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

            if (empty($responseData['data'])) {
                return response()->json(['data' => [], 'message' => 'No se encontraron registros'], 200);
            }

            return response()->json($responseData, 200);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => true,
                'message' => 'Error interno: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function getDocenteByCI($ci)
    {
        try {
            // 1. Crear una llave de caché específica para este CI
            $cacheKey = "docente_individual_{$ci}";

            // 2. Intentar recuperar de caché o buscar en la DB
            $docente = Cache::remember($cacheKey, now()->addMinutes(2), function () use ($ci) {

                $item = informacionpersonal_D::select(
                    'CIInfPer',
                    'NombInfPer',
                    'ApellInfPer',
                    'ApellMatInfPer',
                    'mailInst',
                    'TipoInfPer'
                )
                    ->where('CIInfPer', $ci)
                    ->where('StatusPer', 1)
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
                    'TipoInfPer'       => $item->TipoInfPer,
                    'hasPhoto'         => true,
                    'estaRegistradoHC' => null // Cruce con el estado de HikCentral
                ];
            });

            // 4. Validar si se encontró el docente
            if (!$docente) {
                return response()->json([
                    'error' => true,
                    'message' => "No se encontró el docente con CI: {$ci}"
                ], 404);
            }

            return response()->json($docente, 200);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => true,
                'message' => 'Error al obtener docente: ' . $e->getMessage(),
            ], 500);
        }
    }



    public function getFotografia($ci)
    {
        try {
            // Cacheamos la foto por 60 minutos para evitar consultas repetitivas
            // Usamos el CI como llave de cache
            $fotoData = Cache::remember("foto_docente_{$ci}", 3600, function () use ($ci) {
                $persona = informacionpersonal_D::where('CIInfPer', $ci)
                    ->select('fotografia')
                    ->first();

                if (!$persona || empty($persona->fotografia)) return null;

                // Detectar MIME una sola vez
                $mime = 'image/jpeg';
                if (extension_loaded('fileinfo')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $detectedMime = finfo_buffer($finfo, $persona->fotografia);
                    finfo_close($finfo);
                    if ($detectedMime && strpos($detectedMime, 'image') === 0) {
                        $mime = $detectedMime;
                    }
                }

                return [
                    'binario' => $persona->fotografia,
                    'mime' => $mime
                ];
            });

            if (!$fotoData) {
                return response()->json(['error' => 'No encontrada'], 404);
            }

            return Response::make($fotoData['binario'], 200)
                ->header('Content-Type', $fotoData['mime'])
                ->header('Cache-Control', 'public, max-age=86400')
                ->header('Content-Disposition', 'inline; filename="foto_' . $ci . '"');
        } catch (\Throwable $e) {
            // Log::error('Error en getFotografia DController: ' . $e->getMessage()); // Opcional
            return response()->json(['error' => 'Error al obtener la fotografía: ' . $e->getMessage()], 500);
        }
    }


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
        $data = informacionpersonal_D::select('informacionpersonal_d.*')
            ->where('informacionpersonal_d.CIInfPer', $id)
            ->paginate(20);
        if ($data->isEmpty()) {
            return response()->json(['error' => 'No se encontraron datos para el ID especificado'], 404);
        }

        // Convertir los campos a UTF-8 válido para cada página
        $data->getCollection()->transform(function ($item) {
            $attributes = $item->getAttributes();

            foreach ($attributes as $key => $value) {
                if ($key === 'fotografia' && !empty($value)) {
                    // ✅ Convertir BLOB a base64
                    $attributes[$key] = base64_encode($value);
                } elseif (is_string($value) && $key !== 'fotografia') {
                    $attributes[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                }
            }

            return $attributes;
        });

        // Retornar la respuesta JSON con los metadatos de paginación
        try {
            return response()->json([
                'data' => $data->items(),
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al codificar los datos a JSON: ' . $e->getMessage()], 500);
        }
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

    public function descargarFotosMasiva(Request $request)
    {
        // Aumentar el tiempo límite de ejecución para esta petición pesada
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        try {

            // Usamos la misma lógica de filtro que 'estudiantesfoto', pero pedimos 'fotografia'
            // y NO usamos paginación.
            $query = informacionpersonal_D::select('CIInfPer', 'NombInfPer', 'ApellInfPer', 'ApellMatInfPer', 'mailInst', 'TipoInfPer', 'fotografia')
                ->where('StatusPer', 1)
                // Filtramos a mano los que tienen foto (usando la subconsulta o un join si es necesario)
                // Para mantener la lógica de "solo usuarios con foto" pero sin cargar el BLOB:
                ->whereNotNull('fotografia');

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
                return response()->json(['data' => [], 'message' => 'No se encontraron docentes con fotografía para descarga masiva'], 200);
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
