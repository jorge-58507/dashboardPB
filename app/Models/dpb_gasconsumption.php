<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class dpb_gasconsumption extends Model
{
    protected $primaryKey = 'gasconsumption_id';
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class, 'gasconsumption_userid', 'id');
    }
}
