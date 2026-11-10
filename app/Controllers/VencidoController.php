<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\BajaVencidoModel;
use App\Models\LoteModel;

class VencidoController extends BaseController
{
    /** Vencimientos: por reponer, próximos a vencer y pérdidas */
    public function index()
    {
        if ($r = $this->requiereRol([2, 3])) return $r;

        $modelo = new BajaVencidoModel();
        $nuevo  = $modelo->procesarVencidos();   // retira de la venta lo que ya venció

        $data['recien']    = $nuevo;
        $data['totales']   = $modelo->totales();
        $data['porReponer'] = $modelo->inactivosPorVencimiento();
        $data['proximos']  = (new LoteModel())->proximosAVencer(30);
        $data['historial'] = $modelo->historial();
        $data['titulo']    = 'Vencimientos';
        $data['activo']    = 'vencidos';

        return view('vencidos/index', $data);
    }
}