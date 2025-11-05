<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use App\Models\Usuario;
use App\Models\UsuarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UsuarioController extends Controller
{
    public function index(): JsonResponse
    {
        $usuarios = Usuario::with('rol')->get(['id', 'nombre_completo', 'correo', 'rol_id', 'estado']);
        return response()->json([
            'message' => 'Usuarios obtenidos correctamente',
            'data' => $usuarios,
        ], 200);
    }

    public function vendedores(): JsonResponse
    {
        $vendedores = Usuario::whereHas('rol', function ($query) {
            $query->where('nombre', 'vendedor');
        })->get(['id', 'nombre_completo', 'correo', 'rol_id', 'estado']);
        return response()->json([
            'message' => 'Vendedores obtenidos correctamente',
            'data' => $vendedores,
        ], 200);
    }

    public function almacen(): JsonResponse
    {
        $almacen = Usuario::whereHas('rol', function ($query) {
            $query->where('nombre', 'almacen');
        })->get(['id', 'nombre_completo', 'correo', 'rol_id', 'estado']);
        return response()->json([
            'message' => 'Usuarios de almacén obtenidos correctamente',
            'data' => $almacen,
        ], 200);
    }

    public function delivery(): JsonResponse
    {
        $delivery = Usuario::whereHas('rol', function ($query) {
            $query->where('nombre', 'delivery');
        })->get(['id', 'nombre_completo', 'correo', 'rol_id', 'estado']);
        return response()->json([
            'message' => 'Usuarios de delivery obtenidos correctamente',
            'data' => $delivery,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'nombre_completo' => 'required|string|max:100',
                'correo' => 'required|email|unique:usuarios,correo|max:100',
                'contraseña' => 'required|string|min:8',
                'rol_id' => 'required|exists:roles,id',
            ]);

            $usuario = Usuario::create([
                'nombre_completo' => $validated['nombre_completo'],
                'correo' => $validated['correo'],
                'contraseña' => Hash::make($validated['contraseña']),
                'rol_id' => $validated['rol_id'],
                'estado' => 0,
            ]);

            return response()->json([
                'message' => 'Usuario creado correctamente',
                'data' => $usuario->only(['id', 'nombre_completo', 'correo', 'rol_id', 'estado']),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $usuario = Usuario::findOrFail($id);

            $validated = $request->validate([
                'nombre_completo' => 'sometimes|string|max:100',
                'correo' => 'sometimes|email|unique:usuarios,correo,' . $id . '|max:100',
                'contraseña' => 'sometimes|string|min:8',
                'rol_id' => 'sometimes|exists:roles,id',
            ]);

            $updateData = [];
            if (isset($validated['nombre_completo'])) {
                $updateData['nombre_completo'] = $validated['nombre_completo'];
            }
            if (isset($validated['correo'])) {
                $updateData['correo'] = $validated['correo'];
            }
            if (isset($validated['contraseña'])) {
                $updateData['contraseña'] = Hash::make($validated['contraseña']);
            }
            if (isset($validated['rol_id'])) {
                $updateData['rol_id'] = $validated['rol_id'];
            }

            $usuario->update($updateData);

            return response()->json([
                'message' => 'Perfil actualizado correctamente',
                'data' => $usuario->only(['id', 'nombre_completo', 'correo', 'rol_id', 'estado']),
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el perfil',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $usuario = Usuario::findOrFail($id);
            $usuario->delete();

            return response()->json([
                'message' => 'Usuario eliminado correctamente',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar el usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function resetPassword(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'correo' => 'required|email|exists:usuarios,correo',
            ]);

            $status = Password::sendResetLink(
                $request->only('correo')
            );

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'message' => 'Enlace de restablecimiento de contraseña enviado correctamente',
                ], 200);
            }

            return response()->json([
                'message' => 'No se pudo enviar el enlace de restablecimiento',
                'error' => __($status),
            ], 400);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al procesar la solicitud',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'correo' => 'required|email|exists:usuarios,correo',
            'contraseña' => 'required|string',
        ]);

        // Usamos Eager Loading para prevenir el Error 500 si la relación 'rol' falla más adelante
        $usuario = Usuario::with('rol')->where('correo', $request->correo)->first();

        if (!$usuario || !Hash::check($request->contraseña, $usuario->contraseña)) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        // --- NUEVO PASO CRUCIAL ---
        // Prevenir el Error 500 si el usuario no tiene rol
        if (! $usuario->rol) {
            return response()->json(['message' => 'Acceso denegado: Usuario sin rol asignado.'], 403);
        }
        // -------------------------

        // ====================================================================
        // 🎯 CORRECCIÓN DEL ERROR DE COLUMNA: Cambiamos 'usuario_id' por 'user_id'
        // ====================================================================
        UsuarioToken::where('user_id', $usuario->id)->delete(); // <-- ¡CORREGIDO!

        // Generar token
        $hours = $usuario->rol->nombre === 'Administrador' ? 8 : 4;
        $token = Str::random(60); // ← 60 caracteres = cabe en VARCHAR(64) // Token plano
        $expiresAt = now()->addHours($hours);

        // Guardar en tu tabla
        UsuarioToken::create([
            'user_id' => $usuario->id, // <-- ¡CORREGIDO AQUÍ!
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);

        $usuario->update(['estado' => 1]);

        return response()->json([
            'message' => 'Login exitoso',
            'data' => [
                'id' => $usuario->id,
                'nombre_completo' => $usuario->nombre_completo,
                'rol' => $usuario->rol->nombre,
                'token' => $token,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    // USUARIO AUTENTICADO (ya lo tienes)
    public function showAuthUser(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['message' => 'Token requerido'], 401);
        }
        $tokenRecord = UsuarioToken::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();
        if (!$tokenRecord || !$tokenRecord->usuario) {
            return response()->json(['message' => 'Token inválido o expirado'], 401);
        }
        $user = $tokenRecord->usuario->load('rol');
        return response()->json([
            'message' => 'Usuario obtenido',
            'data' => $user->only(['id', 'nombre_completo', 'correo', 'rol', 'estado'])
        ]);
    }

    // LOGOUT
    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken(); // "Bearer abc123..."
        if (!$token) {
            return response()->json(['message' => 'Token no proporcionado'], 400);
        }
        $tokenRecord = UsuarioToken::where('token', $token)->first();
        if ($tokenRecord) {
            $usuario = $tokenRecord->usuario;
            $tokenRecord->delete();
            if ($usuario) {
                $usuario->update(['estado' => 0]);
            }
            return response()->json(['message' => 'Logout exitoso'], 200);
        }
        return response()->json(['message' => 'Token inválido'], 401);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre_completo' => 'required|string|max:100',
            'correo' => 'required|email|unique:usuarios,correo|max:100',
            'contraseña' => 'required|string|min:8|confirmed',
            'rol_id' => 'required|exists:roles,id',
        ]);

        $usuario = Usuario::create([
            'nombre_completo' => $validated['nombre_completo'],
            'correo' => $validated['correo'],
            'contraseña' => Hash::make($validated['contraseña']),
            'rol_id' => $validated['rol_id'],
            'estado' => 0,
        ]);

        return response()->json([
            'message' => 'Usuario creado correctamente',
            'data' => $usuario->only(['id', 'nombre_completo', 'correo', 'rol_id', 'estado']),
        ], 201);
    }
}
