<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PeriodoLectivo extends Model
{
    use HasFactory;
    // Nombre de la tabla
    protected $table = 'periodolectivo';

    // Clave primaria
    protected $primaryKey = 'idper';

    // Es autoincremental
    public $incrementing = true;

    // Tipo de clave primaria
    protected $keyType = 'int';

    // La tabla NO usa timestamps
    public $timestamps = false;

    // Campos asignables
    protected $fillable = [
        'fechinicioperlec',
        'fechfinalperlec',
        'DescPerLec',
        'StatusPerLec',
        'cicloPerLec',
        'inicioClases',
        'finClases',
        'examfinal_ini',
        'examfinal_fin',
        'examsupletorio_ini',
        'examsupletorio_fin',
        'ci_fechinicio',
        'ci_fechfin',
        'examsuficiencia_ini',
        'examsuficiencia_fin',
        'org_mallacurr',
        'periodosUnificado',
        'descripcion_perlec',
        'fechamaxeliminarmatricula',
        'finiciohemi1',
        'ffinhemi1',
        'finicioef1',
        'ffinef1',
        'finiciohemi2',
        'ffinhemi2',
        'finicioef2',
        'ffinef2',
        'fechainieval',
        'fechafineval',
        'um',
        'fm',
        'porcentajeminimo',
        'fechainibeca',
        'fechafinbeca',
    ];
    
}
