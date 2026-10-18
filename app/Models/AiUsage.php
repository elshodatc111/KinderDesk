<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Platforma jurnali: tenant scope yo'q, chunki super admin hammasini jami ko'radi (faqat sonlar). */
class AiUsage extends Model
{
    protected $table = 'ai_usage';

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'user_id', 'kind', 'model', 'input_tokens', 'output_tokens', 'cache_tokens', 'cost_micro', 'status', 'payload', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
