<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RuangChat extends Model
{
    use HasFactory;

    protected $table = 'ruang_chat';
    protected $primaryKey = 'id_ruang_chat';
    protected $fillable = ['id_pembeli', 'id_penjual', 'id_barang', 'status'];

    public function pesan()
    {
        return $this->hasMany(Pesan::class, 'id_ruang_chat', 'id_ruang_chat');
    }

    // Add a relationship for getting the last message
    public function lastMessage()
    {
        return $this->hasOne(Pesan::class, 'id_ruang_chat', 'id_ruang_chat')
                    ->latest('created_at')
                    ->with('user');
    }

    public function pembeli()
    {
        return $this->belongsTo(User::class, 'id_pembeli', 'id_user');
    }

    public function penjual()
    {
        return $this->belongsTo(User::class, 'id_penjual', 'id_user');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }
}
