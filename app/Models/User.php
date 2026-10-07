<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles, HasApiTokens, Notifiable;

    protected $guard_name = 'web';
    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];

    public function apiAccess(): array
    {
        $roles = DB::table('roles as r')
            ->join('model_has_roles as m', 'm.role_id', '=', 'r.id')
            ->leftJoin('role_has_permissions as rp', 'rp.role_id', '=', 'r.id')
            ->leftJoin('permissions as p', function ($join) {
                $join->on('p.id', '=', 'rp.permission_id')->where('p.guard_name', 'web');
            })
            ->where('m.model_id', $this->getKey())
            ->where('m.model_type', $this->getMorphClass())
            ->where('r.guard_name', 'web')
            ->select('r.name as role', 'p.name as permission');

        $direct = DB::table('permissions as p')
            ->join('model_has_permissions as m', 'm.permission_id', '=', 'p.id')
            ->where('m.model_id', $this->getKey())
            ->where('m.model_type', $this->getMorphClass())
            ->where('p.guard_name', 'web')
            ->selectRaw('NULL as role, p.name as permission');

        $access = $roles->union($direct)->get();

        return [
            'roles' => $access->pluck('role')->filter()->unique()->values()->all(),
            'permissions' => $access->pluck('permission')->filter()->unique()->values()->all(),
        ];
    }

    public function passwordAbility(): string
    {
        return 'password:'.hash_hmac('sha256', $this->password, config('app.key'));
    }
}
