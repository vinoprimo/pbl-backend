<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlamatToko extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'alamat_toko';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id_alamat_toko';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id_toko',
        'nama_pengirim',
        'no_telepon',
        'alamat_lengkap',
        'provinsi',
        'kota',
        'kecamatan',
        'kode_pos',
        'is_primary'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_primary' => 'boolean'
    ];

    /**
     * Get the store that owns the address.
     */
    public function toko()
    {
        return $this->belongsTo(Toko::class, 'id_toko', 'id_toko');
    }

    /**
     * Get the province associated with the address.
     */
    public function province()
    {
        return $this->belongsTo(Province::class, 'provinsi', 'id');
    }

    /**
     * Get the regency (city) associated with the address.
     */
    public function regency()
    {
        return $this->belongsTo(Regency::class, 'kota', 'id');
    }

    /**
     * Get the district associated with the address.
     */
    public function district()
    {
        return $this->belongsTo(District::class, 'kecamatan', 'id');
    }

    /**
     * Get the village associated with the address based on district.
     * This requires the district_id to be properly set.
     */
    public function village()
    {
        return $this->belongsTo(Village::class, 'kelurahan', 'id');
    }
}
