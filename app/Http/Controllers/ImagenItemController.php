<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ImagenItem;
use App\Models\Item;
use Validator;

class ImagenItemController extends Controller
{
    public function crear(Request $request){
        $validate = Validator::make($request->all(), [
            'id_item' => 'required|integer',
            'imagen' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $id_usuario = $this->obtenerUsuarioId($request, $request->id_usuario);

        //Verificamos que el producto pertenezca al usuario
        $producto = Item::where('id', $request->id_item)->with('negocio')->first();
        if (!$producto || $producto->negocio->id_usuario != $id_usuario) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $imagen_item = ImagenItem::create([
            'id_item' => $request->id_item,
            'ruta' => $this->subirArchivo($request->file('imagen'), ['jpg', 'jpeg', 'png', 'gif'], 'productos'),
        ]);

        return response()->json(['data' => $imagen_item, 'message' => 'Imagen subida exitosamente'], 201);
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

        $imagen = ImagenItem::where('id', $request->id)->with('item.negocio')->first();

        if (!$imagen || $imagen->item->negocio->id_usuario != $id_usuario) {
            return response()->json(['message' => 'Imagen no encontrada o no tienes permisos'], 404);
        }

        // Eliminar del bucket
        $this->eliminarArchivo($imagen->ruta);
        
        // Eliminar de la base de datos
        $imagen->delete();

        return response()->json(['message' => 'Imagen eliminada exitosamente'], 200);
    }
}
