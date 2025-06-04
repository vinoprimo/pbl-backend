<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Komplain extends Model
{
    protected $table = 'komplain';
    protected $primaryKey = 'id_komplain';

    protected $fillable = [
        'id_user',
        'id_pembelian',
        'alasan_komplain',
        'isi_komplain',
        'bukti_komplain',
        'status_komplain',
        'admin_notes',
        'processed_by',
        'processed_at'
    ];

    // Add status constants
    const STATUS_MENUNGGU = 'Menunggu';
    const STATUS_DIPROSES = 'Diproses';
    const STATUS_DITOLAK = 'Ditolak';
    const STATUS_SELESAI = 'Selesai';

    protected $dates = [
        'processed_at',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    // Relationship with User model
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    // Relationship with Pembelian model
    public function pembelian(): BelongsTo
    {
        return $this->belongsTo(Pembelian::class, 'id_pembelian', 'id_pembelian')
            ->with(['detailPembelian' => function($query) {
                $query->select('id_detail', 'id_pembelian', 'id_barang', 'id_toko');
            }]);
    }

    // Relationship with User model for processed_by
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by', 'id_user');
    }

    // Add retur relationship
    public function retur(): HasOne
    {
        return $this->hasOne(ReturBarang::class, 'id_komplain', 'id_komplain');
    }

    // Helper method to check if komplain can be updated
    public function canBeUpdated(): bool
    {
        return $this->status_komplain !== 'Selesai';
    }

    // Add helper method to check if complaint can be processed
    public function canBeProcessed(): bool
    {
        return $this->status_komplain === self::STATUS_MENUNGGU;
    }

    // Add helper method to check if complaint is rejected
    public function isRejected(): bool
    {
        return $this->status_komplain === self::STATUS_DITOLAK;
    }

    // Add any necessary accessors
    public function getBuktiKomplainAttribute($value)
    {
        if (!$value) return null;
        return $value;
    }
}
