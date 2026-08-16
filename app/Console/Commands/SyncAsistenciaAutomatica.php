<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\informacionpersonal_D;
use App\Models\Asistencia_empleado;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncAsistenciaAutomatica extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'asistencia:sync-hikcentral';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza automáticamente las asistencias desde HikCentral a la BD Local';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Forzamos las fechas al día actual
        $fechaHoy = Carbon::today()->format('Y-m-d');

        $this->info("=====================================================");
        $this->info(" Iniciando sincronización para la fecha: {$fechaHoy}");
        $this->info("=====================================================");

        // 1. Obtener personal Administrativo y Docente con foto
        $personal = informacionpersonal_D::select('CIInfPer', 'NombInfPer', 'ApellInfPer')
            ->whereIn('TipoInfPer', ['A', 'T'])
            ->whereNotNull('fotografia')
            ->get();
        $totalPersonal = $personal->count();
        $totalSincronizados = 0;

        $this->info("Total de personal encontrado en BD local: {$totalPersonal}");
        $this->line("-----------------------------------------------------");
        foreach ($personal as $index => $persona) {
            $ci = $persona->CIInfPer;
            $nombreCompleto = trim($persona->NombInfPer . ' ' . $persona->ApellInfPer);
            $numProceso = $index + 1;

            $this->line("Procesando [{$numProceso}/{$totalPersonal}]: {$nombreCompleto} ({$ci})...");

            try {
                // 2. Verificar en HikCentral y obtener sus IDs
                $hikStatus = $this->getHikPersonInfo($ci);

                if (!$hikStatus['registrado']) {
                    $this->warn("   -> No registrado en HikCentral. Saltando.");
                    continue;
                }

                $personId = $hikStatus['personId'];
                $orgIndexCode = $hikStatus['orgIndexCode'];

                // 3. Obtener sus marcaciones del día
                $marcacionesHC = $this->getHikAttendance($ci, $nombreCompleto, $personId, $orgIndexCode, $fechaHoy);

                if (empty($marcacionesHC)) {
                    $this->warn("   -> Sin marcaciones en la fecha {$fechaHoy}. Saltando.");
                    continue;
                }

                // 4. Sincronizar con la BD Local
                $huboCambios = false;
                foreach ($marcacionesHC as $hc) {
                    // Si procesarSincronizacion retorna true, significa que insertó o actualizó un dato
                    if ($this->procesarSincronizacion($ci, $fechaHoy, $hc)) {
                        $huboCambios = true;
                    }
                }

                if ($huboCambios) {
                    $this->info("   -> ¡Datos sincronizados exitosamente!");
                    $totalSincronizados++;
                } else {
                    $this->line("   -> Asistencia ya estaba sincronizada (sin cambios nuevos).");
                }
            } catch (\Exception $e) {
                Log::error("Error sincronizando CI {$ci}: " . $e->getMessage());
                $this->error("   -> Error: " . $e->getMessage());
            }
        }

        $this->info("=====================================================");
        $this->info(" RESUMEN DE LA EJECUCIÓN");
        $this->info("=====================================================");
        $this->info(" Personal total evaluado: {$totalPersonal}");
        $this->info(" Empleados sincronizados/actualizados: {$totalSincronizados}");
        $this->info(" Sincronización automática finalizada con éxito.");
    }
    private function getHikPersonInfo($personCode)
    {
        $partnerKey = env('HIKCENTRAL_PARTNER_KEY');
        $urlInfo = env('HIKCENTRAL_PERSON_INFO_URL');

        $response = Http::withoutVerifying()->withHeaders([
            'x-ca-key' => $partnerKey,
            'x-ca-signature' => $this->generateSignature($urlInfo),
            'x-ca-signature-headers' => 'x-ca-key',
            'Accept' => '*/*',
            'Content-Type' => 'application/json'
        ])->post($urlInfo, ['personCode' => (string)$personCode]);

        $data = $response->json();

        if (isset($data['code']) && $data['code'] === "0" && !empty($data['data'])) {
            return [
                'registrado'   => true,
                'personId'     => $data['data']['personId'] ?? null,
                'orgIndexCode' => $data['data']['orgIndexCode'] ?? null
            ];
        }

        return ['registrado' => false];
    }
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
    private function getHikAttendance($personCode, $personName, $personId, $orgIndexCode, $fecha)
    {
        $partnerKey = env('HIKCENTRAL_PARTNER_KEY');
        $urlAttendance = env('HIKCENTRAL_ATTENDANCE_REPORT_URL');
        $timezoneOffset = ' 08:00';

        $beginTime = Carbon::parse($fecha)->startOfDay()->format('Y-m-d\TH:i:s') . $timezoneOffset;
        $endTime   = Carbon::parse($fecha)->endOfDay()->format('Y-m-d\TH:i:s') . $timezoneOffset;

        $payload = [
            "attendanceReportRequest" => [
                "pageNo" => 1,
                "pageSize" => 100,
                "queryInfo" => [
                    "personName"   => $personName,
                    "personCode"   => $personCode,
                    "personID"     => [(int)$personId],
                    "orgIndexCode" => [(int)$orgIndexCode],
                    "beginTime"    => $beginTime,
                    "endTime"      => $endTime,
                    "personState"  => 1,
                    "sortInfo"     => ["sortField" => 3, "sortType" => 2]
                ]
            ]
        ];

        $response = Http::withoutVerifying()->withHeaders([
            'x-ca-key' => $partnerKey,
            'x-ca-signature' => $this->generateSignature($urlAttendance),
            'x-ca-signature-headers' => 'x-ca-key',
            'Accept' => '*/*',
            'Content-Type' => 'application/json'
        ])->post($urlAttendance, $payload);

        $resData = $response->json();

        // Extraer los registros (records) del array. Ajusta esto según cómo HikCentral te devuelva la data.
        return $resData['data']['record'] ?? [];
    }
    private function procesarSincronizacion($ci_empleado, $fecha, $hc)
    {
        // 1. Validar el Estado de Asistencia devuelto por HC
        $rawStatus = (string) ($hc['attendanceBaseInfo']['attendanceStatus'] ?? '');

        // OMITIR si el estado es "7" (No programado)
        if ($rawStatus === '7') {
            return false;
        }

        // Mapear el código numérico de HC
        $estadosMap = [
            '1' => 'Normal',
            '2' => 'Tarde',
            '3' => 'Salida anticipada',
            '4' => 'Ausente',
            '5' => 'Tarde y salida anticipada',
            '6' => 'Día festivo',
            '8' => 'Permiso'
        ];
        $hcEstadoAsistencia = $estadosMap[$rawStatus] ?? 'Normal';

        // 2. Extraer los datos crudos desde la estructura de HikCentral
        $rawEntrada = $hc['attendanceBaseInfo']['beginTime'] ?? null;

        // --- VALIDACIÓN DE HORA FANTASMA 08:05:00 ---
        if ($rawEntrada !== null && strpos($rawEntrada, 'T08:05:00') !== false) {
            $rawEntrada = $hc['attendanceDetailInfo']['recordTime'][0]['beginTime'] ?? null;
        }

        $rawSalida       = $hc['attendanceBaseInfo']['endTime'] ?? null;
        $rawBreakEntrada = $hc['attendanceDetailInfo']['recordTime'][0]['endTime'] ?? null;
        $duracionBreak   = isset($hc['restInfo']['durationTime']) ? (int)$hc['restInfo']['durationTime'] : 0;

        // 3. Parsear a formato fecha/hora compatible con BD local
        $hcHoraEntrada         = $this->parseHikCentralDateTime($rawEntrada);
        $hcHoraSalida          = $this->parseHikCentralDateTime($rawSalida);
        $hcHoraAlmuerzoEntrada = $this->parseHikCentralDateTime($rawBreakEntrada);

        // 4. Calcular "Salida Break" (Almuerzo Salida)
        $hcHoraAlmuerzoSalida = null;
        if ($hcHoraAlmuerzoEntrada && $duracionBreak > 0) {
            $hcHoraAlmuerzoSalida = \Carbon\Carbon::parse($hcHoraAlmuerzoEntrada)
                ->subSeconds($duracionBreak)
                ->format('Y-m-d H:i:s');
        }

        // 5. Consultar si existe un registro local para esa fecha y persona
        $local = Asistencia_empleado::where('ci_empleado', $ci_empleado)
            ->where('fecha', $fecha)
            ->first();

        // Comprobar si existe AL MENOS UNA marcación en HikCentral
        $tieneMarcacionesHC = $hcHoraEntrada || $hcHoraSalida || $hcHoraAlmuerzoEntrada || $hcHoraAlmuerzoSalida;

        // 6. CASO PREVENCIÓN: Si NO hay registro local y HC viene totalmente vacío, abortamos.
        if (!$local && !$tieneMarcacionesHC) {
            return false;
        }

        // Extraer planEndTime de planInfo para la evaluación de salida anticipada
        $planEndTimeRaw = $hc['planInfo']['planEndTime'] ?? null;

        // Helper interno para determinar el estado correcto
        $determinarEstadoFinal = function ($horaEntrada, $almuerzoSalida, $almuerzoEntrada, $horaSalida) use ($hcEstadoAsistencia, $planEndTimeRaw) {
            // Verificar si existen las 4 marcaciones completas
            $tieneLas4Marcaciones = !empty($horaEntrada) && !empty($almuerzoSalida) && !empty($almuerzoEntrada) && !empty($horaSalida);

            if ($tieneLas4Marcaciones && $planEndTimeRaw) {
                $salidaReal = \Carbon\Carbon::parse($horaSalida);
                $salidaPlan = \Carbon\Carbon::parse($planEndTimeRaw);

                // Si salió antes del horario fin programado
                if ($salidaReal->lt($salidaPlan)) {
                    return 'Salida anticipada';
                }

                return 'Normal';
            }

            // Si faltan marcaciones, mantenemos el estado de HC (ej: Ausente o Tarde)
            return $hcEstadoAsistencia;
        };

        // 7. Si YA EXISTE registro local:
        if ($local) {
            $haCambiado = false;

            // --- 1. HORA DE ENTRADA ---
            if ($hcHoraEntrada || $local->hora_entrada) {
                $nuevaEntrada = $local->hora_entrada;
                if ($hcHoraEntrada && $local->hora_entrada) {
                    $nuevaEntrada = $this->getEarliest($local->hora_entrada, $hcHoraEntrada);
                } elseif ($hcHoraEntrada) {
                    $nuevaEntrada = $hcHoraEntrada;
                }

                if ($local->hora_entrada !== $nuevaEntrada || $local->sync_e_hc == 0) {
                    $local->hora_entrada = $nuevaEntrada;
                    $local->sync_e_hc = 1;
                    $haCambiado = true;
                }
            } else {
                if ($local->sync_e_hc != 0) {
                    $local->sync_e_hc = 0;
                    $haCambiado = true;
                }
            }

            // --- 2. HORA ALMUERZO SALIDA (Salida Break) ---
            if ($hcHoraAlmuerzoSalida || $local->hora_almuerzo_salida) {
                $nuevaSalBreak = $local->hora_almuerzo_salida;
                if ($hcHoraAlmuerzoSalida && $local->hora_almuerzo_salida) {
                    $nuevaSalBreak = $this->getEarliest($local->hora_almuerzo_salida, $hcHoraAlmuerzoSalida);
                } elseif ($hcHoraAlmuerzoSalida) {
                    $nuevaSalBreak = $hcHoraAlmuerzoSalida;
                }

                if ($local->hora_almuerzo_salida !== $nuevaSalBreak || $local->sync_sal_hc == 0) {
                    $local->hora_almuerzo_salida = $nuevaSalBreak;
                    $local->sync_sal_hc = 1;
                    $haCambiado = true;
                }
            } else {
                if ($local->sync_sal_hc != 0) {
                    $local->sync_sal_hc = 0;
                    $haCambiado = true;
                }
            }

            // --- 3. HORA ALMUERZO ENTRADA (Entrada Break) ---
            if ($hcHoraAlmuerzoEntrada || $local->hora_almuerzo_entrada) {
                $nuevaEntBreak = $local->hora_almuerzo_entrada;
                if ($hcHoraAlmuerzoEntrada && $local->hora_almuerzo_entrada) {
                    $nuevaEntBreak = $this->getEarliest($local->hora_almuerzo_entrada, $hcHoraAlmuerzoEntrada);
                } elseif ($hcHoraAlmuerzoEntrada) {
                    $nuevaEntBreak = $hcHoraAlmuerzoEntrada;
                }

                if ($local->hora_almuerzo_entrada !== $nuevaEntBreak || $local->sync_eal_hc == 0) {
                    $local->hora_almuerzo_entrada = $nuevaEntBreak;
                    $local->sync_eal_hc = 1;
                    $haCambiado = true;
                }
            } else {
                if ($local->sync_eal_hc != 0) {
                    $local->sync_eal_hc = 0;
                    $haCambiado = true;
                }
            }

            // --- 4. HORA SALIDA ---
            if ($hcHoraSalida || $local->hora_salida) {
                $nuevaSalida = $local->hora_salida;
                if ($hcHoraSalida && $local->hora_salida) {
                    $nuevaSalida = $this->getEarliest($local->hora_salida, $hcHoraSalida);
                } elseif ($hcHoraSalida) {
                    $nuevaSalida = $hcHoraSalida;
                }

                if ($local->hora_salida !== $nuevaSalida || $local->sync_sa_hc == 0) {
                    $local->hora_salida = $nuevaSalida;
                    $local->sync_sa_hc = 1;
                    $haCambiado = true;
                }
            } else {
                if ($local->sync_sa_hc != 0) {
                    $local->sync_sa_hc = 0;
                    $haCambiado = true;
                }
            }

            // --- 5. EVALUAR ESTADO DE ASISTENCIA RECALCULADO ---
            $nuevoEstado = $determinarEstadoFinal(
                $local->hora_entrada,
                $local->hora_almuerzo_salida,
                $local->hora_almuerzo_entrada,
                $local->hora_salida
            );

            if ($local->estado_asistencia !== $nuevoEstado) {
                $local->estado_asistencia = $nuevoEstado;
                $haCambiado = true;
            }

            if ($haCambiado) {
                $local->save();
                return true;
            }

            return false;
        } else {
            // 8. Si NO existe registro local, lo creamos evaluando sus marcaciones
            $nuevoEstado = $determinarEstadoFinal(
                $hcHoraEntrada,
                $hcHoraAlmuerzoSalida,
                $hcHoraAlmuerzoEntrada,
                $hcHoraSalida
            );

            Asistencia_empleado::create([
                'ci_empleado'           => $ci_empleado,
                'fecha'                 => $fecha,
                'hora_entrada'          => $hcHoraEntrada,
                'sync_e_hc'             => $hcHoraEntrada ? 1 : 0,
                'hora_almuerzo_salida'  => $hcHoraAlmuerzoSalida,
                'sync_sal_hc'           => $hcHoraAlmuerzoSalida ? 1 : 0,
                'hora_almuerzo_entrada' => $hcHoraAlmuerzoEntrada,
                'sync_eal_hc'           => $hcHoraAlmuerzoEntrada ? 1 : 0,
                'hora_salida'           => $hcHoraSalida,
                'sync_sa_hc'            => $hcHoraSalida ? 1 : 0,
                'ip_marcacion'          => '190.15.134.93',
                'campus'                => 'Campus Universitario',
                'estado_asistencia'     => $nuevoEstado,
            ]);

            return true;
        }
    }
    private function parseHikCentralDate($dateStr)
    {
        // Si viene vacío, nulo o contiene ceros estructurales
        if (!$dateStr || blank($dateStr) || str_contains($dateStr, '0000-00-00')) {
            return null;
        }

        try {
            if (str_contains($dateStr, '/')) {
                $dt = Carbon::createFromFormat('d/m/Y', $dateStr);
            } else {
                $dt = Carbon::parse($dateStr);
            }

            // Si por error de parseo da un año absurdo (como -0001 o 1970), lo descartamos
            if ($dt->year < 2000) {
                return null;
            }

            return $dt->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Convierte el formato ISO 8601 de HikCentral a Y-m-d H:i:s (MySQL DATETIME)
     * Protegido contra valores vacíos o marcas inexistentes
     */
    private function parseHikCentralDateTime($timeStr)
    {
        // Si el empleado no marcó y viene vacío o con ceros
        if (!$timeStr || blank($timeStr) || str_contains($timeStr, '0000-00-00')) {
            return null;
        }

        try {
            $dt = Carbon::parse($timeStr);

            // Si el biométrico manda un timestamp por defecto antiguo, lo hacemos null
            if ($dt->year < 2000) {
                return null;
            }

            // NOTA: Si tus columnas en la BD son de tipo TIME (ej: 14:30:00) 
            // en lugar de DATETIME, cambia este formato final a 'H:i:s'
            return $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    // Función auxiliar para obtener la hora más temprana
    private function getEarliest($time1, $time2)
    {
        if (empty($time1)) return $time2;
        if (empty($time2)) return $time1;

        return (strtotime($time1) < strtotime($time2)) ? $time1 : $time2;
    }
}
