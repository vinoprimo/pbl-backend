<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Barang extends Model 
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'barang';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id_barang';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id_kategori',
        'id_toko',
        'nama_barang',
        'slug',
        'deskripsi_barang',
        'harga',
        'grade',
        'status_barang',
        'stok',
        'kondisi_detail',
        'berat_barang',
        'dimensi',
        'is_deleted',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_deleted' => 'boolean',
        'harga' => 'float',
        'berat_barang' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the kategori that owns the barang.
     */
    public function kategori()
    {
        return $this->belongsTo(Kategori::class, 'id_kategori', 'id_kategori');
    }

    /**
     * Get the toko that owns the barang with its address.
     */
    public function toko()
    {
        return $this->belongsTo(Toko::class, 'id_toko', 'id_toko')
                    ->with(['alamat_toko' => function($query) {
                        $query->where('is_primary', true);
                    }]);
    }

    /**
     * Get the creator user.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id_user');
    }

    /**
     * Get the updater user.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id_user');
    }

    /**
     * Get all images for this product
     */
    public function gambar_barang()
    {
        return $this->hasMany(GambarBarang::class, 'id_barang', 'id_barang');
    }

    /**
     * Get all images for this product (camelCase alias for compatibility)
     */
    public function gambarBarang()
    {
        return $this->gambar_barang();
    }
}
