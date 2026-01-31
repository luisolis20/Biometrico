<?php

namespace App\Http\Controllers;

use App\Models\PeriodoLectivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PeriodoLectivoController extends Controller
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
    public function destroy(string $id)
    {
        //
    }
    public function getActivos()
    {
        $periodos = Cache::remember('periodos_recientes_activos', 3600, function () {
            // 1. Buscamos el periodo activo (Status 1)
            $activo = PeriodoLectivo::where('StatusPerLec', 1)
                ->select('idper', 'DescPerLec')
                ->first();

            if (!$activo) {
                return [];
            }

            // 2. Buscamos el periodo anterior (el ID más alto que sea menor al activo)
            $anterior = PeriodoLectivo::where('idper', '<', $activo->idper)
                ->select('idper', 'DescPerLec')
                ->orderBy('idper', 'desc')
                ->first();

            // Creamos la colección con ambos
            $resultado = collect([$activo]);
            if ($anterior) {
                $resultado->push($anterior);
            }

            return $resultado;
        });

        return response()->json([
            'status' => true,
            'data'   => $periodos
        ]);
    }
}
