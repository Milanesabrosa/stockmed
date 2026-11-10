<?php

namespace App\Models;

use CodeIgniter\Model;

class PersonaModel extends Model
{
    protected $table          = 'persona';
    protected $primaryKey     = 'idPersona';
    protected $allowedFields  = [
        'idRol', 
        'nombre', 
        'apellido', 
        'ci', 
        'telefono', 
        'correo', 
        'contrasena', 
        'estado'
    ];

    /**
     * Elimina una cuenta de cliente sin perder el historial: cancela sus reservas pendientes
     * (el stock vuelve al inventario), libera el correo y deja la cuenta inactiva y sin acceso.
     */
    public function desactivarCuenta(int $id): void
    {
        $reservas = new ReservaModel();
        foreach ($reservas->where('idPersona', $id)->where('estado', 'Pendiente')->findAll() as $r) {
            $reservas->cancelarRestituyendo((int) $r['idReserva']);
        }

        $this->update($id, [
            'estado'     => 'inactivo',
            'correo'     => 'eliminada' . $id . '@stockmed.invalid',
            'contrasena' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        ]);
    }
}