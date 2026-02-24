<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Categoria;
use Validator;
use Illuminate\Support\Str;

class CategoriaController extends Controller
{
    /**
     * Listar todas las categorías.
     */
    public function listar(Request $request)
    {
        $type = $request->attributes->get('auth_type');
        
        $query = Categoria::with('padre');
        
        if ($type !== 'admin') {
            $query->where('activo', 1);
        }

        $categorias = $query->get();
        return response()->json(['data' => $categorias], 200);
    }

    /**
     * Encontrar una categoría por ID.
     */
    public function encontrar(int $id)
    {
        $categoria = Categoria::with(['padre', 'hijos'])->find($id);

        if (!$categoria) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        return response()->json(['data' => $categoria], 200);
    }

    /**
     * Crear una nueva categoría.
     */
    public function crear(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'nombre' => 'required|string|max:100',
            'id_padre' => 'nullable|integer',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'activo' => 'required|boolean',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $categoria = new Categoria();
        $categoria->nombre = $request->nombre;
        $categoria->id_padre = $request->id_padre;
        $categoria->slug = Str::slug($request->nombre);
        $categoria->activo = $request->activo;

        if ($request->hasFile('imagen')) {
            $categoria->ruta_imagen_destacada = $this->subirArchivo($request->file('imagen'), ['jpeg', 'png', 'jpg', 'webp'], 'categorias');
        }

        $categoria->save();

        return response()->json(['message' => 'Categoría creada exitosamente', 'data' => $categoria], 201);
    }

    /**
     * Actualizar una categoría existente.
     */
    public function actualizar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
            'nombre' => 'required|string|max:100',
            'id_padre' => 'nullable|integer',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'activo' => 'required|boolean',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $categoria = Categoria::find($request->id);

        if (!$categoria) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        $categoria->nombre = $request->nombre;
        $categoria->id_padre = $request->id_padre;
        $categoria->slug = Str::slug($request->nombre);
        $categoria->activo = $request->activo;

        if ($request->hasFile('imagen')) {
            if ($categoria->ruta_imagen_destacada) {
                $this->eliminarArchivo($categoria->ruta_imagen_destacada);
            }
            $categoria->ruta_imagen_destacada = $this->subirArchivo($request->file('imagen'), ['jpeg', 'png', 'jpg', 'webp'], 'categorias');
        }

        $categoria->save();

        return response()->json(['message' => 'Categoría actualizada exitosamente', 'data' => $categoria], 200);
    }

    /**
     * Eliminar (desactivar) una categoría.
     */
    public function eliminar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $categoria = Categoria::find($request->id);

        if (!$categoria) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        $categoria->activo = 0;
        $categoria->save();

        return response()->json(['message' => 'Categoría desactivada exitosamente'], 200);
    }

    /**
     * Restaurar (activar) una categoría.
     */
    public function restaurar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $categoria = Categoria::find($request->id);

        if (!$categoria) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        $categoria->activo = 1;
        $categoria->save();

        return response()->json(['message' => 'Categoría activada exitosamente'], 200);
    }
}
