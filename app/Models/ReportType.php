<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ReportType extends Model
{
    use BelongsToTenant;

    protected $table = 'report_types';

    protected $fillable = ['tenant_id','name','role_key','level','frequency','deadline_time','content','is_parent','affects_bonus','active','hint'];

    protected function casts(): array
    {
        return ['is_parent' => 'boolean', 'affects_bonus' => 'boolean', 'active' => 'boolean'];
    }
}
