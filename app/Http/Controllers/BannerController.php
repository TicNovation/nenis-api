<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Banner;
use Validator;

class BannerController extends Controller
{
    public function listar()
    {
        $banners = Banner::all();
        return response()->json(['data' => $banners], 200);
    }

    public function crear(Request $request)
    {
        $validate = Validator::make($request->all(),
            [
                'id_anunciante' => 'nullable|integer',
                'enlace_externo' => 'required',
                'id_estado' => 'required',
                'id_ciudad' => 'required',
                'prioridad' => 'required',
                'estatus_cotizacion' => 'required',
                'inicia_en' => 'required',
                'termina_en' => 'required',
                'activo' => 'required',
                'alcance_nivel' => 'required',
            ]
        );

        if($validate->fails()){
            return response()->json(['message' => 'Error al validar los datos', 'errors' => $validate->errors()->first()], 400);
        }

        $banner = new Banner();
        $banner->id_anunciante = $request->id_anunciante;
        $banner->enlace_externo = $request->enlace_externo;
        $banner->id_estado = $request->id_estado;
        $banner->id_ciudad = $request->id_ciudad;
        $banner->prioridad = $request->prioridad;
        $banner->estatus_cotizacion = $request->estatus_cotizacion;
        $banner->inicia_en = $request->inicia_en;
        $banner->termina_en = $request->termina_en;
        $banner->alcance_nivel = $request->alcance_nivel;
        $banner->activo = $request->activo;
        if($request->hasFile('imagen')){
            $banner->ruta_imagen = $this->subirArchivo($request->file('imagen'),  ['jpeg', 'png', 'jpg', 'webp', 'heic', 'heif'], 'anuncios');
        }
        $banner->save();

        return response()->json(['message' => 'Banner creado exitosamente', 'data' => $banner]);
    }

    public function actualizar(Request $request)
    {
        $validate = Validator::make($request->all(),
            [
                'id' => 'required',
                'id_anunciante' => 'nullable|integer',
                'enlace_externo' => 'required',
                'id_estado' => 'required',
                'id_ciudad' => 'required',
                'prioridad' => 'required',
                'estatus_cotizacion' => 'required',
                'inicia_en' => 'required',
                'termina_en' => 'required',
                'activo' => 'required',
                'alcance_nivel' => 'required',
            ]
        );

        if($validate->fails()){
            return response()->json(['message' => 'Error al validar los datos', 'errors' => $validate->errors()->first()], 400);
        }

        $banner = Banner::find($request->id);

        if (!$banner) {
            return response()->json(['message' => 'Banner no encontrado'], 404);
        }

        $banner->id_anunciante = $request->id_anunciante;
        $banner->enlace_externo = $request->enlace_externo;
        $banner->id_estado = $request->id_estado;
        $banner->id_ciudad = $request->id_ciudad;
        $banner->prioridad = $request->prioridad;
        $banner->estatus_cotizacion = $request->estatus_cotizacion;
        $banner->inicia_en = $request->inicia_en;
        $banner->termina_en = $request->termina_en;
        $banner->alcance_nivel = $request->alcance_nivel;
        $banner->activo = $request->activo;
        if($request->hasFile('imagen')){
            $this->eliminarArchivo($banner->ruta_imagen);
            $banner->ruta_imagen = $this->subirArchivo($request->file('imagen'),  ['jpeg', 'png', 'jpg', 'webp', 'heic', 'heif'], 'anuncios');
        }
        $banner->save();

        return response()->json(['message' => 'Banner actualizado exitosamente', 'data' => $banner]);
    }

    public function eliminar(Request $request)
    {
        $validate = Validator::make($request->all(),
            [
                'id' => 'required',
            ]
        );

        if($validate->fails()){
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $banner = Banner::find($request->id);

        if (!$banner) {
            return response()->json(['message' => 'Banner no encontrado'], 404);
        }

        $this->eliminarArchivo($banner->ruta_imagen);   
        $banner->delete();

        return response()->json(['message' => 'Banner eliminado exitosamente']);
    }

}
