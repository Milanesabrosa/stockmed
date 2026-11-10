<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\PersonaModel;

class AuthController extends BaseController
{
    public function login()
    {
        return view('auth/login');
    }

    public function registro()
    {
        return view('auth/registro');
    }

    public function guardarRegistro()
    {
        // Reglas de validación con mensajes personalizados en español
        $rules = [
            'nombre'     => [
                'rules'  => 'required|min_length[3]',
                'errors' => [
                    'required'   => 'El campo Nombre es obligatorio.',
                    'min_length' => 'El Nombre debe tener al menos 3 caracteres.'
                ]
            ],
            'apellido'   => [
                'rules'  => 'required|min_length[3]',
                'errors' => [
                    'required'   => 'El campo Apellido es obligatorio.',
                    'min_length' => 'El Apellido debe tener al menos 3 caracteres.'
                ]
            ],
            'ci'         => [
                'rules'  => 'required|is_unique[persona.ci]',
                'errors' => [
                    'required'   => 'El número de Cédula de Identidad (CI) es obligatorio.',
                    'is_unique'  => 'Este número de CI ya se encuentra registrado en el sistema.'
                ]
            ],
            'telefono'   => [
                'rules'  => 'required|regex_match[/^[67][0-9]{7}$/]',
                'errors' => [
                    'required'    => 'El número de teléfono es obligatorio.',
                    'regex_match' => 'El teléfono debe tener 8 dígitos y comenzar obligatoriamente con 6 o 7.'
                ]
            ],
            'correo'     => [
                'rules'  => 'required|valid_email|is_unique[persona.correo]|regex_match[/@gmail\.com$/]',
                'errors' => [
                    'required'    => 'El correo electrónico es obligatorio.',
                    'valid_email' => 'Debe ingresar un correo electrónico válido.',
                    'is_unique'   => 'Este correo electrónico ya está registrado.',
                    'regex_match' => 'El correo electrónico debe terminar obligatoriamente en @gmail.com.'
                ]
            ],
            'contrasena' => [
                'rules'  => 'required|min_length[6]',
                'errors' => [
                    'required'   => 'La contraseña es obligatoria.',
                    'min_length' => 'La contraseña debe tener al menos 6 caracteres.'
                ]
            ]
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $personaModel = new PersonaModel();

        $personaModel->save([
            'idRol'      => 1, // Cliente por defecto
            'nombre'     => $this->request->getPost('nombre'),
            'apellido'   => $this->request->getPost('apellido'),
            'ci'         => $this->request->getPost('ci'),
            'telefono'   => $this->request->getPost('telefono'),
            'correo'     => $this->request->getPost('correo'),
            'contrasena' => password_hash($this->request->getPost('contrasena'), PASSWORD_DEFAULT),
            'estado'     => 'activo'
        ]);

        return redirect()->to('/login')->with('mensaje', '¡Registro exitoso! Ya puede iniciar sesión.');
    }

    public function autenticar()
    {
        $session = session();
        $personaModel = new PersonaModel();

        $correo = $this->request->getPost('correo');
        $contrasena = $this->request->getPost('contrasena');

        $usuario = $personaModel->where('correo', $correo)->first();

        if ($usuario) {
            if (password_verify($contrasena, $usuario['contrasena'])) {
                $sesionData = [
                    'idPersona' => $usuario['idPersona'],
                    'nombre'    => $usuario['nombre'],
                    'apellido'  => $usuario['apellido'],
                    'correo'    => $usuario['correo'],
                    'idRol'     => $usuario['idRol'],
                    'logged_in' => TRUE
                ];
                $session->set($sesionData);

                // Redireccionar según el rol (1: Cliente, 2: Farmacéutico, 3: Administrador)
                if ($usuario['idRol'] == 3) {
                    return redirect()->to('/admin/medicamentos');
                } elseif ($usuario['idRol'] == 2) {
                    return redirect()->to('/farmacia/pedidos');
                } else {
                    return redirect()->to('/');
                }
            }
        }

        return redirect()->back()->with('error', 'Correo o contraseña incorrectos.');
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to('/login');
    }
}