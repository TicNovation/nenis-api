<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sucursal;
use App\Models\SucursalHorario;
use App\Models\Negocio;
use Validator;

class SucursalController extends Controller
{
    /**
     * Listar sucursales de un negocio.
     */
    public function listar(Request $request, int $id_negocio, ?int $usuario_id = null)
    {
        $id_usuario = $this->obtenerUsuarioId($request, $usuario_id);

        if (!$id_usuario) {
            return response()->json(['message' => 'ID de usuario no identificado'], 400);
        }

        // Validación de propiedad
        $negocio = Negocio::where('id', $id_negocio)
            ->where('id_usuario', $id_usuario)
            ->first();

        if (!$negocio) {
            return response()->json(['message' => 'Negocio no encontrado o no autorizado'], 404);
        }

        $sucursales = Sucursal::where('id_negocio', $id_negocio)
            ->with(['estado', 'ciudad', 'horarios'])
            ->get();

        return response()->json(['data' => $sucursales], 200);
    }

    /**
     * Encontrar sucursal por ID.
     */
    public function encontrar(Request $request, int $id, ?int $usuario_id = null)
    {
        $id_usuario = $this->obtenerUsuarioId($request, $usuario_id);
        
        $sucursal = Sucursal::where('id', $id)->with(['negocio', 'estado', 'ciudad', 'horarios'])->first();

        if (!$sucursal || $sucursal->negocio->id_usuario != $id_usuario) {
            return response()->json(['message' => 'Sucursal no encontrada o no autorizada'], 404);
        }

        return response()->json(['data' => $sucursal], 200);
    }

    /**
     * Crear sucursal.
     */
    public function crear(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id_negocio' => 'required|integer',
            'id_estado' => 'required|integer',
            'id_ciudad' => 'required|integer',
            'direccion_texto' => 'required|string|max:255',
            'codigo_postal' => 'nullable|string|max:10',
            'visibilidad_direccion' => 'required|in:estado,ciudad,completa',
            'es_principal' => 'required|boolean',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'google_place_id' => 'nullable|string|max:255',
            'id_usuario' => 'sometimes|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $id_usuario_req = $request->input('id_usuario') ?? $request->query('id_usuario');
        $id_usuario = $this->obtenerUsuarioId($request, $id_usuario_req);

        // Validación de propiedad
        $negocio = Negocio::where('id', $request->id_negocio)
            ->where('id_usuario', $id_usuario)
            ->first();

        if (!$negocio) {
            return response()->json(['message' => 'Negocio no encontrado o no autorizado'], 404);
        }

        // Si esta es la principal, cambiar las demás a no principales
        if ($request->es_principal) {
            Sucursal::where('id_negocio', $request->id_negocio)->update(['es_principal' => 0]);
        }

        $sucursal = Sucursal::create([
            'id_negocio' => $request->id_negocio,
            'id_estado' => $request->id_estado,
            'id_ciudad' => $request->id_ciudad,
            'direccion_texto' => $request->direccion_texto,
            'visibilidad_direccion' => $request->visibilidad_direccion,
            'codigo_postal' => $request->codigo_postal,
            'es_principal' => $request->es_principal,
            'lat' => $request->lat,
            'lng' => $request->lng,
            'google_place_id' => $request->google_place_id,
            'activo' => 1,
        ]);

        return response()->json(['message' => 'Sucursal creada exitosamente', 'data' => $sucursal], 201);
    }

    /**
     * Actualizar sucursal.
     */
    public function actualizar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
            'id_estado' => 'required|integer',
            'id_ciudad' => 'required|integer',
            'direccion_texto' => 'required|string|max:255',
            'codigo_postal' => 'nullable|string|max:10',
            'visibilidad_direccion' => 'required|in:estado,ciudad,completa',
            'es_principal' => 'required|boolean',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'google_place_id' => 'nullable|string|max:255',
            'activo' => 'sometimes|boolean',
            'id_usuario' => 'sometimes|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $id_usuario_req = $request->input('id_usuario') ?? $request->query('id_usuario');
        $id_usuario = $this->obtenerUsuarioId($request, $id_usuario_req);

        $sucursal = Sucursal::where('id', $request->id)->with('negocio')->first();

        if (!$sucursal || $sucursal->negocio->id_usuario != $id_usuario) {
            return response()->json(['message' => 'Sucursal no encontrada o no autorizada'], 404);
        }

        // Si esta se marcará como principal, cambiar las demás a no principales
        if ($request->es_principal && !$sucursal->es_principal) {
            Sucursal::where('id_negocio', $sucursal->id_negocio)
                ->where('id', '!=', $sucursal->id)
                ->update(['es_principal' => 0]);
        }

        $sucursal->id_estado = $request->id_estado;
        $sucursal->id_ciudad = $request->id_ciudad;
        $sucursal->direccion_texto = $request->direccion_texto;
        $sucursal->codigo_postal = $request->codigo_postal;
        $sucursal->visibilidad_direccion = $request->visibilidad_direccion;
        $sucursal->es_principal = $request->es_principal;
        $sucursal->lat = $request->lat;
        $sucursal->lng = $request->lng;
        $sucursal->google_place_id = $request->google_place_id;

        if ($request->has('activo')) {
            $sucursal->activo = $request->activo;
        }

        $sucursal->save();

        return response()->json(['message' => 'Sucursal actualizada exitosamente', 'data' => $sucursal], 200);
    }

    /**
     * Eliminar sucursal.
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

        $id_usuario_req = $request->input('id_usuario') ?? $request->query('id_usuario');
        $id_usuario = $this->obtenerUsuarioId($request, $id_usuario_req);

        $sucursal = Sucursal::where('id', $request->id)->with('negocio')->first();

        if (!$sucursal || $sucursal->negocio->id_usuario != $id_usuario) {
            return response()->json(['message' => 'Sucursal no encontrada o no autorizada'], 404);
        }

        $sucursal->delete();

        return response()->json(['message' => 'Sucursal eliminada exitosamente'], 200);
    }
}
