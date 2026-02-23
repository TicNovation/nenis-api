<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Negocio;
use Validator;
use Illuminate\Support\Str;

class NegocioController extends Controller
{
    /**
     * Listar los negocios del usuario autenticado.
     */
    public function listar(Request $request, ?int $usuario_id = null)
    {
        $id_usuario = $this->obtenerUsuarioId($request, $usuario_id);

        if (!$id_usuario) {
            return response()->json(['message' => 'Se requiere el ID de usuario para esta consulta'], 400);
        }
        
        $negocios = Negocio::where('id_usuario', $id_usuario)
            ->with('categoriaPrincipal')
            ->get();
            
        return response()->json(['data' => $negocios], 200);
    }

    /**
     * Crear un nuevo negocio.
     */
    public function crear(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id_usuario' => 'sometimes|integer', // Solo requerido si es admin
            'id_categoria_principal' => 'required|integer',
            'nombre' => 'required|string|max:190',
            'descripcion' => 'nullable|string',
            'slogan' => 'nullable|string|max:190',
            'palabras_clave' => 'nullable|string',
            'telefono' => 'nullable|string|max:30',
            'whatsapp' => 'nullable|string|max:30',
            'correo_contacto' => 'nullable|email|max:190',
            'sitio_web' => 'nullable|url|max:190',
            'facebook' => 'nullable|string|max:190',
            'instagram' => 'nullable|string|max:190',
            'tiktok' => 'nullable|string|max:190',
            'ruta_logo' => 'nullable|image|max:4048',
            'ruta_imagen_destacada' => 'nullable|image|max:4048',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        // Obtener el objeto usuario para heredar los campos de caché/membresía
        $usuario = $this->obtenerUsuario($request, $request->id_usuario);

        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado o no especificado'], 404);
        }

        $negocio = new Negocio();
        $negocio->id_usuario = $usuario->id;
        $negocio->id_categoria_principal = $request->id_categoria_principal;
        $nombre = $request->input('nombre');
        $negocio->nombre = $nombre;
        $negocio->slug = Str::slug($nombre . '-' . Str::random(5)); // Slug único
        $negocio->descripcion = $request->descripcion;
        $negocio->slogan = $request->slogan;
        $negocio->palabras_clave = $request->palabras_clave;
        $negocio->palabras_clave_normalizadas = $this->normalizarTexto($request->palabras_clave);
        
        // Heredar campos del Usuario (Plan/Membresía)
        $negocio->destacado_cache = $usuario->destacado_cache;
        $negocio->destacado_titulo_cache = $usuario->destacado_titulo_cache;
        $negocio->alcance_visibilidad = $usuario->max_alcance_visibilidad;
        $negocio->prioridad_cache = $usuario->prioridad_cache;
        
        $negocio->telefono = $request->telefono;
        $negocio->whatsapp = $request->whatsapp;
        $negocio->correo_contacto = $request->correo_contacto;
        $negocio->sitio_web = $request->sitio_web;
        $negocio->facebook = $request->facebook;
        $negocio->instagram = $request->instagram;
        $negocio->tiktok = $request->tiktok;
        
        $negocio->estatus = 'borrador'; // Por defecto al crear
        $negocio->estatus_verificacion = 'pendiente';
        $negocio->activo = 1;

        if ($request->hasFile('ruta_logo')) {
            $negocio->ruta_logo = $this->subirArchivo($request->file('ruta_logo'), ['jpg', 'jpeg', 'png', 'webp'], 'negocios');
        }

        if ($request->hasFile('ruta_imagen_destacada')) {
            $negocio->ruta_imagen_destacada = $this->subirArchivo($request->file('ruta_imagen_destacada'), ['jpg', 'jpeg', 'png', 'webp'], 'negocios');
        }

        $negocio->save();

        return response()->json(['message' => 'Negocio creado exitosamente', 'data' => $negocio], 201);
    }

    /**
     * Actualizar un negocio.
     */
    public function actualizar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
            'id_usuario' => 'sometimes|integer', // Para validación de pertenencia
            'id_categoria_principal' => 'required|integer',
            'nombre' => 'required|string|max:190',
            'descripcion' => 'nullable|string',
            'slogan' => 'nullable|string|max:190',
            'palabras_clave' => 'nullable|string',
            'telefono' => 'nullable|string|max:30',
            'whatsapp' => 'nullable|string|max:30',
            'correo_contacto' => 'nullable|email|max:190',
            'sitio_web' => 'nullable|url|max:190',
            'facebook' => 'nullable|string|max:190',
            'instagram' => 'nullable|string|max:190',
            'tiktok' => 'nullable|string|max:190',
            'estatus' => 'sometimes|string|in:borrador,publicado,pausado',
            'categorias_extra' => 'nullable|string', // JSON string of category IDs
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $id_usuario_req = $request->input('id_usuario') ?? $request->query('id_usuario');
        $id_operador = $this->obtenerUsuarioId($request, $id_usuario_req);
        $negocio = Negocio::find($request->id);

        if (!$negocio || $negocio->id_usuario != $id_operador) {
            return response()->json(['message' => 'Negocio no encontrado o no autorizado'], 404);
        }

        $negocio->id_categoria_principal = $request->id_categoria_principal;
        $negocio->nombre = $request->nombre;
        $negocio->descripcion = $request->descripcion;
        $negocio->slogan = $request->slogan;
        $negocio->palabras_clave = $request->palabras_clave;
        $negocio->palabras_clave_normalizadas = $this->normalizarTexto($request->palabras_clave);
        
        $negocio->telefono = $request->telefono;
        $negocio->whatsapp = $request->whatsapp;
        $negocio->correo_contacto = $request->correo_contacto;
        $negocio->sitio_web = $request->sitio_web;
        $negocio->facebook = $request->facebook;
        $negocio->instagram = $request->instagram;
        $negocio->tiktok = $request->tiktok;

        if ($request->has('estatus')) {
            $negocio->estatus = $request->estatus;
        }

        if ($request->hasFile('ruta_logo')) {
            if ($negocio->ruta_logo) {
                $this->eliminarArchivo($negocio->ruta_logo);
            }
            $negocio->ruta_logo = $this->subirArchivo($request->file('ruta_logo'), ['jpg', 'jpeg', 'png', 'webp'], 'negocios');
        }

        if ($request->hasFile('ruta_imagen_destacada')) {
            if ($negocio->ruta_imagen_destacada) {
                $this->eliminarArchivo($negocio->ruta_imagen_destacada);
            }
            $negocio->ruta_imagen_destacada = $this->subirArchivo($request->file('ruta_imagen_destacada'), ['jpg', 'jpeg', 'png', 'webp'], 'negocios');
        }

        $negocio->save();

        if ($request->has('categorias_extra')) {
            $categoriasExtra = json_decode($request->categorias_extra, true);
            if (is_array($categoriasExtra)) {
                $negocio->categorias()->sync($categoriasExtra);
            }
        }

        return response()->json(['message' => 'Negocio actualizado exitosamente', 'data' => $negocio], 200);
    }

    /**
     * Encontrar un negocio específico por ID.
     */
    public function encontrar(Request $request, int $id, ?int $usuario_id = null)
    {
        $id_usuario = $this->obtenerUsuarioId($request, $usuario_id);
        $negocio = Negocio::where('id', $id)
            ->where('id_usuario', $id_usuario)
            ->with(['categoriaPrincipal', 'categorias', 'imagenes', 'sucursales', 'items', 'empleos'])
            ->first();

        if (!$negocio) {
            return response()->json(['message' => 'Negocio no encontrado'], 404);
        }

        return response()->json(['data' => $negocio], 200);
    }

    /**
     * Eliminar (soft delete) un negocio.
     */
    public function eliminar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
            'id_usuario' => 'sometimes|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $id_operador = $this->obtenerUsuarioId($request, $request->id_usuario);
        $negocio = Negocio::where('id', $request->id)
            ->where('id_usuario', $id_operador)
            ->first();

        if (!$negocio) {
            return response()->json(['message' => 'Negocio no encontrado'], 404);
        }

        $negocio->delete();

        return response()->json(['message' => 'Negocio eliminado exitosamente'], 200);
    }
}
