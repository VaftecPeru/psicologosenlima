<?php

namespace App\Http\Controllers;

use App\Models\ComisionVenta;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ComisionVentaController extends Controller
{
    /**
     * Listar todos los registros de comisiones.
     */
    public function index()
    {
        $comisiones = ComisionVenta::all();
        return response()->json($comisiones);
    }

    /**
     * Guardar un nuevo registro de comisión.
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:usuarios,id|unique:comision_ventas,user_id',
            'comision' => 'required|numeric|min:0',
        ]);

        $comision = ComisionVenta::create($request->only(['user_id', 'comision']));

        return response()->json($comision, 201);
    }

    /**
     * Actualizar un registro de comisión por su ID.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'comision' => 'required|numeric|min:0',
        ]);

        $comision = ComisionVenta::findOrFail($id);
        $comision->update($request->only(['comision']));

        return response()->json($comision);
    }

    /**
     * Obtener comisión por user_id (para fetch en frontend).
     */
    public function showByUser($userId)
    {
        $comision = ComisionVenta::where('user_id', $userId)->first();

        if (!$comision) {
            return response()->json(['message' => 'No encontrado'], 404);
        }

        return response()->json($comision);
    }
}