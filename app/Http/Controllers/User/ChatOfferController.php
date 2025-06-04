<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Pesan;
use App\Models\RuangChat;
use App\Events\MessageSent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ChatOfferController extends Controller
{
    /**
     * Send an offer
     */
    public function store(Request $request, int $chatRoomId): JsonResponse
    {
        try {
            $user = Auth::user();
            Log::info("💰 User {$user->id_user} sending offer to room {$chatRoomId}", $request->all());
            
            // Verify that the chat room exists
            $chatRoom = RuangChat::find($chatRoomId);
            
            if (!$chatRoom) {
                Log::error("❌ Chat room {$chatRoomId} not found");
                return response()->json([
                    'success' => false,
                    'message' => "Chat room with ID {$chatRoomId} not found"
                ], 404);
            }
            
            // Ensure user is a participant in this chat room
            if ($chatRoom->id_pembeli != $user->id_user && $chatRoom->id_penjual != $user->id_user) {
                Log::error("❌ User {$user->id_user} not authorized for room {$chatRoomId}");
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to access this chat room'
                ], 403);
            }
            
            // Only buyers can make offers
            if ($chatRoom->id_pembeli != $user->id_user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only buyers can make offers'
                ], 403);
            }
            
            $validated = $request->validate([
                'harga_tawar' => 'required|numeric|min:1',
                'isi_pesan' => 'nullable|string|max:500',
                'quantity' => 'nullable|integer|min:1'
            ]);
            
            // Create the offer message
            $message = new Pesan();
            $message->id_ruang_chat = $chatRoomId;
            $message->id_user = $user->id_user;
            $message->tipe_pesan = 'Penawaran';
            $message->isi_pesan = $validated['isi_pesan'] ?? "Penawaran harga untuk produk";
            $message->harga_tawar = $validated['harga_tawar'];
            $message->status_penawaran = 'Menunggu'; // Default status
            $message->id_barang = $chatRoom->id_barang; // Link to product being discussed
            $message->is_read = false;
            $message->save();
            
            Log::info("💾 Offer message created with ID: {$message->id_pesan}");
            
            // Load the user relation for the broadcast
            $message->load('user');
            
            // Update the room's updated_at timestamp
            $chatRoom->touch();
            
            // Broadcast the offer
            try {
                Log::info("📡 Broadcasting offer message for room {$chatRoomId}");
                
                $event = new MessageSent($message);
                broadcast($event);
                
                Log::info("✅ Offer message broadcasted successfully");
            } catch (\Exception $e) {
                Log::error("❌ Failed to broadcast offer message: " . $e->getMessage());
            }
            
            return response()->json([
                'success' => true,
                'data' => $message,
                'message' => 'Offer sent successfully'
            ], 201);
        } catch (\Exception $e) {
            Log::error("❌ Error sending offer to room {$chatRoomId}: {$e->getMessage()}");
            
            return response()->json([
                'success' => false,
                'message' => "Failed to send offer: {$e->getMessage()}"
            ], 500);
        }
    }

    /**
     * Respond to an offer (accept/reject)
     */
    public function respond(Request $request, int $messageId): JsonResponse
    {
        try {
            $user = Auth::user();
            Log::info("📝 User {$user->id_user} responding to offer {$messageId}", $request->all());
            
            $message = Pesan::findOrFail($messageId);
            
            // Verify this is an offer message
            if ($message->tipe_pesan !== 'Penawaran') {
                return response()->json([
                    'success' => false,
                    'message' => 'This is not an offer message'
                ], 400);
            }
            
            // Get the chat room
            $chatRoom = RuangChat::findOrFail($message->id_ruang_chat);
            
            // Only the seller can respond to offers
            if ($chatRoom->id_penjual != $user->id_user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the seller can respond to offers'
                ], 403);
            }
            
            // Check if offer is still pending
            if ($message->status_penawaran !== 'Menunggu') {
                return response()->json([
                    'success' => false,
                    'message' => 'This offer has already been responded to'
                ], 400);
            }
            
            $validated = $request->validate([
                'status_penawaran' => 'required|in:Diterima,Ditolak',
                'response_message' => 'nullable|string|max:200'
            ]);
            
            // Update the offer status
            $message->status_penawaran = $validated['status_penawaran'];
            $message->save();
            
            // Create a system response message
            $responseText = $validated['status_penawaran'] === 'Diterima' 
                ? "✅ Penawaran diterima" 
                : "❌ Penawaran ditolak";
            
            if (!empty($validated['response_message'])) {
                $responseText .= ": " . $validated['response_message'];
            }
            
            $responseMessage = new Pesan();
            $responseMessage->id_ruang_chat = $message->id_ruang_chat;
            $responseMessage->id_user = $user->id_user;
            $responseMessage->tipe_pesan = 'System';
            $responseMessage->isi_pesan = $responseText;
            $responseMessage->is_read = false;
            $responseMessage->save();
            
            // Update the room's updated_at timestamp
            $chatRoom->touch();
            
            // Broadcast the updated offer message first
            try {
                $message->load('user');
                $responseMessage->load('user');
                
                // Broadcast updated offer with new status
                Log::info("📡 Broadcasting updated offer message");
                broadcast(new MessageSent($message));
                
                // Then broadcast the system response message
                Log::info("📡 Broadcasting system response message");
                broadcast(new MessageSent($responseMessage));
                
                Log::info("✅ Offer response broadcasted successfully");
            } catch (\Exception $e) {
                Log::error("❌ Failed to broadcast offer response: " . $e->getMessage());
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'updated_offer' => $message,
                    'response_message' => $responseMessage
                ],
                'message' => 'Offer response sent successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error("❌ Error responding to offer {$messageId}: {$e->getMessage()}");
            
            return response()->json([
                'success' => false,
                'message' => "Failed to respond to offer: {$e->getMessage()}"
            ], 500);
        }
    }

    /**
     * Create purchase from accepted offer using same logic as buy-now
     */
    public function createPurchaseFromOffer(Request $request, int $messageId): JsonResponse
    {
        try {
            $user = Auth::user();
            Log::info("🛒 User {$user->id_user} creating purchase from offer {$messageId}");
            
            $offerMessage = Pesan::with(['ruangChat.barang', 'ruangChat.penjual'])
                ->findOrFail($messageId);
            
            // Verify this is an accepted offer
            if ($offerMessage->tipe_pesan !== 'Penawaran' || $offerMessage->status_penawaran !== 'Diterima') {
                return response()->json([
                    'success' => false,
                    'message' => 'This offer is not accepted'
                ], 400);
            }
            
            // Verify user is the buyer
            $chatRoom = $offerMessage->ruangChat;
            if ($chatRoom->id_pembeli != $user->id_user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the buyer can create purchase from offer'
                ], 403);
            }
            
            // Check if purchase already exists from this offer FIRST
            $existingDetail = \App\Models\DetailPembelian::where('id_pesan', $messageId)
                ->with('pembelian')
                ->first();
            
            if ($existingDetail && $existingDetail->pembelian) {
                return response()->json([
                    'success' => false,
                    'message' => 'Purchase already exists from this offer',
                    'existing_purchase' => [
                        'kode_pembelian' => $existingDetail->pembelian->kode_pembelian,
                        'status' => $existingDetail->pembelian->status_pembelian,
                        'created_at' => $existingDetail->pembelian->created_at
                    ]
                ], 409); // Conflict status code
            }
            
            $validated = $request->validate([
                'jumlah' => 'required|integer|min:1',
                'id_alamat' => 'required|exists:alamat_user,id_alamat'
            ]);
            
            $quantity = $validated['jumlah'];
            $barang = $chatRoom->barang;
            
            // Check if address belongs to user
            $alamat = \App\Models\AlamatUser::where('id_alamat', $validated['id_alamat'])
                                          ->where('id_user', $user->id_user)
                                          ->first();

            if (!$alamat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid address'
                ], 400);
            }
            
            // Check product availability
            if ($barang->status_barang != 'Tersedia' || $barang->is_deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product is no longer available'
                ], 400);
            }
            
            // Check stock
            if ($barang->stok < $quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock'
                ], 400);
            }
            
            \DB::beginTransaction();
            try {
                // Create purchase using same pattern as buy-now
                $pembelian = new \App\Models\Pembelian();
                $pembelian->id_pembeli = $user->id_user;
                $pembelian->id_alamat = $validated['id_alamat'];
                $pembelian->kode_pembelian = \App\Models\Pembelian::generateKodePembelian();
                $pembelian->status_pembelian = 'Draft';
                $pembelian->catatan_pembeli = "Purchase from accepted offer - Message ID: {$messageId}";
                $pembelian->is_deleted = false;
                $pembelian->created_by = $user->id_user;
                $pembelian->save();
                
                // Create purchase detail with offer price instead of original price
                $detail = new \App\Models\DetailPembelian();
                $detail->id_pembelian = $pembelian->id_pembelian;
                $detail->id_barang = $barang->id_barang;
                $detail->id_toko = $barang->id_toko;
                $detail->id_pesan = $offerMessage->id_pesan; // Link to offer message
                $detail->harga_satuan = $offerMessage->harga_tawar; // Use offer price instead of original price
                $detail->jumlah = $quantity;
                $detail->subtotal = $offerMessage->harga_tawar * $quantity;
                $detail->save();
                
                // Create system message in chat to indicate purchase created
                $systemMessage = new Pesan();
                $systemMessage->id_ruang_chat = $chatRoom->id_ruang_chat;
                $systemMessage->id_user = $user->id_user;
                $systemMessage->tipe_pesan = 'System';
                $systemMessage->isi_pesan = "✅ Pesanan berhasil dibuat dari penawaran. Kode: {$pembelian->kode_pembelian}";
                $systemMessage->is_read = false;
                $systemMessage->save();
                
                // Broadcast the system message
                $systemMessage->load('user');
                broadcast(new MessageSent($systemMessage));
                
                \DB::commit();
                
                Log::info("✅ Purchase created from offer successfully", [
                    'purchase_code' => $pembelian->kode_pembelian,
                    'offer_price' => $offerMessage->harga_tawar,
                    'quantity' => $quantity,
                    'savings' => ($barang->harga - $offerMessage->harga_tawar) * $quantity
                ]);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'kode_pembelian' => $pembelian->kode_pembelian,
                        'id_pembelian' => $pembelian->id_pembelian,
                        'offer_price' => $offerMessage->harga_tawar,
                        'original_price' => $barang->harga,
                        'quantity' => $quantity,
                        'savings' => ($barang->harga - $offerMessage->harga_tawar) * $quantity
                    ],
                    'message' => 'Purchase created from offer successfully'
                ], 201);
                
            } catch (\Exception $e) {
                \DB::rollback();
                throw $e;
            }
            
        } catch (\Exception $e) {
            Log::error("❌ Error creating purchase from offer {$messageId}: {$e->getMessage()}");
            
            return response()->json([
                'success' => false,
                'message' => "Failed to create purchase from offer: {$e->getMessage()}"
            ], 500);
        }
    }

    /**
     * Check if purchase already exists from this offer
     */
    public function checkExistingPurchase(int $messageId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $offerMessage = Pesan::findOrFail($messageId);
            
            // Verify this is an accepted offer
            if ($offerMessage->tipe_pesan !== 'Penawaran' || $offerMessage->status_penawaran !== 'Diterima') {
                return response()->json([
                    'success' => false,
                    'message' => 'This offer is not accepted'
                ], 400);
            }
            
            // Verify user is the buyer
            $chatRoom = $offerMessage->ruangChat;
            if ($chatRoom->id_pembeli != $user->id_user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the buyer can check purchase from offer'
                ], 403);
            }
            
            // Check if purchase already exists from this offer
            $existingDetail = \App\Models\DetailPembelian::where('id_pesan', $messageId)
                ->with('pembelian')
                ->first();
            
            if ($existingDetail && $existingDetail->pembelian) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'kode_pembelian' => $existingDetail->pembelian->kode_pembelian,
                        'status' => $existingDetail->pembelian->status_pembelian,
                        'created_at' => $existingDetail->pembelian->created_at
                    ],
                    'message' => 'Purchase already exists from this offer'
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'No existing purchase found from this offer'
            ], 404);
            
        } catch (\Exception $e) {
            Log::error("❌ Error checking existing purchase from offer {$messageId}: {$e->getMessage()}");
            
            return response()->json([
                'success' => false,
                'message' => "Failed to check existing purchase: {$e->getMessage()}"
            ], 500);
        }
    }
}
