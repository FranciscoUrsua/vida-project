<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'country_id'];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function socialUsers()
    {
        return $this->hasMany(SocialUser::class, 'region_id');
    }

    public function ruu()
    {
        return $this->hasMany(Ruu::class, 'region_id');
    }
}
