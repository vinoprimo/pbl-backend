<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaldoPerusahaan extends Model
{
    use HasFactory;

    protected $table = 'saldo_perusahaan';
    protected $primaryKey = 'id_saldo_perusahaan';
    public $timestamps = true;

    protected $fillable = [
        'id_pembelian',
        'id_penjual',
        'jumlah_saldo',
        'status'
    ];

    protected $casts = [
        'jumlah_saldo' => 'double'
    ];

    /**
     * Relationship with Pembelian
     */
    public function pembelian()
    {
        return $this->belongsTo(Pembelian::class, 'id_pembelian', 'id_pembelian');
    }

    /**
     * Relationship with User (Penjual)
     */
    public function penjual()
    {
        return $this->belongsTo(User::class, 'id_penjual', 'id_user');
    }

    /**
     * Check if saldo is ready to be withdrawn
     */
    public function isReadyToWithdraw()
    {
        return $this->status === 'Siap Dicairkan';
    }

    /**
     * Check if saldo is already withdrawn
     */
    public function isWithdrawn()
    {
        return $this->status === 'Dicairkan';
    }
}
