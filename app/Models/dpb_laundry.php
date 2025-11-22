<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class dpb_laundry extends Model
{
    protected $primaryKey = 'laundry_id';
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class, 'laundry_userid', 'id');
    }
}
