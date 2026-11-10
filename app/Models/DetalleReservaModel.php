<?php

namespace App\Models;

use CodeIgniter\Model;

class DetalleReservaModel extends Model
{
    protected $table          = 'detalle_reserva';
    protected $primaryKey     = 'idDetalleReserva';
    protected $allowedFields  = [
        'idReserva', 
        'idMedicamento', 
        'cantidad', 
        'precio'
    ];
}