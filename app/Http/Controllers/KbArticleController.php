<?php

namespace App\Http\Controllers;

use App\Models\KbArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Exception;

class KbArticleController extends Controller
{
    public function listar()
    {
        try {
            $articulos = KbArticle::orderBy('id', 'desc')->get();
            return response()->json($articulos, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al listar artículos: ' . $e->getMessage()], 500);
        }
    }

    public function encontrar($id)
    {
        try {
            $articulo = KbArticle::find($id);
            if (!$articulo) {
                return response()->json(['error' => 'Artículo no encontrado'], 404);
            }
            return response()->json($articulo, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al encontrar el artículo'], 500);
        }
    }

    public function crear(Request $request)
    {
        try {
            $data = $request->validate([
                'titulo' => 'required|string|max:190',
                'categoria' => 'required|in:planes,registro,legal,contacto,faq,negocio',
                'contenido' => 'required|string',
                'link_fuente' => 'nullable|url|max:255',
                'keywords' => 'nullable|array',
                'es_publico' => 'boolean',
                'estatus' => 'in:borrador,publicado'
            ]);

            // Generar slug único
            $slug = Str::slug($data['titulo']);
            $originalSlug = $slug;
            $count = 1;
            while (KbArticle::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $count;
                $count++;
            }
            $data['slug'] = $slug;

            $articulo = KbArticle::create($data);
            return response()->json($articulo, 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al crear artículo: ' . $e->getMessage()], 400);
        }
    }

    public function actualizar(Request $request)
    {
        try {
            $data = $request->validate([
                'id' => 'required|exists:kb_articles,id',
                'titulo' => 'string|max:190',
                'categoria' => 'in:planes,registro,legal,contacto,faq,negocio',
                'contenido' => 'string',
                'link_fuente' => 'nullable|url|max:255',
                'keywords' => 'nullable|array',
                'es_publico' => 'boolean',
                'estatus' => 'in:borrador,publicado'
            ]);

            $articulo = KbArticle::find($data['id']);
            
            if (isset($data['titulo']) && $data['titulo'] !== $articulo->titulo) {
                $slug = Str::slug($data['titulo']);
                $originalSlug = $slug;
                $count = 1;
                while (KbArticle::where('slug', $slug)->where('id', '!=', $articulo->id)->exists()) {
                    $slug = $originalSlug . '-' . $count;
                    $count++;
                }
                $articulo->slug = $slug;
            }

            $articulo->update($data);
            return response()->json($articulo, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al actualizar artículo: ' . $e->getMessage()], 400);
        }
    }

    public function eliminar(Request $request)
    {
        try {
            $request->validate(['id' => 'required|exists:kb_articles,id']);
            KbArticle::destroy($request->id);
            return response()->json(['mensaje' => 'Artículo eliminado correctamente'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al eliminar el artículo'], 500);
        }
    }
}
