<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ReservaModel;
use App\Models\MedicamentoModel;
use App\Models\CategoriaModel;
use App\Models\LoteModel;

class FarmaciaController extends BaseController
{
    /* ---------- Pedidos de clientes ---------- */

    public function pedidos()
    {
        if ($r = $this->requiereRol([2, 3])) return $r;

        $reservas = (new ReservaModel())->listarConDetalle();
        // Pendientes primero (las que vencen antes arriba); luego el resto, más recientes primero
        usort($reservas, function ($a, $b) {
            $pa = $a['estado'] === 'Pendiente' ? 0 : 1;
            $pb = $b['estado'] === 'Pendiente' ? 0 : 1;
            if ($pa !== $pb) return $pa <=> $pb;
            return $pa === 0 ? strcmp($a['fechaExpira'], $b['fechaExpira']) : strcmp($b['fechaReserva'], $a['fechaReserva']);
        });

        return view('farmacia/pedidos', [
            'titulo'   => 'Pedidos de clientes',
            'activo'   => 'pedidos',
            'reservas' => $reservas,
        ]);
    }

    /**
     * El cliente recogió el pedido: pasa de Pendiente a Completado.
     * El stock NO se descuenta de nuevo: ya se descontó al reservar.
     */
    public function completar($idReserva)
    {
        if ($r = $this->requiereRol([2, 3])) return $r;

        $modelo = new ReservaModel();
        $reserva = $modelo->where('idReserva', $idReserva)->where('estado', 'Pendiente')->first();

        if (!$reserva) {
            return redirect()->to($this->destino())->with('error', 'El pedido no existe o ya no está pendiente.');
        }

        $modelo->update($idReserva, ['estado' => 'Completado', 'fechaCompletado' => date('Y-m-d H:i:s')]);

        return redirect()->to($this->destino())->with('mensaje', "Pedido #{$idReserva} marcado como completado.");
    }

    /** Cancela (o da por vencido) un pedido pendiente y devuelve el stock al inventario */
    public function cancelar($idReserva)
    {
        if ($r = $this->requiereRol([2, 3])) return $r;

        if ((new ReservaModel())->cancelarRestituyendo((int) $idReserva)) {
            return redirect()->to($this->destino())->with('mensaje', "Pedido #{$idReserva} cancelado. El stock volvió al inventario.");
        }
        return redirect()->to($this->destino())->with('error', 'No se pudo cancelar: el pedido no existe o ya no está pendiente.');
    }

    /* ---------- Venta en mostrador ---------- */

    public function ventas()
    {
        (new \App\Models\BajaVencidoModel())->procesarVencidos();
        if ($r = $this->requiereRol([2, 3])) return $r;

        $data['medicamentos'] = (new MedicamentoModel())
            ->select('medicamento.*, categoria.nombreCategoria')
            ->join('categoria', 'categoria.idCategoria = medicamento.idCategoria')
            ->where('medicamento.estado', 'activo')
            ->orderBy('medicamento.nombreComercial', 'ASC')
            ->findAll();
        $data['categorias'] = (new CategoriaModel())->orderBy('nombreCategoria', 'ASC')->findAll();
        $data['vencimientos'] = (new LoteModel())->proximoVencimiento();
        $data['titulo'] = 'Venta en mostrador';
        $data['activo'] = 'ventas';

        return view('farmacia/ventas', $data);
    }

    /** Descuenta del inventario lo vendido en tienda (respuesta JSON para la vista) */
    public function vender()
    {
        if (!session()->get('logged_in') || !in_array((int) session()->get('idRol'), [2, 3], true)) {
            return $this->json(false, 'No tiene permisos para vender.', 403);
        }

        $id       = filter_var($this->request->getPost('idMedicamento'), FILTER_VALIDATE_INT);
        $cantidad = filter_var($this->request->getPost('cantidad'), FILTER_VALIDATE_INT);
        if (!$id || !$cantidad || $cantidad < 1) {
            return $this->json(false, 'Cantidad no válida.', 422);
        }

        $modelo = new MedicamentoModel();
        $med = $modelo->where('idMedicamento', $id)->where('estado', 'activo')->first();
        if (!$med) {
            return $this->json(false, 'El medicamento no existe.', 404);
        }
        if ($med['stockActual'] < $cantidad) {
            return $this->json(false, "Stock insuficiente de {$med['nombreComercial']} (disponible: {$med['stockActual']}).", 422, $med['stockActual']);
        }

        // Descuento atómico + registro de la venta en una sola transacción
        $db = \Config\Database::connect();
        $db->transBegin();

        $db->table('medicamento')
           ->set('stockActual', 'stockActual - ' . $cantidad, false)
           ->where('idMedicamento', $id)
           ->where('stockActual >=', $cantidad)
           ->update();
        $descontado = $db->affectedRows() > 0; // se consulta justo después del UPDATE

        if (!$descontado) {
            $db->transRollback();
            return $this->json(false, 'El stock cambió, intente nuevamente.', 409, $modelo->find($id)['stockActual']);
        }

        $db->table('venta')->insert([
            'idPersona'     => session()->get('idPersona'),
            'idMedicamento' => $id,
            'cantidad'      => $cantidad,
            'precio'        => $med['precioUnitario'],
            'fechaVenta'    => date('Y-m-d H:i:s'),
        ]);

        if ($db->transStatus() === false) {
            $db->transRollback();
            return $this->json(false, 'No se pudo registrar la venta.', 500, $med['stockActual']);
        }
        $db->transCommit();

        $actual = $modelo->find($id)['stockActual'];

        return $this->json(true, "Venta registrada: {$cantidad} x {$med['nombreComercial']}.", 200, $actual);
    }

    /**
     * Venta en mostrador con carrito: varios medicamentos en una sola operación.
     * Recibe items = JSON [{"id":1,"cantidad":2}, ...]. Todo o nada (una transacción).
     */
    public function cobrar()
    {
        if (!session()->get('logged_in') || !in_array((int) session()->get('idRol'), [2, 3], true)) {
            return $this->json(false, 'No tiene permisos para vender.', 403);
        }

        // Lo vencido se da de baja antes de validar, para que no se pueda vender
        (new \App\Models\BajaVencidoModel())->procesarVencidos();

        $lista = json_decode((string) $this->request->getPost('items'), true);
        $items = [];
        if (is_array($lista)) {
            foreach ($lista as $it) {
                $id   = filter_var($it['id'] ?? null, FILTER_VALIDATE_INT);
                $cant = filter_var($it['cantidad'] ?? null, FILTER_VALIDATE_INT);
                if (!$id || !$cant || $cant < 1) { $items = []; break; }
                $items[$id] = ($items[$id] ?? 0) + $cant;
            }
        }
        if (empty($items)) {
            return $this->json(false, 'La venta está vacía o tiene cantidades no válidas.', 422);
        }

        // 1) Validar existencia y stock sin escribir nada
        $modelo = new MedicamentoModel();
        $meds = [];
        foreach ($items as $id => $cant) {
            $med = $modelo->where('idMedicamento', $id)->where('estado', 'activo')->first();
            if (!$med) {
                return $this->json(false, 'Uno de los medicamentos ya no está disponible (inactivo o vencido). Recargue la página.', 422);
            }
            if ((int) $med['stockActual'] < $cant) {
                return $this->json(false, "Stock insuficiente de {$med['nombreComercial']} (disponible: {$med['stockActual']}).", 422, [$id => (int) $med['stockActual']]);
            }
            $meds[$id] = $med;
        }

        // 2) Descontar y registrar todo en una sola transacción
        $db    = \Config\Database::connect();
        $ahora = date('Y-m-d H:i:s');
        $total = 0;
        $db->transBegin();
        try {
            foreach ($items as $id => $cant) {
                $db->table('medicamento')
                   ->set('stockActual', 'stockActual - ' . (int) $cant, false)
                   ->where('idMedicamento', $id)
                   ->where('stockActual >=', $cant)
                   ->update();
                if ($db->affectedRows() < 1) {
                    throw new \RuntimeException('El stock de ' . $meds[$id]['nombreComercial'] . ' cambió, intente nuevamente.');
                }
                $db->table('venta')->insert([
                    'idPersona'     => session()->get('idPersona'),
                    'idMedicamento' => $id,
                    'cantidad'      => $cant,
                    'precio'        => $meds[$id]['precioUnitario'],
                    'fechaVenta'    => $ahora,
                ]);
                $total += $cant * $meds[$id]['precioUnitario'];
            }
            if ($db->transStatus() === false) {
                throw new \RuntimeException('No se pudo registrar la venta.');
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            $msg = $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo registrar la venta.';
            return $this->json(false, $msg, 409);
        }

        $stocks = [];
        foreach ($modelo->select('idMedicamento, stockActual')->whereIn('idMedicamento', array_keys($items))->findAll() as $m) {
            $stocks[$m['idMedicamento']] = (int) $m['stockActual'];
        }

        return $this->json(true, 'Venta registrada: ' . count($items) . ' producto(s), total Bs. ' . number_format($total, 2) . '.', 200, $stocks);
    }

    /** Historial de ventas en mostrador (tabla venta) con filtro por período */
    public function historial()
    {
        if ($r = $this->requiereRol([2, 3])) return $r;

        [$periodo, $desde, $hasta] = $this->rango();

        $db  = \Config\Database::connect();
        $ini = $db->escape($desde . ' 00:00:00');
        $fin = $db->escape($hasta . ' 23:59:59');

        $ventas = $db->table('venta')
            ->select("venta.*, medicamento.nombreComercial, medicamento.nombreGenerico, categoria.nombreCategoria, CONCAT(COALESCE(persona.nombre, ''), ' ', COALESCE(persona.apellido, '')) AS vendedor", false)
            ->join('medicamento', 'medicamento.idMedicamento = venta.idMedicamento')
            ->join('categoria', 'categoria.idCategoria = medicamento.idCategoria')
            ->join('persona', 'persona.idPersona = venta.idPersona', 'left')
            ->where("venta.fechaVenta >= $ini AND venta.fechaVenta <= $fin", null, false)
            ->orderBy('venta.fechaVenta', 'DESC')
            ->get()->getResultArray();

        $total = 0; $unidades = 0;
        foreach ($ventas as $v) {
            $total    += $v['cantidad'] * $v['precio'];
            $unidades += $v['cantidad'];
        }

        return view('farmacia/historial', [
            'titulo'   => 'Historial de ventas',
            'activo'   => 'historial',
            'ventas'   => $ventas,
            'total'    => $total,
            'unidades' => $unidades,
            'periodo'  => $periodo,
            'desde'    => $desde,
            'hasta'    => $hasta,
        ]);
    }

    /** Período del historial: hoy (por defecto), 7 días, mes, mes anterior o rango personalizado */
    private function rango(): array
    {
        $periodo = $this->request->getGet('periodo') ?: 'hoy';
        $hoy = date('Y-m-d');
        $valida = function ($v) {
            $d = \DateTime::createFromFormat('Y-m-d', (string) $v);
            return ($d && $d->format('Y-m-d') === $v) ? $v : null;
        };

        switch ($periodo) {
            case 'semana':
                return [$periodo, date('Y-m-d', strtotime('-6 days')), $hoy];
            case 'mes':
                return [$periodo, date('Y-m-01'), $hoy];
            case 'mes_anterior':
                $ref = strtotime('first day of last month');
                return [$periodo, date('Y-m-01', $ref), date('Y-m-t', $ref)];
            case 'personalizado':
                $d = $valida($this->request->getGet('desde')) ?? $hoy;
                $h = $valida($this->request->getGet('hasta')) ?? $hoy;
                return $d > $h ? [$periodo, $h, $d] : [$periodo, $d, $h];
            default:
                return ['hoy', $hoy, $hoy];
        }
    }

    /** A dónde volver tras aceptar/rechazar un pedido (solo páginas permitidas) */
    private function destino(): string
    {
        return $this->request->getPost('volver') === '/admin/pedidos' ? '/admin/pedidos' : '/farmacia/pedidos';
    }

    private function json(bool $ok, string $mensaje, int $status, $stock = null)
    {
        return $this->response->setStatusCode($status)->setJSON([
            'ok'      => $ok,
            'mensaje' => $mensaje,
            'stock'   => $stock,
            'csrf'    => csrf_hash(),
        ]);
    }
}