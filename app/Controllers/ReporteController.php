<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\MedicamentoModel;
use App\Models\LoteModel;
use App\Models\BajaVencidoModel;

/**
 * Reportes para Administrador y Farmacéutico: ventas, más vendidos, agotados,
 * sin movimiento, por vencer, vencidos e inventario. Se ven en pantalla, se imprimen /
 * guardan como PDF desde el navegador y se exportan a CSV (Excel).
 *
 * Cada reporte devuelve la misma estructura:
 *   ['titulo', 'subtitulo', 'resumen' => [[etiqueta, celda]], 'secciones' => [[titulo, nota, columnas, filas, vacio]]]
 * Una celda puede ser texto/número, ['t' => texto, 'sub' => subtexto, 'b' => ok|bajo|aviso],
 * ['n' => número, 'd' => decimales, 'pre' => prefijo] o ['form' => [...]] (solo en pantalla).
 */
class ReporteController extends BaseController
{
    private const TIPOS = [
        'ventas'        => 'Ventas de medicamentos',
        'masvendidos'   => 'Más vendidos',
        'agotados'      => 'Agotados',
        'sinmovimiento' => 'Sin movimiento',
        'porvencer'     => 'Por vencer (próximos)',
        'vencidos'      => 'Productos vencidos (pérdidas)',
        'inventario'    => 'Inventario actual',
    ];

    public function index()
    {
        if ($r = $this->requiereRol([2, 3])) return $r;

        $f = $this->filtros();
        $rep = $this->construir($f, (int) session()->get('idRol') === 3);

        return view('reportes/index', $f + [
            'titulo'      => 'Reportes',
            'activo'      => 'reportes',
            'tipos'       => self::TIPOS,
            'rep'         => $rep,
            'query'       => http_build_query(['tipo' => $f['tipo'], 'periodo' => $f['periodo'], 'desde' => $f['desde'], 'hasta' => $f['hasta'], 'dias' => $f['dias']]),
            'generadoPor' => trim(session()->get('nombre') . ' ' . session()->get('apellido')),
            'generadoEn'  => date('d/m/Y H:i'),
        ]);
    }

    /** CSV con ";" como separador y UTF-8 con BOM: se abre directo en Excel */
    public function exportar()
    {
        if ($r = $this->requiereRol([2, 3])) return $r;

        $f = $this->filtros();
        $rep = $this->construir($f, false);

        $filas = [['StockMed - ' . $rep['titulo']]];
        if (!empty($rep['subtitulo'])) $filas[] = [$rep['subtitulo']];
        $filas[] = ['Generado', date('d/m/Y H:i'), 'Por', trim(session()->get('nombre') . ' ' . session()->get('apellido'))];
        $filas[] = [];

        $filas[] = ['RESUMEN'];
        foreach ($rep['resumen'] as [$etiqueta, $valor]) {
            $filas[] = [$etiqueta, $this->csv($valor)];
        }
        $filas[] = [];

        foreach ($rep['secciones'] as $sec) {
            $filas[] = [mb_strtoupper($sec['titulo'])];
            if (empty($sec['filas'])) {
                $filas[] = [$sec['vacio'] ?? 'Sin datos'];
            } else {
                $filas[] = $sec['columnas'];
                foreach ($sec['filas'] as $fila) {
                    $filas[] = array_map([$this, 'csv'], $fila);
                }
            }
            $filas[] = [];
        }

        $fh = fopen('php://temp', 'r+');
        foreach ($filas as $fila) {
            fputcsv($fh, $fila, ';', '"', '');
        }
        rewind($fh);
        $contenido = stream_get_contents($fh);
        fclose($fh);

        $sufijo = in_array($f['tipo'], ['ventas', 'masvendidos', 'vencidos'], true) ? "{$f['desde']}_a_{$f['hasta']}" : date('Y-m-d');

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="reporte_' . $f['tipo'] . '_' . $sufijo . '.csv"')
            ->setBody("\xEF\xBB\xBF" . $contenido);
    }

    /* ---------------- Filtros y utilidades ---------------- */

    private function filtros(): array
    {
        $tipo = $this->request->getGet('tipo');
        $tipo = isset(self::TIPOS[$tipo]) ? $tipo : 'ventas';

        $periodo = $this->request->getGet('periodo') ?: 'mes';
        $hoy = date('Y-m-d');

        switch ($periodo) {
            case 'hoy':
                $desde = $hasta = $hoy;
                break;
            case 'semana':
                $desde = date('Y-m-d', strtotime('-6 days'));
                $hasta = $hoy;
                break;
            case 'mes_anterior':
                $ref   = strtotime('first day of last month');
                $desde = date('Y-m-01', $ref);
                $hasta = date('Y-m-t', $ref);
                break;
            case 'personalizado':
                $desde = $this->fecha($this->request->getGet('desde')) ?? date('Y-m-01');
                $hasta = $this->fecha($this->request->getGet('hasta')) ?? $hoy;
                if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];
                break;
            default:
                $periodo = 'mes';
                $desde   = date('Y-m-01');
                $hasta   = $hoy;
        }

        $dias = (int) $this->request->getGet('dias');
        if (!in_array($dias, [30, 60, 90, 180], true)) {
            $dias = $tipo === 'porvencer' ? 90 : 30;
        }

        return compact('tipo', 'periodo', 'desde', 'hasta', 'dias');
    }

    private function fecha($valor): ?string
    {
        $d = \DateTime::createFromFormat('Y-m-d', (string) $valor);
        return ($d && $d->format('Y-m-d') === $valor) ? $valor : null;
    }

    private function csv($c)
    {
        if (is_array($c)) {
            if (isset($c['n'])) return number_format((float) $c['n'], $c['d'] ?? 0, ',', '');
            return $c['t'] ?? '';
        }
        return $c;
    }

    private function n($valor, int $dec = 0, string $pre = ''): array
    {
        return ['n' => $valor, 'd' => $dec, 'pre' => $pre];
    }

    private function nombre(array $m): array
    {
        return ['t' => $m['nombreComercial'], 'sub' => $m['nombreGenerico']];
    }

    private function dmy(?string $f): string
    {
        return $f ? date('d/m/Y', strtotime($f)) : '-';
    }

    private function construir(array $f, bool $acciones): array
    {
        switch ($f['tipo']) {
            case 'masvendidos':   return $this->repMasVendidos($f);
            case 'agotados':      return $this->repAgotados();
            case 'sinmovimiento': return $this->repSinMovimiento($f['dias']);
            case 'porvencer':     return $this->repPorVencer($f['dias'], $acciones);
            case 'vencidos':      return (new BajaVencidoModel())->reporteVencidos($f['desde'], $f['hasta']);
            case 'inventario':    return $this->repInventario();
            default:              return $this->repVentas($f);
        }
    }

    private function periodoTexto(array $f): string
    {
        return 'Período: del ' . $this->dmy($f['desde']) . ' al ' . $this->dmy($f['hasta']);
    }

    /* ---------------- Datos base ---------------- */

    /** Ventas (mostrador + pedidos completados) del período, con totales y agrupaciones */
    private function movimientos(string $desde, string $hasta): array
    {
        $db  = \Config\Database::connect();
        $ini = $db->escape($desde . ' 00:00:00');
        $fin = $db->escape($hasta . ' 23:59:59');

        $mostrador = $db->table('venta')
            ->select("venta.fechaVenta AS fecha, 'Mostrador' AS tipo, 0 AS idPedido, venta.idMedicamento, medicamento.nombreComercial, medicamento.nombreGenerico, categoria.nombreCategoria, venta.cantidad, venta.precio, CONCAT(COALESCE(persona.nombre, ''), ' ', COALESCE(persona.apellido, '')) AS responsable", false)
            ->join('medicamento', 'medicamento.idMedicamento = venta.idMedicamento')
            ->join('categoria', 'categoria.idCategoria = medicamento.idCategoria')
            ->join('persona', 'persona.idPersona = venta.idPersona', 'left')
            ->where("venta.fechaVenta >= $ini AND venta.fechaVenta <= $fin", null, false)
            ->get()->getResultArray();

        $fechaPedido = 'COALESCE(reserva.fechaCompletado, reserva.fechaReserva)';
        $pedidos = $db->table('detalle_reserva')
            ->select("$fechaPedido AS fecha, 'Pedido' AS tipo, reserva.idReserva AS idPedido, detalle_reserva.idMedicamento, medicamento.nombreComercial, medicamento.nombreGenerico, categoria.nombreCategoria, detalle_reserva.cantidad, detalle_reserva.precio, CONCAT(persona.nombre, ' ', persona.apellido) AS responsable", false)
            ->join('reserva', 'reserva.idReserva = detalle_reserva.idReserva')
            ->join('medicamento', 'medicamento.idMedicamento = detalle_reserva.idMedicamento')
            ->join('categoria', 'categoria.idCategoria = medicamento.idCategoria')
            ->join('persona', 'persona.idPersona = reserva.idPersona')
            ->where('reserva.estado', 'Completado')
            ->where("$fechaPedido >= $ini AND $fechaPedido <= $fin", null, false)
            ->get()->getResultArray();

        $detalle = array_merge($mostrador, $pedidos);
        usort($detalle, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));

        $resumen = ['ingresos' => 0, 'unidades' => 0, 'ventasMostrador' => count($mostrador), 'pedidosCompletados' => count(array_unique(array_column($pedidos, 'idPedido')))];
        $porMed = [];
        $porCat = [];

        foreach ($detalle as &$d) {
            $d['subtotal'] = $d['cantidad'] * $d['precio'];
            $resumen['ingresos'] += $d['subtotal'];
            $resumen['unidades'] += $d['cantidad'];

            $k = $d['idMedicamento'];
            if (!isset($porMed[$k])) {
                $porMed[$k] = ['nombreComercial' => $d['nombreComercial'], 'nombreGenerico' => $d['nombreGenerico'], 'nombreCategoria' => $d['nombreCategoria'],
                               'uMostrador' => 0, 'uPedidos' => 0, 'unidades' => 0, 'ingresos' => 0];
            }
            $porMed[$k][$d['tipo'] === 'Mostrador' ? 'uMostrador' : 'uPedidos'] += $d['cantidad'];
            $porMed[$k]['unidades'] += $d['cantidad'];
            $porMed[$k]['ingresos'] += $d['subtotal'];

            $c = $d['nombreCategoria'];
            if (!isset($porCat[$c])) {
                $porCat[$c] = ['nombreCategoria' => $c, 'unidades' => 0, 'ingresos' => 0];
            }
            $porCat[$c]['unidades'] += $d['cantidad'];
            $porCat[$c]['ingresos'] += $d['subtotal'];
        }
        unset($d);

        usort($porMed, fn($a, $b) => $b['ingresos'] <=> $a['ingresos']);
        usort($porCat, fn($a, $b) => $b['ingresos'] <=> $a['ingresos']);

        return ['resumen' => $resumen, 'porMedicamento' => $porMed, 'porCategoria' => $porCat, 'detalle' => $detalle];
    }

    /** [idMedicamento => fecha de la última salida] (venta en mostrador o pedido no cancelado) */
    private function ultimaSalida(): array
    {
        $db = \Config\Database::connect();
        $mapa = [];

        foreach ($db->table('venta')->select('idMedicamento, MAX(fechaVenta) AS f')->groupBy('idMedicamento')->get()->getResultArray() as $r) {
            $mapa[$r['idMedicamento']] = $r['f'];
        }
        $pedidos = $db->table('detalle_reserva')
            ->select('detalle_reserva.idMedicamento, MAX(reserva.fechaReserva) AS f')
            ->join('reserva', 'reserva.idReserva = detalle_reserva.idReserva')
            ->where('reserva.estado !=', 'Cancelada')
            ->groupBy('detalle_reserva.idMedicamento')
            ->get()->getResultArray();
        foreach ($pedidos as $r) {
            if (!isset($mapa[$r['idMedicamento']]) || $r['f'] > $mapa[$r['idMedicamento']]) {
                $mapa[$r['idMedicamento']] = $r['f'];
            }
        }
        return $mapa;
    }

    private function medicamentosActivos(): array
    {
        return (new MedicamentoModel())
            ->select('medicamento.*, categoria.nombreCategoria')
            ->join('categoria', 'categoria.idCategoria = medicamento.idCategoria')
            ->where('medicamento.estado', 'activo')
            ->orderBy('medicamento.nombreComercial', 'ASC')
            ->findAll();
    }

    /* ---------------- Reportes ---------------- */

    private function repVentas(array $f): array
    {
        $m = $this->movimientos($f['desde'], $f['hasta']);
        $rs = $m['resumen'];

        $porMed = array_map(fn($x) => [
            $this->nombre($x), $x['nombreCategoria'], $this->n($x['uMostrador']), $this->n($x['uPedidos']), $this->n($x['unidades']), $this->n($x['ingresos'], 2)
        ], $m['porMedicamento']);

        $porCat = array_map(fn($x) => [$x['nombreCategoria'], $this->n($x['unidades']), $this->n($x['ingresos'], 2)], $m['porCategoria']);

        $detalle = array_map(fn($d) => [
            $d['fecha'], ['t' => $d['tipo'], 'b' => $d['tipo'] === 'Pedido' ? 'ok' : ''], $d['nombreComercial'], $this->n($d['cantidad']),
            $this->n($d['precio'], 2), $this->n($d['subtotal'], 2), trim($d['responsable'])
        ], $m['detalle']);

        return [
            'titulo'    => 'Reporte de ventas',
            'subtitulo' => $this->periodoTexto($f),
            'resumen'   => [
                ['Ingresos', $this->n($rs['ingresos'], 2, 'Bs. ')],
                ['Unidades vendidas', $this->n($rs['unidades'])],
                ['Ventas en mostrador', $this->n($rs['ventasMostrador'])],
                ['Pedidos completados', $this->n($rs['pedidosCompletados'])],
            ],
            'secciones' => [
                ['titulo' => 'Ventas por medicamento', 'columnas' => ['Medicamento', 'Categoría', 'Mostrador', 'Pedidos', 'Unidades', 'Ingresos (Bs)'], 'filas' => $porMed, 'vacio' => 'No hay ventas registradas en este período.'],
                ['titulo' => 'Ventas por categoría', 'columnas' => ['Categoría', 'Unidades', 'Ingresos (Bs)'], 'filas' => $porCat, 'vacio' => 'No hay ventas registradas en este período.'],
                ['titulo' => 'Detalle de movimientos', 'columnas' => ['Fecha', 'Tipo', 'Medicamento', 'Cant.', 'Precio (Bs)', 'Subtotal (Bs)', 'Cliente / Vendedor'], 'filas' => $detalle, 'vacio' => 'No hay ventas registradas en este período.'],
            ],
        ];
    }

    private function repMasVendidos(array $f): array
    {
        $m = $this->movimientos($f['desde'], $f['hasta']);
        $lista = $m['porMedicamento'];
        usort($lista, fn($a, $b) => [$b['unidades'], $b['ingresos']] <=> [$a['unidades'], $a['ingresos']]);
        $total = max(1, $m['resumen']['unidades']);

        $filas = [];
        foreach (array_slice($lista, 0, 20) as $i => $x) {
            $filas[] = [$this->n($i + 1), $this->nombre($x), $x['nombreCategoria'], $this->n($x['uMostrador']), $this->n($x['uPedidos']),
                        $this->n($x['unidades']), $this->n($x['ingresos'], 2), $this->n($x['unidades'] * 100 / $total, 1) ];
        }

        return [
            'titulo'    => 'Medicamentos más vendidos',
            'subtitulo' => $this->periodoTexto($f),
            'resumen'   => [
                ['Más vendido', $lista ? $lista[0]['nombreComercial'] : '-'],
                ['Unidades vendidas', $this->n($m['resumen']['unidades'])],
                ['Ingresos', $this->n($m['resumen']['ingresos'], 2, 'Bs. ')],
                ['Medicamentos con ventas', $this->n(count($lista))],
            ],
            'secciones' => [[
                'titulo'   => 'Ranking por unidades vendidas (máx. 20)',
                'columnas' => ['#', 'Medicamento', 'Categoría', 'Mostrador', 'Pedidos', 'Unidades', 'Ingresos (Bs)', '% de unidades'],
                'filas'    => $filas,
                'vacio'    => 'No hay ventas registradas en este período.',
            ]],
        ];
    }

    private function repAgotados(): array
    {
        $todos = $this->medicamentosActivos();
        $ultima = $this->ultimaSalida();

        $filas = [];
        foreach ($todos as $m) {
            if ($m['stockActual'] > 0) continue;
            $filas[] = [
                $this->nombre($m), $m['nombreCategoria'], $this->n($m['precioUnitario'], 2), $this->n($m['rop']), $this->n($m['eoq']),
                isset($ultima[$m['idMedicamento']]) ? $this->dmy($ultima[$m['idMedicamento']]) : 'Sin ventas'
            ];
        }

        return [
            'titulo'    => 'Medicamentos agotados',
            'subtitulo' => 'Situación al ' . date('d/m/Y'),
            'resumen'   => [
                ['Medicamentos agotados', $this->n(count($filas))],
                ['Medicamentos activos', $this->n(count($todos))],
            ],
            'secciones' => [[
                'titulo'   => 'Agotados (stock en 0)',
                'nota'     => 'Se sugiere reponer el lote económico (EOQ). El ingreso se registra con lote y vencimiento desde el inventario.',
                'columnas' => ['Medicamento', 'Categoría', 'Precio (Bs)', 'ROP', 'EOQ sugerido', 'Última salida'],
                'filas'    => $filas,
                'vacio'    => 'No hay medicamentos agotados.',
            ]],
        ];
    }

    private function repSinMovimiento(int $dias): array
    {
        $limite = date('Y-m-d', strtotime("-{$dias} days"));
        $ultima = $this->ultimaSalida();

        $lista = [];
        foreach ($this->medicamentosActivos() as $m) {
            if ($m['stockActual'] <= 0) continue;
            $u = $ultima[$m['idMedicamento']] ?? null;
            if ($u !== null && substr($u, 0, 10) >= $limite) continue;
            $m['ultima'] = $u;
            $m['valor'] = $m['stockActual'] * $m['precioUnitario'];
            $lista[] = $m;
        }
        usort($lista, fn($a, $b) => $b['valor'] <=> $a['valor']);

        $filas = []; $unidades = 0; $valor = 0;
        foreach ($lista as $m) {
            $unidades += $m['stockActual']; $valor += $m['valor'];
            $filas[] = [
                $this->nombre($m), $m['nombreCategoria'], $this->n($m['stockActual']), $this->n($m['valor'], 2),
                $m['ultima'] ? $this->dmy($m['ultima']) : 'Sin ventas',
                $m['ultima'] ? $this->n((int) floor((time() - strtotime($m['ultima'])) / 86400)) : '-'
            ];
        }

        return [
            'titulo'    => 'Medicamentos sin movimiento',
            'subtitulo' => "Con stock y sin ventas ni pedidos en los últimos {$dias} días (al " . date('d/m/Y') . ')',
            'resumen'   => [
                ['Medicamentos sin movimiento', $this->n(count($lista))],
                ['Unidades inmovilizadas', $this->n($unidades)],
                ['Valor inmovilizado', $this->n($valor, 2, 'Bs. ')],
            ],
            'secciones' => [[
                'titulo'   => "Sin movimiento en {$dias} días",
                'nota'     => 'Los medicamentos recién registrados también aparecen hasta que tengan su primera venta.',
                'columnas' => ['Medicamento', 'Categoría', 'Stock', 'Valor en stock (Bs)', 'Última salida', 'Días sin movimiento'],
                'filas'    => $filas,
                'vacio'    => 'Todos los medicamentos con stock tuvieron movimiento en este período.',
            ]],
        ];
    }

    /** Lotes que vencen en los próximos N días y siguen a la venta (lo ya vencido está en el reporte "Productos vencidos") */
    private function repPorVencer(int $dias, bool $acciones): array
    {
        (new BajaVencidoModel())->procesarVencidos();   // lo vencido se retira solo del inventario

        $hoy  = date('Y-m-d');
        $tope = date('Y-m-d', strtotime("+{$dias} days"));

        $lotes = [];
        foreach ((new LoteModel())->conRestante() as $l) {
            if ($l['restante'] > 0 && $l['fechaVencimiento'] > $hoy && $l['fechaVencimiento'] <= $tope) {
                $lotes[] = $l;
            }
        }
        usort($lotes, fn($a, $b) => strcmp($a['fechaVencimiento'], $b['fechaVencimiento']));

        $unidades = array_sum(array_column($lotes, 'restante'));
        $valor = 0;
        $filas = [];
        foreach ($lotes as $l) {
            $valor += $l['restante'] * $l['precioUnitario'];
            $d = (int) floor((strtotime($l['fechaVencimiento']) - strtotime($hoy)) / 86400);
            $estado = ['t' => $d === 1 ? 'Vence mañana' : "Vence en {$d} d", 'b' => $d <= 7 ? 'bajo' : 'aviso'];
            $filas[] = [
                $this->nombre($l), $l['nombreCategoria'], $l['numeroLote'], $this->dmy($l['fechaVencimiento']),
                $this->n($l['restante']), $this->n($l['restante'] * $l['precioUnitario'], 2), $estado
            ];
        }

        return [
            'titulo'    => 'Medicamentos por vencer',
            'subtitulo' => "Lotes que vencen en los próximos {$dias} días y siguen a la venta (al " . date('d/m/Y') . ')',
            'resumen'   => [
                ['Lotes por vencer', $this->n(count($lotes))],
                ['Unidades por vencer', $this->n($unidades)],
                ['Valor en riesgo', $this->n($valor, 2, 'Bs. ')],
            ],
            'secciones' => [[
                'titulo'   => "Lotes por vencer (próximos {$dias} días)",
                'nota'     => 'Los lotes que ya vencieron se retiran solos del inventario y aparecen en el reporte "Productos vencidos". Las unidades por lote son una estimación: se asume que se despacha primero lo que vence antes.',
                'columnas' => ['Medicamento', 'Categoría', 'Lote', 'Vencimiento', 'Unidades', 'Valor (Bs)', 'Estado'],
                'filas'    => $filas,
                'vacio'    => 'No hay lotes por vencer en este plazo.',
            ]],
        ];
    }

    private function repInventario(): array
    {
        $meds = $this->medicamentosActivos();
        usort($meds, fn($a, $b) => [$a['nombreCategoria'], $a['nombreComercial']] <=> [$b['nombreCategoria'], $b['nombreComercial']]);
        $venc = (new LoteModel())->proximoVencimiento();

        $unidades = 0; $valor = 0; $bajos = 0; $filas = [];
        foreach ($meds as $m) {
            $v = $m['stockActual'] * $m['precioUnitario'];
            $unidades += $m['stockActual']; $valor += $v;
            if ($m['stockActual'] <= $m['rop']) $bajos++;
            $estado = $m['stockActual'] <= 0 ? 'Agotado' : ($m['stockActual'] <= $m['rop'] ? 'Stock bajo' : 'Normal');
            $filas[] = [
                $this->nombre($m), $m['nombreCategoria'], $this->n($m['precioUnitario'], 2), $this->n($m['stockActual']), $this->n($m['rop']), $this->n($m['eoq']),
                $this->n($v, 2), isset($venc[$m['idMedicamento']]) ? $this->dmy($venc[$m['idMedicamento']]['fecha']) : 'Sin lote',
                ['t' => $estado, 'b' => $estado === 'Normal' ? 'ok' : 'bajo']
            ];
        }

        return [
            'titulo'    => 'Reporte de inventario',
            'subtitulo' => 'Situación al ' . date('d/m/Y'),
            'resumen'   => [
                ['Medicamentos activos', $this->n(count($meds))],
                ['Unidades en stock', $this->n($unidades)],
                ['Valor del inventario', $this->n($valor, 2, 'Bs. ')],
                ['Con stock bajo o agotado', $this->n($bajos)],
            ],
            'secciones' => [[
                'titulo'   => 'Inventario actual',
                'columnas' => ['Medicamento', 'Categoría', 'Precio (Bs)', 'Stock', 'ROP', 'EOQ', 'Valor (Bs)', 'Próx. vencimiento', 'Estado'],
                'filas'    => $filas,
                'vacio'    => 'No hay medicamentos registrados.',
            ]],
        ];
    }
}