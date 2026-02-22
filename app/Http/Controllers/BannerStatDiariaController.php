<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BannerStatDiaria;
use App\Models\Banner;
use Carbon\Carbon;
use Validator;

class BannerStatDiariaController extends Controller
{
    /**
     * Registrar un clic en un banner.
     * Esta ruta debe ser pública para que cualquier visitante registre el clic.
     */
    public function registrarClic(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id_banner' => 'required|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        // Opcional: Validar que el banner existe y está activo
        $banner = Banner::find($request->id_banner);
        if (!$banner) {
            return response()->json(['message' => 'Banner no encontrado'], 404);
        }

        $hoy = Carbon::now()->toDateString();

        // Buscar el registro de hoy o crearlo si no existe (con 0 impresiones y 0 clicks iniciales)
        $stat = BannerStatDiaria::firstOrCreate(
            ['id_banner' => $request->id_banner, 'fecha' => $hoy],
            ['impresiones' => 0, 'clicks' => 0]
        );

        // Incrementar el contador de clics
        $stat->increment('clicks');

        return response()->json(['message' => 'Clic registrado exitosamente'], 200);
    }
}
