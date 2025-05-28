<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Keranjang extends Model
{
    use HasFactory;

    protected $table = 'keranjang';
    protected $primaryKey = 'id_keranjang';
    
    protected $fillable = [
        'id_user',
        'id_barang',
        'jumlah',
        'is_selected'
    ];

    /**
     * Get the user who owns this cart item
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * Get the product associated with this cart item
     */
    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }

    /**
     * Scope a query to only include cart items from a specific user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('id_user', $userId);
    }

    /**
     * Scope a query to only include selected cart items
     */
    public function scopeSelected($query)
    {
        return $query->where('is_selected', true);
    }
}
