<?php

namespace App\Models;

use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    protected $fillable = [
        'current_tenant_id',
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            $user->uuid = Str::uuid();
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function getRoleAttribute()
    {
        if (!$this->current_tenant_id) {
            return null;
        }

        $tenant = $this->tenants()->where('tenants.id', $this->current_tenant_id)->first();

        if (!$tenant || !$tenant->pivot || !$tenant->pivot->role_id) {
            return null;
        }

        $role = Role::find($tenant->pivot->role_id);
        return $role?->name;
    }

    public function currentTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'current_tenant_id');
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function assignedTasks()
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function hasAccessToTenant($tenantId): bool
    {
        return $this->tenants()->where('tenants.id', $tenantId)->exists();
    }

    public function switchTenant($tenantId): bool
    {
        if (!$this->hasAccessToTenant($tenantId)) {
            return false;
        }

        $this->update(['current_tenant_id' => $tenantId]);

        $tenant = $this->tenants()->where('tenants.id', $tenantId)->first();
        if ($tenant && $tenant->pivot && $tenant->pivot->role_id) {
            $role = Role::find($tenant->pivot->role_id);
            if ($role) {
                $this->syncRoles([$role->name]);
            }
        }

        return true;
    }

    public function getRoleForTenant($tenantId)
    {
        $tenant = $this->tenants()->where('tenants.id', $tenantId)->first();
        return $tenant?->pivot?->role_id;
    }
}
