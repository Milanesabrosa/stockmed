<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\PersonaModel;
use App\Models\ReservaModel;

class PerfilController extends BaseController
{
    /** Datos del usuario con sesión iniciada */
    public function index()
    {
        if (!session()->get('logged_in')) {
            return redirect()->to('/login');
        }

        $persona = (new PersonaModel())
            ->select('idPersona, idRol, nombre, apellido, ci, telefono, correo')
            ->find(session()->get('idPersona'));

        if (!$persona) {
            return redirect()->to('/logout');
        }

        return view('cliente/perfil', ['persona' => $persona]);
    }

    public function guardar()
    {
        if (!session()->get('logged_in')) {
            return redirect()->to('/login');
        }

        $reglas = [
            'nombre'   => 'required|min_length[2]|max_length[60]',
            'apellido' => 'required|min_length[2]|max_length[60]',
            'telefono' => 'required|regex_match[/^[0-9+\s-]{7,20}$/]',
            'correo'   => 'required|valid_email|max_length[100]',
        ];
        $mensajes = [
            'nombre'   => ['required' => 'Ingrese su nombre.', 'min_length' => 'El nombre es demasiado corto.'],
            'apellido' => ['required' => 'Ingrese su apellido.', 'min_length' => 'El apellido es demasiado corto.'],
            'telefono' => ['required' => 'Ingrese su número de teléfono.', 'regex_match' => 'El teléfono no es válido (solo números, de 7 a 20 dígitos).'],
            'correo'   => ['required' => 'Ingrese su correo.', 'valid_email' => 'El correo no es válido.'],
        ];
        if (!$this->validate($reglas, $mensajes)) {
            return redirect()->to('/perfil')->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $id     = (int) session()->get('idPersona');
        $modelo = new PersonaModel();
        $correo = trim((string) $this->request->getPost('correo'));

        // El correo no puede estar en uso por otra persona
        $otro = $modelo->where('correo', $correo)->where('idPersona !=', $id)->first();
        if ($otro) {
            return redirect()->to('/perfil')->withInput()->with('error', 'Ese correo ya está registrado por otra cuenta.');
        }

        $nombre   = trim((string) $this->request->getPost('nombre'));
        $apellido = trim((string) $this->request->getPost('apellido'));

        $modelo->update($id, [
            'nombre'   => $nombre,
            'apellido' => $apellido,
            'telefono' => trim((string) $this->request->getPost('telefono')),
            'correo'   => $correo,
        ]);

        session()->set(['nombre' => $nombre, 'apellido' => $apellido]);

        return redirect()->to('/perfil')->with('mensaje', 'Sus datos fueron actualizados.');
    }

    /**
     * El cliente elimina su cuenta. Se conserva el historial (reservas, ventas) pero la cuenta
     * queda inactiva, sin contraseña utilizable y con el correo liberado.
     */
    public function eliminar()
    {
        if (!session()->get('logged_in')) {
            return redirect()->to('/login');
        }
        if ((int) session()->get('idRol') !== 1) {
            return redirect()->to('/perfil')->with('error', 'Las cuentas del personal las gestiona el administrador.');
        }

        $id      = (int) session()->get('idPersona');
        $modelo  = new PersonaModel();
        $persona = $modelo->find($id);

        $clave = (string) $this->request->getPost('contrasena');
        if (!$persona || $clave === '' || !password_verify($clave, (string) $persona['contrasena'])) {
            return redirect()->to('/perfil')->with('error', 'La contraseña es incorrecta. No se eliminó la cuenta.');
        }

        $modelo->desactivarCuenta($id);

        // Cerrar sesión conservando un mensaje para la siguiente página
        foreach (array_keys(session()->get()) as $k) {
            if (strpos((string) $k, '__ci') !== 0) {
                session()->remove($k);
            }
        }
        session()->regenerate(true);
        session()->setFlashdata('mensaje', 'Su cuenta fue eliminada. Gracias por haber usado StockMed.');

        return redirect()->to('/');
    }
}