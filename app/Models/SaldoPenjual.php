<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaldoPenjual extends Model
{
    use HasFactory;

    protected $table = 'saldo_penjual';
    protected $primaryKey = 'id_saldo_penjual';
    public $timestamps = true;

    protected $fillable = [
        'id_user',
        'saldo_tersedia',
        'saldo_tertahan'
    ];

    protected $casts = [
        'saldo_tersedia' => 'double',
        'saldo_tertahan' => 'double'
    ];

    protected $appends = ['total_saldo'];

    /**
     * Relationship with User
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * Relationship with PengajuanPencairan
     */
    public function pengajuanPencairan()
    {
        return $this->hasMany(PengajuanPencairan::class, 'id_saldo_penjual', 'id_saldo_penjual');
    }

    /**
     * Get total balance attribute
     */
    public function getTotalSaldoAttribute()
    {
        return $this->saldo_tersedia + $this->saldo_tertahan;
    }

    /**
     * Check if user has sufficient available balance
     */
    public function hasSufficientBalance($amount)
    {
        return $this->saldo_tersedia >= $amount;
    }

    /**
     * Hold balance (move from available to held)
     */
    public function holdBalance($amount)
    {
        if (!$this->hasSufficientBalance($amount)) {
            return false;
        }

        $this->saldo_tersedia -= $amount;
        $this->saldo_tertahan += $amount;
        
        return $this->save();
    }

    /**
     * Release held balance (move from held back to available)
     */
    public function releaseHeldBalance($amount)
    {
        if ($this->saldo_tertahan < $amount) {
            return false;
        }

        $this->saldo_tertahan -= $amount;
        $this->saldo_tersedia += $amount;
        
        return $this->save();
    }

    /**
     * Withdraw balance (remove from held balance permanently)
     */
    public function withdrawBalance($amount)
    {
        if ($this->saldo_tertahan < $amount) {
            return false;
        }

        $this->saldo_tertahan -= $amount;
        
        return $this->save();
    }
}
