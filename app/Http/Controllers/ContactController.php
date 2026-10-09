<?php

namespace App\Http\Controllers;

use App\Services\ContactWriter;
use Illuminate\Http\Request;

abstract class ContactController extends Controller
{
    protected string $tabla;

    public function store(Request $request, ContactWriter $writer)
    {
        $datos = $request->validate($this->reglas() + ['s_id' => 'prohibited']);
        $id = $writer->guardar($this->tabla, $datos, (int) $request->user()->id);

        return $this->respuesta($id, 'Contacto registrado.');
    }

    public function update(Request $request, ContactWriter $writer)
    {
        $datos = $request->validate($this->reglas() + ['s_id' => 'required|integer|min:1']);
        $id = $writer->guardar($this->tabla, $datos, (int) $request->user()->id);

        return $this->respuesta($id, 'Contacto actualizado.');
    }

    public function changer(Request $request, ContactWriter $writer)
    {
        $datos = $request->validate(['s_id' => 'required|integer|min:1']);
        $writer->estado($this->tabla, $datos['s_id'], (int) $request->user()->id);

        return $this->respuesta($datos['s_id'], 'Estado actualizado.');
    }

    private function reglas(): array
    {
        return [
            's_nombre' => 'required|string|max:255',
            's_cel' => 'nullable|string|max:30',
            's_telefono' => 'nullable|string|max:30',
            's_correo' => 'required|email|max:255',
            's_descripcion' => 'nullable|string|max:1000',
        ];
    }

    private function respuesta(int $id, string $mensaje)
    {
        return response()->json([[['mensa' => $mensaje, 'error' => 0, 'numid' => $id]]]);
    }
}
