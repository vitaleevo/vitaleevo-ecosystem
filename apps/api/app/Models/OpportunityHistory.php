<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpportunityHistory extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }
}
