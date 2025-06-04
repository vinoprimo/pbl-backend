<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengirimanPembelian extends Model
{
    use HasFactory;

    protected $table = 'pengiriman_pembelian';
    protected $primaryKey = 'id_pengiriman';
    public $timestamps = true;
    
    protected $fillable = [
        'id_detail_pembelian',
        'nomor_resi',
        'tanggal_pengiriman',
        'bukti_pengiriman',
        'catatan_pengiriman'
    ];
    
    // Relationship with DetailPembelian - fix the foreign key reference
    public function detailPembelian()
    {
        return $this->belongsTo(DetailPembelian::class, 'id_detail_pembelian', 'id_detail');
    }
}
