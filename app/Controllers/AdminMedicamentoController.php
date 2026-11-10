<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\MedicamentoModel;
use App\Models\CategoriaModel;
use App\Models\LoteModel;

class AdminMedicamentoController extends BaseController
{
    /** Inventario con filtros por categoría, buscador y lotes */
    public function index()
    {
        if ($r = $this->requiereRol([3])) return $r;

        $loteModel = new LoteModel();
        $todos = $loteModel->conRestante();
        $lotes = [];
        foreach ($todos as $l) {
            $lotes[$l['idMedicamento']][] = $l;
        }
        foreach ($lotes as &$lista) {
            usort($lista, fn($a, $b) => strcmp($a['fechaVencimiento'], $b['fechaVencimiento']));
        }
        unset($lista);

        $data['medicamentos'] = (new MedicamentoModel())
            ->select('medicamento.*, categoria.nombreCategoria')
            ->join('categoria', 'categoria.idCategoria = medicamento.idCategoria')
            ->where('medicamento.estado', 'activo')
            ->orderBy('medicamento.nombreComercial', 'ASC')
            ->findAll();
        $data['categorias']   = (new CategoriaModel())->orderBy('nombreCategoria', 'ASC')->findAll();
        $data['lotes']        = $lotes;
        $data['vencimientos'] = $loteModel->proximoVencimiento($todos);
        $data['titulo'] = 'Inventario';
        $data['activo'] = 'inventario';

        return view('admin/medicamentos/index', $data);
    }

    public function nuevo()
    {
        if ($r = $this->requiereRol([3])) return $r;

        $data['categorias'] = (new CategoriaModel())->orderBy('nombreCategoria', 'ASC')->findAll();
        $data['titulo'] = 'Nuevo medicamento';
        $data['activo'] = 'nuevo';

        return view('admin/medicamentos/crear', $data);
    }

    public function guardar()
    {
        if ($r = $this->requiereRol([3])) return $r;

        $reglas = [
            'idCategoria'     => 'required|is_natural_no_zero',
            'nombreComercial' => 'required|min_length[2]',
            'nombreGenerico'  => 'required|min_length[2]',
            'precioUnitario'  => 'required|decimal|greater_than_equal_to[0]',
            'stockActual'     => 'required|is_natural',
            'rop'             => 'required|is_natural',
            'eoq'             => 'required|is_natural',
        ];
        if (!$this->validate($reglas)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        // Si hay stock inicial, debe registrarse con su lote y vencimiento
        $stock = (int) $this->request->getPost('stockActual');
        $lote  = trim((string) $this->request->getPost('numeroLote'));
        $venc  = (string) $this->request->getPost('fechaVencimiento');
        if ($stock > 0) {
            if ($lote === '' || strlen($lote) > 50) {
                return redirect()->back()->withInput()->with('error', 'Indique el número de lote del stock inicial (máximo 50 caracteres).');
            }
            if (!$this->fechaFutura($venc)) {
                return redirect()->back()->withInput()->with('error', 'Indique una fecha de vencimiento válida y posterior a hoy.');
            }
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        $idMed = (new MedicamentoModel())->insert([
            'idCategoria'        => $this->request->getPost('idCategoria'),
            'nombreComercial'    => $this->request->getPost('nombreComercial'),
            'nombreGenerico'     => $this->request->getPost('nombreGenerico'),
            'descripcion'        => $this->request->getPost('descripcion'),
            'imagen'             => $this->request->getPost('imagen'),
            'indicaciones'       => $this->request->getPost('indicaciones'),
            'efectosSecundarios' => $this->request->getPost('efectosSecundarios'),
            'viaAdministrativa'  => $this->request->getPost('viaAdministrativa'),
            'dosis'              => $this->request->getPost('dosis'),
            'recetaMedica'       => $this->request->getPost('recetaMedica') ? 1 : 0,
            'precioUnitario'     => $this->request->getPost('precioUnitario'),
            'stockActual'        => $stock,
            'rop'                => $this->request->getPost('rop'),
            'eoq'                => $this->request->getPost('eoq'),
            'estado'             => 'activo'
        ], true);

        if ($stock > 0) {
            (new LoteModel())->insert([
                'idMedicamento'    => $idMed,
                'numeroLote'       => $lote,
                'fechaVencimiento' => $venc,
                'cantidad'         => $stock,
                'fechaIngreso'     => date('Y-m-d H:i:s'),
                'idPersona'        => session()->get('idPersona'),
            ]);
        }

        if ($db->transStatus() === false) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', 'No se pudo registrar el medicamento. Verifique que la tabla "lote" exista.');
        }
        $db->transCommit();

        return redirect()->to('/admin/medicamentos')->with('mensaje', 'Medicamento registrado con éxito.');
    }

    /** Baja lógica: el medicamento deja de verse pero se conserva el historial de reservas. */
    public function eliminar($id)
    {
        if ($r = $this->requiereRol([3])) return $r;

        (new MedicamentoModel())->update($id, ['estado' => 'inactivo']);

        return redirect()->to('/admin/medicamentos')->with('mensaje', 'Medicamento eliminado del inventario.');
    }

    /** Ingreso de stock a un medicamento existente, con lote y fecha de vencimiento */
    public function agregarStock()
    {
        if ($r = $this->requiereRol([3])) return $r;

        $volver = in_array($this->request->getPost('volver'), ['/admin/pedidos', '/vencidos'], true)
    ? $this->request->getPost('volver') : '/admin/medicamentos';

        $reglas = [
            'idMedicamento'    => 'required|is_natural_no_zero',
            'cantidad'         => 'required|is_natural_no_zero',
            'numeroLote'       => 'required|max_length[50]',
            'fechaVencimiento' => 'required',
        ];
        if (!$this->validate($reglas)) {
            return redirect()->to($volver)->with('error', 'Complete cantidad, número de lote y fecha de vencimiento.');
        }

        $id       = (int) $this->request->getPost('idMedicamento');
        $cantidad = (int) $this->request->getPost('cantidad');
        $lote     = trim((string) $this->request->getPost('numeroLote'));
        $venc     = (string) $this->request->getPost('fechaVencimiento');

        if (!$this->fechaFutura($venc)) {
            return redirect()->to($volver)->with('error', 'La fecha de vencimiento debe ser válida y posterior a hoy.');
        }

        $modelo = new MedicamentoModel();
        $bajas = new \App\Models\BajaVencidoModel();
        $med = $modelo->find($id);
        if (!$med || ($med['estado'] !== 'activo' && !$bajas->estaInactivoPorVencimiento($id))) {
            return redirect()->to($volver)->with('error', 'El medicamento no existe.');
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        (new LoteModel())->insert([
            'idMedicamento'    => $id,
            'numeroLote'       => $lote,
            'fechaVencimiento' => $venc,
            'cantidad'         => $cantidad,
            'fechaIngreso'     => date('Y-m-d H:i:s'),
            'idPersona'        => session()->get('idPersona'),
        ]);
        $modelo->builder()->set('stockActual', 'stockActual + ' . $cantidad, false)->where('idMedicamento', $id)->update();
        $bajas->reactivar($id);

        if ($db->transStatus() === false) {
            $db->transRollback();
            return redirect()->to($volver)->with('error', 'No se pudo registrar el ingreso. Verifique que la tabla "lote" exista.');
        }
        $db->transCommit();

        $nuevo = $modelo->find($id)['stockActual'];
        $fecha = date('d/m/Y', strtotime($venc));
        return redirect()->to($volver)->with('mensaje', "Ingreso registrado: {$cantidad} unidades de {$med['nombreComercial']} (lote {$lote}, vence {$fecha}). Stock actual: {$nuevo}.");
    }

    /** Descuenta del inventario las unidades de lotes ya vencidos de un medicamento */
    public function bajaVencidos()
    {
        if (!$id || !$modelo->find($id)) {
    return redirect()->to('/reportes?tipo=porvencer')->with('error', 'El medicamento no existe.');
}
$res = (new \App\Models\BajaVencidoModel())->procesarVencidos($id);
if ($res['unidades'] <= 0) {
    return redirect()->to('/reportes?tipo=porvencer')->with('error', 'Ese medicamento no tiene unidades vencidas.');
}
return redirect()->to('/reportes?tipo=porvencer')->with('mensaje', "Se dieron de baja {$res['unidades']} unidades vencidas. Quedan registradas en Vencidos.");
    }

    public function guardarCategoria()
    {
        if ($r = $this->requiereRol([3])) return $r;

        $reglas = [
            'nombreCategoria' => [
                'rules'  => 'required|min_length[3]|max_length[100]|is_unique[categoria.nombreCategoria]',
                'errors' => [
                    'required'   => 'El nombre de la categoría es obligatorio.',
                    'min_length' => 'El nombre debe tener al menos 3 caracteres.',
                    'is_unique'  => 'Esa categoría ya existe.'
                ]
            ],
        ];
        if (!$this->validate($reglas)) {
            return redirect()->to('/admin/medicamentos')->with('error', implode(' ', $this->validator->getErrors()));
        }

        (new CategoriaModel())->save([
            'nombreCategoria' => trim($this->request->getPost('nombreCategoria')),
            'descripcion'     => trim((string) $this->request->getPost('descripcion')),
        ]);

        return redirect()->to('/admin/medicamentos')->with('mensaje', 'Categoría agregada correctamente.');
    }

    private function fechaFutura(string $valor): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $valor);
        return $d && $d->format('Y-m-d') === $valor && $valor > date('Y-m-d');
    }
}