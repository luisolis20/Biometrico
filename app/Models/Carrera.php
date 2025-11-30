<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Carrera extends Model
{
   protected $table = 'carrera';
    protected $primaryKey = 'idCarr';
    public $incrementing = false; // CHAR(6)
    public $timestamps = false;

    protected $fillable = [
        'NombCarr',
        'nivelCarr',
        'StatusCarr',
        'codCarr_senescyt',
        'mod_id',
        'sau_id',
        'id_tc',
        'inst_cod',
        'idcarr_utelvt',
        'idsede',
        'idfacultad',
        'culminacion',
        'optativa',
        'carreracol',
        'habilitada',
        'tituloh',
        'titulom',
        'folio',
        'cantidadestudiante',
        'cantidadporpagina',
        'cantidadlibro',
        'fechaaprobacion',
        'resolucion',
        'duracion',
        'titulo',
        'director',
        'equivalencia',
        'secretaria',
        'carrerahomologada'
    ];

    // Relaciones
    public function sede()
    {
        return $this->belongsTo(Sede::class, 'idsede', 'idsede');
    }

    public function facultad()
    {
        return $this->belongsTo(Facultad::class, 'idfacultad', 'idfacultad');
    }

    public function modalidades()
    {
        return $this->hasMany(CarreraModalidad::class, 'idCarr', 'idCarr');
    }
}
