<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\PaginaClienteController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\AnuncianteController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\ImagenItemController;
use App\Http\Controllers\ImagenNegocioController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\NegocioController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MembresiaController;
use App\Http\Controllers\MensajeDiarioController;
use App\Http\Controllers\NegocioCategoriaController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\SucursalHorarioController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\BannerStatDiariaController;
use App\Http\Controllers\SolicitudArcoController;
use App\Http\Controllers\AuditoriaEliminacionController;
use App\Http\Controllers\OfertaEmpleoController;
use App\Http\Controllers\SolicitudSoporteController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\PlanPrecioController;
use App\Http\Controllers\KbArticleController;
use App\Http\Controllers\ChatAiController;

// Rutas públicas de la Página del Cliente
Route::get('home', [PaginaClienteController::class, 'mostrarHome']);
Route::post('obtener-ubicacion', [PaginaClienteController::class, 'obtenerUbicacion']);
Route::get('obtener-ciudades', [PaginaClienteController::class, 'obtenerCiudades']);
Route::get('buscar-negocios', [PaginaClienteController::class, 'buscarNegocios']);
Route::get('negocios-categoria', [PaginaClienteController::class, 'buscarNegociosPorCategoria']);
Route::get('negocio/{slug}', [PaginaClienteController::class, 'encontrarNegocio']);
Route::get('listar-empleos', [PaginaClienteController::class, 'listarEmpleos']);
Route::post('publicidad/contacto', [PaginaClienteController::class, 'contactoPublicidad'])->middleware('throttle:3,1');

//Rutas públicas
Route::post('admin/login', [AdminController::class, 'login']);
Route::post('usuario/login', [UsuarioController::class, 'login']);
Route::post('usuario/registro', [UsuarioController::class, 'registro']);
Route::post('usuario/verificar-correo', [UsuarioController::class, 'verificarCorreo']);
Route::post('usuario/recuperar-password', [UsuarioController::class, 'recuperarPassword']);
Route::post('reporte/crear', [ReporteController::class, 'crear']);
Route::post('banner/clic', [BannerStatDiariaController::class, 'registrarClic']);
Route::post('stripe/webhook', [StripeController::class, 'webhook']);
Route::post('chat', [ChatAiController::class, 'chat']);

//Rutas cliente final


//Rutas Administradores

Route::group(['middleware' => 'jwt.admin'], function () {
    Route::post('admin/crear', [AdminController::class, 'crear']);
    Route::post('admin/actualizar', [AdminController::class, 'actualizar']);
    Route::post('admin/eliminar', [AdminController::class, 'eliminar']);
    Route::get('admin/listar', [AdminController::class, 'listar']);
    Route::post('admin/restaurar', [AdminController::class, 'restaurar']);

    // Categorías
    Route::post('categoria/crear', [CategoriaController::class, 'crear']);
    Route::post('categoria/actualizar', [CategoriaController::class, 'actualizar']);
    Route::post('categoria/eliminar', [CategoriaController::class, 'eliminar']);
    Route::get('categoria/encontrar/{id}', [CategoriaController::class, 'encontrar']);
    Route::post('categoria/restaurar', [CategoriaController::class, 'restaurar']);

    // Anunciantes
    Route::post('anunciante/crear', [AnuncianteController::class, 'crear']);
    Route::post('anunciante/actualizar', [AnuncianteController::class, 'actualizar']);
    Route::post('anunciante/eliminar', [AnuncianteController::class, 'eliminar']);
    Route::get('anunciante/listar', [AnuncianteController::class, 'listar']);
    Route::get('anunciante/encontrar/{id}', [AnuncianteController::class, 'encontrar']);

    // Banners
    Route::post('banner/crear', [BannerController::class, 'crear']);
    Route::post('banner/actualizar', [BannerController::class, 'actualizar']);
    Route::post('banner/eliminar', [BannerController::class, 'eliminar']);
    Route::get('banner/listar', [BannerController::class, 'listar']);

    // Mensajes Diarios
    Route::post('mensaje/crear', [MensajeDiarioController::class, 'crear']);
    Route::post('mensaje/actualizar', [MensajeDiarioController::class, 'actualizar']);
    Route::post('mensaje/eliminar', [MensajeDiarioController::class, 'eliminar']);
    Route::get('mensaje/encontrar/{id}', [MensajeDiarioController::class, 'encontrar']);
    Route::get('mensaje/listar', [MensajeDiarioController::class, 'listar']);

    // Reportes de Usuarios y Negocios
    Route::get('reporte/listar', [ReporteController::class, 'listar']);
    Route::get('reporte/encontrar/{id}', [ReporteController::class, 'encontrar']);
    Route::post('reporte/actualizar', [ReporteController::class, 'actualizar']);

    // Solicitudes ARCO
    Route::get('arco/listar', [SolicitudArcoController::class, 'listar']);
    Route::get('arco/encontrar/{id}', [SolicitudArcoController::class, 'encontrar']);
    Route::post('arco/actualizar', [SolicitudArcoController::class, 'actualizar']);

    // Auditoría de Eliminaciones
    Route::get('auditoria/listar', [AuditoriaEliminacionController::class, 'listar']);
    Route::get('auditoria/encontrar/{id}', [AuditoriaEliminacionController::class, 'encontrar']);

    // Soporte
    Route::get('admin/soporte/listar', [SolicitudSoporteController::class, 'listar']);
    Route::get('admin/soporte/encontrar/{id}', [SolicitudSoporteController::class, 'encontrar']);
    Route::post('admin/soporte/actualizar', [SolicitudSoporteController::class, 'actualizar']);
    Route::post('admin/soporte/eliminar', [SolicitudSoporteController::class, 'eliminar']);

    // Ofertas de Empleo (Admin Global)
    Route::get('admin/oferta/listar', [OfertaEmpleoController::class, 'listar']);

    // Planes
    Route::post('plan/crear', [PlanController::class, 'crear']);
    Route::post('plan/actualizar', [PlanController::class, 'actualizar']);
    Route::post('plan/eliminar', [PlanController::class, 'eliminar']);
    Route::get('plan/listar', [PlanController::class, 'listar']);
    Route::get('plan/encontrar/{id}', [PlanController::class, 'encontrar']);
    Route::post('plan/restaurar', [PlanController::class, 'restaurar']);

    // Usuarios
    Route::post('usuario/crear', [UsuarioController::class, 'crear']);
    Route::post('usuario/actualizar', [UsuarioController::class, 'actualizar']);
    Route::post('usuario/eliminar', [UsuarioController::class, 'eliminar']);
    Route::get('usuario/listar', [UsuarioController::class, 'listar']);
    Route::get('usuario/encontrar/{id}', [UsuarioController::class, 'encontrar']);
    
    // Plan Precios
    Route::get('plan-precio/listar/{id_plan}', [PlanPrecioController::class, 'listarPorPlan']);
    Route::post('plan-precio/crear', [PlanPrecioController::class, 'crear']);
    Route::post('plan-precio/actualizar', [PlanPrecioController::class, 'actualizar']);
    Route::post('plan-precio/eliminar', [PlanPrecioController::class, 'eliminar']);

    // Negocios (Admin)
    Route::get('admin/negocio/listar', [NegocioController::class, 'listarAdmin']);
    Route::post('admin/negocio/verificar', [NegocioController::class, 'verificar']);
    Route::post('admin/negocio/eliminar', [NegocioController::class, 'eliminarAdmin']);

    // Knowledge Base (IA)
    Route::get('kb/listar', [KbArticleController::class, 'listar']);
    Route::post('kb/crear', [KbArticleController::class, 'crear']);
    Route::post('kb/actualizar', [KbArticleController::class, 'actualizar']);
    Route::post('kb/eliminar', [KbArticleController::class, 'eliminar']);
    Route::get('kb/encontrar/{id}', [KbArticleController::class, 'encontrar']);

});

//Rutas compartidas

Route::group(['middleware' => 'jwt.auth'], function () {
    // Imagenes de productos
    Route::post('producto/imagen/crear', [ImagenItemController::class, 'crear']);
    Route::post('producto/imagen/eliminar', [ImagenItemController::class, 'eliminar']);

    // Galeria de negocios
    Route::post('negocio/imagen/crear', [ImagenNegocioController::class, 'crear'])->middleware('check.limits:imagenes');
    Route::post('negocio/imagen/eliminar', [ImagenNegocioController::class, 'eliminar']);
    Route::get('negocio/imagen/listar/{id_negocio}/{usuario_id?}', [ImagenNegocioController::class, 'listar']);

    // Planes disponibles
    Route::get('planes/disponibles', [PlanController::class, 'listarActivos']);
    Route::get('usuario/perfil', [UsuarioController::class, 'perfil']);
    Route::post('usuario/actualizar', [UsuarioController::class, 'actualizar']);
    Route::get('categoria/listar', [CategoriaController::class, 'listar']);
    Route::post('usuario/cambiar-plan', [UsuarioController::class, 'cambiarPlan']);
    Route::post('usuario/renovar-plan', [UsuarioController::class, 'renovarPlan']);

    // Solicitudes ARCO (Usuario)
    Route::post('arco/crear', [SolicitudArcoController::class, 'crear']);
    Route::get('arco/mis-solicitudes', [SolicitudArcoController::class, 'misSolicitudes']);

    // Soporte Técnico (Usuario)
    Route::post('soporte/crear', [SolicitudSoporteController::class, 'crear']);
    Route::get('soporte/listar', [SolicitudSoporteController::class, 'listar']);
    Route::get('soporte/encontrar/{id}', [SolicitudSoporteController::class, 'encontrar']);
    Route::post('soporte/eliminar', [SolicitudSoporteController::class, 'eliminar']);

    // Membresías e Historial
    Route::get('membresia/listar/{usuario_id?}', [MembresiaController::class, 'listar']);
    Route::get('membresia/encontrar/{id}/{usuario_id?}', [MembresiaController::class, 'encontrar']);

    // Gestión de Negocios
    Route::get('negocio/listar/{usuario_id?}', [NegocioController::class, 'listar']);
    Route::post('negocio/crear', [NegocioController::class, 'crear'])->middleware('check.limits:negocios');
    Route::post('negocio/actualizar', [NegocioController::class, 'actualizar']);
    Route::post('negocio/cambiar-estatus', [NegocioController::class, 'cambiarEstatus']);
    Route::get('negocio/encontrar/{id}/{usuario_id?}', [NegocioController::class, 'encontrar']);
    Route::post('negocio/eliminar', [NegocioController::class, 'eliminar']);

    // Categorías Secundarias del Negocio
    Route::post('negocio/categoria/agregar', [NegocioCategoriaController::class, 'agregar']);
    Route::post('negocio/categoria/eliminar', [NegocioCategoriaController::class, 'eliminar']);

    // Gestión de Items (Productos/Servicios)
    Route::get('item/listar/{id_negocio}/{usuario_id?}', [ItemController::class, 'listar']);
    Route::get('item/encontrar/{id}/{usuario_id?}', [ItemController::class, 'encontrar']);
    Route::post('item/crear', [ItemController::class, 'crear'])->middleware('check.limits:items');
    Route::post('item/actualizar', [ItemController::class, 'actualizar']);
    Route::post('item/eliminar', [ItemController::class, 'eliminar']);

    // Sucursales
    Route::get('sucursal/listar/{id_negocio}/{usuario_id?}', [SucursalController::class, 'listar']);
    Route::get('sucursal/encontrar/{id}/{usuario_id?}', [SucursalController::class, 'encontrar']);
    Route::post('sucursal/crear', [SucursalController::class, 'crear'])->middleware('check.limits:sucursales');
    Route::post('sucursal/actualizar', [SucursalController::class, 'actualizar']);
    Route::post('sucursal/eliminar', [SucursalController::class, 'eliminar']);

    // Ofertas de Empleo
    Route::post('oferta/crear', [OfertaEmpleoController::class, 'crear'])->middleware('check.limits:empleos');
    Route::post('oferta/actualizar', [OfertaEmpleoController::class, 'actualizar']);
    Route::post('oferta/eliminar', [OfertaEmpleoController::class, 'eliminar']);

    // Horarios de Sucursales
    Route::get('sucursal/horario/listar/{id_sucursal}/{usuario_id?}', [SucursalHorarioController::class, 'listar']);
    Route::get('sucursal/horario/encontrar/{id}/{usuario_id?}', [SucursalHorarioController::class, 'encontrar']);
    Route::post('sucursal/horario/crear', [SucursalHorarioController::class, 'crear']);
    Route::post('sucursal/horario/actualizar', [SucursalHorarioController::class, 'actualizar']);
    Route::post('sucursal/horario/eliminar', [SucursalHorarioController::class, 'eliminar']);
    Route::post('sucursal/horario/sincronizar', [SucursalHorarioController::class, 'sincronizar']);

    // Auditoría (Ruta compartida para registrar el motivo al borrar)
    Route::post('auditoria/registrar', [AuditoriaEliminacionController::class, 'registrar']);

    // Stripe
    Route::post('stripe/crear-sesion-pago', [StripeController::class, 'crearSesionPago']);
});


//Rutas usuarios

Route::group(['middleware' => 'jwt.usuario'], function () {
    
});
