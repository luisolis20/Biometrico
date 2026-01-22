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
            $partnerKey = env('HIKCENTRAL_PARTNER_KEY');

            //Ob estudiante
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
                return response()->json(['error' => 'No se encontró picUri'], 404);
            }

            $picUri = $data['data']['personPhoto']['picUri'];

            // Ob tiene foto
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

            // texto plano de la respuesta
            $fotoBase64 = $photoResponse->body();

            // Retornamos un JSON
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
        $data = $res->getData();

        if (isset($data->error)) {
            return response('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', 200)
                ->header('Content-Type', 'image/gif');
        }

        $rawBody = $data->base64;

        if (strpos($rawBody, 'data:image') !== false) {
            $parts = explode('data:image', $rawBody);
            $base64String = 'data:image' . end($parts);
        } else {
            $base64String = $rawBody;
        }
        $imageData = explode(',', $base64String);
        $content = base64_decode(end($imageData));

        return response($content)
            ->header('Content-Type', 'image/jpeg');
    }
    public function checkHikStatus($personCode)
    {
        try {
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

            // Si el código es "0", el usuario existe
            $existe = (isset($data['code']) && $data['code'] === "0");

            return response()->json([
                'registrado' => $existe,
                'mensaje' => $existe ? 'Usuario encontrado' : ($data['msg'] ?? 'No encontrado')
            ]);
        } catch (\Exception $e) {
            return response()->json(['registrado' => false, 'error' => $e->getMessage()], 500);
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

            if($docente->TipoInfPer === "D"){
                $departmentCode = "4";
            }else if($docente->TipoInfPer === "A"){
                $departmentCode = "72";
            }else if($docente->TipoInfPer === "T"){
                $departmentCode = "6";
            }

            // 4. Construir el JSON para HikCentral
            $body = [
                "personCode"       => (string)$docente->CIInfPer,
                "personFamilyName" => $docente->ApellInfPer . " " . ($docente->ApellMatInfPer ?? ""),
                "personGivenName"  => $docente->NombInfPer,
                "gender"           => $gender,
                "orgIndexCode"     => $departmentCode, // Ajustar según tu estructura en HikCentral
                "remark"           => "Sincronizado desde SIAD - " . ($docente->TipoInfPer ?? "Docente"),
                "email"            => $docente->mailPer ?? "",
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
            $response =Http::withoutVerifying()->withHeaders([
                'x-ca-key' => $partnerKey,
                'x-ca-signature' => $this->generateSignature($urlInfo),
                'x-ca-signature-headers' => 'x-ca-key',
                'Accept' => '*/*',
                'Content-Type' => 'application/json'
            ])->post($urlInfo, $body);

            if ($response->successful()) {
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
        $resHik = $this->getHikPhotoBase64($ci);
        $dataHik = $resHik->getData();

        if (isset($dataHik->error)) return response()->json(['identicas' => false, 'error' => 'Error HikCentral']);

        // 1. Preparar imagen HikCentral
        $pureHik = $dataHik->base64;
        if (strpos($pureHik, 'base64,') !== false) {
            $pureHik = explode('base64,', $pureHik)[1];
        }
        $binHik = base64_decode(preg_replace('/\s+/', '', $pureHik));

        // 2. Preparar imagen Local
        $personaLocal = informacionpersonal_D::where('CIInfPer', $ci)->first();
        $binLocal = $personaLocal->fotografia;

        // 3. COMPARACIÓN POR SIMILITUD VISUAL (P-Hash simplificado)
        $esSimilar = $this->compareVisualSimilarity($binHik, $binLocal);

        return response()->json([
            'identicas' => $esSimilar['match'],
            'mensaje' => $esSimilar['match'] ? 'Es la misma persona (Visualmente)' : 'Las fotos son de personas diferentes',
            'similitud' => $esSimilar['score'] . '%',
            'debug' => [
                'longitud_hik' => strlen($binHik),
                'longitud_local' => strlen($binLocal)
            ]
        ]);
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
}
