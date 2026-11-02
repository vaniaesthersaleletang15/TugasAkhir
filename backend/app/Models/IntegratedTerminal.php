<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegratedTerminal extends Model
{
    protected $fillable = ['code', 'name', 'city', 'region_code', 'region_name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
