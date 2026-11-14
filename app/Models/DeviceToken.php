<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceToken extends Model
{
    public const ENVIRONMENTS = ['sandbox', 'production'];

    protected $fillable = ['user_id', 'token', 'environment'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
