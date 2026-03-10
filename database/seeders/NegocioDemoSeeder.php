<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Negocio;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * Seeder de prueba: genera negocios ficticios para probar paginación e infinite scroll.
 * 
 * Ejecutar:   php artisan db:seed --class=NegocioDemoSeeder
 * Revertir:   php artisan db:seed --class=NegocioDemoSeeder -- --rollback
 *             (o simplemente: DELETE FROM negocios WHERE slug LIKE 'demo-%')
 */
class NegocioDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Si se pasa --rollback, eliminamos los demos y salimos
        if (in_array('--rollback', $_SERVER['argv'] ?? [])) {
            $count = DB::table('sucursales')
                ->whereIn('id_negocio', Negocio::where('slug', 'LIKE', 'demo-%')->pluck('id'))
                ->delete();
            $deleted = Negocio::where('slug', 'LIKE', 'demo-%')->forceDelete();
            $this->command->info("🗑️  Eliminados {$deleted} negocios demo y {$count} sucursales.");
            return;
        }

        // Datos realistas por categoría (id_categoria => datos[])
        $negociosPorCategoria = [
            // 8: Belleza y Cuidado Personal
            8 => [
                ['Glamour Studio', 'Salón de belleza premium con servicios de corte, color y tratamientos capilares', 'Tu mejor versión empieza aquí', 'salon belleza corte cabello tinte peinado', 'estilista maquillaje uñas tratamiento capilar keratina'],
                ['Nails & Lashes MX', 'Especialistas en uñas acrílicas, gel y extensiones de pestañas', 'Detalles que marcan la diferencia', 'uñas acrilicas gel pestañas', 'manicure pedicure nail art extensiones'],
                ['Barbería El Patrón', 'Barbería clásica y moderna para caballeros exigentes', 'Estilo que define', 'barberia corte barba', 'barbero fade degradado rasurado'],
            ],
            // 11: Cafetería y Postres
            11 => [
                ['La Dulce Esquina', 'Cafetería artesanal con postres caseros y café de especialidad', 'Endulzamos tu día', 'cafeteria postres pasteles cafe', 'reposteria galletas brownies cheesecake latte'],
                ['Café Arábica', 'Café de especialidad con granos seleccionados de Chiapas y Oaxaca', 'El mejor café, siempre', 'cafe especialidad capuchino', 'espresso americano latte mocha granos'],
                ['Sweet Dreams Bakery', 'Pastelería creativa con diseños personalizados para toda ocasión', 'Hacemos tus sueños dulces realidad', 'pasteleria pasteles cupcakes', 'pastel cumpleaños bodas fondant betun'],
                ['Boba House', 'Bubble tea y bebidas asiáticas con sabores únicos en México', 'Prueba algo diferente', 'bubble tea boba te', 'te perlas tapioca matcha taro'],
            ],
            // 12: Comidas y Bebidas
            12 => [
                ['Taquería Don Beto', 'Los mejores tacos al pastor, bistec y suadero de la ciudad', 'Tradición en cada taco', 'taqueria tacos comida mexicana', 'pastor bistec suadero quesadillas gringas'],
                ['Sushi Sakura', 'Cocina japonesa fusión con ingredientes frescos del día', 'Sabor que trasciende', 'sushi comida japonesa rolls', 'sashimi nigiri tempura ramen miso'],
                ['Pizza Nostra', 'Pizzería artesanal con horno de leña y masa madre', 'La pizza como debe ser', 'pizzeria pizza italiana', 'pizza horno leña margarita pepperoni hawaiana'],
                ['Mariscos El Rey', 'Pescados y mariscos frescos estilo Sinaloa', 'Del mar a tu mesa', 'mariscos pescado ceviche', 'camaron pulpo aguachile coctel tostada'],
                ['Hamburguesas Mammoth', 'Hamburguesas gourmet con carne Angus y pan brioche artesanal', 'Tamaño descomunal, sabor colosal', 'hamburguesas comida rapida', 'burger angus papas malteada alitas'],
            ],
            // 20: Ropa y Accesorios
            20 => [
                ['Moda Urbana MX', 'Tienda de streetwear y moda urbana con las últimas tendencias', 'Tu estilo, tu regla', 'ropa moda streetwear urbana', 'playeras sudaderas sneakers gorras jeans'],
                ['Boutique Isabella', 'Moda femenina elegante para toda ocasión', 'Elegancia en cada prenda', 'ropa mujer vestidos boutique', 'vestido blusa falda bolsa accesorios'],
                ['Calzado Rústico', 'Calzado artesanal en piel genuina hecho a mano', 'Camina con estilo', 'zapatos calzado piel botas', 'botas huaraches sandalias cinturon piel'],
            ],
            // 22: Servicios Profesionales
            22 => [
                ['Contadores Express', 'Servicios contables, fiscales y de nómina para PyMEs', 'Tu tranquilidad fiscal', 'contador contabilidad impuestos', 'sat declaraciones facturacion nomina contable'],
                ['LegalPro Abogados', 'Bufete de abogados especializados en derecho corporativo y familiar', 'Tu mejor defensa', 'abogado legal derecho', 'abogado divorcio herencia mercantil laboral'],
                ['CleanPro Servicios', 'Limpieza profunda residencial y comercial con productos ecológicos', 'Espacios impecables', 'limpieza hogar oficina', 'limpieza profunda desinfeccion sanitizacion'],
                ['Mudanzas Veloz', 'Servicio de mudanzas locales y foráneas con seguro incluido', 'Tu mudanza sin estrés', 'mudanzas fletes transporte', 'mudanza embalaje flete local foranea'],
                ['Plomería Hidráulica Total', 'Plomero certificado para instalaciones y reparaciones', 'Soluciones que fluyen', 'plomero plomeria agua', 'plomero fuga tuberia tinaco bomba instalacion'],
            ],
            // 23: Tecnología y Marketing
            23 => [
                ['PixelCode Studio', 'Desarrollo de páginas web, apps y tiendas en línea', 'Tu idea, nuestro código', 'paginas web desarrollo software', 'sitios web aplicaciones ecommerce tienda en linea programacion'],
                ['SocialBoost MX', 'Agencia de marketing digital y manejo de redes sociales', 'Hacemos crecer tu marca', 'marketing digital redes sociales', 'community manager publicidad facebook instagram google ads'],
                ['TechFix Pro', 'Reparación de computadoras, laptops y equipos de cómputo', 'Tecnología que funciona', 'reparacion computadoras laptop', 'computadora formateo virus pantalla teclado disco duro'],
                ['Imprenta Digital Express', 'Impresión digital, lonas, volantes y material publicitario', 'Tu imagen impresa', 'imprenta impresion lonas', 'volantes tarjetas presentacion banner lona vinil'],
                ['CyberSeguro MX', 'Consultoría en ciberseguridad y protección de datos', 'Protege tu negocio digital', 'ciberseguridad seguridad informatica', 'seguridad datos hacking etico firewall proteccion'],
            ],
            // 15: Fiestas y Eventos
            15 => [
                ['Globos y Fantasía', 'Decoración con globos, arcos y centros de mesa para todo evento', 'Creamos la magia', 'decoracion globos fiestas', 'arco globos centros mesa decoracion evento quinceañera'],
                ['DJ Noche Eterna', 'DJ profesional con equipo de audio e iluminación para eventos', 'La fiesta no para', 'dj musica eventos sonido', 'dj audio iluminacion boda quinceañera fiesta karaoke'],
                ['Banquetes Sabor Real', 'Servicio de banquetes y catering para bodas y eventos corporativos', 'Sabor que impresiona', 'banquetes catering comida eventos', 'banquete boda evento corporativo meseros buffet'],
                ['Foto & Video Moments', 'Fotografía y video profesional para bodas, XV años y eventos', 'Capturamos tus momentos', 'fotografia video bodas', 'fotografo video boda xv años sesion fotos drone'],
            ],
            // 17: Mascotas
            17 => [
                ['PetShop Huellitas', 'Tienda de mascotas con alimento premium, accesorios y juguetes', 'Todo para tu peludo', 'mascotas tienda alimento perro gato', 'croquetas alimento juguetes correas camas accesorios'],
                ['Veterinaria San Francisco', 'Clínica veterinaria con servicio 24 horas y cirugía especializada', 'Cuidamos a quien más quieres', 'veterinaria vacunas cirugia', 'veterinario consulta vacuna desparasitacion esterilizacion'],
                ['Dog Spa & Grooming', 'Estética canina con baño, corte y spa para tu mascota', 'Consentimos a tu mejor amigo', 'estetica canina baño perro', 'grooming baño corte spa perro gato mascota'],
            ],
            // 14: Educación
            14 => [
                ['English Now Academy', 'Cursos de inglés para niños, jóvenes y adultos con metodología dinámica', 'Speak English, live global', 'ingles cursos idiomas clases', 'ingles curso academia idioma toefl conversacion'],
                ['MateFácil Tutorías', 'Clases particulares de matemáticas y ciencias para todos los niveles', 'Aprende sin complicaciones', 'clases matematicas tutorias', 'matematicas fisica quimica algebra calculo tutorias'],
                ['Danza Libre Studio', 'Academia de danza contemporánea, ballet y hip hop', 'Mueve tu mundo', 'danza baile academia clases', 'ballet contemporaneo hip hop jazz danza clase'],
            ],
            // 19: Reparación y Mantenimiento
            19 => [
                ['Taller Mecánico Rodríguez', 'Servicio mecánico automotriz general y especializado', 'Tu auto en las mejores manos', 'mecanico taller auto carro', 'afinacion frenos suspension motor transmision aceite'],
                ['ElectroFix', 'Reparación de electrodomésticos: lavadoras, refrigeradores y más', 'Reparamos todo', 'reparacion electrodomesticos lavadora', 'lavadora refrigerador microondas estufa reparacion'],
                ['Cerrajería 24 Horas', 'Servicio de cerrajería de emergencia a domicilio', 'Siempre disponibles', 'cerrajero llaves cerraduras', 'cerrajeria apertura chapa llave copia duplicado'],
            ],
            // 13: Deportes
            13 => [
                ['GymFit Total', 'Gimnasio con equipo de última generación y clases grupales', 'Transforma tu cuerpo', 'gimnasio gym fitness ejercicio', 'gym pesas cardio crossfit spinning zumba'],
                ['Yoga Zen Space', 'Estudio de yoga y meditación para todos los niveles', 'Encuentra tu equilibrio', 'yoga meditacion bienestar', 'yoga pilates meditacion mindfulness flexibilidad'],
            ],
        ];

        $id_usuario = 5; // NED
        // Todos en León, Guanajuato para que aparezcan en pruebas locales
        $id_estado_gto = 11;
        $id_ciudad_leon = 345;
        $lat_leon = 21.1250;
        $lng_leon = -101.6860;
        $prioridades = [1, 1, 1, 2, 2, 3, 5]; // Distribución: mayoría baja
        $totalCreados = 0;

        foreach ($negociosPorCategoria as $id_categoria => $negocios) {
            foreach ($negocios as $data) {
                [$nombre, $descripcion, $slogan, $palabras_clave, $palabras_clave_normalizadas] = $data;

                $slug = 'demo-' . Str::slug($nombre);
                $counter = 1;
                $baseSlug = $slug;
                while (Negocio::where('slug', $slug)->exists()) {
                    $slug = $baseSlug . '-' . $counter++;
                }

                $negocio = Negocio::create([
                    'id_usuario' => $id_usuario,
                    'id_categoria_principal' => $id_categoria,
                    'slug' => $slug,
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'slogan' => $slogan,
                    'palabras_clave' => $palabras_clave,
                    'palabras_clave_normalizadas' => $palabras_clave_normalizadas,
                    'estatus' => 'publicado',
                    'estatus_verificacion' => 'verificado',
                    'alcance_visibilidad' => 'pais',
                    'prioridad_cache' => $prioridades[array_rand($prioridades)],
                    'telefono' => '55' . rand(10000000, 99999999),
                    'whatsapp' => '52' . rand(1000000000, 9999999999),
                    'activo' => 1,
                    'total_vistas' => rand(0, 500),
                    'total_items' => 0,
                    'total_sucursales' => 1,
                    'total_imagenes' => 0,
                    'total_ofertas_empleo' => 0,
                ]);

                // Crear sucursal principal
                DB::table('sucursales')->insert([
                    'id_negocio' => $negocio->id,
                    'id_estado' => $id_estado_gto,
                    'id_ciudad' => $id_ciudad_leon,
                    'direccion_texto' => 'Blvd. Demo #' . rand(100, 999) . ', Col. Centro, León, Gto.',
                    'visibilidad_direccion' => 'completa',
                    'codigo_postal' => '37' . str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT),
                    'es_principal' => 1,
                    'lat' => $lat_leon + (rand(-100, 100) / 10000),
                    'lng' => $lng_leon + (rand(-100, 100) / 10000),
                    'activo' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $totalCreados++;
            }
        }

        $this->command->info("✅ Creados {$totalCreados} negocios demo con sucursales. (slug: demo-*)");
        $this->command->info("💡 Para eliminarlos: php artisan db:seed --class=NegocioDemoSeeder -- --rollback");
    }


}
