<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Pesan;
use App\Models\RuangChat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Events\MessageSent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PesanController extends Controller
{
    /**
     * Get messages for a chat room
     */
    public function index(Request $request, int $chatRoomId): JsonResponse
    {
        try {
            $user = Auth::user();
            Log::info("Fetching messages for room {$chatRoomId}");
            
            // Check if the chat room exists
            $chatRoom = RuangChat::find($chatRoomId);
            
            if (!$chatRoom) {
                return response()->json([
                    'success' => false,
                    'message' => "Chat room with ID {$chatRoomId} not found"
                ], 404);
            }
            
            // Ensure user is a participant in this chat room
            if ($chatRoom->id_pembeli != $user->id_user && $chatRoom->id_penjual != $user->id_user) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to access this chat room'
                ], 403);
            }
            
            // Get messages with their authors
            $messages = Pesan::where('id_ruang_chat', $chatRoomId)
                ->with('user')
                ->orderBy('created_at')
                ->get();
            
            // Mark messages from the other user as read
            Pesan::where('id_ruang_chat', $chatRoomId)
                ->where('id_user', '!=', $user->id_user)
                ->where('is_read', false)
                ->update(['is_read' => true]);
            
            // Update the room's updated_at timestamp
            $chatRoom->touch();
            
            return response()->json([
                'success' => true,
                'data' => $messages
            ]);
        } catch (\Exception $e) {
            Log::error("Error fetching messages for room {$chatRoomId}: {$e->getMessage()}");
            
            return response()->json([
                'success' => false,
                'message' => "Failed to load messages: {$e->getMessage()}"
            ], 500);
        }
    }

    /**
     * Send a new message
     */
    public function store(Request $request, int $chatRoomId): JsonResponse
    {
        try {
            $user = Auth::user();
            Log::info("📤 User {$user->id_user} sending message to room {$chatRoomId}", $request->all());
            
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
            
            // Validate the message data based on message type
            $rules = [
                'tipe_pesan' => 'required|in:Text,Penawaran,Gambar,System',
                'isi_pesan' => 'nullable|string',
                'harga_tawar' => 'nullable|numeric|min:1',
                'status_penawaran' => 'nullable|in:Menunggu,Diterima,Ditolak',
                'id_barang' => 'nullable|exists:barang,id_barang',
            ];

            // Add image validation for Gambar type
            if ($request->input('tipe_pesan') === 'Gambar') {
                $rules['image'] = 'required|image|mimes:jpeg,png,jpg,gif|max:5120'; // 5MB max
            }

            $validated = $request->validate($rules);
            
            // Handle image upload for Gambar type messages
            $imagePath = null;
            if ($validated['tipe_pesan'] === 'Gambar' && $request->hasFile('image')) {
                $image = $request->file('image');
                $imagePath = $image->store('chat-images', 'public');
                $validated['isi_pesan'] = $imagePath; // Store the image path in isi_pesan
            }
            
            // Create the message
            $message = new Pesan();
            $message->id_ruang_chat = $chatRoomId;
            $message->id_user = $user->id_user;
            $message->tipe_pesan = $validated['tipe_pesan'];
            $message->isi_pesan = $validated['isi_pesan'] ?? null;
            $message->harga_tawar = $validated['harga_tawar'] ?? null;
            $message->status_penawaran = $validated['status_penawaran'] ?? null;
            $message->id_barang = $validated['id_barang'] ?? null;
            $message->is_read = false;
            $message->save();
            
            Log::info("💾 Message created with ID: {$message->id_pesan}");
            
            // Load the user relation for the broadcast
            $message->load('user');
            
            // Update the room's updated_at timestamp
            $chatRoom->touch();
            
            // Debug: Check if Reverb is running and broadcast config
            Log::info("🔧 Broadcasting configuration check", [
                'broadcast_driver' => config('broadcasting.default'),
                'reverb_config' => config('broadcasting.connections.reverb'),
                'app_env' => config('app.env')
            ]);
            
            // Force immediate broadcast
            try {
                Log::info("📡 Broadcasting MessageSent event IMMEDIATELY for room {$chatRoomId}");
                
                $event = new MessageSent($message);
                
                // Try multiple broadcast methods
                broadcast($event);
                
                // Also try direct pusher broadcast for debugging
                $pusher = app('pusher');
                $chatRoomChannelName = "private-chat-room.{$chatRoomId}";
                $chatListChannelName = "private-chat-list.{$chatRoomId}";
                $eventName = 'MessageSent';
                $eventData = $event->broadcastWith();
                
                Log::info("📡 Direct Pusher broadcast attempt", [
                    'chat_room_channel' => $chatRoomChannelName,
                    'chat_list_channel' => $chatListChannelName,
                    'event' => $eventName,
                    'data_keys' => array_keys($eventData)
                ]);
                
                $pusher->trigger([$chatRoomChannelName, $chatListChannelName], $eventName, $eventData);
                
                Log::info("✅ MessageSent event broadcasted IMMEDIATELY via both methods");
            } catch (\Exception $e) {
                Log::error("❌ Failed to broadcast MessageSent event: " . $e->getMessage());
                Log::error("❌ Broadcast error trace: " . $e->getTraceAsString());
            }
            
            return response()->json([
                'success' => true,
                'data' => $message,
                'message' => 'Message sent successfully'
            ], 201);
        } catch (\Exception $e) {
            Log::error("❌ Error sending message to room {$chatRoomId}: {$e->getMessage()}");
            
            return response()->json([
                'success' => false,
                'message' => "Failed to send message: {$e->getMessage()}"
            ], 500);
        }
    }

    /**
     * Update a message (primarily for offer status)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            Log::info("Updating message {$id}");
            
            $message = Pesan::findOrFail($id);
            
            // Make sure the user can update this offer
            $chatRoom = RuangChat::findOrFail($message->id_ruang_chat);
            
            // Only the seller can accept/reject offers
            if ($chatRoom->id_penjual != $user->id_user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the seller can update offer status'
                ], 403);
            }
            
            // Check if this is an offer message
            if ($message->tipe_pesan !== 'Penawaran') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only offer messages can be updated'
                ], 400);
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
            
            // Broadcast both messages
            $message->load('user');
            $responseMessage->load('user');
            
            event(new MessageSent($message));
            event(new MessageSent($responseMessage));
            
            return response()->json([
                'success' => true,
                'data' => [
                    'updated_offer' => $message,
                    'response_message' => $responseMessage
                ],
                'message' => 'Offer status updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error("Error updating message {$id}: {$e->getMessage()}");
            
            return response()->json([
                'success' => false,
                'message' => "Failed to update message: {$e->getMessage()}"
            ], 500);
        }
    }

    /**
     * Mark a specific message as read
     */
    public function markAsRead(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $message = Pesan::findOrFail($id);
            
            // Check if user is authorized to access this message
            $chatRoom = RuangChat::findOrFail($message->id_ruang_chat);
            if ($chatRoom->id_pembeli != $user->id_user && $chatRoom->id_penjual != $user->id_user) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to access this message'
                ], 403);
            }
            
            // Only mark as read if the message was sent by the other user
            if ($message->id_user != $user->id_user) {
                $message->is_read = true;
                $message->save();
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Message marked as read'
            ]);
        } catch (\Exception $e) {
            Log::error("Error marking message {$id} as read: {$e->getMessage()}");
            
            return response()->json([
                'success' => false,
                'message' => "Failed to mark message as read: {$e->getMessage()}"
            ], 500);
        }
    }
}