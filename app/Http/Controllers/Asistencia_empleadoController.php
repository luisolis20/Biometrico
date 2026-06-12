<?php

namespace App\Http\Controllers;

use App\Models\Asistencia_empleado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class Asistencia_empleadoController extends Controller
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
    public function show(Request $request, string $id) {}

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
    public function checkLocal(Request $request)
    {
        $ci_empleado = $request->ci_empleado;
        $beginTime = $request->beginTime;
        $endTime = $request->endTime;

        $data = Asistencia_empleado::where('ci_empleado', $ci_empleado)
            ->whereBetween('fecha', [$beginTime, $endTime])
            ->get();

        $exists = $data->isNotEmpty();

        return response()->json([
            'exists' => $exists,
            'data'   => $data // Enviamos la data al frontend
        ]);
    }
    
    public function syncAttendance(Request $request)
    {
        $ci_empleado = $request->ci_empleado;
        $marcacionesHC = $request->marcaciones;

        foreach ($marcacionesHC as $hc) {
            // 1. Normalizar la fecha del registro
            $fecha = $this->parseHikCentralDate($hc['fecha']);

            // 2. Normalizar todas las horas de HikCentral a formato MySQL antes de operar con ellas
            $hcHoraEntrada         = $this->parseHikCentralDateTime($hc['hora_entrada'] ?? null);
            $hcHoraAlmuerzoSalida  = $this->parseHikCentralDateTime($hc['hora_almuerzo_salida'] ?? null);
            $hcHoraAlmuerzoEntrada = $this->parseHikCentralDateTime($hc['hora_almuerzo_entrada'] ?? null);
            $hcHoraSalida          = $this->parseHikCentralDateTime($hc['hora_salida'] ?? null);
            $hcEstadoAsistencia     = $hc['estado_asistencia'] ?? null;

            // Buscar si ya existe la marcación local para esa fecha
            $local = Asistencia_empleado::where('ci_empleado', $ci_empleado)
                ->where('fecha', $fecha)
                ->first();

            if ($local) {
                // Actualizar tomando la hora más temprana entre local y HikCentral (ya formateadas)
                $local->hora_entrada          = $this->getEarliest($local->hora_entrada, $hcHoraEntrada);
                $local->hora_almuerzo_salida  = $this->getEarliest($local->hora_almuerzo_salida, $hcHoraAlmuerzoSalida);
                $local->hora_almuerzo_entrada = $this->getEarliest($local->hora_almuerzo_entrada, $hcHoraAlmuerzoEntrada);
                $local->hora_salida           = $this->getEarliest($local->hora_salida, $hcHoraSalida);
                $local->save();
            } else {
                // Registrar nueva marcación si no existe en BD
                Asistencia_empleado::create([
                    'ci_empleado'           => $ci_empleado,
                    'fecha'                 => $fecha,
                    'hora_entrada'          => $hcHoraEntrada,
                    'hora_almuerzo_salida'  => $hcHoraAlmuerzoSalida,
                    'hora_almuerzo_entrada' => $hcHoraAlmuerzoEntrada,
                    'hora_salida'           => $hcHoraSalida,
                    'ip_marcacion'          => request()->ip(),
                    'campus'                => '',
                    'estado_asistencia'     => $hcEstadoAsistencia,
                ]);
            }
        }

        // 3. Convertir beginTime y endTime que vienen de HikCentral para que la consulta no falle
        $begin = $this->parseHikCentralDate($request->beginTime);
        $end = $this->parseHikCentralDate($request->endTime);

        // Retornar los datos finales combinados/actualizados de la base de datos
        $datosFinales = Asistencia_empleado::where('ci_empleado', $ci_empleado)
            ->whereBetween('fecha', [$begin, $end])
            ->orderBy('fecha', 'asc')
            ->get();

        return response()->json($datosFinales);
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
