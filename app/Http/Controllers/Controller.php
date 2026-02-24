<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Storage;
use App\Models\Usuario;
use Illuminate\Http\Request;

abstract class Controller
{
    public function subirArchivo($archivo, $extensionesPermitidas, $carpeta){
        //Carpetas: negocios, productos, categorias, anuncios
        
        $ext = $archivo->getClientOriginalExtension();

        if (in_array($ext, $extensionesPermitidas) && in_array($carpeta, ['negocios', 'productos', 'categorias', 'anuncios'])) {
            $newname = $filename ?? uniqid() . '.' . $ext;
            $filePath = $carpeta . '/' . $newname;
            Storage::disk('s3')->put($filePath, file_get_contents($archivo), 'public');

            $finalPath = config('filesystems.disks.s3.url') . $filePath;
            return $finalPath;
        } else {
            return null;
        }
    }

    public function eliminarArchivo($archivo){
        try {
            if($archivo && $archivo != ''){
                $sliced = explode(".com/", $archivo);
                Storage::disk('s3')->delete($sliced[1]);
                return true;
            }
            return false;
        } catch (\Throwable $th) {
            return false;
        }
    }

    public function eliminarAcentos($cadena)
    {
        $buscar = array('á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ', 'ü', 'Ü');
        $reemplazar = array('a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u', 'n', 'n', 'u', 'u');
        return str_replace($buscar, $reemplazar, $cadena);
    }

    public function normalizarTexto($cadena)
    {
        if (!$cadena) return '';
        $cadena = mb_strtolower($cadena, 'UTF-8');
        $cadena = $this->eliminarAcentos($cadena);
        $cadena = preg_replace('/[^a-z0-9\s]/', '', $cadena); // Solo letras y números
        $cadena = trim(preg_replace('/\s+/', ' ', $cadena)); // Quitar espacios múltiples
        return $cadena;
    }

    //Obtiene el Usuario. Si un admin lo ejecuta, entonces buscará al usuario que viene en el request, si lo ejecuta un Usuario emprendedor, entonces los buscará con base en su token
    public function obtenerUsuarioId(Request $request, $id_usuario){
        $user = $request->attributes->get('user');
        $type = $request->attributes->get('auth_type');

        if($type == 'admin'){
            return $id_usuario;
        }else if($type == 'usuario'){
            return $user->id;
        }
    }

    public function obtenerUsuario(Request $request, $id_usuario){
        $user = $request->attributes->get('user');
        $type = $request->attributes->get('auth_type');

        if($type == 'admin'){
            return Usuario::where('id', $id_usuario)->first();
        }else if($type == 'usuario'){
            return Usuario::where('id', $user->id)->first();
        }
    }
}
