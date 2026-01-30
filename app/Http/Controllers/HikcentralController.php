<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\informacionpersonal;
use App\Models\informacionpersonal_D;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class HikcentralController extends Controller
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
    public function show(string $id) {}

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id) {}

    /**
     * Remove the specified resource from storage.
     */

    public function generateSignature($url, $hasBody = true)
    {
        $partnerKey = env('HIKCENTRAL_PARTNER_KEY');
        $partnerSecret = env('HIKCENTRAL_PARTNER_SECRET');
        $path = parse_url($url, PHP_URL_PATH);

        $accept = "*/*";
        $contentType = $hasBody ? "application/json\n" : "";

        $stringToSign = "POST\n" . $accept . "\n" . $contentType . "x-ca-key:" . $partnerKey . "\n" . $path;

        return base64_encode(hash_hmac('sha256', $stringToSign, $partnerSecret, true));
    }

    /**
     * Obtiene la foto de HikCentral y la retorna en Base64
     */
    public function getHikPhotoBase64($personCode)
    {
        try {
            // Creamos una llave única para la foto de HikCentral
            $cacheKey = "hik_photo_base64_{$personCode}";

            // Intentamos obtener el base64 de la caché por 2 horas (7200 segundos)
            $fotoBase64 = Cache::remember($cacheKey, 7200, function () use ($personCode) {
                $partnerKey = env('HIKCENTRAL_PARTNER_KEY');

                // 1. Obtener la información de la persona (para sacar el picUri)
                $urlInfo = env('HIKCENTRAL_PERSON_INFO_URL');
                $response = Http::withoutVerifying()->withHeaders([
                    'x-ca-key' => $partnerKey,
                    'x-ca-signature' => $this->generateSignature($urlInfo),
                    'x-ca-signature-headers' => 'x-ca-key',
                    'Accept' => '*/*',
                    'Content-Type' => 'application/json'
                ])->post($urlInfo, ['personCode' => $personCode]);

                $data = $response->json();

                if (!isset($data['data']['personPhoto']['picUri'])) {
                    return null; // Si no hay foto, retornamos null para no cachear error
                }

                $picUri = $data['data']['personPhoto']['picUri'];

                // 2. Obtener la foto real usando el picUri
                $urlPhoto = env('HIKCENTRAL_PHOTO_URL');
                $photoResponse = Http::withoutVerifying()->withHeaders([
                    'x-ca-key' => $partnerKey,
                    'x-ca-signature' => $this->generateSignature($urlPhoto),
                    'x-ca-signature-headers' => 'x-ca-key',
                    'Accept' => '*/*',
                    'Content-Type' => 'application/json'
                ])->post($urlPhoto, [
                    'personCode' => $personCode,
                    'picUri' => $picUri
                ]);

                // Retornamos el cuerpo (base64) para que se guarde en caché
                return $photoResponse->body();
            });

            if (!$fotoBase64) {
                return response()->json(['error' => 'No se encontró la fotografía en HikCentral'], 404);
            }

            return response()->json([
                'personCode' => $personCode,
                'base64' => $fotoBase64
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function testPhotoBase64($ci)
    {
        $res = $this->getHikPhotoBase64($ci);

        // Si es una respuesta de error (404 o 500) devolvemos un pixel transparente
        if ($res->getStatusCode() !== 200) {
            return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 200)
                ->header('Content-Type', 'image/gif');
        }

        $data = $res->getData();
        $rawBody = $data->base64;

        // Limpiar el string Base64 si viene con prefijo data:image
        if (strpos($rawBody, 'data:image') !== false) {
            $parts = explode(',', $rawBody);
            $content = base64_decode(end($parts));
        } else {
            $content = base64_decode($rawBody);
        }

        // Retornamos la imagen con Cache-Control para el navegador
        return response($content)
            ->header('Content-Type', 'image/jpeg')
            ->header('Cache-Control', 'public, max-age=86400');
    }
    public function checkHikStatus($personCode)
    {
        try {
            // 1. Definir una llave única para este estado
            $cacheKey = "hik_status_{$personCode}";

            // 2. Intentar obtener de caché o ejecutar la petición
            // Guardamos el estado por 30 minutos (1800 segundos)
            $registrado = Cache::remember($cacheKey, 1800, function () use ($personCode) {
                $partnerKey = env('HIKCENTRAL_PARTNER_KEY');
                $urlInfo = env('HIKCENTRAL_PERSON_INFO_URL');

                $response = Http::withoutVerifying()->withHeaders([
                    'x-ca-key' => $partnerKey,
                    'x-ca-signature' => $this->generateSignature($urlInfo),
                    'x-ca-signature-headers' => 'x-ca-key',
                    'Accept' => '*/*',
                    'Content-Type' => 'application/json'
                ])->post($urlInfo, ['personCode' => $personCode]);

                $data = $response->json();

                // Retornamos true si existe, false si no. Este valor se guardará en caché.
                return (isset($data['code']) && $data['code'] === "0" && !empty($data['data']));
            });

            return response()->json([
                'registrado' => $registrado,
                'mensaje' => $registrado ? 'Usuario encontrado' : 'No registrado en HikCentral',
                'from_cache' => Cache::has($cacheKey) // Opcional: para saber si vino de caché
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'registrado' => false,
                'error' => 'Error al verificar status: ' . $e->getMessage()
            ], 500);
        }
    }
    public function checkHikStatusEst($personCode)
    {
        try {
            // 1. Definir una llave única para este estado
            $cacheKey = "hik_status_est_{$personCode}";

            // 2. Intentar obtener de caché o ejecutar la petición
            // Guardamos el estado por 30 minutos (1800 segundos)
            $registrado = Cache::remember($cacheKey, 1800, function () use ($personCode) {
                $partnerKey = env('HIKCENTRAL_PARTNER_KEY');
                $urlInfo = env('HIKCENTRAL_PERSON_INFO_URL');

                $response = Http::withoutVerifying()->withHeaders([
                    'x-ca-key' => $partnerKey,
                    'x-ca-signature' => $this->generateSignature($urlInfo),
                    'x-ca-signature-headers' => 'x-ca-key',
                    'Accept' => '*/*',
                    'Content-Type' => 'application/json'
                ])->post($urlInfo, ['personCode' => $personCode]);

                $data = $response->json();

                // Retornamos true si existe, false si no. Este valor se guardará en caché.
                return (isset($data['code']) && $data['code'] === "0" && !empty($data['data']));
            });

            return response()->json([
                'registrado' => $registrado,
                'mensaje' => $registrado ? 'Usuario encontrado' : 'No registrado en HikCentral',
                'from_cache' => Cache::has($cacheKey) // Opcional: para saber si vino de caché
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'registrado' => false,
                'error' => 'Error al verificar status: ' . $e->getMessage()
            ], 500);
        }
    }
    public function getPendingSync(Request $request)
    {
        try {
            $tipoFilter = $request->input('tipoFilter', 'Todos');

            $query = informacionpersonal_D::where('StatusPer', 1)
                ->whereNotNull('fotografia');

            if ($tipoFilter !== 'Todos') {
                $query->where('TipoInfPer', $tipoFilter);
            }

            // Seleccionamos solo lo necesario para no saturar la RAM
            $personal = $query->select('CIInfPer', 'NombInfPer', 'ApellInfPer')->get();
            $pendientes = [];

            foreach ($personal as $p) {
                $cacheKey = "hik_status_{$p->CIInfPer}";
                if (!Cache::get($cacheKey)) {
                    $pendientes[] = [
                        'CIInfPer' => $p->CIInfPer, // Usamos el nombre original para consistencia
                        'NombInfPer' => "{$p->NombInfPer} {$p->ApellInfPer}"
                    ];
                }
            }

            return response()->json([
                'total_potencial' => count($pendientes),
                'pendientes' => $pendientes
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    public function syncToHikCentral(Request $request, $ci)
    {
        try {
            // 1. Obtener datos del docente desde el SIAD
            $docente = informacionpersonal_D::where('CIInfPer', $ci)->first();

            if (!$docente || empty($docente->fotografia)) {
                return response()->json(['error' => 'Docente o foto no encontrada'], 404);
            }

            // 2. Preparar la foto en Base64
            $fotoBase64 = base64_encode($docente->fotografia);

            // 3. Mapear géneros (SIAD suele usar M/F, HikCentral usa 1 para Masc, 2 para Fem)
            $gender = ($docente->GeneroPer === 'M') ? 2 : 1;

            $departmentCode = "1";

            if ($docente->TipoInfPer === "D") {
                $departmentCode = "4";
            } else if ($docente->TipoInfPer === "A") {
                $departmentCode = "72";
            } else if ($docente->TipoInfPer === "T") {
                $departmentCode = "6";
            } else if ($docente->TipoInfPer === "TDO") {
                $departmentCode = "5";
            }

            // 4. Construir el JSON para HikCentral
            $body = [
                "personCode"       => (string)$docente->CIInfPer,
                "personFamilyName" => $docente->ApellInfPer . " " . ($docente->ApellMatInfPer ?? ""),
                "personGivenName"  => $docente->NombInfPer,
                "gender"           => $gender,
                "orgIndexCode"     => $departmentCode, // Ajustar según tu estructura en HikCentral
                "remark"           => "Sincronizado desde SIAD - " . ($docente->TipoInfPer ?? "No Definido"),
                "email"            => $docente->mailInst ?? "",
                "faces" => [
                    ["faceData" => $fotoBase64]
                ],
                // Si tienes tarjetas en la DB, agrégalas aquí. Si no, enviar vacío o remover.
                "cards" => [
                    ["cardNo" => (string)$docente->CIInfPer]
                ],
                "beginTime" => now()->toIso8601String(),
                "endTime"   => now()->addYears(10)->toIso8601String(),
            ];

            $partnerKey = env('HIKCENTRAL_PARTNER_KEY');
            $urlInfo = env('HIKCENTRAL_ADD_PERSON');
            $response = Http::withoutVerifying()->withHeaders([
                'x-ca-key' => $partnerKey,
                'x-ca-signature' => $this->generateSignature($urlInfo),
                'x-ca-signature-headers' => 'x-ca-key',
                'Accept' => '*/*',
                'Content-Type' => 'application/json'
            ])->post($urlInfo, $body);

            if ($response->successful() && $response->json('code') === "0") {
                // Dentro de syncToHikCentral, después del éxito:
                Cache::forget("hik_status_{$ci}");
                Cache::put("hik_status_{$ci}", true, 1800);
                // También borrar la caché de la lista general para que se refresque el frontend
                Cache::flush();

                return response()->json($response->json());
            } else {
                return response()->json([
                    'error' => 'Error en HikCentral',
                    'details' => $response->json()
                ], $response->status());
            }
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error interno: ' . $e->getMessage()], 500);
        }
    }
    public function getPendingSyncEst(Request $request)
    {
        try {
            $carreraFilter = $request->input('carrera_name');
            $carrerasAExcluir = ['056', '122', '124', '197', '206', '601', '602', '603'];

            $query = informacionpersonal::select(
                'informacionpersonal.CIInfPer',
                'informacionpersonal.NombInfPer',
                'informacionpersonal.ApellInfPer',
                'informacionpersonal.ApellMatInfPer'
            )
                ->join('factura', 'factura.cedula', '=', 'informacionpersonal.CIInfPer')
                ->join('ingreso', 'ingreso.CIInfPer', '=', 'informacionpersonal.CIInfPer')
                ->join('carrera', 'carrera.idCarr', '=', 'ingreso.idcarr')
                ->where('factura.idper', 125) // Periodo vigente
                ->where('carrera.StatusCarr', 1)
                ->whereNotNull('informacionpersonal.fotografia')
                // --- MISMA LÓGICA DE FILTRADO DE CARRERA ÚLTIMA ---
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
                ->where('carrera.NombCarr', 'NOT LIKE', '%TRABAJO DE INTEGRACIÓN CURRICULAR%');

            // Aplicar el mismo filtro de carrera que la tabla
            if (!empty($carreraFilter) && $carreraFilter !== 'Todos') {
                $query->where('carrera.idCarr', $carreraFilter);
            }

            // Agrupamos para evitar duplicados en el listado masivo
            $personal = $query->groupBy(
                'informacionpersonal.CIInfPer',
                'informacionpersonal.NombInfPer',
                'informacionpersonal.ApellInfPer',
                'informacionpersonal.ApellMatInfPer'
            )->get();

            $pendientes = [];

            foreach ($personal as $p) {
                $cacheKey = "hik_status_est_{$p->CIInfPer}";

                // Solo lo agregamos si NO está marcado como registrado en caché
                if (Cache::get($cacheKey) !== true) {
                    $pendientes[] = [
                        'CIInfPer' => $p->CIInfPer,
                        'NombInfPer' => "{$p->NombInfPer} {$p->ApellInfPer}"
                    ];
                }
            }

            return response()->json([
                'total_potencial' => count($pendientes),
                'pendientes' => $pendientes
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error interno: ' . $e->getMessage()], 500);
        }
    }
    public function syncToHikCentralEst(Request $request, $ci)
    {
        try {
            $carrerasAExcluir = ['056', '122', '124', '197', '206', '601', '602', '603'];
            // 1. Obtener datos (Usando el mismo modelo que estudiantesfoto)
            // 1. Obtener datos con la MISMA lógica de estudiantesfoto
            $estudiante = informacionpersonal::select(
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
                ->where('factura.idper', 125) // Solo periodo vigente
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

            if (!$estudiante || empty($estudiante->fotografia)) {
                return response()->json(['error' => 'Estudiante o foto no encontrada'], 404);
            }

            // 2. Preparar datos
            $fotoBase64 = base64_encode($estudiante->fotografia);

            // Género: HikCentral 1:Masculino, 2:Femenino (Ajustar según tu DB)
            $gender = ($estudiante->GeneroPer === 'M') ? 1 : 2;
            // --- Validación y Formateo del orgIndexCode ---

            $departmentCode = $estudiante->codihicenter;

            if (is_null($departmentCode) || $departmentCode === '' || $departmentCode === 0) {
                return response()->json([
                    'error' => 'Configuración incompleta en HikCentral',
                    'message' => "El campo 'codihicenter' no se ha registrado para esta carrera.",
                    'carrera' => [
                        'idCarr'     => $estudiante->idCarr,
                        'NombCarr'   => $estudiante->NombCarr,
                        'StatusCarr' => $estudiante->StatusCarr,
                    ]
                ], 422); // 422 Unprocessable Entity
            }

            // Convertimos a string y eliminamos cualquier espacio accidental
            $departmentCode = trim((string)$departmentCode);
            $body = [
                "personCode"       => (string)$estudiante->CIInfPer,
                "personFamilyName" => $estudiante->ApellInfPer . " " . ($estudiante->ApellMatInfPer ?? ""),
                "personGivenName"  => $estudiante->NombInfPer,
                "gender"           => $gender,
                "orgIndexCode"     => $departmentCode, // Código por defecto para Estudiantes
                "remark"           => "Sincronizado Estudiante SIAD",
                "email"            => $estudiante->mailInst ?? "",
                "faces" => [
                    ["faceData" => $fotoBase64]
                ],
                "cards" => [
                    ["cardNo" => (string)$estudiante->CIInfPer]
                ],
                "beginTime" => now()->toIso8601String(),
                "endTime"   => now()->addYears(5)->toIso8601String(),
            ];

            $partnerKey = env('HIKCENTRAL_PARTNER_KEY');
            $urlInfo = env('HIKCENTRAL_ADD_PERSON');

            $response = Http::withoutVerifying()->withHeaders([
                'x-ca-key' => $partnerKey,
                'x-ca-signature' => $this->generateSignature($urlInfo),
                'x-ca-signature-headers' => 'x-ca-key',
                'Accept' => '*/*',
                'Content-Type' => 'application/json'
            ])->post($urlInfo, $body);



            $resData = $response->json();

            // Verificamos si HikCentral respondió con éxito (code 0)
            // Usamos == para comparar "0" o 0 indistintamente
            if ($response->successful() && isset($resData['code']) && $resData['code'] == 0) {

                // 🔥 ACTUALIZACIÓN DE CACHÉ
                Cache::put("hik_status_est_{$ci}", true, 1800);
                Cache::forget("foto_blob_{$ci}");

                // RETORNO CRÍTICO: Debe llevar 'code' y 'msg' para tu JS
                return response()->json([
                    'code' => "0",
                    'msg'  => "Success",
                    'data' => $resData['data'] ?? $ci // Retorna el personId de HC o el CI
                ], 200);

            } else {
                // Si HikCentral devuelve error (ej. persona ya existe o error de parámetros)
                return response()->json([
                    'code'    => $resData['code'] ?? "500",
                    'msg'     => $resData['msg'] ?? "Error desconocido en HikCentral",
                    'details' => $resData
                ], 400);
            }
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Compara la foto de HikCentral con la de la Base de Datos local
     */
    public function compararFotosHCKWithDB($ci)
    {
        $resHik = $this->getHikPhotoBase64($ci);
        $dataHik = $resHik->getData();

        if (isset($dataHik->error)) return response()->json(['error' => 'No hay foto en Hik'], 404);

        // Limpiar el string de HikCentral para obtener solo el Base64 puro
        $stringHik = $dataHik->base64;
        if (strpos($stringHik, 'base64,') !== false) {
            $parts = explode('base64,', $stringHik);
            $pureHik = trim(end($parts));
        } else {
            $pureHik = trim($stringHik);
        }

        // Obtener Local
        $personaLocal = informacionpersonal::where('CIInfPer', $ci)->first();
        if (!$personaLocal) return response()->json(['error' => 'No local'], 404);

        $pureLocal = base64_encode($personaLocal->fotografia);

        // Comparar
        $match = ($pureHik === $pureLocal);

        return response()->json([
            'identicas' => $match,
            'mensaje' => $match ? 'Match perfecto' : 'Son diferentes'
        ]);
    }
    public function compararFotosHCKWithDBDOC($ci)
    {
        try {
            // 1. Definir llave única para la comparación
            $cacheKey = "compare_result_{$ci}";

            // 2. Cachear el resultado por 30 minutos
            $resultado = Cache::remember($cacheKey, 1800, function () use ($ci) {

                // Reutilizamos el método que ya tiene su propia caché
                $resHik = $this->getHikPhotoBase64($ci);
                $dataHik = $resHik->getData();

                if (isset($dataHik->error)) {
                    // Si hay error, no devolvemos nada para que no se cachee el fallo
                    return null;
                }

                // Preparar imagen HikCentral
                $pureHik = $dataHik->base64;
                if (strpos($pureHik, 'base64,') !== false) {
                    $pureHik = explode('base64,', $pureHik)[1];
                }
                $binHik = base64_decode(preg_replace('/\s+/', '', $pureHik));

                // Preparar imagen Local (Base de Datos)
                $personaLocal = informacionpersonal_D::where('CIInfPer', $ci)->select('fotografia')->first();

                if (!$personaLocal || !$personaLocal->fotografia) {
                    return ['error' => 'No existe foto local para comparar'];
                }

                $binLocal = $personaLocal->fotografia;

                // 3. Ejecutar comparación visual pesada
                $esSimilar = $this->compareVisualSimilarity($binHik, $binLocal);

                return [
                    'identicas' => $esSimilar['match'],
                    'mensaje' => $esSimilar['match'] ? 'Es la misma persona (Visualmente)' : 'Las fotos son de personas diferentes',
                    'similitud' => $esSimilar['score'] . '%',
                    'debug' => [
                        'longitud_hik' => strlen($binHik),
                        'longitud_local' => strlen($binLocal)
                    ]
                ];
            });

            if (!$resultado) {
                return response()->json(['identicas' => false, 'error' => 'Error al procesar comparación'], 500);
            }

            if (isset($resultado['error'])) {
                return response()->json(['identicas' => false, 'error' => $resultado['error']], 404);
            }

            return response()->json($resultado);
        } catch (\Exception $e) {
            return response()->json(['identicas' => false, 'error' => $e->getMessage()], 500);
        }
    }
    public function compararFotosHCKWithDBEstudiante($ci)
    {
        try {
            // 1. Definir llave única para la comparación de estudiantes
            $cacheKey = "compare_estudiante_result_{$ci}";

            // 2. Cachear el resultado por 30 minutos
            $resultado = Cache::remember($cacheKey, 1800, function () use ($ci) {

                // Obtener foto de HikCentral (debe retornar el base64 de la API de Hik)
                $resHik = $this->getHikPhotoBase64($ci);
                $dataHik = $resHik->getData();

                if (isset($dataHik->error)) {
                    return null; // No cacheamos errores de conexión
                }

                // Limpieza del base64 de HikCentral
                $pureHik = $dataHik->base64;
                if (strpos($pureHik, 'base64,') !== false) {
                    $pureHik = explode('base64,', $pureHik)[1];
                }
                $binHik = base64_decode(preg_replace('/\s+/', '', $pureHik));

                // --- CAMBIO CLAVE: Usar modelo de estudiantes ---
                $estudianteLocal = informacionpersonal::where('CIInfPer', $ci)
                    ->select('fotografia')
                    ->first();

                if (!$estudianteLocal || !$estudianteLocal->fotografia) {
                    return ['error' => 'No existe fotografía del estudiante en el SIAD'];
                }

                $binLocal = $estudianteLocal->fotografia;

                // 3. Ejecutar comparación visual (Lógica de reconocimiento facial/hashes)
                // 
                $esSimilar = $this->compareVisualSimilarity($binHik, $binLocal);

                return [
                    'identicas' => $esSimilar['match'],
                    'mensaje' => $esSimilar['match'] ? 'Match confirmado' : 'Las fotografías no coinciden',
                    'similitud' => $esSimilar['score'] . '%',
                    'tipo' => 'estudiante',
                    'debug' => [
                        'longitud_hik' => strlen($binHik),
                        'longitud_local' => strlen($binLocal)
                    ]
                ];
            });

            if (!$resultado) {
                return response()->json(['identicas' => false, 'error' => 'No se pudo obtener la imagen de HikCentral'], 500);
            }

            if (isset($resultado['error'])) {
                return response()->json(['identicas' => false, 'error' => $resultado['error']], 404);
            }

            return response()->json($resultado);
        } catch (\Exception $e) {
            return response()->json(['identicas' => false, 'error' => $e->getMessage()], 500);
        }
    }
    private function compareVisualSimilarity($bin1, $bin2)
    {
        $img1 = imagecreatefromstring($bin1);
        $img2 = imagecreatefromstring($bin2);

        // Redimensionar ambas a 8x8 píxeles para comparar la estructura básica
        $thumb1 = imagecreatetruecolor(8, 8);
        $thumb2 = imagecreatetruecolor(8, 8);

        imagecopyresampled($thumb1, $img1, 0, 0, 0, 0, 8, 8, imagesx($img1), imagesy($img1));
        imagecopyresampled($thumb2, $img2, 0, 0, 0, 0, 8, 8, imagesx($img2), imagesy($img2));

        // Convertir a escala de grises y calcular diferencia
        $diff = 0;
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $rgb1 = imagecolorat($thumb1, $x, $y);
                $rgb2 = imagecolorat($thumb2, $x, $y);

                $gray1 = (($rgb1 >> 16) & 0xFF) + (($rgb1 >> 8) & 0xFF) + ($rgb1 & 0xFF);
                $gray2 = (($rgb2 >> 16) & 0xFF) + (($rgb2 >> 8) & 0xFF) + ($rgb2 & 0xFF);

                $diff += abs($gray1 - $gray2);
            }
        }

        // Un umbral de 2000 suele ser bueno para fotos con distinta compresión
        $maxDiff = 8 * 8 * 255 * 3;
        $score = round((1 - ($diff / 30000)) * 100, 2); // Ajuste de sensibilidad

        return [
            'match' => ($diff < 2500), // Si la diferencia es pequeña, es la misma foto
            'score' => max(0, $score)
        ];
    }
    public function clearDocenteCache($ci)
    {
        // Borramos la caché del estado y la caché del Base64
        Cache::forget("hik_status_{$ci}");
        Cache::forget("hik_status_est_{$ci}");
        Cache::forget("hik_photo_base64_{$ci}");

        return response()->json(['message' => 'Caché limpiada correctamente']);
    }
}
