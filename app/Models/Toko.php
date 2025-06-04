<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Toko extends Model
{
    use HasFactory;

    protected $table = 'toko';
    protected $primaryKey = 'id_toko';

    // Fields that can be mass-assigned
    protected $fillable = [
        'id_user',
        'nama_toko',
        'slug',
        'deskripsi',
        'alamat',
        'kontak',
        'is_active',
        'is_deleted'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_deleted' => 'boolean'
    ];

    /**
     * Boot function from Laravel.
     */
    protected static function boot()
    {
        parent::boot();
        
        // Auto-generate slug before saving
        static::creating(function ($toko) {
            if (empty($toko->slug)) {
                $toko->slug = Toko::generateUniqueSlug($toko->nama_toko);
            }
        });
        
        // Update slug if store name changes
        static::updating(function ($toko) {
            // If name changed and slug is not manually set, update the slug
            if ($toko->isDirty('nama_toko') && !$toko->isDirty('slug')) {
                $toko->slug = Toko::generateUniqueSlug($toko->nama_toko);
            }
        });
    }
    
    /**
     * Generate a unique slug.
     *
     * @param string $name
     * @return string
     */
    public static function generateUniqueSlug($name)
    {
        $baseSlug = \Illuminate\Support\Str::slug($name);
        $slug = $baseSlug;
        $count = 1;
        
        // Keep incrementing the slug count until we find a unique one
        while (static::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$count}";
            $count++;
        }
        
        return $slug;
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    // Define relationship with User model
    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * Get the addresses for the store.
     */
    public function alamat_toko()
    {
        return $this->hasMany(AlamatToko::class, 'id_toko', 'id_toko')
                    ->with(['province', 'regency', 'district']);
    }

    public function barang()
    {
        return $this->hasMany(Barang::class, 'id_toko', 'id_toko');
    }

    public function detailPembelian()
    {
        return $this->hasMany(DetailPembelian::class, 'id_toko', 'id_toko');
    }
}
