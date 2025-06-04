<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturBarang extends Model
{
    protected $table = 'retur_barang';
    protected $primaryKey = 'id_retur';

    protected $fillable = [
        'id_user',
        'id_pembelian',
        'id_detail_pembelian',
        'id_komplain',
        'alasan_retur',
        'deskripsi_retur',
        'foto_bukti',
        'status_retur',
        'admin_notes',
        'tanggal_pengajuan',
        'tanggal_disetujui',
        'tanggal_selesai',
        'created_by',
        'updated_by'
    ];

    protected $dates = [
        'tanggal_pengajuan',
        'tanggal_disetujui',
        'tanggal_selesai',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'tanggal_pengajuan' => 'datetime',
        'tanggal_disetujui' => 'datetime',
        'tanggal_selesai' => 'datetime',
        'alasan_retur' => 'string',
        'status_retur' => 'string'
    ];

    // User who created the return request
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    // Related purchase
    public function pembelian(): BelongsTo
    {
        return $this->belongsTo(Pembelian::class, 'id_pembelian', 'id_pembelian');
    }

    // Related purchase detail
    public function detailPembelian(): BelongsTo
    {
        return $this->belongsTo(DetailPembelian::class, 'id_detail_pembelian', 'id_detail');
    }

    // Related complaint
    public function komplain(): BelongsTo
    {
        return $this->belongsTo(Komplain::class, 'id_komplain', 'id_komplain');
    }

    // Admin who created the record
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id_user');
    }

    // Admin who last updated the record
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'id_user');
    }

    // Accessor for foto_bukti to ensure URL is complete
    public function getFotoBuktiAttribute($value)
    {
        if (!$value) return null;
        if (filter_var($value, FILTER_VALIDATE_URL)) return $value;
        return url($value);
    }

    // Helper method to check if status can be updated
    public function canUpdateStatus(): bool
    {
        return !in_array($this->status_retur, ['Selesai', 'Ditolak']);
    }

    // Helper to get formatted created date
    public function getFormattedCreatedAtAttribute(): string
    {
        return $this->created_at->format('d F Y H:i');
    }

    // Helper to get retur status label
    public function getStatusLabelAttribute(): string
    {
        return match($this->status_retur) {
            'Menunggu Persetujuan' => 'Waiting for Approval',
            'Disetujui' => 'Approved',
            'Ditolak' => 'Rejected',
            'Diproses' => 'Processing',
            'Selesai' => 'Completed',
            default => $this->status_retur,
        };
    }

    // Boot method for model events
    protected static function boot()
    {
        parent::boot();

        // Set default values when creating
        static::creating(function ($retur) {
            if (!$retur->status_retur) {
                $retur->status_retur = 'Menunggu Persetujuan';
            }
            if (!$retur->tanggal_pengajuan) {
                $retur->tanggal_pengajuan = now();
            }
        });

        // Record who created/updated the record
        static::creating(function ($retur) {
            if (auth()->check()) {
                $retur->created_by = auth()->id();
                $retur->updated_by = auth()->id();
            }
        });

        static::updating(function ($retur) {
            if (auth()->check()) {
                $retur->updated_by = auth()->id();
            }
        });
    }
}
