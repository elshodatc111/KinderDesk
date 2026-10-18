<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAttendance extends Model
{
    use BelongsToTenant;

    protected $table = 'employee_attendance';

    protected $fillable = ['tenant_id', 'employee_id', 'date', 'status', 'check_in', 'note', 'excuse', 'excuse_by', 'marked_by'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
