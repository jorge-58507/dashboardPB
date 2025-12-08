<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'user_status'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function gasConsumptions()   {   return $this->hasMany(dpb_gasconsumption::class, 'gasconsumption_userid', 'id');    }
    public function sales()             {   return $this->hasMany(dpb_sale::class, 'sale_userid', 'id');    }
    public function gasprices()         {   return $this->hasMany(dpb_gasprice::class, 'gasprice_userid', 'id');    }
    public function incomes()           {   return $this->hasMany(dpb_income::class, 'income_userid', 'id');    }
    public function inventoryhks()      {   return $this->hasMany(dpb_inventoryhk::class, 'inventoryhk_userid', 'id');    }
    public function laundries()         {   return $this->hasMany(dpb_laundry::class, 'laundry_userid', 'id');    }
    public function phonecalls()        {   return $this->hasMany(dpb_phonecall::class, 'phonecall_userid', 'id');    }
    
}
