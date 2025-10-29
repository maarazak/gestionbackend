<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Project extends Model
{
    use HasUuids, BelongsToTenant,HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'status'
    ];

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}
