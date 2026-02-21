<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;


//Rutas públicas
Route::post('admin/login', [AdminController::class, 'login']);

//Rutas cliente final


//Rutas Administradores

Route::group(['middleware' => 'jwt.admin'], function () {
    Route::post('admin/crear', [AdminController::class, 'crear']);
    Route::post('admin/actualizar', [AdminController::class, 'actualizar']);
    Route::post('admin/eliminar', [AdminController::class, 'eliminar']);
    Route::post('admin/listar', [AdminController::class, 'listar']);
    Route::post('admin/restaurar', [AdminController::class, 'restaurar']);
});

//Rutas compartidas

Route::group(['middleware' => 'jwt.auth'], function () {

});


//Rutas usuarios

Route::group(['middleware' => 'jwt.usuario'], function () {
    
});
