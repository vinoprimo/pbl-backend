<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailPembelian extends Model
{
    use HasFactory;

    protected $table = 'detail_pembelian';
    protected $primaryKey = 'id_detail';
    
    protected $fillable = [
        'id_pembelian',
        'id_barang',
        'id_toko',
        'id_keranjang',
        'id_pesan', // Add this for offer message reference
        'harga_satuan',
        'jumlah',
        'subtotal'
    ];

    /**
     * Relationship with Pembelian
     */
    public function pembelian()
    {
        return $this->belongsTo(Pembelian::class, 'id_pembelian', 'id_pembelian');
    }
    
    /**
     * Relationship with Barang
     */
    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }
    
    /**
     * Relationship with Toko
     */
    public function toko()
    {
        return $this->belongsTo(Toko::class, 'id_toko', 'id_toko');
    }

    /**
     * Relationship with Keranjang
     */
    public function keranjang()
    {
        return $this->belongsTo(Keranjang::class, 'id_keranjang', 'id_keranjang');
    }

    // Relationship to offer message
    public function pesanPenawaran()
    {
        return $this->belongsTo(Pesan::class, 'id_pesan', 'id_pesan');
    }

    /**
     * Get the shipping information record associated with this purchase detail
     */
    public function pengiriman_pembelian()
    {
        return $this->hasOne(PengirimanPembelian::class, 'id_detail_pembelian', 'id_detail');
    }

    // Check if this detail was created from an offer
    public function isFromOffer()
    {
        return !is_null($this->id_pesan);
    }

    // Get the offer price if this was from an offer
    public function getOfferPrice()
    {
        if ($this->isFromOffer() && $this->pesanPenawaran) {
            return $this->pesanPenawaran->harga_tawar;
        }
        return null;
    }

    // Get the original product price
    public function getOriginalPrice()
    {
        return $this->barang ? $this->barang->harga : null;
    }

    // Calculate savings if this was from an offer
    public function getSavings()
    {
        if ($this->isFromOffer()) {
            $originalPrice = $this->getOriginalPrice();
            $offerPrice = $this->getOfferPrice();
            
            if ($originalPrice && $offerPrice) {
                return ($originalPrice - $offerPrice) * $this->jumlah;
            }
        }
        return 0;
    }

    // Keep the camelCase relationship for backwards compatibility
    public function pengirimanPembelian()
    {
        return $this->pengiriman_pembelian();
    }
}
