<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistroTitulos extends Model
{
    use HasFactory;
     // Nombre de la tabla
     protected $table = 'registrotitulos';

     // Definir la clave primaria
     protected $primaryKey = 'idregistro';
 
     // Indicar si la clave primaria es incremental
     public $incrementing = true;
 
     // Tipo de clave primaria
     protected $keyType = 'int';
 
     // Campos que pueden ser llenados masivamente
     protected $fillable = [
         'ciinfper',
         'idcarr',
         'folio',
         'acta',
         'numerolibro',
         'detalle',
         'elaborado',
         'impreso',
         'contadorimpresion',
         'fechaincorporacion',
         'fechainicio',
         'fechafin',
         'fechainvestigacion',
         'fecharefrendacion',
         'fechaelaboracion',
         'requsitograduacion',
         'nombretesis',
         'tituloadmision',
         'tipocolegio',
         'procedenciatittuloadmision',
         'reconocimientoestudiosprevios',
         'recnocimientoaniosprevios',
         'actadegrado',
         'numeroespecis',
         'idsede',
         'tipodocumento',
         'insttucionestudiosprevios',
         'recnocimneitoestudiosprevios',
         'tipoduracionestudiosprevios',
         'mecanismotitulaion',
         'linktesis',
         'notapromedioacumulado',
         'notatrabajotitulacion',
         'observaciones',
         'carreraestudioeprevios',
         'usuarioreg',
         'usuariomod',
         'fechamod'
     ];
    
     public function informacionPersonal()
    {
        return $this->belongsTo(informacionpersonal::class, 'ciinfper', 'CIInfPer');
    }
}
