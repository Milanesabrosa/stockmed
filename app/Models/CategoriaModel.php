<?php

namespace App\Models;

use CodeIgniter\Model;

class CategoriaModel extends Model
{
    protected $table         = 'categoria';
    protected $primaryKey    = 'idCategoria';
    protected $allowedFields = [
        'nombreCategoria',
        'descripcion'
    ];
}