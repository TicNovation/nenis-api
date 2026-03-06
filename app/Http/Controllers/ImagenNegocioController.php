<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ImagenNegocio;
use App\Models\Negocio;
use Validator;

class ImagenNegocioController extends Controller
{
    public function crear(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id_negocio' => 'required|integer',
            'imagen' => 'required|image|mimes:jpeg,png,jpg,gif|max:3048',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $id_usuario = $this->obtenerUsuarioId($request, $request->id_usuario);

        // Verificamos que el negocio pertenezca al usuario
        $negocio = Negocio::where('id', $request->id_negocio)->first();
        if (!$negocio || $negocio->id_usuario != $id_usuario) {
            return response()->json(['message' => 'Negocio no encontrado'], 404);
        }

        $imagen_negocio = ImagenNegocio::create([
            'id_negocio' => $request->id_negocio,
            'ruta' => $this->subirArchivo($request->file('imagen'), ['jpg', 'jpeg', 'png', 'gif'], 'negocios'),
        ]);

        $negocio->increment('total_imagenes');

        return response()->json(['data' => $imagen_negocio, 'message' => 'Imagen subida exitosamente'], 201);
    }

    public function eliminar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $id_usuario = $this->obtenerUsuarioId($request, $request->id_usuario);

        $imagen = ImagenNegocio::where('id', $request->id)->with('negocio')->first();

        if (!$imagen || $imagen->negocio->id_usuario != $id_usuario) {
            return response()->json(['message' => 'Imagen no encontrada o no tienes permisos'], 404);
        }

        // Eliminar del bucket
        $this->eliminarArchivo($imagen->ruta);
        
        // Eliminar de la base de datos
        $imagen->delete();
        //Decrementar la cantidad de sucursales
        $negocio = Negocio::find($imagen->negocio->id);
        $negocio->decrement('total_imagenes');

        return response()->json(['message' => 'Imagen eliminada exitosamente'], 200);
    }

    public function listar(Request $request, int $id_negocio, ?int $usuario_id = null)
    {

        $id_usuario = $this->obtenerUsuarioId($request, $usuario_id);

        $negocio = Negocio::where('id', $id_negocio)->where('id_usuario', $id_usuario)->with('imagenes')->first();

        return response()->json(['data' => $negocio->imagenes, 'message' => 'Imagenes obtenidas exitosamente'], 200);
    }
}
