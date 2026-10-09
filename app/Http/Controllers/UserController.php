<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

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

        if (! $user || ! $passwordMatches || (int) $user->user_estado !== 1) {
            return response()->json(['mensaje' => 'Credenciales inválidas', 'error' => '100'], 401);
        }

        $access = $user->apiAccess();
        $expiresAt = now()->addHours(8);
        $token = $user->createToken('web-session', ['api', $user->passwordAbility()], $expiresAt);

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'cliente' => [
                'id' => $user->id,
                'nombre' => $user->name,
                'email' => $user->email,
                'roles' => $access['roles'],
                'permissions' => $access['permissions'],
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
        $data = $request->validate([
            's_nombre' => 'required|string|max:255',
            's_email' => ['required', 'email', 'max:255', $this->uniqueEmail()],
            's_documento' => ['required', 'regex:/^\d{8}$/'],
            's_id_rol' => ['required', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
            's_password' => 'required|string|min:8|max:4096',
        ]);

        $user = DB::transaction(function () use ($data) {
            $role = Role::where('guard_name', 'web')->findOrFail($data['s_id_rol']);
            $user = new User;
            $user->forceFill([
                'name' => trim($data['s_nombre']), 'email' => trim($data['s_email']),
                'password' => Hash::make($data['s_password']),
                'identificador' => $data['s_documento'], 'user_estado' => 1,
            ])->save();
            $user->syncRoles([$role]);

            return $user;
        });

        return response()->json([[['mensaje' => 'Usuario registrado correctamente', 'error' => 0, 'numid' => $user->id]]]);
    }

    private function protectLastAdministrator(User $user, Role $role): void
    {
        if ($role->name === 'admin' || ! $user->hasRole('admin')) {
            return;
        }
        // Lock active administrators so simultaneous edits cannot remove every administrator.
        $administrators = User::role('admin')->where('user_estado', 1)->lockForUpdate()->get();
        if ($administrators->count() <= 1) {
            throw ValidationException::withMessages(['s_id_rol' => 'Debe permanecer al menos un administrador activo.']);
        }
    }

    private function uniqueEmail(?int $ignore = null): \Closure
    {
        return function ($attribute, $value, $fail) use ($ignore) {
            $query = User::whereRaw('LOWER(email) = ?', [strtolower(trim($value))]);
            if ($ignore !== null) {
                $query->where('id', '!=', $ignore);
            }
            if ($query->exists()) {
                $fail('El correo electrónico ya se encuentra registrado.');
            }
        };
    }

    public function roles()
    {
        return response()->json(Role::where('guard_name', 'web')->orderBy('name')->get(['id', 'name']))
            ->header('Cache-Control', 'no-store');
    }

    public function index(Request $request)
    {
        $request->validate([
            's_nada' => 'required',
        ]);

        $respuesta = DB::select('SELECT * FROM spu_listar_users()');
        $users = User::with('roles')->whereIn('id', array_column($respuesta, 'id'))->get()->keyBy('id');
        foreach ($respuesta as $row) {
            $row->roles = $users->get($row->id)?->roles->map(fn ($role) => ['id' => $role->id, 'name' => $role->name])->values()->all() ?? [];
        }

        return response()->json([$respuesta]);

    }

    public function editarUsuario(Request $request)
    {
        $request->validate(['s_id' => 'required|integer|exists:users,id']);
        $data = $request->validate([
            's_nombre' => 'required|string|max:255',
            's_email' => ['required', 'email', 'max:255', $this->uniqueEmail((int) $request->s_id)],
            's_documento' => ['required', 'regex:/^\d{8}$/'],
            's_id_rol' => ['required', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ]);

        DB::transaction(function () use ($request, $data) {
            $user = User::lockForUpdate()->findOrFail($request->s_id);
            $role = Role::where('guard_name', 'web')->findOrFail($data['s_id_rol']);
            $this->protectLastAdministrator($user, $role);
            $user->forceFill(['name' => trim($data['s_nombre']), 'email' => trim($data['s_email']),
                'identificador' => $data['s_documento']])->save();
            $user->syncRoles([$role]);
        });

        return response()->json([[['mensaje' => 'Usuario actualizado correctamente', 'error' => 0]]]);
    }

    public function cambiarContraUsuario(Request $request)
    {
        $datos = $request->validate([
            's_id_user' => 'required|integer|exists:users,id',
            's_password' => 'required|string|min:8|max:4096|confirmed',
        ], [
            's_password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            's_password.max' => 'La contraseña no puede superar 4096 caracteres.',
            's_password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        DB::transaction(function () use ($datos) {
            $usuario = User::lockForUpdate()->findOrFail($datos['s_id_user']);
            $usuario->password = Hash::make($datos['s_password']);
            $usuario->save();
            $usuario->tokens()->delete();
        });

        return response()->json([[['error' => 0, 'mensa' => 'Contraseña actualizada.']]]);
    }

    public function cambiarEstadoUsuario(Request $request)
    {
        $request->validate([
            's_id_user' => 'required',
        ]);

        $p_id = $request->s_id_user;

        $respuesta = DB::select('SELECT * FROM spu_cambiar_estado(?)', [$p_id]);
        if (isset($respuesta[0]) && (int) $respuesta[0]->error === 0) {
            User::findOrFail($p_id)->tokens()->delete();
        }

        return response()->json([$respuesta]);

    }

    public function asignacionRol(Request $request)
    {
        $data = $request->validate([
            's_id_user' => 'required|integer|exists:users,id',
            's_id_rol' => 'required|integer|exists:roles,id',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::lockForUpdate()->findOrFail($data['s_id_user']);
            $role = Role::where('guard_name', 'web')->findOrFail($data['s_id_rol']);
            $this->protectLastAdministrator($user, $role);
            $user->syncRoles([$role]);
            return $user;
        });

        return response()->json(['id' => $user->id, 'roles' => $user->getRoleNames()]);
    }
}
