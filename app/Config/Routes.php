<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->post('admin/clientes/eliminar', 'AdminController::eliminarCliente');
$routes->post('farmacia/ventas/cobrar', 'FarmaciaController::cobrar');

$routes->get('perfil',            'PerfilController::index');
$routes->post('perfil/guardar',   'PerfilController::guardar');
$routes->post('perfil/eliminar',  'PerfilController::eliminar');
// ----- Administrador (rol 3) -----
$routes->get('admin/medicamentos',                 'AdminMedicamentoController::index');
$routes->get('admin/medicamentos/nuevo',           'AdminMedicamentoController::nuevo');
$routes->post('admin/medicamentos/guardar',        'AdminMedicamentoController::guardar');
$routes->post('admin/medicamentos/eliminar/(:num)','AdminMedicamentoController::eliminar/$1');
$routes->post('admin/medicamentos/stock',          'AdminMedicamentoController::agregarStock');
$routes->post('admin/categorias/guardar',          'AdminMedicamentoController::guardarCategoria');
$routes->get('admin/clientes',                     'AdminController::clientes');
$routes->get('admin/pedidos',                      'AdminController::pedidos');
 
// ----- Farmacéutico (rol 2) -----
$routes->get('farmacia/pedidos',                   'FarmaciaController::pedidos');
$routes->post('farmacia/pedidos/completar/(:num)', 'FarmaciaController::completar/$1');
$routes->post('farmacia/pedidos/cancelar/(:num)',  'FarmaciaController::cancelar/$1');
$routes->get('farmacia/ventas',                    'FarmaciaController::ventas');
$routes->post('farmacia/ventas/vender',            'FarmaciaController::vender');
 
// ----- Reportes (administrador y farmacéutico) -----
$routes->get('reportes',          'ReporteController::index');
$routes->get('reportes/exportar', 'ReporteController::exportar');
 
// ----- Lotes: dar de baja el stock vencido (administrador) -----
$routes->post('admin/medicamentos/baja-vencidos', 'AdminMedicamentoController::bajaVencidos');
 
// ----- Historial de ventas en mostrador (administrador y farmacéutico) -----
$routes->get('farmacia/ventas/historial', 'FarmaciaController::historial');
 
// ----- Cambiar el rol de un usuario (administrador) -----
$routes->post('admin/clientes/rol', 'AdminController::cambiarRol');

// ----- Vencidos (administrador y farmacéutico) -----
$routes->get('vencidos', 'VencidoController::index');

// Ruta del Catálogo Público 
$routes->get('/', 'CatalogoController::index');
$routes->get('catalogo', 'CatalogoController::index');
$routes->get('cliente/catalogo', 'CatalogoController::index');

// Rutas de Autenticación y Registro
$routes->get('login', 'AuthController::login');
$routes->post('login/autenticar', 'AuthController::autenticar');
$routes->get('registro', 'AuthController::registro');
$routes->post('registro/guardar', 'AuthController::guardarRegistro');
$routes->get('logout', 'AuthController::logout');

// Rutas de Reservas para Clientes
$routes->post('reserva/registrar', 'ReservaController::registrar');
$routes->get('mis-reservas', 'ReservaController::misReservas');
$routes->get('reserva/cancelar/(:num)', 'ReservaController::cancelar/$1');