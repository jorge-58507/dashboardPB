<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class dpb_income extends Model
{
    protected $primaryKey = 'income_id';
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class, 'income_userid', 'id');
    }
}
