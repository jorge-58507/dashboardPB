<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class dpb_sale extends Model
{
    protected $primaryKey = 'sale_id';
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class, 'sale_userid', 'id');
    }
}
