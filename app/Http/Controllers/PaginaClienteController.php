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
use App\Mail\SolicitudPublicidadEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

class PaginaClienteController extends Controller
{
    /**
     * Maneja el envío del formulario de contacto para publicidad "Anúnciate"
     */
    public function contactoPublicidad(Request $request)
    {
        // 1. Honeypot Check: Si el campo 'website' viene lleno, es un bot
        if ($request->filled('website')) {
            return response()->json(['message' => 'Solicitud procesada correctamente (bot detected)'], 200);
        }

        // 2. Validación básica
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:100',
            'correo' => 'required|email|max:100',
            'telefono' => 'nullable|string|max:20',
            'nombre_negocio' => 'required|string|max:150',
            'mensaje' => 'required|string|min:10|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        // 3. Validación de contenido (Escudo anti-spam)
        $spamKeywords = [
            'crypto', 'bitcoin', 'ethereum', 'casino', 'betting', 'poker', 'porno', 
            'sex', 'girls', 'dating', 'viagra', 'cialis', 'earn money', 'work from home',
            'seo services', 'marketing agency', 'optimization', 'ranking', 'backlinks',
        ];

        $content = strtolower($request->mensaje);
        foreach ($spamKeywords as $keyword) {
            if (str_contains($content, $keyword)) {
                return response()->json(['message' => 'Tu mensaje contiene contenido no permitido por nuestras políticas de seguridad.'], 403);
            }
        }

        // Contar enlaces (bots suelen meter muchos links)
        if (substr_count($content, 'http') > 2) {
            return response()->json(['message' => 'Demasiados enlaces detectados.'], 403);
        }

        // 4. Envío de correo
        try {
            // El correo se envía al administrador (puedes configurar esto en el .env)
            $adminEmail = 'contacto@nuevaeradigital.mx';//config('mail.from.address'); // O un correo específico de ventas
            
            Mail::to($adminEmail)->send(new SolicitudPublicidadEmail($request->only([
                'nombre', 'correo', 'telefono', 'nombre_negocio', 'mensaje'
            ])));

            return response()->json([
                'message' => '¡Tu solicitud ha sido enviada con éxito! Nos pondremos en contacto contigo muy pronto.'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error enviando correo de publicidad: ' . $e->getMessage());
            return response()->json(['message' => 'Hubo un error al enviar tu solicitud. Por favor, inténtalo de nuevo más tarde.'], 500);
        }
    }
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

        // Intentamos obtener los datos de caché (1 hora)
        $cacheKey = "home_data_e{$id_estado}_c{$id_ciudad}";
        
        $data = Cache::remember($cacheKey, 3600, function() use ($id_estado, $id_ciudad) {
            // 1. Banners con jerarquía
            $banners = Banner::alcance($id_estado, $id_ciudad)->get();

            // 2. Categorías padre
            $categorias = Categoria::where('activo', 1)
                ->whereNull('id_padre')
                ->inRandomOrder()
                ->get();

            // 3. Mensajes diarios (Nota: Al cachear, el random se fijará por 1 hora)
            $mensajes = MensajeDiario::where('activo', 1)->inRandomOrder()->limit(20)->get();

            // 4. Estados para selección manual
            $estados = Estado::with('ciudades')->orderBy('nombre', 'ASC')->get();

            return [
                'banners' => $banners,
                'categorias' => $categorias,
                'mensajes' => $mensajes,
                'estados' => $estados,
            ];
        });

        // 1.1 Registrar impresiones (FUERA DEL CACHÉ para que cuente cada visita)
        if (!empty($data['banners']) && count($data['banners']) > 0) {
            $hoy = Carbon::now()->toDateString();
            $values = [];
            $bindings = [];
            
            foreach ($data['banners'] as $banner) {
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

        return response()->json(['data' => $data], 200)
            ->setPublic()
            ->setMaxAge(60)
            ->setEtag(md5(json_encode($data)));
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

        $input_estado = $request->estado;
        $input_ciudad = $request->ciudad;
        $slug_estado = Str::slug($input_estado);
        $slug_ciudad = Str::slug($input_ciudad);

        // Buscar estado por slug, nombre exacto o coincidencia parcial
        $estado = Estado::where('slug', $slug_estado)
            ->orWhere('nombre', 'LIKE', "%{$input_estado}%")
            ->orWhereRaw('? LIKE CONCAT("%", nombre, "%")', [$input_estado])
            ->first();

        if (!$estado) {
            return response()->json(['message' => 'Estado no encontrado en nuestra base de datos'], 404);
        }

        // Buscar ciudad dentro del estado de forma inteligente e insensible a acentos/caracteres
        $ciudad = Ciudad::where('id_estado', $estado->id)
            ->where(function($q) use ($slug_ciudad, $input_ciudad) {
                // 1. Coincidencia de slug (Normalizado sin acentos/especiales)
                $q->where('slug', 'LIKE', "%{$slug_ciudad}%")
                  // 2. Por si el input de Google es más largo: "leon-de-los-aldama" contiene "leon"
                  ->orWhereRaw('? LIKE CONCAT("%", slug, "%")', [$slug_ciudad])
                  // 3. Fallback por nombre por si acaso
                  ->orWhere('nombre', 'LIKE', "%{$input_ciudad}%")
                  ->orWhereRaw('? LIKE CONCAT("%", nombre, "%")', [$input_ciudad]);
            })
            // Ordenar por la coincidencia más cercana en longitud para evitar falsos positivos
            ->orderByRaw('ABS(LENGTH(slug) - LENGTH(?)) ASC', [$slug_ciudad])
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

        $id_estado = $request->id_estado;
        
        $ciudades = Cache::remember("ciudades_estado_{$id_estado}", 86400, function() use ($id_estado) {
            return Ciudad::where('id_estado', $id_estado)->orderBy('nombre', 'ASC')->get();
        });

        return response()->json(['data' => $ciudades], 200)
            ->setPublic()
            ->setMaxAge(86400); // 24 horas, las ciudades no cambian diario
    }

    /**
     * Buscar negocios por palabra clave.
     * Usa búsqueda por palabras individuales con scoring de relevancia:
     * - Coincidencia exacta en nombre: +10 pts
     * - Coincidencia parcial en nombre: +5 pts por palabra
     * - Coincidencia en palabras clave: +3 pts por palabra
     * - Coincidencia en slogan: +2 pts por palabra
     * - Coincidencia en descripción: +1 pt por palabra
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

            // Separar en palabras individuales (máximo 5 para evitar abuso)
            $palabras = array_slice(array_filter(explode(' ', $busqueda_limpia)), 0, 5);

            if (!empty($palabras)) {
                // --- Filtro: al menos UNA palabra debe coincidir en algún campo ---
                $query->where(function ($q) use ($busqueda, $busqueda_limpia, $palabras) {
                    // Coincidencia exacta de frase completa (como antes)
                    $busqueda_sql_frase = str_replace(' ', '%', $busqueda_limpia);
                    $q->where('nombre', 'LIKE', "%{$busqueda}%")
                        ->orWhere('palabras_clave_normalizadas', 'LIKE', "%{$busqueda_sql_frase}%")
                        ->orWhere('descripcion', 'LIKE', "%{$busqueda}%")
                        ->orWhere('slogan', 'LIKE', "%{$busqueda}%");

                    // Coincidencia por cada palabra individual
                    foreach ($palabras as $palabra) {
                        if (mb_strlen($palabra) >= 3) { // Ignorar palabras muy cortas (de, en, la...)
                            $q->orWhere('nombre', 'LIKE', "%{$palabra}%")
                                ->orWhere('palabras_clave_normalizadas', 'LIKE', "%{$palabra}%")
                                ->orWhere('descripcion', 'LIKE', "%{$palabra}%")
                                ->orWhere('slogan', 'LIKE', "%{$palabra}%");
                        }
                    }
                });

                // --- Scoring de relevancia para ordenar los mejores resultados primero ---
                $scoreSQL = [];
                $bindings = [];

                // +10 pts: nombre contiene la frase exacta
                $scoreSQL[] = "(CASE WHEN nombre LIKE ? THEN 10 ELSE 0 END)";
                $bindings[] = "%{$busqueda}%";

                // +8 pts: palabras clave contienen la frase completa normalizada
                $busqueda_sql_frase = str_replace(' ', '%', $busqueda_limpia);
                $scoreSQL[] = "(CASE WHEN palabras_clave_normalizadas LIKE ? THEN 8 ELSE 0 END)";
                $bindings[] = "%{$busqueda_sql_frase}%";

                // Por cada palabra individual, sumar puntos según el campo donde coincide
                foreach ($palabras as $palabra) {
                    if (mb_strlen($palabra) >= 3) {
                        // +5 pts por palabra en nombre
                        $scoreSQL[] = "(CASE WHEN nombre LIKE ? THEN 5 ELSE 0 END)";
                        $bindings[] = "%{$palabra}%";

                        // +3 pts por palabra en palabras_clave_normalizadas
                        $scoreSQL[] = "(CASE WHEN palabras_clave_normalizadas LIKE ? THEN 3 ELSE 0 END)";
                        $bindings[] = "%{$palabra}%";

                        // +2 pts por palabra en slogan
                        $scoreSQL[] = "(CASE WHEN slogan LIKE ? THEN 2 ELSE 0 END)";
                        $bindings[] = "%{$palabra}%";

                        // +1 pt por palabra en descripción
                        $scoreSQL[] = "(CASE WHEN descripcion LIKE ? THEN 1 ELSE 0 END)";
                        $bindings[] = "%{$palabra}%";
                    }
                }

                $scoreExpression = implode(' + ', $scoreSQL);
                $query->selectRaw("negocios.*, ({$scoreExpression}) as relevancia", $bindings)
                    ->orderBy('relevancia', 'DESC');
            }
        }

        $negocios = $query->with(['categoriaPrincipal', 'categorias', 'sucursales' => function($q) {
                $q->where('activo', 1)->with(['estado', 'ciudad']);
            }])
            ->orderBy('prioridad_cache', 'DESC')
            ->paginate($request->input('por_pagina', 100));

        $negocios->getCollection()->transform(function ($negocio) {
            // Ocultar el campo de scoring interno del response
            unset($negocio->relevancia);
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
            'slug' => 'required|string',
            'id_estado' => 'sometimes|nullable|integer',
            'id_ciudad' => 'sometimes|nullable|integer',
            'por_pagina' => 'sometimes|integer|min:1|max:100',
            'buscar' => 'sometimes|nullable|string',
            'page' => 'sometimes|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $slug_cat = $request->slug;
        $id_estado = $request->id_estado;
        $id_ciudad = $request->id_ciudad;
        $page = $request->page ?? 1;
        $buscar = $request->buscar ?? '';
        $por_pagina = $request->input('por_pagina', 100);

        $cacheKey = "search_cat_{$slug_cat}_e{$id_estado}_c{$id_ciudad}_b{$buscar}_pp{$por_pagina}_p{$page}";

        $data = Cache::remember($cacheKey, 1800, function() use ($request, $slug_cat, $id_estado, $id_ciudad, $por_pagina) {
            $categoria = Categoria::where('slug', $slug_cat)->where('activo', 1)->first();

            if (!$categoria) {
                return null;
            }

            $id_categoria = $categoria->id;

            // Obtener subcategorías
            $subcategorias = Categoria::where('id_padre', $id_categoria)
                ->where('activo', 1)
                ->get();

            $ids_categorias = $subcategorias->pluck('id')->toArray();
            array_push($ids_categorias, $id_categoria);

            $query = Negocio::where('activo', 1)
                ->where('estatus', 'publicado')
                ->where('estatus_verificacion', 'verificado')
                ->visibilidadJerarquica($id_estado, $id_ciudad);

            // Búsqueda por palabra clave si existe (misma lógica de scoring que buscarNegocios)
            if ($request->filled('buscar')) {
                $busqueda = $request->buscar;
                $busqueda_limpia = $this->normalizarTexto($busqueda);
                $palabras = array_slice(array_filter(explode(' ', $busqueda_limpia)), 0, 5);

                if (!empty($palabras)) {
                    $query->where(function ($q) use ($busqueda, $busqueda_limpia, $palabras) {
                        $busqueda_sql_frase = str_replace(' ', '%', $busqueda_limpia);
                        $q->where('nombre', 'LIKE', "%{$busqueda}%")
                            ->orWhere('palabras_clave_normalizadas', 'LIKE', "%{$busqueda_sql_frase}%")
                            ->orWhere('descripcion', 'LIKE', "%{$busqueda}%")
                            ->orWhere('slogan', 'LIKE', "%{$busqueda}%");

                        foreach ($palabras as $palabra) {
                            if (mb_strlen($palabra) >= 3) {
                                $q->orWhere('nombre', 'LIKE', "%{$palabra}%")
                                    ->orWhere('palabras_clave_normalizadas', 'LIKE', "%{$palabra}%")
                                    ->orWhere('descripcion', 'LIKE', "%{$palabra}%")
                                    ->orWhere('slogan', 'LIKE', "%{$palabra}%");
                            }
                        }
                    });

                    // Scoring de relevancia
                    $scoreSQL = [];
                    $bindings = [];

                    $scoreSQL[] = "(CASE WHEN nombre LIKE ? THEN 10 ELSE 0 END)";
                    $bindings[] = "%{$busqueda}%";

                    $busqueda_sql_frase = str_replace(' ', '%', $busqueda_limpia);
                    $scoreSQL[] = "(CASE WHEN palabras_clave_normalizadas LIKE ? THEN 8 ELSE 0 END)";
                    $bindings[] = "%{$busqueda_sql_frase}%";

                    foreach ($palabras as $palabra) {
                        if (mb_strlen($palabra) >= 3) {
                            $scoreSQL[] = "(CASE WHEN nombre LIKE ? THEN 5 ELSE 0 END)";
                            $bindings[] = "%{$palabra}%";
                            $scoreSQL[] = "(CASE WHEN palabras_clave_normalizadas LIKE ? THEN 3 ELSE 0 END)";
                            $bindings[] = "%{$palabra}%";
                            $scoreSQL[] = "(CASE WHEN slogan LIKE ? THEN 2 ELSE 0 END)";
                            $bindings[] = "%{$palabra}%";
                            $scoreSQL[] = "(CASE WHEN descripcion LIKE ? THEN 1 ELSE 0 END)";
                            $bindings[] = "%{$palabra}%";
                        }
                    }

                    $scoreExpression = implode(' + ', $scoreSQL);
                    $query->selectRaw("negocios.*, ({$scoreExpression}) as relevancia", $bindings)
                        ->orderBy('relevancia', 'DESC');
                }
            }

            // Filtrar por categorías
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
                ->paginate($por_pagina);

            $negocios->getCollection()->transform(function ($negocio) {
                unset($negocio->relevancia);
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

            return [
                'categoria' => $categoria,
                'subcategorias' => $subcategorias,
                'negocios' => $negocios,
            ];
        });

        if (!$data) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        return response()->json(['data' => $data], 200)
            ->setPublic()
            ->setMaxAge(300); // 5 minutos para búsquedas por categoría
    }

    public function encontrarNegocio(Request $request, $slug)
    {
        $negocio = Cache::remember("negocio_perfil_{$slug}", 3600, function() use ($slug) {
            $n = Negocio::where('slug', $slug)
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

            if ($n) {
                foreach ($n->sucursales as $sucursal) {
                    if ($sucursal->visibilidad_direccion !== 'completa') {
                        $sucursal->direccion_texto = null;
                        $sucursal->codigo_postal = null;
                        $sucursal->lat = null;
                        $sucursal->lng = null;
                    }
                }
                // Renombrar items a productos para el front-end
                $n->setRelation('productos', $n->items);
                $n->unsetRelation('items');
            }
            return $n;
        });

        if (!$negocio) {
            return response()->json(['message' => 'Negocio no encontrado'], 404);
        }

        // Incrementar vistas (Fuera del caché)
        // Usamos query builder para no ensuciar el objeto cacheado ni disparar eventos innecesarios
        DB::table('negocios')->where('id', $negocio->id)->increment('total_vistas');

        return response()->json(['data' => $negocio], 200)
            ->setPublic()
            ->setMaxAge(600); // 10 minutos para el perfil de negocio en el navegador
    }

    public function listarEmpleos(Request $request)
    {
        $id_estado = $request->id_estado;
        $id_ciudad = $request->id_ciudad;

        $query = \App\Models\OfertaEmpleo::where('activo', 1)
            ->where('estatus', 'activo')
            ->where(function($q) {
                $q->whereNull('expira_en')
                  ->orWhere('expira_en', '>',Carbon::now());
            })
            ->visibilidadJerarquica($id_estado, $id_ciudad)
            ->with(['negocio', 'estado', 'ciudad'])
            ->orderBy('created_at', 'DESC');

        $empleos = $query->paginate($request->por_pagina ?? 15);

        return response()->json(['data' => $empleos], 200);
    }
}
