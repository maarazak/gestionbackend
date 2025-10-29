<?php

namespace App\Models;

use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    protected $fillable = [
        'tenant_id',
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
        return $this->roles->first()?->name;
    }
    
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
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

    public function switchTenant($tenantId)
    {
        $tenant = $this->tenants()->where('tenants.id', $tenantId)->first();
        
        if (!$tenant) {
            return false;
        }

        $this->update(['current_tenant_id' => $tenantId]);
        return true;
    }

    public function getRoleForTenant($tenantId)
    {
        $tenant = $this->tenants()->where('tenants.id', $tenantId)->first();
        return $tenant?->pivot?->role_id;
    }
}
