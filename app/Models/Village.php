<?php

/*
 * This file is part of the IndoRegion package.
 *
 * (c) Azis Hapidin <azishapidin.com | azishapidin@gmail.com>
 *
 */

namespace App\Models;

use AzisHapidin\IndoRegion\Traits\VillageTrait;
use Illuminate\Database\Eloquent\Model;
use App\Models\District;

/**
 * Village Model.
 */
class Village extends Model
{
    use VillageTrait;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'villages';

    public $timestamps = false;
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'district_id', 'name'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_at', 
        'updated_at'
    ];

	/**
     * Village belongs to District.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function district()
    {
        return $this->belongsTo(District::class, 'district_id', 'id');
    }

    /**
     * Village has many AlamatUser.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function alamatUser()
    {
        return $this->hasMany(AlamatUser::class, 'kelurahan', 'id');
    }
}
