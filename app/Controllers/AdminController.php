<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\PersonaModel;
use App\Models\ReservaModel;
use App\Models\MedicamentoModel;

class AdminController extends BaseController
{
    /** Lista de usuarios registrados (sin la contraseña) */
    public function clientes()
    {
        if ($r = $this->requiereRol([3])) return $r;

        $data['usuarios'] = (new PersonaModel())
            ->select('idPersona, idRol, nombre, apellido, ci, telefono, correo, estado')
            ->groupStart()->where('estado !=', 'inactivo')->orWhere('estado IS NULL', null, false)->groupEnd()
            ->orderBy('idPersona', 'DESC')
            ->findAll();
        $data['titulo'] = 'Usuarios registrados';
        $data['activo'] = 'clientes';

        return view('admin/clientes', $data);
    }

    /** Cambia el rol de un usuario registrado: Cliente (1) <-> Farmacéutico (2) */
    public function cambiarRol()
    {
        if ($r = $this->requiereRol([3])) return $r;

        $id  = filter_var($this->request->getPost('idPersona'), FILTER_VALIDATE_INT);
        $rol = filter_var($this->request->getPost('idRol'), FILTER_VALIDATE_INT);

        $modelo  = new PersonaModel();
        $persona = $id ? $modelo->find($id) : null;

        if (!$persona || !in_array($rol, [1, 2], true)) {
            return redirect()->to('/admin/clientes')->with('error', 'No se pudo cambiar el rol: datos no válidos.');
        }
        // Los administradores no se modifican desde aquí (evita quedarse sin acceso)
        if ((int) $persona['idRol'] === 3) {
            return redirect()->to('/admin/clientes')->with('error', 'No se puede cambiar el rol de un administrador.');
        }

        $modelo->update($id, ['idRol' => $rol]);

        $nuevo = $rol === 2 ? 'farmacéutico' : 'cliente';
        return redirect()->to('/admin/clientes')->with('mensaje', "{$persona['nombre']} {$persona['apellido']} ahora es {$nuevo}. El cambio se aplica cuando esa persona vuelva a iniciar sesión.");
    }

    /** Elimina la cuenta de un cliente (el historial se conserva; la cuenta queda inactiva) */
    public function eliminarCliente()
    {
        if ($r = $this->requiereRol([3])) return $r;

        $id      = filter_var($this->request->getPost('idPersona'), FILTER_VALIDATE_INT);
        $modelo  = new PersonaModel();
        $persona = $id ? $modelo->find($id) : null;

        if (!$persona) {
            return redirect()->to('/admin/clientes')->with('error', 'La cuenta no existe.');
        }
        if ((int) $persona['idRol'] !== 1) {
            return redirect()->to('/admin/clientes')->with('error', 'Solo se pueden eliminar cuentas de clientes. Cambie primero el rol si corresponde.');
        }

        $modelo->desactivarCuenta($id);

        return redirect()->to('/admin/clientes')->with('mensaje', "La cuenta de {$persona['nombre']} {$persona['apellido']} fue eliminada. Sus reservas pendientes se cancelaron y el stock volvió al inventario.");
    }

    /** Pedidos pendientes (con el cliente) y medicamentos con stock bajo */
    public function pedidos()
    {
        if ($r = $this->requiereRol([3])) return $r;

        $pendientes = (new ReservaModel())->listarConDetalle(['Pendiente']);
        usort($pendientes, fn($a, $b) => strcmp($a['fechaExpira'], $b['fechaExpira']));

        $data['pendientes'] = $pendientes;
        $data['bajoStock']  = (new MedicamentoModel())
            ->select('medicamento.*, categoria.nombreCategoria')
            ->join('categoria', 'categoria.idCategoria = medicamento.idCategoria')
            ->where('medicamento.estado', 'activo')
            ->where('medicamento.stockActual <= medicamento.rop', null, false)
            ->orderBy('medicamento.stockActual', 'ASC')
            ->findAll();
        $data['titulo'] = 'Pedidos';
        $data['activo'] = 'pedidos';

        return view('admin/pedidos', $data);
    }
}