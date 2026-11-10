<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\MedicamentoModel;
use App\Models\CategoriaModel;

class CatalogoController extends BaseController
{
    public function index()
    {
        (new \App\Models\BajaVencidoModel())->procesarVencidos();
        $medicamentoModel = new MedicamentoModel();
        
        // Obtenemos los medicamentos junto con el nombre de su categoría
        $data['medicamentos'] = $medicamentoModel->select('medicamento.*, categoria.nombreCategoria')
                                                ->join('categoria', 'categoria.idCategoria = medicamento.idCategoria')
                                                ->where('medicamento.estado', 'activo')
                                                ->findAll();

        // Todas las categorías (aunque aún no tengan medicamentos)
        $data['categorias'] = (new CategoriaModel())->orderBy('nombreCategoria', 'ASC')->findAll();

        return view('cliente/catalogo', $data);
    }
}