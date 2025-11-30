<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    public $table = "usuario";
    protected $primaryKey = 'LoginUsu';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'LoginUsu',
        'StatusUsu',
        'NombUsu',
        'email',
        'movil',
        'idperfil',
        'ciinfper',
        'idcarr',
        'id_actdist',
        'usa_biometrico',
        'fecha_reg',
        'fecha_ultimo_acceso',
        'titulo',
        'homologar',
        'crearnota',
        'posgrado',
        'idcampus',
        'inscribir',
        'equivalencia',
        'id_grupo',
        'usuarioreg',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    

    

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
   
     public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
