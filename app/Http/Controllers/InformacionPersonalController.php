<?php

namespace App\Http\Controllers;

use App\Models\informacionpersonal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

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

    public function estudiantesfoto(Request $request)
    {
        $startTime = microtime(true);

        try {
            $perPage = $request->input('per_page', 20);
            $perPage = min($perPage, 50);

            // --- Nuevos Parámetros de Filtrado ---
            $searchQuery = $request->input('search_query');
            $carreraFilter = $request->input('carrera_name');
            // -------------------------------------

            $carrerasAExcluir = ['056', '122', '124', '197', '206', '601', '602', '603'];

            $query = informacionpersonal::select(
                'informacionpersonal.CIInfPer',
                'informacionpersonal.NombInfPer',
                'informacionpersonal.ApellInfPer',
                'informacionpersonal.ApellMatInfPer',
                'informacionpersonal.mailPer',
                'carrera.NombCarr'
            )
                ->join('factura', 'factura.cedula', '=', 'informacionpersonal.CIInfPer')
                ->join('ingreso', 'ingreso.CIInfPer', '=', 'informacionpersonal.CIInfPer')
                ->join('carrera', 'carrera.idCarr', '=', 'ingreso.idcarr')
                ->where('factura.idper', 125)
                // Usando una función de subconsulta para encontrar el MAX(idper) por estudiante.
                // Esta es la parte más crítica para el rendimiento.
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
                ->whereNotNull('informacionpersonal.fotografia')
                ->whereRaw('LENGTH(informacionpersonal.fotografia) > 0');

            // ======================================
            // APLICACIÓN DE FILTROS DESDE EL FRONTEND
            // ======================================

            // 1. Filtrar por Cédula/Nombres (Búsqueda global)
            if (! empty($searchQuery)) {
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('informacionpersonal.CIInfPer', 'LIKE', "%{$searchQuery}%")
                        ->orWhere('informacionpersonal.NombInfPer', 'LIKE', "%{$searchQuery}%")
                        ->orWhere('informacionpersonal.ApellInfPer', 'LIKE', "%{$searchQuery}%")
                        ->orWhere('informacionpersonal.ApellMatInfPer', 'LIKE', "%{$searchQuery}%");
                });
            }

            // 2. Filtrar por Carrera
            if (! empty($carreraFilter) && $carreraFilter !== 'Todos') {
                $query->where('carrera.NombCarr', $carreraFilter);
            }

            // ======================================
            // AGRUPACIÓN Y PAGINACIÓN
            // ======================================
            $query->groupBy(
                'informacionpersonal.CIInfPer',
                'informacionpersonal.NombInfPer',
                'informacionpersonal.ApellInfPer',
                'informacionpersonal.ApellMatInfPer',
                'informacionpersonal.mailPer',
                'carrera.NombCarr'
            );

            // Ejecución de la consulta con paginación
            $data = $query->paginate($perPage);

            // --------------------------------------------------------------------------------
            // FIN DE LA MEDICIÓN
            // --------------------------------------------------------------------------------
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2); // Tiempo en milisegundos

            if ($data->isEmpty()) {
                return response()->json([
                    'data' => [], 
                    'message' => 'No se encontraron estudiantes con fotografía',
                    'execution_time_ms' => $executionTime // Tiempo en respuesta
                ], 200);
            }

            $data->getCollection()->transform(function ($item) {
                $attributes = $item->getAttributes();
                $attributes['hasPhoto'] = true;

                return $attributes;
            });

            return response()->json([
                'data' => $data->items(),
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                ],
                'execution_time_ms' => $executionTime // Tiempo en respuesta
            ], 200);

        } catch (\Throwable $e) {
            // --------------------------------------------------------------------------------
            // La medición también termina aquí en caso de error
            // --------------------------------------------------------------------------------
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2); // Tiempo en milisegundos

            return response()->json([
                'error' => true,
                'message' => 'Error interno del servidor: '.$e->getMessage(),
                'execution_time_ms' => $executionTime // Tiempo en respuesta
            ], 500);
        }
    }

    public function getFotografia2($ci)
    {
        try {
            // 1. Obtener SÓLO la columna 'fotografia' para el CI específico
            $persona = informacionpersonal::where('CIInfPer', $ci)
                ->select('fotografia')
                ->first();

            // 2. Verificar si el usuario existe y si tiene foto
            if (! $persona || empty($persona->fotografia)) {
                // Devolver una respuesta HTTP 404 (Not Found)
                return response()->json(['error' => 'Fotografía no encontrada para el CI: '.$ci], 404);
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
                ->header('Content-Disposition', 'inline; filename="foto_'.$ci.'"');
        } catch (\Throwable $e) {
            // Log::error('Error en getFotografia DController: ' . $e->getMessage()); // Opcional
            return response()->json(['error' => 'Error al obtener la fotografía: '.$e->getMessage()], 500);
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
                'informacionpersonal.mailPer',
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
                    'informacionpersonal.mailPer',
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
                'message' => 'Error interno del servidor en descarga masiva: '.$e->getMessage(),
            ], 500);
        }
    }
}
