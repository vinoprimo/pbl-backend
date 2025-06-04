<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengajuanPencairan extends Model
{
    use HasFactory;

    protected $table = 'pengajuan_pencairan';
    protected $primaryKey = 'id_pencairan';
    public $timestamps = true;

    protected $fillable = [
        'id_user',
        'id_saldo_penjual',
        'jumlah_dana',
        'keterangan',
        'nomor_rekening',
        'nama_bank',
        'nama_pemilik_rekening',
        'tanggal_pengajuan',
        'status_pencairan',
        'tanggal_pencairan',
        'catatan_admin',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'jumlah_dana' => 'double',
        'tanggal_pengajuan' => 'date',
        'tanggal_pencairan' => 'datetime'
    ];

    /**
     * Relationship with User (who made the withdrawal request)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * Relationship with SaldoPenjual
     */
    public function saldoPenjual()
    {
        return $this->belongsTo(SaldoPenjual::class, 'id_saldo_penjual', 'id_saldo_penjual');
    }

    /**
     * Relationship with User who created the record
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id_user');
    }

    /**
     * Relationship with User who updated the record
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id_user');
    }

    /**
     * Check if withdrawal request can be processed
     */
    public function canBeProcessed()
    {
        return $this->status_pencairan === 'Menunggu';
    }

    /**
     * Check if withdrawal request can be approved
     */
    public function canBeApproved()
    {
        return in_array($this->status_pencairan, ['Menunggu', 'Diproses']);
    }

    /**
     * Check if withdrawal request can be rejected
     */
    public function canBeRejected()
    {
        return in_array($this->status_pencairan, ['Menunggu', 'Diproses']);
    }
}
