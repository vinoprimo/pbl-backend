<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Tagihan extends Model
{
    use HasFactory;

    protected $table = 'tagihan';
    protected $primaryKey = 'id_tagihan';
    protected $guarded = [];

    protected $fillable = [
        'id_pembelian',
        'kode_tagihan',
        'total_harga',
        'biaya_kirim',
        'opsi_pengiriman',
        'biaya_admin',
        'total_tagihan',
        'metode_pembayaran',
        'midtrans_transaction_id',
        'midtrans_payment_type',
        'midtrans_status',
        'status_pembayaran',
        'deadline_pembayaran',
        'tanggal_pembayaran',
        'snap_token',
        'payment_url',
        'group_id',
    ];
    
    protected $dates = [
        'deadline_pembayaran',
        'tanggal_pembayaran',
    ];

    /**
     * Get the purchase associated with this invoice
     */
    public function pembelian()
    {
        return $this->belongsTo(Pembelian::class, 'id_pembelian', 'id_pembelian');
    }

    /**
     * Generate unique invoice code
     */
    public static function generateKodeTagihan()
    {
        $prefix = 'INV';
        $year = date('Y');
        $month = date('m');
        $day = date('d');
        $random = mt_rand(1000, 9999);
        
        return "{$prefix}{$year}{$month}{$day}{$random}";
    }
    
    /**
     * Set payment deadline based on hours
     */
    public function setPaymentDeadline($hours = 24)
    {
        $this->deadline_pembayaran = Carbon::now()->addHours($hours);
        return $this;
    }
    
    /**
     * Check if the invoice is expired
     */
    public function isExpired()
    {
        return Carbon::now()->isAfter($this->deadline_pembayaran);
    }
    
    /**
     * Check and update status if expired
     */
    public function checkExpiry()
    {
        if ($this->status_pembayaran === 'Menunggu' && $this->isExpired()) {
            $this->status_pembayaran = 'Expired';
            $this->save();
            
            // If this is part of a group, update all related invoices
            if ($this->group_id) {
                self::where('group_id', $this->group_id)
                    ->where('id_tagihan', '!=', $this->id_tagihan)
                    ->where('status_pembayaran', 'Menunggu')
                    ->update(['status_pembayaran' => 'Expired']);
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Find all invoices in the same group
     */
    public function getGroupInvoices()
    {
        if (!$this->group_id) {
            return collect([$this]);
        }
        
        return self::where('group_id', $this->group_id)->get();
    }
    
    /**
     * Check if payment is valid (not expired)
     */
    public function isValid()
    {
        if ($this->checkExpiry()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Handle payment success for all invoices in a group
     */
    public function markGroupAsPaid()
    {
        if (!$this->group_id) {
            return;
        }
        
        // Update all invoices in the group
        self::where('group_id', $this->group_id)
            ->where('status_pembayaran', 'Menunggu')
            ->update([
                'status_pembayaran' => 'Dibayar',
                'tanggal_pembayaran' => now()
            ]);
            
        // Update all related purchases to paid status
        $invoices = self::where('group_id', $this->group_id)->get();
        foreach ($invoices as $invoice) {
            if ($invoice->pembelian) {
                $invoice->pembelian->status_pembelian = 'Dibayar';
                $invoice->pembelian->save();
            }
        }
    }
    
    // Check if payment is already completed
    public function isPaid()
    {
        return $this->status_pembayaran == 'Dibayar';
    }
    
    // Calculate the total invoice amount
    public function calculateTotal()
    {
        return $this->total_harga + $this->biaya_kirim + $this->biaya_admin;
    }
    
    // Update payment status based on Midtrans callback
    public function updateFromMidtransCallback($midtransStatus)
    {
        $this->midtrans_status = $midtransStatus;
        
        if ($midtransStatus == 'settlement' || $midtransStatus == 'capture') {
            $this->status_pembayaran = 'Dibayar';
            $this->tanggal_pembayaran = Carbon::now();
        } 
        elseif ($midtransStatus == 'pending') {
            $this->status_pembayaran = 'Menunggu';
        }
        elseif ($midtransStatus == 'deny' || $midtransStatus == 'cancel' || $midtransStatus == 'expire') {
            $this->status_pembayaran = 'Gagal';
            
            // Update related purchase status but don't change stock since it wasn't reduced
            if ($this->pembelian) {
                $this->pembelian->status_pembelian = 'Dibatalkan';
                $this->pembelian->save();
            }
        }
        
        return $this->save();
    }
}
