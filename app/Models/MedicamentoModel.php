<?php

namespace App\Models;

use CodeIgniter\Model;

class MedicamentoModel extends Model
{
    protected $table          = 'medicamento';
    protected $primaryKey     = 'idMedicamento';
    protected $allowedFields  = [
        'idCategoria', 
        'nombreComercial', 
        'nombreGenerico', 
        'laboratorio',
        'descripcion',
        'imagen',
        'indicaciones', 
        'efectosSecundarios', 
        'viaAdministrativa', 
        'dosis', 
        'recetaMedica', 
        'precioUnitario', 
        'stockActual', 
        'rop', 
        'eoq', 
        'estado'
    ];
}