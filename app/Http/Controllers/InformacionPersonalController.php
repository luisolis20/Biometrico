<?php

namespace App\Http\Controllers;

use App\Models\informacionpersonal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

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
    public function getFotografiaHC($ci)
    {
        $partnerKey = env('HIKCENTRAL_PARTNER_KEY');
        $firma = env('HIKCENTRAL_FIRMA');
        $personInfoUrl = env('HIKCENTRAL_PERSON_INFO_URL');
        $photoUrl = env('HIKCENTRAL_PHOTO_URL');

        try {
            // 1. OBTENER picUri de la Persona
            $personResponse = Http::withoutVerifying() // <--- ¡AÑADIR ESTO!
                ->withHeaders([
                    'x-ca-key' => $partnerKey,
                    'x-ca-signature' => $firma,
                    'x-ca-signature-headers' => 'x-ca-key',
                ])->post($personInfoUrl, [
                    'personCode' => $ci,
                ]);

            // ... lógica de manejo de errores ...

            $picUri = $personResponse->json('data.personPhoto.picUri');

            // 2. OBTENER FOTOGRAFÍA binaria
            $photoResponse = Http::withoutVerifying() // <--- ¡AÑADIR ESTO!
                ->withHeaders([
                    'x-ca-key' => $partnerKey,
                    'x-ca-signature' => $firma,
                    'x-ca-signature-headers' => 'x-ca-key',
                ])->post($photoUrl, [
                    'picUri' => $picUri,
                ]);

            // ... lógica de manejo de errores y retorno de la respuesta binaria ...
            $fotoBinaria = $photoResponse->body();
            return Response::make($fotoBinaria, 200)
                ->header('Content-Type', 'image/jpeg')
                ->header('Content-Disposition', 'inline; filename="foto_hc_' . $ci . '.jpg"');
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error de conexión con HikCentral: ' . $e->getMessage()], 500);
        }
    }
    private function getPhotoFromHikCentral($ci)
    {
        $partnerKey = env('HIKCENTRAL_PARTNER_KEY');
        $firma = env('HIKCENTRAL_FIRMA');
        $personInfoUrl = env('HIKCENTRAL_PERSON_INFO_URL');
        $photoUrl = env('HIKCENTRAL_PHOTO_URL');

        // 1. Obtener picUri
        $personResponse = Http::withoutVerifying() // <--- ¡AÑADIR ESTO!
            ->withHeaders([
                'x-ca-key' => $partnerKey,
                'x-ca-signature' => $firma,
                'x-ca-signature-headers' => 'x-ca-key',
            ])->post($personInfoUrl, ['personCode' => $ci]);

        // ... lógica de manejo de picUri ...
        $picUri = $personResponse->json('data.personPhoto.picUri');

        if ($personResponse->failed() || !$picUri) {
            return false;
        }

        // 2. Obtener la foto binaria
        $photoResponse = Http::withoutVerifying() // <--- ¡AÑADIR ESTO!
            ->withHeaders([
                'x-ca-key' => $partnerKey,
                'x-ca-signature' => $firma,
                'x-ca-signature-headers' => 'x-ca-key',
            ])->post($photoUrl, ['picUri' => $picUri]);

        if ($photoResponse->failed()) {
            return false;
        }

        return $photoResponse->body();
    }

    public function compararFotos2($ci)
    {
        try {
            // 1. Foto SIAD (local en la BDD) - Se mantiene igual
            $persona = informacionpersonal::where('CIInfPer', $ci)
                ->select('fotografia')
                ->first();

            if (! $persona || empty($persona->fotografia)) {
                return response()->json([
                    'different' => true,
                    'message' => 'No existe foto SIAD',
                ]);
            }
            $fotoLocal = $persona->fotografia;

            // 2. Foto HC (externa) - Lógica de HikCentral
            $fotoExterna = $this->getPhotoFromHikCentral($ci);

            if ($fotoExterna === false) {
                return response()->json([
                    'different' => true,
                    'message' => 'No se pudo obtener foto HC (HikCentral)',
                ]);
            }

            // 3. Comparación (Se mantiene igual)
            if ($fotoLocal !== $fotoExterna) {
                return response()->json(['different' => true]);
            }

            // Si llega aquí → son iguales
            return response()->json(['different' => false]);
        } catch (\Throwable $e) {
            return response()->json([
                'different' => true,
                'error' => 'Error en comparación: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function compararFotos($ci)
    {
        try {
            // Foto SIAD (local en la BDD)
            $persona = informacionpersonal::where('CIInfPer', $ci)
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
            $urlExterna = env('API_BOLSA') . '/b_e/vin/fotografia/' . $ci;

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
                    ip.mailPer,
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
                    ip.mailPer,
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
                ],
                // Opcional: devolver la lista completa de carreras para el combobox,
                // si no quieres hacer otra consulta separada.
                // Para la primera carga (página 1 sin filtros), podrías hacer una consulta
                // separada eficiente para obtener todas las carreras disponibles en la DB
                // y enviarla en la respuesta.
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => true,
                'message' => 'Error interno del servidor: ' . $e->getMessage(),
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
                'message' => 'Error interno del servidor en descarga masiva: ' . $e->getMessage(),
            ], 500);
        }
    }
}
