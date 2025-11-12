<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class dpb_phonecall extends Model
{
    protected $primaryKey = 'phonecall_id';
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class, 'phonecall_userid', 'id');
    }
}
