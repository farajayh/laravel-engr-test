<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Claim extends Model
{
    use HasFactory;

    protected $table = 'claims';

    public function insurer()
    {
        return $this->belongsTo(Insurer::class, 'insurer_code', 'code');
    }
} 