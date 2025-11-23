<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class dpb_inventoryhk extends Model
{
    protected $primaryKey = 'inventoryhk_id';
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class, 'inventoryhk_userid', 'id');
    }
}
