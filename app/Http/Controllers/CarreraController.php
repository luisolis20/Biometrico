<?php

namespace App\Http\Controllers;

use App\Models\Carrera;
use Illuminate\Http\Request;

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
            $carrerasAExcluir = ['056', '122', '124', '197', '206', '601', '602', '603'];

            $carreras = Carrera::select('carrera.NombCarr')
                ->distinct()
                ->join('ingreso', 'ingreso.idcarr', '=', 'carrera.idCarr')
                ->join('informacionpersonal', 'informacionpersonal.CIInfPer', '=', 'ingreso.CIInfPer')
                ->whereNotNull('informacionpersonal.fotografia')
                // Se puede replicar la lógica de filtro de estudiantesfoto para más precisión, si es necesario.
                // Para ser simple, solo se filtran las que tienen alguna foto en información personal:
                ->whereNotIn('carrera.idCarr', $carrerasAExcluir)
                ->where('carrera.NombCarr', 'NOT LIKE', '%TRABAJO DE INTEGRACIÓN CURRICULAR%')
                ->orderBy('carrera.NombCarr')
                ->pluck('NombCarr'); // Obtiene directamente un array de nombres

            return response()->json([
                'data' => $carreras,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al obtener lista de carreras: ' . $e->getMessage()], 500);
        }
    }
}
