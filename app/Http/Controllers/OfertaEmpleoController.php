<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OfertaEmpleo;
use App\Models\Negocio;
use Validator;

class OfertaEmpleoController extends Controller
{
    /**
     * Crear una oferta de empleo (Admin).
     */
    public function crear(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id_negocio' => 'nullable|integer',
            'publicador_tipo' => 'required|in:negocio,admin,gobierno',
            'titulo' => 'required|string|max:190',
            'descripcion' => 'required|string',
            'id_estado' => 'required|integer',
            'id_ciudad' => 'required|integer',
            'es_remoto' => 'boolean',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        // Obtener ID real del admin autenticado
        $id_admin = null;
        if ($request->attributes->get('auth_type') === 'admin') {
            $admin = $request->attributes->get('user');
            if ($admin) {
                $id_admin = $admin->id;
            }
        }

        $oferta = OfertaEmpleo::create([
            'id_negocio' => $request->id_negocio,
            'id_admin_publicador' => $id_admin,
            'publicador_tipo' => $request->publicador_tipo,
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion,
            'requisitos' => $request->requisitos,
            'beneficios' => $request->beneficios,
            'correo_contacto' => $request->correo_contacto,
            'telefono_contacto' => $request->telefono_contacto,
            'organizacion_externa' => $request->organizacion_externa,
            'alcance_visibilidad' => $request->alcance_visibilidad ?? 'ciudad',
            'id_estado' => $request->id_estado,
            'id_ciudad' => $request->id_ciudad,
            'es_remoto' => $request->es_remoto ? 1 : 0,
            'estatus' => $request->estatus ?? 'activo',
            'expira_en' => $request->expira_en, // TODO: Si es null colocar +30 días
            'activo' => 1,
        ]);

        $negocio = Negocio::find($request->id_negocio);
        if ($negocio) {
            $negocio->increment('total_ofertas_empleo');
        }

        return response()->json(['message' => 'Oferta creada exitosamente', 'data' => $oferta], 201);
    }

    /**
     * Listar las ofertas de empleo (Admin puede ver todas).
     */
    public function listar(Request $request)
    {
        $type = $request->attributes->get('auth_type');
        
        $query = OfertaEmpleo::where('activo', 1)->orderBy('created_at', 'DESC');

        if ($type === 'admin') {
            $query->with(['negocio', 'admin', 'estado', 'ciudad']);
        } else {
            return response()->json(['message' => 'Acceso denegado'], 403);
        }

        $ofertas = $query->get();

        return response()->json(['data' => $ofertas], 200);
    }

    /**
     * Actualizar una oferta.
     */
    public function actualizar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
            'titulo' => 'required|string|max:190',
            'descripcion' => 'required|string',
            'id_estado' => 'required|integer',
            'id_ciudad' => 'required|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $oferta = OfertaEmpleo::find($request->id);

        if (!$oferta) {
            return response()->json(['message' => 'Oferta no encontrada'], 404);
        }

        $oferta->update([
            'id_negocio' => $request->id_negocio ?? $oferta->id_negocio,
            'publicador_tipo' => $request->publicador_tipo ?? $oferta->publicador_tipo,
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion,
            'requisitos' => $request->requisitos,
            'beneficios' => $request->beneficios,
            'correo_contacto' => $request->correo_contacto,
            'telefono_contacto' => $request->telefono_contacto,
            'organizacion_externa' => $request->organizacion_externa,
            'alcance_visibilidad' => $request->alcance_visibilidad ?? $oferta->alcance_visibilidad,
            'id_estado' => $request->id_estado,
            'id_ciudad' => $request->id_ciudad,
            'es_remoto' => $request->es_remoto ? 1 : 0,
            'estatus' => $request->estatus ?? $oferta->estatus,
            'expira_en' => $request->expira_en,
            'activo' => $request->has('activo') ? ($request->activo ? 1 : 0) : $oferta->activo,
        ]);

        return response()->json(['message' => 'Oferta actualizada exitosamente', 'data' => $oferta], 200);
    }

    /**
     * Eliminar (Soft delete).
     */
    public function eliminar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $oferta = OfertaEmpleo::find($request->id);

        if (!$oferta) {
            return response()->json(['message' => 'Oferta no encontrada'], 404);
        }

        $oferta->delete();
        
        if ($oferta->id_negocio) {
            $negocio = Negocio::find($oferta->id_negocio);
            if ($negocio) {
                $negocio->decrement('total_ofertas_empleo');
            }
        }

        return response()->json(['message' => 'Oferta eliminada correctamente'], 200);
    }
}
