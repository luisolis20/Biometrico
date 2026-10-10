<?php

namespace App\Http\Controllers;

use App\Models\InvitadoHikcentral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File; // Importamos la clase Facultad
use Illuminate\Support\Facades\Log; // Importamos la clase JsonResponse
use Illuminate\Http\JsonResponse; // Utilizado para realizar operaciones de archivos
use Illuminate\Support\Facades\Validator;
use App\Models\Bitacora;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class InvitadoHikcentralController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $searchQuery = $request->input('search_query');
            $query = InvitadoHikcentral::select('hikcentral_invitados.*');
            if ($searchQuery) {
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('cedula', 'LIKE', '%' . $searchQuery . '%');
                });
            }
            if ($request->has('all') && $request->all === 'true') {
                $data = $query->get();

                // Convertir los datos a UTF-8 válido
                $data->transform(function ($item) {
                    $attributes = $item->getAttributes();
                    foreach ($attributes as $key => $value) {
                        if (is_string($value)) {
                            $attributes[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                        }
                    }
                    return $attributes;
                });

                return response()->json(['data' => $data]);
            }

            // Paginación por defecto
            $data = $query->paginate(20);

            if ($data->isEmpty()) {
                return response()->json([
                    'data' => [],
                    'message' => 'No se encontraron datos'
                ], 200);
            }

            // Convertir los datos de cada página a UTF-8 válido
            $data->getCollection()->transform(function ($item) {
                $attributes = $item->getAttributes();
                foreach ($attributes as $key => $value) {
                    if (is_string($value)) {
                        $attributes[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                    }
                }
                return $attributes;
            });

            // Retornar respuesta JSON con metadatos de paginación
            return response()->json([
                'data' => $data->items(),
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al codificar los datos a JSON: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. Validaciones requeridas por la BD
        $validator = Validator::make($request->all(), [
            'cedula'              => 'required|string|max:20|unique:hikcentral_invitados,cedula', // unique valida que no exista
            'nombres'             => 'required|string|max:100',
            'apellidos'           => 'required|string|max:100',
            'genero'              => 'required|integer|in:1,2',
            'correo'              => 'nullable|email|max:150',
            'foto'                => 'nullable|string|max:255',
            'begin_time'          => 'required|date_format:Y-m-d H:i:s',
            'end_time'            => 'required|date_format:Y-m-d H:i:s|after:begin_time', // end_time debe ser posterior a begin_time
            'codigo_departamento' => 'required|integer',
            'estado'              => 'nullable|integer|in:1,2',
            'evidencia'           => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'mensaje' => 'Errores de validación',
                'errores' => $validator->errors()
            ], 400);
        }

        $inputs = $request->all();
        $res = InvitadoHikcentral::create($inputs);
        try {
            $user = Auth::user(); // Obtenemos el usuario autenticado
            Bitacora::create([
                'bt_usuario' => $user->ciinfper,
                'bt_fechahora' => Carbon::now(),
                'bt_accion' => 'REGISTRO DE INVITADOS',
                'bt_ippc' => $request->ip(),
                'bt_observacion' => "USUARIO: {$user->NombUsu} REALIZÓ: REGISTRO DE INVITADO: {$inputs['cedula']}",
            ]);
        } catch (\Exception $ex) {
            Log::error('Error bitácora en guardarCambios: ' . $ex->getMessage());
        }

        return response()->json([
            'data' => $res,
            'mensaje' => "Agregado con Éxito!!",
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $res = InvitadoHikcentral::where('cedula', $id)->first(); // Usar first() ya que cédula es única

        if ($res) {
            return response()->json([
                'data' => $res,
                'mensaje' => "Encontrado con Éxito!!",
            ]);
        } else {
            return response()->json([
                'error' => true,
                'mensaje' => "El Usuario con cédula: $id no Existe",
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // 1. Validaciones (Se excluye el ID actual de la validación unique de cédula)
        $validator = Validator::make($request->all(), [
            'cedula'              => 'required|string|max:20|unique:hikcentral_invitados,cedula,' . $id,
            'nombres'             => 'required|string|max:100',
            'apellidos'           => 'required|string|max:100',
            'genero'              => 'required|integer|in:1,2',
            'correo'              => 'nullable|email|max:150',
            'foto'                => 'nullable|string|max:255',
            'begin_time'          => 'required|date_format:Y-m-d H:i:s',
            'end_time'            => 'required|date_format:Y-m-d H:i:s|after:begin_time',
            'codigo_departamento' => 'required|integer',
            'estado'              => 'nullable|integer|in:1,2',
            'evidencia'           => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'mensaje' => 'Errores de validación',
                'errores' => $validator->errors()
            ], 400);
        }
        $res = InvitadoHikcentral::find($id);

        if (isset($res)) {
            $res->cedula = $request->cedula;
            $res->nombres = $request->nombres;
            $res->apellidos = $request->apellidos;
            $res->genero = $request->genero;
            $res->correo = $request->correo;
            $res->foto = $request->foto;
            $res->begin_time = $request->begin_time;
            $res->end_time = $request->end_time;
            $res->codigo_departamento = $request->codigo_departamento;
            $res->estado = $request->estado;
            $res->evidencia = $request->evidencia;
            if ($res->save()) {
                try {
                    $user = Auth::user(); // Obtenemos el usuario autenticado
                    Bitacora::create([
                        'bt_usuario' => $user->ciinfper,
                        'bt_fechahora' => Carbon::now(),
                        'bt_accion' => 'ACTUALIZACIÓN DE INVITADOS',
                        'bt_ippc' => $request->ip(),
                        'bt_observacion' => "USUARIO: {$user->NombUsu} REALIZÓ: ACTUALIZACIÓN DE INVITADO: {$res->cedula}",
                    ]);
                } catch (\Exception $ex) {
                    Log::error('Error bitácora en guardarCambios: ' . $ex->getMessage());
                }
                return response()->json([
                    'data' => $res,
                    'mensaje' => "Actualizado con Éxito!!",
                ]);
            }

            return response()->json(['error' => true, 'mensaje' => 'Error al Actualizar'], 500);
        }

        return response()->json(['error' => true, 'mensaje' => "El invitado con id: $id no Existe"], 404);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request,string $id)
    {
        $res = InvitadoHikcentral::find($id);
        if (isset($res)) {
            $res->estado = 0;
            $res->save();
            $data = $res->toArray();
            if ($data) {
                try {
                    $user = Auth::user(); // Obtenemos el usuario autenticado
                    Bitacora::create([
                        'bt_usuario' => $user->ciinfper,
                        'bt_fechahora' => Carbon::now(),
                        'bt_accion' => 'INHIBICIÓN DE INVITADOS',
                        'bt_ippc' => $request->ip(),
                        'bt_observacion' => "USUARIO: {$user->NombUsu} REALIZÓ: INHIBICIÓN DE INVITADO: {$res->cedula}",
                    ]);
                } catch (\Exception $ex) {
                    Log::error('Error bitácora en guardarCambios: ' . $ex->getMessage());
                }
                return response()->json([
                    'data' => $data,
                    'mensaje' => "Inhabilitado con Éxito!!",
                ]);
            } else {
                return response()->json([
                    'data' => $data,
                    'mensaje' => "El invitado no existe (puede que ya lo haya eliminado)",
                ]);
            }
        } else {
            return response()->json([
                'error' => true,
                'mensaje' => "El invitado con id: $id no Existe",
            ]);
        }
    }
    public function habilitar(Request $request, string $id)
    {
        $res = InvitadoHikcentral::find($id);
        if (isset($res)) {
            $res->estado = 1;
            $res->save();
            $data = $res->toArray();
            if ($data) {
                try {
                    $user = Auth::user(); // Obtenemos el usuario autenticado
                    Bitacora::create([
                        'bt_usuario' => $user->ciinfper,
                        'bt_fechahora' => Carbon::now(),
                        'bt_accion' => 'HABILITACIÓN DE INVITADOS',
                        'bt_ippc' => $request->ip(),
                        'bt_observacion' => "USUARIO: {$user->NombUsu} REALIZÓ: HABILITACIÓN DE INVITADO: {$res->cedula}",
                    ]);
                } catch (\Exception $ex) {
                    Log::error('Error bitácora en guardarCambios: ' . $ex->getMessage());
                }
                return response()->json([
                    'data' => $data,
                    'mensaje' => "Habilitado con Éxito!!",
                ]);
            } else {
                return response()->json([
                    'data' => $data,
                    'mensaje' => "El invitado no existe (puede que ya lo haya eliminado)",
                ]);
            }
        } else {
            return response()->json([
                'error' => true,
                'mensaje' => "El invitado con id: $id no Existe",
            ]);
        }
    }
    public function uploadArchivo(Request $request)
    {
        if ($request->hasFile('file')) { // Si el archivo existe
            Log::info('Archivo detectado: ' . $request->file('file')->getClientOriginalName()); // Se registra el nombre del archivo detectado
            Log::info('Error de subida PHP: ' . $request->file('file')->getError()); // Se registra el error de subida PHP
            Log::info('Tamaño recibido: ' . $request->file('file')->getSize()); // Se registra el tamaño del archivo recibido
        } else {
            // Se registra un error de subida si no se detectó ningún archivo en la petición
            Log::warning('No se detectó ningún archivo en la petición.');
        }
        $request->validate([
            'file' => 'required|max:10240', // 10MB
            'ci' => 'required|alpha_dash',
            'old_filename' => 'nullable|string',
        ]); // Se validan los datos de la petición

        try {
            $ci = basename($request->ci); // Se obtiene el cédula del archivo
            $file = $request->file('file'); // Se obtiene el archivo subido
            if (! $file->isValid()) { // Si el archivo no es válido
                throw new \Exception('Archivo inválido o corrupto.'); // Se lanza un error
            }
            if ($request->filled('old_filename')) { // Si se ha proporcionado un nombre de archivo antiguo
                $oldFilename = basename($request->old_filename); // Seguridad extra
                $oldPath = public_path("Documentos/Biometrico/Invitados/Evidencia/{$ci}/{$oldFilename}"); // Se obtiene la ruta del archivo antiguo
                if (File::exists($oldPath)) { // Si el archivo antiguo existe
                    File::delete($oldPath); // Se elimina el archivo antiguo
                }
            }

            // Crear carpeta publica si no existe
            $directory = public_path("Documentos/Biometrico/Invitados/Evidencia/{$ci}");

            if (! File::isDirectory($directory)) { // Si la carpeta no existe
                File::makeDirectory($directory, 0755, true, true); // Se crea la carpeta
            }

            // Generar nombre: CI + _ + aleatorio + _ + fecha (Ymd_His)
            $aleatorio = bin2hex(random_bytes(8)); // 16 caracteres hex
            $fechaHora = date('Ymd_His');          // Ej: 20251112_1741
            $extension = $file->getClientOriginalExtension(); // pdf

            $filename = "{$ci}_{$aleatorio}_{$fechaHora}.{$extension}"; // Se genera el nombre final del archivo

            // Guardar archivo
            $file->move($directory, $filename);

            // URL pública, para acceder al archivo desde fuera de la aplicación
            $url = url('Documentos/Biometrico/Invitados/Evidencia/' . $ci . '/' . $filename);

            // Se devuelve un array con el mensaje de éxito y el nombre del archivo y la URL pública
            return response()->json([
                'status' => true,
                'filename' => $filename,
                'url' => $url,
            ]);
        } catch (\Exception $e) {
            // Se devuelve un array con el mensaje de error y el error generado
            return response()->json([
                'status' => false,
                'message' => 'Seguridad: El archivo no pudo ser procesado.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function uploadFoto(Request $request)
    {
        if ($request->hasFile('file')) {
            Log::info('Foto detectada: ' . $request->file('file')->getClientOriginalName());
        } else {
            Log::warning('No se detectó ninguna foto en la petición.');
        }

        // Validación específica para imágenes (jpeg, png, jpg, webp) y máximo 5MB
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
            'ci' => 'required|alpha_dash',
            'old_filename' => 'nullable|string',
        ]);

        try {
            $ci = basename($request->ci);
            $file = $request->file('file');

            if (! $file->isValid()) {
                throw new \Exception('Imagen inválida o corrupta.');
            }

            // Eliminar imagen anterior si existe
            if ($request->filled('old_filename')) {
                $oldFilename = basename($request->old_filename);
                $oldPath = public_path("Documentos/Biometrico/Invitados/Fotos/{$ci}/{$oldFilename}");
                if (File::exists($oldPath)) {
                    File::delete($oldPath);
                }
            }

            // Crear carpeta para Fotos
            $directory = public_path("Documentos/Biometrico/Invitados/Fotos/{$ci}");

            if (! File::isDirectory($directory)) {
                File::makeDirectory($directory, 0755, true, true);
            }

            // Generar nombre único
            $aleatorio = bin2hex(random_bytes(4));
            $fechaHora = date('Ymd_His');
            $extension = $file->getClientOriginalExtension();

            $filename = "foto_{$ci}_{$aleatorio}_{$fechaHora}.{$extension}";

            // Guardar imagen
            $file->move($directory, $filename);

            // URL pública
            $url = url("Documentos/Biometrico/Invitados/Fotos/{$ci}/{$filename}");

            return response()->json([
                'status' => true,
                'filename' => $filename,
                'url' => $url,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Seguridad: La imagen no pudo ser procesada.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
