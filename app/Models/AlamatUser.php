<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlamatUser extends Model
{
    use HasFactory;

    protected $table = 'alamat_user';
    protected $primaryKey = 'id_alamat';
    
    protected $fillable = [
        'id_user',
        'nama_penerima',
        'no_telepon',
        'alamat_lengkap',
        'provinsi',
        'kota',
        'kecamatan',
        'kode_pos',
        'is_primary'
    ];
    
    protected $casts = [
        'is_primary' => 'boolean',
    ];
    
    // Define relationship to User
    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
    
    // Define relationships to region models
    public function province()
    {
        return $this->belongsTo(Province::class, 'provinsi', 'id');
    }
    
    public function regency()
    {
        return $this->belongsTo(Regency::class, 'kota', 'id');
    }
    
    public function district()
    {
        return $this->belongsTo(District::class, 'kecamatan', 'id');
    }
    
    public function village()
    {
        return $this->belongsTo(Village::class, 'desa', 'id');
    }
}
