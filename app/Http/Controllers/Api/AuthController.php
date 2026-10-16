<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /** Inicia sesión y entrega un token Bearer de Sanctum. */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $user = User::query()
            ->where('email', mb_strtolower(trim($credentials['email'])))
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Las credenciales ingresadas no son correctas.',
                'errors' => ['email' => ['Verificá el correo electrónico y la contraseña.']],
            ], 401);
        }

        $token = $user->createToken('tif-frontend')->plainTextToken;

        return response()->json([
            'message' => 'Inicio de sesión correcto.',
            'token_type' => 'Bearer',
            'token' => $token,
            'user' => $this->publicUser($user),
        ]);
    }

    /** Invalida el token usado en la solicitud actual. */
    public function logout(Request $request): JsonResponse
    {
        $currentToken = $request->user()->currentAccessToken();

        if ($currentToken && method_exists($currentToken, 'delete')) {
            $currentToken->delete();
        }

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    /** Devuelve el usuario asociado al token actual. */
    public function user(Request $request): JsonResponse
    {
        return response()->json($this->publicUser($request->user()));
    }

    /** Evita exponer campos internos del modelo en la API. */
    private function publicUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'created_at' => $user->created_at,
        ];
    }
}
