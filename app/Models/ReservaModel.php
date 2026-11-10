<?php

namespace App\Models;

use CodeIgniter\Model;

class ReservaModel extends Model
{
    protected $table          = 'reserva';
    protected $primaryKey     = 'idReserva';
    protected $allowedFields  = [
        'idPersona', 
        'fechaReserva', 
        'fechaExpira', 
        'estado', 
        'requiereReceta',
        'fechaCompletado'
    ];

    /**
     * Reservas con los datos del cliente y sus medicamentos.
     * @param array|null $estados Filtra por estado (ej. ['Pendiente']); null = todas.
     */
    public function listarConDetalle(?array $estados = null): array
    {
        $builder = $this->db->table('reserva')
            ->select('reserva.*, persona.nombre, persona.apellido, persona.ci, persona.telefono, persona.correo')
            ->join('persona', 'persona.idPersona = reserva.idPersona');

        if (!empty($estados)) {
            $builder->whereIn('reserva.estado', $estados);
        }

        $reservas = $builder->orderBy('reserva.fechaReserva', 'DESC')->get()->getResultArray();
        if (empty($reservas)) {
            return [];
        }

        $detalles = $this->db->table('detalle_reserva')
            ->select('detalle_reserva.*, medicamento.nombreComercial, medicamento.nombreGenerico, medicamento.imagen')
            ->join('medicamento', 'medicamento.idMedicamento = detalle_reserva.idMedicamento')
            ->whereIn('detalle_reserva.idReserva', array_column($reservas, 'idReserva'))
            ->get()->getResultArray();

        $porReserva = [];
        foreach ($detalles as $d) {
            $porReserva[$d['idReserva']][] = $d;
        }

        foreach ($reservas as &$r) {
            $r['items'] = $porReserva[$r['idReserva']] ?? [];
            $r['total'] = 0;
            foreach ($r['items'] as $it) {
                $r['total'] += $it['cantidad'] * $it['precio'];
            }
        }
        unset($r);

        return $reservas;
    }

    /** Cancela una reserva pendiente y devuelve el stock reservado al inventario. */
    public function cancelarRestituyendo(int $idReserva): bool
    {
        $reserva = $this->where('idReserva', $idReserva)->where('estado', 'Pendiente')->first();
        if (!$reserva) {
            return false;
        }

        $this->db->transBegin();
        try {
            $detalles = $this->db->table('detalle_reserva')->where('idReserva', $idReserva)->get()->getResultArray();
            foreach ($detalles as $d) {
                $this->db->table('medicamento')
                    ->set('stockActual', 'stockActual + ' . (int) $d['cantidad'], false)
                    ->where('idMedicamento', $d['idMedicamento'])
                    ->update();
            }
            $this->db->table('reserva')->where('idReserva', $idReserva)->update(['estado' => 'Cancelada']);
            $this->db->transCommit();
            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return false;
        }
    }
}