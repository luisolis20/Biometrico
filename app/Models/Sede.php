<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
   protected $table = 'sede';
    protected $primaryKey = 'idsede';
    public $timestamps = false;

    protected $fillable = [
        'sede',
        'sedeglobal',
        'direccion',
        'codigocampusceaaces',
        'coordinador',
        'cargo'
    ];

    // Relaciones
    public function facultades()
    {
        return $this->hasMany(Facultad::class, 'idsede', 'idsede');
    }

    public function carreras()
    {
        return $this->hasMany(Carrera::class, 'idsede', 'idsede');
    }
}
