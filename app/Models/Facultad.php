<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Facultad extends Model
{
   protected $table = 'facultad';
    protected $primaryKey = 'idfacultad';
    public $timestamps = false;

    protected $fillable = [
        'idsede',
        'facultad',
        'decano',
        'cargodecano',
        'secretario',
        'cargosecretario',
        'fechacreacion',
        'siglas'
    ];

    // Relaciones
    public function sede()
    {
        return $this->belongsTo(Sede::class, 'idsede', 'idsede');
    }

    public function carreras()
    {
        return $this->hasMany(Carrera::class, 'idfacultad', 'idfacultad');
    }
}
