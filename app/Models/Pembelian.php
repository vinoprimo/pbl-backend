<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;

class Pembelian extends Model
{
    use HasFactory;

    protected $table = 'pembelian';
    protected $primaryKey = 'id_pembelian';
    public $timestamps = true;
    
    // Update fillable array to match the actual database columns
    protected $fillable = [
        'id_pembeli',
        'id_alamat',
        'kode_pembelian',
        'status_pembelian',
        'catatan_pembeli',
        'is_deleted',
        'created_by',
        'updated_by'
    ];
    
    // Add a global scope to filter out deleted purchases by default
    protected static function boot()
    {
        parent::boot();
        
        static::addGlobalScope('notDeleted', function (Builder $builder) {
            $builder->where('is_deleted', false);
        });
    }
    
    // Define the relationship with the buyer (user)
    public function pembeli()
    {
        return $this->belongsTo(User::class, 'id_pembeli', 'id_user');
    }
    
    // Add an alias relationship for 'user' that points to 'pembeli'
    public function user()
    {
        return $this->belongsTo(User::class, 'id_pembeli', 'id_user');
    }
    
    // Define the relationship with purchase details
    public function detailPembelian()
    {
        return $this->hasMany(DetailPembelian::class, 'id_pembelian', 'id_pembelian');
    }
    
    // Add alias for detail_pembelian relationship
    public function detail_pembelian()
    {
        return $this->detailPembelian();
    }
    
    // Additional helper method to ensure detailPembelian is loaded
    public function getDetailPembelianAttribute()
    {
        // If the relationship is not loaded yet, load it
        if (!$this->relationLoaded('detailPembelian')) {
            $this->load('detailPembelian');
        }
        
        return $this->getRelation('detailPembelian');
    }
    
    // Define the relationship with the invoice
    public function tagihan()
    {
        return $this->hasOne(Tagihan::class, 'id_pembelian', 'id_pembelian');
    }
    
    // Define the relationship with the shipping address
    public function alamat()
    {
        return $this->belongsTo(AlamatUser::class, 'id_alamat', 'id_alamat');
    }
    
    // Define relationship with the user who created the record
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id_user');
    }
    
    // Define relationship with the user who updated the record
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id_user');
    }
    
    // Define the relationship with the shipping information
    public function pengiriman()
    {
        return $this->hasOne(PengirimanPembelian::class, 'id_detail_pembelian', 'id_pembelian');
    }

    // Define the relationship with the items in the purchase
    public function items()
    {
        return $this->hasMany(DetailPembelian::class, 'id_pembelian', 'id_pembelian');
    }
    
    // Define the relationship with the review
    public function review()
    {
        return $this->hasOne(Review::class, 'id_pembelian', 'id_pembelian');
    }
    
    // Define the relationship with complaints
    public function komplain()
    {
        return $this->hasOne(Komplain::class, 'id_pembelian', 'id_pembelian')
            ->with('retur');
    }
    
    // Helper method to check if purchase can be canceled
    public function canBeCancelled()
    {
        return in_array($this->status_pembelian, ['Draft', 'Menunggu Pembayaran']);
    }
    
    // Generate a unique purchase code
    public static function generateKodePembelian()
    {
        $prefix = 'PBL-' . date('ymd');
        $unique = false;
        $code = '';
        
        while (!$unique) {
            $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $code = $prefix . $random;
            
            // Check if code already exists
            $exists = self::where('kode_pembelian', $code)->exists();
            
            if (!$exists) {
                $unique = true;
            }
        }
        
        return $code;
    }

    /**
     * Calculate the total order amount from detail items
     * This can be used when the tagihan is not available
     */
    public function getCalculatedTotalAttribute()
    {
        // If we have a tagihan with total_tagihan, use that
        if ($this->tagihan && $this->tagihan->total_tagihan) {
            return $this->tagihan->total_tagihan;
        }
        
        // Otherwise calculate from detail items
        return $this->detailPembelian->sum('subtotal') ?? 0;
    }

    /**
     * Append calculated_total to the model when converting to array/JSON
     */
    protected $appends = ['calculated_total'];

    // Add a utility method to force fetch even deleted purchases
    public static function withDeleted()
    {
        return static::withoutGlobalScope('notDeleted');
    }
    
    // Check if the purchase contains items from multiple stores
    public function hasMultipleStores()
    {
        $storeIds = $this->detailPembelian()
            ->select('id_toko')
            ->distinct()
            ->pluck('id_toko');
            
        return $storeIds->count() > 1;
    }
    
    // Get store ids in this purchase
    public function getStoreIdsAttribute()
    {
        return $this->detailPembelian()
            ->select('id_toko')
            ->distinct()
            ->pluck('id_toko')
            ->toArray();
    }

    /**
     * Check if order status can be updated to the given status
     * based on the current status
     */
    public function canUpdateStatusTo($newStatus)
    {
        $validTransitions = [
            'Draft' => ['Menunggu Pembayaran', 'Dibatalkan'],
            'Menunggu Pembayaran' => ['Dibayar', 'Dibatalkan'],
            'Dibayar' => ['Diproses', 'Dibatalkan'],
            'Diproses' => ['Dikirim'],
            'Dikirim' => ['Diterima'],
            'Diterima' => ['Selesai']
        ];
        
        $currentStatus = $this->status_pembelian;
        
        return isset($validTransitions[$currentStatus]) && 
               in_array($newStatus, $validTransitions[$currentStatus]);
    }
    
    /**
     * Get all orders for a specific shop by shop ID
     */
    public static function getOrdersForShop($shopId)
    {
        return self::whereHas('detailPembelian', function ($query) use ($shopId) {
                $query->where('id_toko', $shopId);
            })
            ->where('is_deleted', false)
            ->orderBy('updated_at', 'desc');
    }
    
    /**
     * Define the relationship with saldo perusahaan
     */
    public function saldoPerusahaan()
    {
        return $this->hasMany(SaldoPerusahaan::class, 'id_pembelian', 'id_pembelian');
    }
}
