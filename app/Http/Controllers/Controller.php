<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Storage;

abstract class Controller
{
    //


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
}
