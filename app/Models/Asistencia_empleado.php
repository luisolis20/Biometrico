<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asistencia_empleado extends Model
{
   protected $table = 'asistencia_empleado';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'ci_empleado',
        'fecha',
        'hora_entrada',//DATETIME NULL DEFAULT NULL
        'hora_almuerzo_salida',//DATETIME NULL DEFAULT NULL
        'hora_almuerzo_entrada',//DATETIME NULL DEFAULT NULL
        'hora_salida',//DATETIME NULL DEFAULT NULL
        'ip_marcacion',
        'campus',
        'estado_asistencia'
    ];
    public function informacion_personal_d()
    {
        return $this->hasMany(informacionpersonal_D::class, 'ci_empleado', 'CIInfPer');
    }

}
