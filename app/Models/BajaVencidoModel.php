<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Registro de unidades perdidas por vencimiento.
 * Al procesar: descuenta el stock vencido, guarda la pérdida y, si el
 * medicamento se queda sin unidades vigentes, lo pasa a 'inactivo'.
 */
class BajaVencidoModel extends Model
{
    protected $table         = 'baja_vencido';
    protected $primaryKey    = 'idBaja';
    protected $allowedFields = ['idLote', 'idMedicamento', 'cantidad', 'precioUnitario', 'fechaBaja', 'idPersona', 'inactivado'];

    /**
     * Da de baja el stock vencido (de todos los medicamentos o de uno).
     * @return array{unidades:int, medicamentos:int, inactivados:int}
     */
    public function procesarVencidos(?int $idMedicamento = null): array
    {
        $res  = ['unidades' => 0, 'medicamentos' => 0, 'inactivados' => 0];
        $hoy  = date('Y-m-d');
        $lotes = (new LoteModel())->conRestante($idMedicamento ? [$idMedicamento] : null);

        $porMed = [];
        foreach ($lotes as $l) {
            if ($l['fechaVencimiento'] <= $hoy && (int) $l['restante'] > 0) {
                $porMed[$l['idMedicamento']][] = $l;
            }
        }
        if (empty($porMed)) {
            return $res;
        }

        $idPersona = session()->get('idPersona');
        $ahora     = date('Y-m-d H:i:s');

        $this->db->transBegin();
        try {
            foreach ($porMed as $idMed => $lista) {
                $total = 0;
                foreach ($lista as $l) {
                    $total += (int) $l['restante'];
                    $this->db->table('baja_vencido')->insert([
                        'idLote'         => $l['idLote'],
                        'idMedicamento'  => $idMed,
                        'cantidad'       => (int) $l['restante'],
                        'precioUnitario' => $l['precioUnitario'],
                        'fechaBaja'      => $ahora,
                        'idPersona'      => $idPersona ?: null,
                        'inactivado'     => 0,
                    ]);
                }

                $this->db->table('medicamento')
                    ->set('stockActual', 'GREATEST(stockActual - ' . $total . ', 0)', false)
                    ->where('idMedicamento', $idMed)
                    ->update();

                $stock = (int) $this->db->table('medicamento')->select('stockActual')->where('idMedicamento', $idMed)->get()->getRow('stockActual');
                if ($stock <= 0) {
                    $this->db->table('medicamento')->where('idMedicamento', $idMed)->update(['estado' => 'inactivo']);
                    $this->db->table('baja_vencido')
                        ->where('idMedicamento', $idMed)->where('fechaBaja', $ahora)
                        ->update(['inactivado' => 1]);
                    $res['inactivados']++;
                }
                $res['unidades']     += $total;
                $res['medicamentos'] += 1;
            }
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return ['unidades' => 0, 'medicamentos' => 0, 'inactivados' => 0];
        }
        return $res;
    }

    /** Historial de pérdidas (más reciente primero) */
    public function historial(int $limite = 300): array
    {
        return $this->db->table('baja_vencido b')
            ->select('b.*, lote.numeroLote, lote.fechaVencimiento, medicamento.nombreComercial, medicamento.nombreGenerico, medicamento.estado, medicamento.eoq, categoria.nombreCategoria')
            ->join('lote', 'lote.idLote = b.idLote', 'left')
            ->join('medicamento', 'medicamento.idMedicamento = b.idMedicamento')
            ->join('categoria', 'categoria.idCategoria = medicamento.idCategoria', 'left')
            ->orderBy('b.fechaBaja', 'DESC')->orderBy('b.idBaja', 'DESC')
            ->limit($limite)->get()->getResultArray();
    }

    /** Unidades y valor (a precio de venta) perdidos en total */
    public function totales(): array
    {
        $r = $this->db->table('baja_vencido')
            ->select('COALESCE(SUM(cantidad),0) AS unidades, COALESCE(SUM(cantidad * precioUnitario),0) AS valor')
            ->get()->getRowArray();
        return ['unidades' => (int) $r['unidades'], 'valor' => (float) $r['valor']];
    }

    /** Medicamentos inactivos porque se vencieron (esperan un nuevo lote) */
    public function inactivosPorVencimiento(): array
    {
        return $this->db->table('medicamento')
            ->select('medicamento.*, categoria.nombreCategoria, (SELECT MAX(fechaBaja) FROM baja_vencido WHERE baja_vencido.idMedicamento = medicamento.idMedicamento) AS ultimaBaja')
            ->join('categoria', 'categoria.idCategoria = medicamento.idCategoria', 'left')
            ->where('medicamento.estado', 'inactivo')
            ->where('medicamento.idMedicamento IN (SELECT idMedicamento FROM baja_vencido WHERE inactivado = 1)', null, false)
            ->orderBy('medicamento.nombreComercial', 'ASC')
            ->get()->getResultArray();
    }

    public function estaInactivoPorVencimiento(int $idMedicamento): bool
    {
        return $this->db->table('baja_vencido')->where('idMedicamento', $idMedicamento)->where('inactivado', 1)->countAllResults() > 0
            && $this->db->table('medicamento')->where('idMedicamento', $idMedicamento)->where('estado', 'inactivo')->countAllResults() > 0;
    }

    /** Vuelve a activar el medicamento al registrar un nuevo lote */
    public function reactivar(int $idMedicamento): void
    {
        $this->db->table('medicamento')->where('idMedicamento', $idMedicamento)->update(['estado' => 'activo']);
        $this->db->table('baja_vencido')->where('idMedicamento', $idMedicamento)->update(['inactivado' => 0]);
    }

    /**
     * Reporte "Productos vencidos" con el mismo formato que usan los demás reportes
     * (titulo, subtitulo, resumen, secciones). $desde y $hasta en formato Y-m-d.
     */
    public function reporteVencidos(string $desde, string $hasta): array
    {
        $this->procesarVencidos();

        $filas = $this->db->table('baja_vencido b')
            ->select('b.*, lote.numeroLote, lote.fechaVencimiento, medicamento.nombreComercial, medicamento.nombreGenerico, categoria.nombreCategoria')
            ->join('lote', 'lote.idLote = b.idLote', 'left')
            ->join('medicamento', 'medicamento.idMedicamento = b.idMedicamento')
            ->join('categoria', 'categoria.idCategoria = medicamento.idCategoria', 'left')
            ->where('b.fechaBaja >=', $desde . ' 00:00:00')
            ->where('b.fechaBaja <=', $hasta . ' 23:59:59')
            ->orderBy('b.fechaBaja', 'DESC')->orderBy('b.idBaja', 'DESC')
            ->get()->getResultArray();

        $unidades = 0; $valor = 0.0; $porMed = [];
        $detalle = [];
        foreach ($filas as $f) {
            $v = $f['cantidad'] * $f['precioUnitario'];
            $unidades += $f['cantidad']; $valor += $v;

            $k = $f['idMedicamento'];
            if (!isset($porMed[$k])) {
                $porMed[$k] = ['nombre' => $f['nombreComercial'], 'gen' => $f['nombreGenerico'], 'cat' => $f['nombreCategoria'] ?? '', 'u' => 0, 'v' => 0.0];
            }
            $porMed[$k]['u'] += $f['cantidad']; $porMed[$k]['v'] += $v;

            $detalle[] = [
                date('d/m/Y H:i', strtotime($f['fechaBaja'])),
                ['t' => $f['nombreComercial'], 'sub' => $f['nombreGenerico']],
                $f['nombreCategoria'] ?? '',
                $f['numeroLote'] ?? '-',
                !empty($f['fechaVencimiento']) ? date('d/m/Y', strtotime($f['fechaVencimiento'])) : '-',
                ['n' => $f['cantidad']],
                ['n' => $v, 'pre' => 'Bs. ', 'd' => 2],
            ];
        }

        uasort($porMed, fn($a, $b) => $b['v'] <=> $a['v']);
        $resumenMed = [];
        foreach ($porMed as $m) {
            $resumenMed[] = [
                ['t' => $m['nombre'], 'sub' => $m['gen']],
                $m['cat'],
                ['n' => $m['u']],
                ['n' => $m['v'], 'pre' => 'Bs. ', 'd' => 2],
            ];
        }

        $reponer = [];
        foreach ($this->inactivosPorVencimiento() as $m) {
            $reponer[] = [
                ['t' => $m['nombreComercial'], 'sub' => $m['nombreGenerico']],
                $m['nombreCategoria'] ?? '',
                ['t' => 'Sin venta hasta nuevo lote', 'b' => 'bajo'],
            ];
        }

        return [
            'titulo'    => 'Productos vencidos',
            'subtitulo' => 'Del ' . date('d/m/Y', strtotime($desde)) . ' al ' . date('d/m/Y', strtotime($hasta)),
            'resumen'   => [
                ['Unidades perdidas',      ['n' => $unidades]],
                ['Valor perdido',          ['n' => $valor, 'pre' => 'Bs. ', 'd' => 2]],
                ['Lotes vencidos',         ['n' => count($filas)]],
                ['Medicamentos afectados', ['n' => count($porMed)]],
            ],
            'secciones' => [
                [
                    'titulo'   => 'Pérdida por medicamento',
                    'nota'     => 'Valor calculado con el precio de venta del medicamento al momento de la baja.',
                    'columnas' => ['Medicamento', 'Categoría', 'Unidades perdidas', 'Valor perdido'],
                    'filas'    => $resumenMed,
                    'vacio'    => 'No hubo pérdidas por vencimiento en este período.',
                ],
                [
                    'titulo'   => 'Detalle de lotes vencidos',
                    'columnas' => ['Retirado el', 'Medicamento', 'Categoría', 'Lote', 'Venció', 'Unidades', 'Valor perdido'],
                    'filas'    => $detalle,
                    'vacio'    => 'Sin lotes vencidos en este período.',
                ],
                [
                    'titulo'   => 'Medicamentos por reponer',
                    'nota'     => 'Se vencieron y ya no se venden. Registre un nuevo lote para reactivarlos.',
                    'columnas' => ['Medicamento', 'Categoría', 'Estado'],
                    'filas'    => $reponer,
                    'vacio'    => 'No hay medicamentos pendientes de reposición.',
                ],
            ],
        ];
    }
}