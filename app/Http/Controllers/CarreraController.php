<?php

namespace App\Http\Controllers;

use App\Models\Carrera;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CarreraController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        /// return response()->json(Carrera::all());
    }

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
    public function update(Request $request, string $id)
    {
        //

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id) {}
    public function carrerasconsula()
    {
        try {
            // 1. Definir una llave única para la caché de carreras
            $cacheKey = 'lista_carreras_estudiantes_foto';

            // 2. Usar Cache::remember para evitar consultas pesadas en cada carga
            $carreras = Cache::remember($cacheKey, 86400, function () { // 86400 segundos = 24 horas

                $carrerasAExcluir = ['056', '122', '124', '197', '206', '601', '602', '603'];

                return Carrera::select('carrera.idCarr', 'carrera.NombCarr', 'carrera.StatusCarr','carrera.codihicenter')
                    ->distinct()
                    // Replicamos exactamente los JOINS de estudiantesfoto para consistencia total
                    ->join('ingreso', 'ingreso.idcarr', '=', 'carrera.idCarr')
                    ->join('informacionpersonal', 'informacionpersonal.CIInfPer', '=', 'ingreso.CIInfPer')
                    ->join('factura', 'factura.cedula', '=', 'informacionpersonal.CIInfPer')
                    ->where('factura.idper', 125) // Solo periodo vigente
                    ->where('carrera.StatusCarr', 1) // Solo periodo vigente
                    ->whereNotNull('informacionpersonal.fotografia')
                    ->whereNotIn('carrera.idCarr', $carrerasAExcluir)
                    ->where('carrera.NombCarr', 'NOT LIKE', '%TRABAJO DE INTEGRACIÓN CURRICULAR%')
                    ->orderBy('carrera.NombCarr', 'asc')
                    
                    ->get() // Traemos ID y Nombre
                    ->map(function ($carrera) {
                        return [
                            'id' => $carrera->idCarr,
                            'nombre' => $carrera->NombCarr,
                            'estado' => $carrera->StatusCarr,
                            'codihi' => $carrera->codihicenter
                        ];
                    });
            });

            return response()->json([
                'data' => $carreras,
                'source' => Cache::has($cacheKey) ? 'cache' : 'database' // Opcional: para debug
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Error al obtener lista de carreras: ' . $e->getMessage()
            ], 500);
        }
    }
}
