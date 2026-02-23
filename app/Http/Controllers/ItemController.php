<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\Negocio;
use App\Models\Usuario;
use App\Models\Plan;
use Validator;
use Illuminate\Support\Str;

class ItemController extends Controller
{
    /**
     * Listar todos los items de un negocio específico.
     */
    public function listar(Request $request, int $id_negocio, ?int $usuario_id = null)
    {
        $id_usuario = $this->obtenerUsuarioId($request, $usuario_id);

        if (!$id_usuario) {
            return response()->json(['message' => 'ID de usuario no identificado'], 400);
        }
        
        // Verificamos que el negocio pertenezca al usuario
        $negocio = Negocio::where('id', $id_negocio)
            ->where('id_usuario', $id_usuario)
            ->first();

        if (!$negocio) {
            return response()->json(['message' => 'Negocio no encontrado'], 404);
        }

        $items = Item::where('id_negocio', $id_negocio)
            ->with(['imagenes', 'categoria'])
            ->get();

        return response()->json(['data' => $items], 200);
    }

    /**
     * Crear un nuevo item (producto/servicio).
     */
    public function crear(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id_negocio' => 'required|integer',
            'id_categoria' => 'required|integer',
            'nombre' => 'required|string|max:190',
            'descripcion' => 'nullable|string',
            'tipo_item' => 'required|in:producto,servicio',
            'precio' => 'required|numeric|min:0',
            'url_externa' => 'nullable|url|max:255',
            'ruta_imagen_destacada' => 'nullable|image|max:3048',
            'id_usuario' => 'sometimes|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $usuario = $this->obtenerUsuario($request, $request->id_usuario);
        
        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        // Verificamos que el negocio pertenezca al usuario
        $negocio = Negocio::where('id', $request->id_negocio)
            ->where('id_usuario', $usuario->id)
            ->first();

        if (!$negocio) {
            return response()->json(['message' => 'Negocio no encontrado'], 404);
        }

        // Validar límite de items según plan
        $plan = Plan::find($usuario->id_plan_activo);
        if ($usuario->total_items >= $plan->max_items) {
            return response()->json(['message' => 'Has alcanzado el límite máximo de productos en tu plan actual'], 403);
        }

        $item = new Item();
        $item->id_negocio = $request->id_negocio;
        $item->id_categoria = $request->id_categoria;
        $item->nombre = $request->nombre;
        $item->slug = Str::slug($request->nombre . '-' . Str::random(5));
        $item->descripcion = $request->descripcion;
        $item->tipo_item = $request->tipo_item;
        $item->precio = $request->precio;
        $item->url_externa = $request->url_externa;
        $item->total_vistas = 0;
        $item->activo = 1;

        if ($request->hasFile('ruta_imagen_destacada')) {
            $item->ruta_imagen_destacada = $this->subirArchivo($request->file('ruta_imagen_destacada'), ['jpg', 'jpeg', 'png', 'webp'], 'productos');
        }

        $item->save();

        // Incrementamos el contador del usuario
        $usuario->increment('total_items');

        return response()->json(['message' => 'Item creado exitosamente', 'data' => $item], 201);
    }

    /**
     * Actualizar un item existente.
     */
    public function actualizar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
            'id_categoria' => 'required|integer',
            'nombre' => 'required|string|max:190',
            'descripcion' => 'nullable|string',
            'tipo_item' => 'required|in:producto,servicio',
            'precio' => 'required|numeric|min:0',
            'url_externa' => 'nullable|url|max:255',
            'ruta_imagen_destacada' => 'nullable|image|max:2048',
            'activo' => 'sometimes|boolean',
            'id_usuario' => 'sometimes|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $id_usuario = $this->obtenerUsuarioId($request, $request->id_usuario);
        $item = Item::where('id', $request->id)->with('negocio')->first();

        if (!$item || $item->negocio->id_usuario != $id_usuario) {
            return response()->json(['message' => 'Item no encontrado'], 404);
        }

        $item->id_categoria = $request->id_categoria;
        $item->nombre = $request->nombre;
        $item->descripcion = $request->descripcion;
        $item->tipo_item = $request->tipo_item;
        $item->precio = $request->precio;
        $item->url_externa = $request->url_externa;
        
        if ($request->has('activo')) {
            $item->activo = $request->activo;
        }

        if ($request->hasFile('ruta_imagen_destacada')) {
            if ($item->ruta_imagen_destacada) {
                $this->eliminarArchivo($item->ruta_imagen_destacada);
            }
            $item->ruta_imagen_destacada = $this->subirArchivo($request->file('ruta_imagen_destacada'), ['jpg', 'jpeg', 'png', 'webp'], 'productos');
        }

        $item->save();

        return response()->json(['message' => 'Item actualizado exitosamente', 'data' => $item], 200);
    }

    /**
     * Encontrar un item específico por ID.
     */
    public function encontrar(Request $request, int $id, ?int $usuario_id = null)
    {
        $id_usuario = $this->obtenerUsuarioId($request, $usuario_id);
        $item = Item::where('id', $id)->with(['negocio', 'imagenes'])->first();

        if (!$item || $item->negocio->id_usuario != $id_usuario) {
            return response()->json(['message' => 'Item no encontrado'], 404);
        }

        return response()->json(['data' => $item], 200);
    }

    /**
     * Eliminar (soft delete) un item.
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

        $usuario = $this->obtenerUsuario($request, $request->id_usuario);
        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $item = Item::where('id', $request->id)->with('negocio')->first();

        if (!$item || $item->negocio->id_usuario != $usuario->id) {
            return response()->json(['message' => 'Item no encontrado'], 404);
        }

        $item->delete();

        // Decrementamos el contador del usuario
        $usuario->decrement('total_items');

        return response()->json(['message' => 'Item eliminado exitosamente'], 200);
    }
}
