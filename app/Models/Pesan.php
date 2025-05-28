<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pesan extends Model
{
    use HasFactory;

    protected $table = 'pesan';
    protected $primaryKey = 'id_pesan';
    protected $fillable = [
        'id_ruang_chat', 
        'id_user', 
        'tipe_pesan', 
        'isi_pesan', 
        'harga_tawar', 
        'status_penawaran', 
        'id_barang', 
        'is_read'
    ];

    public function ruangChat()
    {
        return $this->belongsTo(RuangChat::class, 'id_ruang_chat', 'id_ruang_chat');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }
}
