<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Lotes de ingreso de stock (número de lote + fecha de vencimiento).
 *
 * El stock total de cada medicamento sigue siendo medicamento.stockActual.
 * Las unidades que quedan de cada lote se calculan asumiendo FEFO
 * (primero en vencer, primero en salir): lo que está en existencia pertenece
 * a los lotes que vencen más tarde; los que vencen antes se consumen primero.
 */
class LoteModel extends Model
{
    protected $table         = 'lote';
    protected $primaryKey    = 'idLote';
    protected $allowedFields = [
        'idMedicamento',
        'numeroLote',
        'fechaVencimiento',
        'cantidad',
        'fechaIngreso',
        'idPersona'
    ];

    /** Lotes de medicamentos activos con la clave 'restante' (unidades estimadas que aún quedan) */
    public function conRestante(?array $idsMedicamento = null): array
    {
        $b = $this->db->table('lote')
            ->select('lote.*, medicamento.nombreComercial, medicamento.nombreGenerico, medicamento.stockActual, medicamento.precioUnitario, categoria.nombreCategoria')
            ->join('medicamento', 'medicamento.idMedicamento = lote.idMedicamento')
            ->join('categoria', 'categoria.idCategoria = medicamento.idCategoria')
            ->where('medicamento.estado', 'activo');

        if (!empty($idsMedicamento)) {
            $b->whereIn('lote.idMedicamento', $idsMedicamento);
        }

        $filas = $b->orderBy('lote.idMedicamento', 'ASC')
                   ->orderBy('lote.fechaVencimiento', 'DESC')
                   ->orderBy('lote.idLote', 'DESC')
                   ->get()->getResultArray();

        // Lotes ya dados de baja por vencimiento: no vuelven a contar (evita bajas repetidas)
        $bajados = [];
        if (!empty($filas)) {
            $rows = $this->db->table('baja_vencido')->select('idLote')
                        ->whereIn('idLote', array_column($filas, 'idLote'))->get()->getResultArray();
            foreach ($rows as $r) {
                $bajados[$r['idLote']] = true;
            }
        }

        $porAsignar = [];
        foreach ($filas as &$l) {
            $id = $l['idMedicamento'];
            if (!isset($porAsignar[$id])) {
                $porAsignar[$id] = max(0, (int) $l['stockActual']);
            }
            if (isset($bajados[$l['idLote']])) {
                $l['restante'] = 0;
                continue;
            }
            $asignar = min((int) $l['cantidad'], $porAsignar[$id]);
            $l['restante'] = $asignar;
            $porAsignar[$id] -= $asignar;
        }
        unset($l);

        return $filas;
    }

    /** [idMedicamento => ['fecha' => 'Y-m-d', 'lote' => '...']] con el vencimiento más próximo que aún tiene unidades */
    public function proximoVencimiento(?array $lotes = null): array
    {
        $lotes = $lotes ?? $this->conRestante();
        $mapa = [];
        foreach ($lotes as $l) {
            if ($l['restante'] <= 0) continue;
            $id = $l['idMedicamento'];
            if (!isset($mapa[$id]) || $l['fechaVencimiento'] < $mapa[$id]['fecha']) {
                $mapa[$id] = ['fecha' => $l['fechaVencimiento'], 'lote' => $l['numeroLote']];
            }
        }
        return $mapa;
    }

    /** Lotes con unidades que vencen en los próximos $dias días (aún se pueden vender) */
    public function proximosAVencer(int $dias = 30): array
    {
        $hoy    = date('Y-m-d');
        $limite = date('Y-m-d', strtotime("+{$dias} days"));
        $res    = [];
        foreach ($this->conRestante() as $l) {
            if ($l['restante'] > 0 && $l['fechaVencimiento'] > $hoy && $l['fechaVencimiento'] <= $limite) {
                $l['dias'] = (int) floor((strtotime($l['fechaVencimiento']) - strtotime($hoy)) / 86400);
                $res[] = $l;
            }
        }
        usort($res, fn($a, $b) => strcmp($a['fechaVencimiento'], $b['fechaVencimiento']));
        return $res;
    }

    /** Unidades (estimadas) en lotes ya vencidos de un medicamento */
    public function unidadesVencidas(int $idMedicamento): int
    {
        $hoy = date('Y-m-d');
        $total = 0;
        foreach ($this->conRestante([$idMedicamento]) as $l) {
            if ($l['fechaVencimiento'] <= $hoy) {
                $total += $l['restante'];
            }
        }
        return $total;
    }
}