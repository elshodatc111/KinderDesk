<?php

namespace App\Models\Scopes;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $ctx = app(TenantContext::class);

        if ($ctx->id()) {
            $builder->where($model->getTable().'.tenant_id', $ctx->id());
        } elseif ($ctx->isStrict()) {
            // Xavfsizlik: bog'cha aniqlanmagan bo'lsa hech qanday ma'lumot ko'rinmaydi
            $builder->whereRaw('1 = 0');
        }
    }
}
