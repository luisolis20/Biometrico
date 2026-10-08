<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvitadoHikcentral extends Model
{
   protected $table = 'hikcentral_invitados';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'cedula',
        'nombres',
        'apellidos',
        'genero',
        'correo',
        'foto',
        'begin_time',
        'end_time',
        'codigo_departamento',
        'estado',
        'evidencia',
    ];

}
