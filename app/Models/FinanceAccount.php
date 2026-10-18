<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Direktorning hisob raqami (karta yoki bank rekviziti). */
class FinanceAccount extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'kind', 'title', 'number', 'holder', 'bank_name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function masked(): string
    {
        $n = preg_replace('/\s+/', '', $this->number);

        return strlen($n) > 8 ? substr($n, 0, 4).' •••• '.substr($n, -4) : $this->number;
    }
}
