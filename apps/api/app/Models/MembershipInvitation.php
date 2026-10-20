<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipInvitation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }
}
