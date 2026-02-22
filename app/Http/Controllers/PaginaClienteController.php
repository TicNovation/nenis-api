<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Negocio;
use App\Models\Banner;
use App\Models\Categoria;
use App\Models\MensajeDiario;
use App\Models\Estado;
use App\Models\Ciudad;
use Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaginaClienteController extends Controller
{
    public function mostrarHome(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id_estado' => 'sometimes|nullable',
            'id_ciudad' => 'sometimes|nullable',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $id_estado = $request->id_estado;
        $id_ciudad = $request->id_ciudad;

        // 1. Banners con jerarquía
        $banners = Banner::alcance($id_estado, $id_ciudad)->get();

        // 1.1 Registrar impresiones eficientemente en un solo query (Batch Insert / Update)
        if ($banners->isNotEmpty()) {
            $hoy = Carbon::now()->toDateString();
            $values = [];
            $bindings = [];
            
            foreach ($banners as $banner) {
                $values[] = "(?, ?, 1, 0)";
                $bindings[] = $banner->id;
                $bindings[] = $hoy;
            }
            
            $valuesSql = implode(', ', $values);
            DB::statement("
                INSERT INTO banners_stats_diarias (id_banner, fecha, impresiones, clicks) 
                VALUES {$valuesSql} 
                ON DUPLICATE KEY UPDATE impresiones = impresiones + 1
            ", $bindings);
        }

        // 2. Categorías padre
        $categorias = Categoria::where('activo', 1)
            ->whereNull('id_padre')
            ->get();

        // 3. Mensajes diarios
        $mensajes = MensajeDiario::where('activo', 1)->inRandomOrder()->limit(20)->get();

        // 4. Estados para selección manual
        $estados = Estado::orderBy('nombre', 'ASC')->get();

        $data = [
            'banners' => $banners,
            'categorias' => $categorias,
            'mensajes' => $mensajes,
            'estados' => $estados,
        ];

        return response()->json(['data' => $data], 200);
    }

    public function obtenerUbicacion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'estado' => 'required|string',
            'ciudad' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        $slug_estado = Str::slug($request->estado);
        $slug_ciudad = Str::slug($request->ciudad);

        // Buscar estado por slug o nombre
        $estado = Estado::where('slug', $slug_estado)
            ->orWhere('nombre', 'LIKE', "%{$request->estado}%")
            ->first();

        if (!$estado) {
            return response()->json(['message' => 'Estado no encontrado en nuestra base de datos'], 404);
        }

        // Buscar ciudad dentro del estado por slug o nombre
        $ciudad = Ciudad::where('id_estado', $estado->id)
            ->where(function($q) use ($slug_ciudad, $request) {
                $q->where('slug', $slug_ciudad)
                  ->orWhere('nombre', 'LIKE', "%{$request->ciudad}%");
            })
            ->first();

        if (!$ciudad) {
            return response()->json(['message' => 'Ciudad no encontrada en nuestra base de datos para este estado'], 404);
        }

        return response()->json([
            'data' => [
                'id_estado' => $estado->id,
                'id_ciudad' => $ciudad->id,
                'nombre_estado' => $estado->nombre,
                'nombre_ciudad' => $ciudad->nombre
            ]
        ], 200);
    }

    public function obtenerCiudades(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_estado' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        $ciudades = Ciudad::where('id_estado', $request->id_estado)->orderBy('nombre', 'ASC')->get();

        return response()->json(['data' => $ciudades], 200);
    }

    /**
     * Buscar negocios por palabra clave
     */
    public function buscarNegocios(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'buscar' => 'sometimes|nullable|string|min:3',
            'id_estado' => 'sometimes|nullable|integer',
            'id_ciudad' => 'sometimes|nullable|integer',
            'por_pagina' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $query = Negocio::where('activo', 1)
            ->where('estatus', 'publicado')
            ->where('estatus_verificacion', 'verificado')
            ->visibilidadJerarquica($request->id_estado, $request->id_ciudad);

        if ($request->filled('buscar')) {
            $busqueda = $request->buscar;
            $busqueda_limpia = $this->normalizarTexto($busqueda);
            $busqueda_sql = str_replace(' ', '%', $busqueda_limpia);

            $query->where(function ($q) use ($busqueda, $busqueda_sql) {
                $q->where('nombre', 'LIKE', "%{$busqueda}%")
                    ->orWhere('descripcion', 'LIKE', "%{$busqueda}%")
                    ->orWhere('slogan', 'LIKE', "%{$busqueda}%")
                    ->orWhere('palabras_clave_normalizadas', 'LIKE', "%{$busqueda_sql}%");
            });
        }

        $negocios = $query->with(['categoriaPrincipal', 'categorias', 'sucursales' => function($q) {
                $q->where('activo', 1)->with(['estado', 'ciudad']);
            }])
            ->orderBy('prioridad_cache', 'DESC')
            ->paginate($request->input('por_pagina', 100));

        $negocios->getCollection()->transform(function ($negocio) {
            foreach ($negocio->sucursales as $sucursal) {
                if ($sucursal->visibilidad_direccion !== 'completa') {
                    $sucursal->direccion_texto = null;
                    $sucursal->codigo_postal = null;
                    $sucursal->lat = null;
                    $sucursal->lng = null;
                }
            }
            return $negocio;
        });

        return response()->json(['data' => $negocios], 200);
    }

    /**
     * Buscar negocios por categoría padre e hijos
     */
    public function buscarNegociosPorCategoria(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id_categoria' => 'required|integer',
            'id_estado' => 'sometimes|nullable|integer',
            'id_ciudad' => 'sometimes|nullable|integer',
            'por_pagina' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        // Obtener subcategorías (colección para reusar y IDs para filtrar)
        $subcategorias = Categoria::where('id_padre', $request->id_categoria)
            ->where('activo', 1)
            ->get();

        $ids_categorias = $subcategorias->pluck('id')->toArray();
        array_push($ids_categorias, $request->id_categoria);

        $query = Negocio::where('activo', 1)
            ->where('estatus', 'publicado')
            ->where('estatus_verificacion', 'verificado')
            ->visibilidadJerarquica($request->id_estado, $request->id_ciudad);

        // Filtrar por categorías (Principal o Secundarias)
        $query->where(function ($q) use ($ids_categorias) {
            $q->whereIn('id_categoria_principal', $ids_categorias)
              ->orWhereHas('categorias', function ($sq) use ($ids_categorias) {
                  $sq->whereIn('id_categoria', $ids_categorias);
              });
        });


        $negocios = $query->with(['categoriaPrincipal', 'categorias', 'sucursales' => function($q) {
                $q->where('activo', 1)->with(['estado', 'ciudad']);
            }])
            ->orderBy('prioridad_cache', 'DESC')
            ->paginate($request->input('por_pagina', 100));

        $negocios->getCollection()->transform(function ($negocio) {
            foreach ($negocio->sucursales as $sucursal) {
                if ($sucursal->visibilidad_direccion !== 'completa') {
                    $sucursal->direccion_texto = null;
                    $sucursal->codigo_postal = null;
                    $sucursal->lat = null;
                    $sucursal->lng = null;
                }
            }
            return $negocio;
        });

        $data = [
            'categoria' => Categoria::find($request->id_categoria),
            'subcategorias' => $subcategorias,
            'negocios' => $negocios,
        ];

        return response()->json(['data' => $data], 200);
    }

    public function encontrarNegocio(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'slug' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        $negocio = Negocio::where('slug', $request->slug)
            ->where('activo', 1)
            ->where('estatus', 'publicado')
            ->with([
                'imagenes' => function($q) {
                    $q->where('activo', 1);
                },
                'items' => function($q) {
                    $q->where('activo', 1)->with(['imagenes' => function($sq) {
                        $sq->where('activo', 1);
                    }]);
                },
                'sucursales' => function($q) {
                    $q->where('activo', 1)->with(['horarios', 'estado', 'ciudad']);
                },
                'categoriaPrincipal'
            ])
            ->first();

        if (!$negocio) {
            return response()->json(['message' => 'Negocio no encontrado'], 404);
        }

        foreach ($negocio->sucursales as $sucursal) {
            if ($sucursal->visibilidad_direccion !== 'completa') {
                $sucursal->direccion_texto = null;
                $sucursal->codigo_postal = null;
                $sucursal->lat = null;
                $sucursal->lng = null;
            }
        }

        // Renombrar items a productos para el front-end
        $negocio->setRelation('productos', $negocio->items);
        $negocio->unsetRelation('items');

        // Incrementar vistas
        $negocio->increment('total_vistas');

        return response()->json(['data' => $negocio], 200);
    }
}
