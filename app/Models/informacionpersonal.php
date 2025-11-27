<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class informacionpersonal extends Model
{
    use HasFactory;
   
    protected $table = 'informacionpersonal';
    protected $primaryKey = 'CIInfPer';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'CIInfPer',
        'cedula_pasaporte',
        'TipoDocInfPer',
        'ApellInfPer',
        'ApellMatInfPer',
        'NombInfPer',
        'NacionalidadPer',
        'EtniaPer',
        'tipo_nacionalidad',
        'FechNacimPer',
        'LugarNacimientoPer',
        'GeneroPer',
        'orientacionsexual',
        'EstadoCivilPer',
        'CiudadPer',
        'DirecDomicilioPer',
        'Telf1InfPer',
        'CelularInfPer',
        'TipoInfPer',
        'statusper',
        'mailPer',
        'mailInst',
        'GrupoSanguineo',
        'tipo_discapacidad',
        'carnet_conadis',
        'num_carnet_conadis',
        'porcentaje_discapacidad',
        'fotografia',
        'codigo_dactilar',
        'hd_posicion',
        'huella_dactilar',
        'codigo_verificacion',
        'entregofichamedica',
        'provinciaresidencia',
        'cantonresidencia',
        'idoperadora',
        'idparroquia',
        'nombreparentesco',
        'parentesco',
        'telefonoparentesco',
        'celularparentesco',
        'direccionparentesco',
        'informacionpersonalcol',
        'paisresidencia',
        'idparroquiaresidencia',
        'sectorresi',
        'calleprincipalresi',
        'callesecundariaresi',
        'barrioresi',
        'referenciaresi',
        'numerocasaresi',
        'tieneagua',
        'tienealcantarillado',
        'tienetelefonofijo',
        'tieneinternet',
        'tienetvcable',
        'ingresofamiliar',
        'actividaddelestudiante',
        'finalizofichase',
        'finalizofichasg',
        'residenteexterior',
        'tiporesidenciaexterior',
        'verification_code',
        'cabezadefamilia',
        'dependepadres',
        'espadresoltero',
        'padresrecibenbono',
        'viveen',
        'viviendaes',
        'estructuravivienda',
        'tieneelectricidad',
    ];
    
  

}
