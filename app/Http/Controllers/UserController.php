<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'p_email' => 'required|email|max:255',
            'p_password' => 'required|string|max:4096',
        ]);

        $user = User::where('email', $credentials['p_email'])->first();
        $passwordMatches = Hash::check($credentials['p_password'], $user?->password
            ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');

        if (!$user || !$passwordMatches || (int) $user->user_estado !== 1) {
            return response()->json(['mensaje' => 'Credenciales inválidas', 'error' => '100'], 401);
        }

        $expiresAt = now()->addHours(8);
        $token = $user->createToken('web-session', ['api', $user->passwordAbility()], $expiresAt);

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'cliente' => [
                'id' => $user->id,
                'nombre' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
            'error' => '0',
        ])->header('Cache-Control', 'no-store');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function store(Request $request)
    {
        $request->validate([
            's_nombre' => 'required|string',
            's_email' => 'required|string',
            's_password' => 'required|string',
            's_documento' => 'required|string',
        ]);

        $p_nombre = $request->s_nombre;
        $p_emil = $request->s_email;
        $p_password = Hash::make($request->s_password) ;
        $p_identificador = $request->s_documento;\n\n        $respuesta = DB::select('SELECT * FROM fn_insertar_usuario(?,?,?,?)', [$p_nombre, $p_emil, $p_password, $p_identificador]);

        return response()->json([$respuesta]);\n\n    }

    public function index(Request $request)
    {
        $request->validate([
            's_nada' => 'required',
        ]);

        $respuesta = DB::select('SELECT * FROM spu_listar_users()');

        return response()->json([$respuesta]);

    }

    public function editarUsuario(Request $request)
    {
        $request->validate([
            's_id' => 'required',
            's_nombre' => 'required|string',
            's_email' => 'required|string',
            's_documento' => 'required|string',
        ]);

        $p_id = $request->s_id;
        $p_nombre = $request->s_nombre;
        $p_emil = $request->s_email;
        $p_identificador = $request->s_documento;\n\n        $respuesta = DB::select('SELECT * FROM spu_users_upd(?,?,?,?)', [$p_id, $p_nombre, $p_emil, $p_identificador]);

        return response()->json([$respuesta]);

    }

    public function cambiarContraUsuario(Request $request)
    {
        $request->validate([
            's_id_user' => 'required|integer',
            's_password' => 'required|string|min:8',
        ]);

        $respuesta = DB::select('SELECT * FROM spu_users_cambiar_contra(?,?)', [
            $request->s_id_user,
            Hash::make($request->s_password),
        ]);

        return response()->json([$respuesta]);
    }

    public function cambiarEstadoUsuario(Request $request)
    {
        $request->validate([
            's_id_user' => 'required',
        ]);

        $p_id = $request->s_id_user;

        $respuesta = DB::select('SELECT * FROM spu_cambiar_estado(?)', [ $p_id]);
        if (isset($respuesta[0]) && (int) $respuesta[0]->error === 0) {
            User::findOrFail($p_id)->tokens()->delete();
        }

        return response()->json([$respuesta]);

    }\n\n    public function asignacionRol(Request $request)
    {
        $data = $request->validate([
            's_id_user' => 'required|integer|exists:users,id',
            's_id_rol' => 'required|integer|exists:roles,id',
        ]);

        $user = User::findOrFail($data['s_id_user']);
        $role = \Spatie\Permission\Models\Role::where('guard_name', 'web')->findOrFail($data['s_id_rol']);
        $user->syncRoles([$role]);

        return response()->json(['id' => $user->id, 'roles' => $user->getRoleNames()]);
    }
}\n