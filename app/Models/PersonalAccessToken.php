<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * Sanctum writes last_used_at on every authenticated request, which is one
     * DB write per API call. Skip the write if the stored value is less than
     * five minutes old and nothing else changed (same approach as ode-api).
     */
    public function save(array $options = []): bool
    {
        if (array_keys($this->getDirty()) === ['last_used_at']) {
            $lastUsed = $this->getOriginal('last_used_at');
            if ($lastUsed && $lastUsed->gt(now()->subMinutes(5))) {
                return true;
            }
        }

        return parent::save($options);
    }
}
