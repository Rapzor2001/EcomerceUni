<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'is_secret'];
    protected function casts(): array { return ['value' => 'array', 'is_secret' => 'boolean']; }
}
