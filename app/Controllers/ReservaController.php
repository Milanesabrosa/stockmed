<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ReservaModel;
use App\Models\DetalleReservaModel;
use App\Models\MedicamentoModel;

class ReservaController extends BaseController
{
    /**
     * Registra UNA reserva con uno o varios medicamentos.
     * Recibe:
     *  - items: JSON [{"id":1,"cantidad":2}, ...]  (carrito del catálogo)
     *  - o bien idMedicamento (+ cantidad opcional) para un pedido simple.
     * Si la petición es AJAX responde JSON; si no, redirige.
     */
    public function registrar()
    {
        (new \App\Models\BajaVencidoModel())->procesarVencidos();
        if (!session()->get('logged_in')) {
            return $this->responder(false, 'Debe iniciar sesión para realizar una reserva.', '/login', 401);
        }

        $items = $this->leerItems();
        if (empty($items)) {
            return $this->responder(false, 'El pedido está vacío o tiene cantidades no válidas.', null, 422);
        }
        (new \App\Models\BajaVencidoModel())->procesarVencidos();
        $medicamentoModel = new MedicamentoModel();

        // 1) Validación previa de existencia y stock (sin escribir nada)
        $medicamentos = [];
        $requiereReceta = 0;
        foreach ($items as $idMed => $cantidad) {
            $med = $medicamentoModel->where('idMedicamento', $idMed)->where('estado', 'activo')->first();

            if (!$med) {
                return $this->responder(false, 'Uno de los medicamentos del pedido ya no está disponible.', null, 422);
            }
            if ($med['stockActual'] < $cantidad) {
                return $this->responder(
                    false,
                    'Stock insuficiente de ' . $med['nombreComercial'] . ' (disponible: ' . $med['stockActual'] . ').',
                    null,
                    422
                );
            }
            if ($med['recetaMedica'] == 1) {
                $requiereReceta = 1;
            }
            $medicamentos[$idMed] = $med;
        }

        // 2) Escritura en una sola transacción
        $db = \Config\Database::connect();
        $reservaModel = new ReservaModel();
        $detalleModel = new DetalleReservaModel();

        $db->transBegin();
        try {
            $idReserva = $reservaModel->insert([
                'idPersona'      => session()->get('idPersona'),
                'fechaReserva'   => date('Y-m-d H:i:s'),
                'fechaExpira'    => date('Y-m-d H:i:s', strtotime('+24 hours')),
                'estado'         => 'Pendiente',
                'requiereReceta' => $requiereReceta
            ], true);

            foreach ($items as $idMed => $cantidad) {
                // Descuento atómico: solo si aún alcanza el stock (RF-02)
                $db->table('medicamento')
                   ->set('stockActual', 'stockActual - ' . (int) $cantidad, false)
                   ->where('idMedicamento', $idMed)
                   ->where('stockActual >=', $cantidad)
                   ->update();

                if ($db->affectedRows() < 1) {
                    throw new \RuntimeException('Stock insuficiente de ' . $medicamentos[$idMed]['nombreComercial'] . '.');
                }

                $detalleModel->insert([
                    'idReserva'     => $idReserva,
                    'idMedicamento' => $idMed,
                    'cantidad'      => $cantidad,
                    'precio'        => $medicamentos[$idMed]['precioUnitario']
                ]);
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Error al procesar la reserva.');
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->responder(false, $e->getMessage() ?: 'Error al procesar la reserva. Intente nuevamente.', null, 422);
        }

        $mensaje = '¡Reserva realizada con éxito! Tiene un plazo de 24 horas para recoger sus medicamentos.';
        if ($requiereReceta) {
            $mensaje .= ' Atención: al menos un medicamento requiere presentar su receta médica al recogerlo.';
        }

        return $this->responder(true, $mensaje, '/mis-reservas', 200);
    }

    public function misReservas()
    {
        if (!session()->get('logged_in')) {
            return redirect()->to('/login');
        }

        $db = \Config\Database::connect();

        $filas = $db->table('reserva')
                    ->select('reserva.*, medicamento.nombreComercial, medicamento.nombreGenerico, detalle_reserva.cantidad, detalle_reserva.precio, medicamento.imagen')
                    ->join('detalle_reserva', 'detalle_reserva.idReserva = reserva.idReserva')
                    ->join('medicamento', 'medicamento.idMedicamento = detalle_reserva.idMedicamento')
                    ->where('reserva.idPersona', session()->get('idPersona'))
                    ->orderBy('reserva.fechaReserva', 'DESC')
                    ->orderBy('reserva.idReserva', 'DESC')
                    ->get()
                    ->getResultArray();

        // Agrupar: una reserva con todos sus medicamentos
        $reservas = [];
        foreach ($filas as $f) {
            $id = $f['idReserva'];
            if (!isset($reservas[$id])) {
                $reservas[$id] = [
                    'idReserva'      => $id,
                    'fechaReserva'   => $f['fechaReserva'],
                    'fechaExpira'    => $f['fechaExpira'],
                    'estado'         => $f['estado'],
                    'requiereReceta' => $f['requiereReceta'],
                    'total'          => 0,
                    'items'          => []
                ];
            }
            $reservas[$id]['items'][] = [
                'nombreComercial' => $f['nombreComercial'],
                'nombreGenerico'  => $f['nombreGenerico'],
                'cantidad'        => $f['cantidad'],
                'precio'          => $f['precio'],
                'imagen'          => $f['imagen']
            ];
            $reservas[$id]['total'] += $f['cantidad'] * $f['precio'];
        }

        $data['reservas'] = array_values($reservas);
        return view('cliente/mis_reservas', $data);
    }

    public function cancelar($idReserva)
    {
        if (!session()->get('logged_in')) {
            return redirect()->to('/login');
        }

        $reservaModel = new ReservaModel();
        $detalleModel = new DetalleReservaModel();
        $medicamentoModel = new MedicamentoModel();

        // Verificar que la reserva pertenezca al usuario actual y esté pendiente
        $reserva = $reservaModel->where('idReserva', $idReserva)
                                ->where('idPersona', session()->get('idPersona'))
                                ->where('estado', 'Pendiente')
                                ->first();

        if (!$reserva) {
            return redirect()->to('/mis-reservas')->with('mensaje', 'No se pudo encontrar la reserva o ya no está pendiente.');
        }

        $detalles = $detalleModel->where('idReserva', $idReserva)->findAll();

        $db = \Config\Database::connect();
        $db->transStart();

        foreach ($detalles as $det) {
            $med = $medicamentoModel->find($det['idMedicamento']);
            if ($med) {
                $medicamentoModel->update($det['idMedicamento'], ['stockActual' => $med['stockActual'] + $det['cantidad']]);
            }
        }

        $reservaModel->update($idReserva, ['estado' => 'Cancelada']);

        $db->transComplete();

        return redirect()->to('/mis-reservas')->with('mensaje', 'Reserva cancelada con éxito. El stock ha sido restituido.');
    }

    /* ---------------- Auxiliares ---------------- */

    /** Devuelve [idMedicamento => cantidad] ya validado y sin duplicados. */
    private function leerItems(): array
    {
        $crudos = [];
        $json = $this->request->getPost('items');

        if ($json !== null) {
            $lista = json_decode($json, true);
            if (is_array($lista)) {
                foreach ($lista as $it) {
                    $crudos[] = [$it['id'] ?? null, $it['cantidad'] ?? null];
                }
            }
        } elseif ($this->request->getPost('idMedicamento') !== null) {
            $crudos[] = [$this->request->getPost('idMedicamento'), $this->request->getPost('cantidad') ?? 1];
        }

        $items = [];
        foreach ($crudos as [$id, $cant]) {
            $id = filter_var($id, FILTER_VALIDATE_INT);
            $cant = filter_var($cant, FILTER_VALIDATE_INT);
            if (!$id || !$cant || $cant < 1) {
                return []; // cualquier dato inválido invalida el pedido completo
            }
            $items[$id] = ($items[$id] ?? 0) + $cant;
        }
        return $items;
    }

    /** Respuesta unificada: JSON para AJAX, redirección para formularios normales. */
    private function responder(bool $ok, string $mensaje, ?string $destino, int $status)
    {
        if ($this->request->isAJAX()) {
            if ($ok) {
                session()->setFlashdata('mensaje', $mensaje);
            }
            return $this->response->setStatusCode($status)->setJSON([
                'ok'       => $ok,
                'error'    => $ok ? null : $mensaje,
                'redirect' => $destino,
                'csrf'     => function_exists('csrf_hash') ? csrf_hash() : null
            ]);
        }

        if ($ok) {
            return redirect()->to($destino ?? '/mis-reservas')->with('mensaje', $mensaje);
        }
        return $destino
            ? redirect()->to($destino)->with('error', $mensaje)
            : redirect()->back()->with('error', $mensaje);
    }
}