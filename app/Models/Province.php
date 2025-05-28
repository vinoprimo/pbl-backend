<?php

/*
 * This file is part of the IndoRegion package.
 *
 * (c) Azis Hapidin <azishapidin.com | azishapidin@gmail.com>
 *
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Province Model.
 */
class Province extends Model
{
    protected $table = 'provinces';
    public $timestamps = false;
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'name'];

    /**
     * Province has many regencies.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function regencies()
    {
        return $this->hasMany(Regency::class, 'province_id', 'id');
    }

    public function alamatUser()
    {
        return $this->hasMany(AlamatUser::class, 'provinsi', 'id');
    }

    // Hide timestamps in JSON responses
    protected $hidden = [
        'created_at', 
        'updated_at'
    ];
}
