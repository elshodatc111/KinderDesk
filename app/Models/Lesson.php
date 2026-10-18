<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    use BelongsToTenant;

    protected $table = 'lessons';

    protected $fillable = ['tenant_id','subject_id','employee_id','date','held','note','created_by'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'held' => 'boolean'];
    }

    public function subject(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function groups(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'lesson_groups');
    }
}
