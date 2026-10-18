<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AiChat extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'user_id', 'lang', 'question', 'answer', 'masked', 'rating'];

    protected function casts(): array
    {
        return ['masked' => 'boolean'];
    }
}
