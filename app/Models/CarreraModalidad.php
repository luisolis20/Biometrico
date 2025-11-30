<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarreraModalidad extends Model
{
   protected $table = 'carrera_modalidad';
    public $timestamps = false;

    protected $fillable = [
        'mod_id',
        'idCarr'
    ];

    // Relaciones
    public function carrera()
    {
        return $this->belongsTo(Carrera::class, 'idCarr', 'idCarr');
    }
}
