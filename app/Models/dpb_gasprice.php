<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class dpb_gasprice extends Model
{
    protected $primaryKey = 'gasprice_id';
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class, 'gasprice_userid', 'id');
    }
}
