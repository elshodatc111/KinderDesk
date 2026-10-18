<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ReportSubmission extends Model
{
    use BelongsToTenant;

    protected $table = 'report_submissions';

    protected $fillable = ['tenant_id','type_id','employee_id','user_id','date','status','late','body','files','meta','submitted_at','reviewed_by','reviewed_at','note'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'late' => 'boolean', 'files' => 'array', 'meta' => 'array', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function type(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ReportType::class, 'type_id');
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
