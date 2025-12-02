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
            $data = informacionpersonal_D::select('CIInfPer', 'NombInfPer', 'ApellInfPer', 'ApellMatInfPer', 'mailPer', 'TipoInfPer', 'fotografia')
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
            // 🔹 Controlar el número de registros por página
            $perPage = $request->input('per_page', 20);
            $perPage = min($perPage, 50); // No permitir más de 50 por página
            // --- Nuevos Parámetros de Filtrado ---
            $searchQuery = $request->input('search_query');
            $tipoFilter = $request->input('tipoFilter');
            // 🔹 Consulta optimizada: solo columnas necesarias. ***QUITAMOS 'fotografia'***
            $query = informacionpersonal_D::select('CIInfPer', 'NombInfPer', 'ApellInfPer', 'ApellMatInfPer', 'mailPer', 'TipoInfPer')
                ->where('StatusPer', 1)
                // Filtramos a mano los que tienen foto (usando la subconsulta o un join si es necesario)
                // Para mantener la lógica de "solo usuarios con foto" pero sin cargar el BLOB:
                ->whereNotNull('fotografia');
            // 1. Filtrar por Cédula/Nombres (Búsqueda global)
            if (! empty($searchQuery)) {
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('informacionpersonal_d.CIInfPer', 'LIKE', "%{$searchQuery}%")
                        ->orWhere('informacionpersonal_d.NombInfPer', 'LIKE', "%{$searchQuery}%")
                        ->orWhere('informacionpersonal_d.ApellInfPer', 'LIKE', "%{$searchQuery}%")
                        ->orWhere('informacionpersonal_d.ApellMatInfPer', 'LIKE', "%{$searchQuery}%");
                });
            }

            // 2. Filtrar por Carrera
            if (! empty($tipoFilter) && $tipoFilter !== 'Todos') {
                $query->where('informacionpersonal_d.TipoInfPer', $tipoFilter);
            }

            $data = $query->paginate($perPage);

            if ($data->isEmpty()) {
                return response()->json(['data' => [], 'message' => 'No se encontraron estudiantes con fotografía'], 200);
            }

            $data->getCollection()->transform(function ($item) {
                $attributes = $item->getAttributes();
                $attributes['hasPhoto'] = true;

                // No need to unset fotografia here, as it's not selected.
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
            // Log::error('Error en index DController: ' . $e->getMessage()); // Opcional
            return response()->json([
                'error' => true,
                'message' => 'Error interno del servidor: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function compararFotos($ci)
    {
        try {
            // Foto SIAD (local en la BDD)
            $persona = informacionpersonal_D::where('CIInfPer', $ci)
                ->select('fotografia')
                ->first();

            if (! $persona || empty($persona->fotografia)) {
                return response()->json([
                    'different' => true,
                    'message' => 'No existe foto SIAD',
                ]);
            }

            $fotoLocal = $persona->fotografia;

            // Foto HC (externa)
            $urlExterna = env('API_BOLSA').'/b_e/vin/fotografia/'.$ci;

            $fotoExterna = @file_get_contents($urlExterna);

            if ($fotoExterna === false) {
                return response()->json([
                    'different' => true,
                    'message' => 'No se pudo obtener foto HC',
                ]);
            }

            // Comparación rápida: tamaño
            if (strlen($fotoLocal) !== strlen($fotoExterna)) {
                return response()->json([
                    'different' => true,
                ]);
            }

            // Comparación byte a byte más rápida en PHP
            if ($fotoLocal !== $fotoExterna) {
                return response()->json([
                    'different' => true,
                ]);
            }

            // Si llega aquí → son iguales
            return response()->json([
                'different' => false,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'different' => true,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getFotografia($ci)
    {
        try {
            // 1. Obtener SÓLO la columna 'fotografia' para el CI específico
            $persona = informacionpersonal_D::where('CIInfPer', $ci)
                ->select('fotografia')
                ->first();

            // 2. Verificar si el usuario existe y si tiene foto
            if (! $persona || empty($persona->fotografia)) {
                // Devolver una respuesta HTTP 404 (Not Found)
                return response()->json(['error' => 'Fotografía no encontrada para el CI: ' . $ci], 404);
            }

            $fotoBinaria = $persona->fotografia;

            // 3. Determinar el MIME type
            $mime = 'image/jpeg'; // MIME type por defecto

            // Intenta determinar el MIME type si el ambiente lo permite
            if (extension_loaded('fileinfo')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $detectedMime = finfo_buffer($finfo, $fotoBinaria);
                finfo_close($finfo);

                if ($detectedMime && strpos($detectedMime, 'image') === 0) {
                    $mime = $detectedMime;
                }
            }

            // 4. Devolver la imagen como una respuesta binaria (STREAM)
            return Response::make($fotoBinaria, 200)
                ->header('Content-Type', $mime)
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
            $query = informacionpersonal_D::select('CIInfPer', 'NombInfPer', 'ApellInfPer', 'ApellMatInfPer', 'mailPer', 'TipoInfPer', 'fotografia')
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
