<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bitacora extends Model
{
    protected $table = 'bitacora';
    protected $primaryKey = 'bt_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $fillable = [
        'bt_usuario',
        'bt_fechahora',
        'bt_accion',
        'bt_ippc',//ip de la computadora que realizo la accion
        'bt_observacion',
    ];

}