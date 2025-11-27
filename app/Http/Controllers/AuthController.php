<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
//use App\Models\RegistroTitulos;
//use App\Models\informacionpersonal;
use Illuminate\Validation\Rule;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class AuthController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CIInfPer' => 'required|string',
            'codigo_dactilar' => 'required|string',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }
    
        $CIInfPer = $request->input('CIInfPer');
        $codigo_dactilar = $request->input('codigo_dactilar');
    
        // Buscar en InformacionPersonal (Estudiante)
        //$res = informacionpersonal::where('CIInfPer', $CIInfPer)->first();
        //$resdocen = RegistroTitulos::where('ciinfper', $CIInfPer)->first();
        $user = User::where('CIInfPer', $CIInfPer)->first();
        /*if ($res) {
            if($resdocen){

                if (md5($codigo_dactilar) !== $res->codigo_dactilar) {
                    return response()->json([
                        'error' => true,
                        'clave' => 'clave error',
                        'mensaje' => 'Usuario correcto pero la clave es incorrecta',
                    ], Response::HTTP_UNAUTHORIZED);
                }
        
                // Crear o actualizar usuario en users_cvn
                $user = User::updateOrCreate(
                    ['email' => $res->mailPer],
                    [
                        'name' => $res->ApellInfPer,
                        'CIInfPer' => $res->CIInfPer,
                        'password' => md5($codigo_dactilar),
                        'role' => 'Estudiante',
                        'estado' => 1,
                    ]
                );
        
                $token = auth()->login($user);
        
                return response()->json([
                    'mensaje' => 'Autenticación exitosa',
                    'Graduado' => 'Si',
                    'Rol' => 'Estudiante',
                    'CIInfPer' => $res->CIInfPer,
                    'ApellInfPer' => $res->ApellInfPer,
                    'mailPer' => $res->mailPer,
                    'token' => $token,
                    'token_type' => 'bearer'
                ]);
            }else{
                
                return response()->json([
                    'mensaje' => 'El usuario estudiante aun no se ha graduado',
                    'error' => true,
                    'Rol' => 'Estudiante',
                    'Graduado' => 'No',
                    'CIInfPer' => $res->CIInfPer,
                    'ApellInfPer' => $res->ApellInfPer,
                    'mailPer' => $res->mailPer,
                    
                ]);
            }
        }
        
        else*/
        if ($user) {
            if ($user->estado !== 1) {
                return response()->json([
                    'error' => true,
                    'mensaje' => 'El usuario está inhabilitado',
                ], Response::HTTP_UNAUTHORIZED);
            }
    
            if (md5($codigo_dactilar) !== $user->password) {
                return response()->json([
                    'error' => true,
                    'mensaje' => 'Usuario correcto pero la clave es incorrecta',
                ], Response::HTTP_UNAUTHORIZED);
            }
    
            $token = auth()->login($user);
            $user->last_login_at = now();
            $user->is_online = 1;
            $user->save();
    
            return response()->json([
                'mensaje' => 'Autenticación exitosa',
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60,
                'name' => $user->name,
                'email' => $user->email,
                'id' => $user->id,
                'CIInfPer' => $user->CIInfPer,
                'Rol' => $user->role,
            ]);
        }else{
            
            return response()->json([
                'error' => true,
                'mensaje' => "El Usuario: $CIInfPer no Existe",
            ], Response::HTTP_NOT_FOUND);
        }
    
    }
   
    public function me(){
        return response()->json(auth()->user());
    }
    public function logout(){
        //auth()->logout();
        try {
            $token = JWTAuth::getToken();

            if (!$token) {
                return response()->json(['error' => 'No hay token'], Response::HTTP_BAD_REQUEST);
            }

            // ✅ Obtener usuario antes de invalidar token o cerrar sesión
            $user = JWTAuth::authenticate($token);

            if ($user) {
                $user->is_online = 0;
                $user->last_logout_at = now();
                $user->save();
            }

            // ✅ Invalidar token después
            JWTAuth::invalidate($token);
            return response()->json(['message' => 'Has cerrado sesion'], Response::HTTP_OK);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'Token inválido'], Response::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No se pudo cerrar sesion'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function refresh(){
        try{
            $token = JWTAuth::getToken();
            if(!$token){
                return response()->json(['error'=>'No hay token'],Response::HTTP_BAD_REQUEST);
            }
            $nuevo_token = JWTAuth::refresh();
            JWTAuth::invalidate($token);
            return $this->respondWithToken($nuevo_token);
        }catch(TokenInvalidException $e){
            return response()->json(['error'=>'Token inválido'],Response::HTTP_UNAUTHORIZED);
        }catch(\Exception $e){
            return response()->json(['error'=>'No se pudo refrescar sesion'],Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    protected function respondWithToken($token){
        return response()->json([
            'token'=>$token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60
        ],Response::HTTP_OK);
    }
}
