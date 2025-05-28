<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\RuangChat;
use App\Models\Pesan;
use App\Models\Barang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class RuangChatController extends Controller
{
    /**
     * Get all chat rooms for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            Log::info("Fetching chat rooms for user {$user->id_user}");
            
            // Get all rooms where the user is either buyer or seller
            $chatRooms = RuangChat::where('id_pembeli', $user->id_user)
                ->orWhere('id_penjual', $user->id_user)
                ->with([
                    'pembeli', 
                    'penjual', 
                    'barang',
                    // Load the last message with user information
                    'pesan' => function($query) {
                        $query->latest('created_at')
                              ->with('user')
                              ->limit(1);
                    }
                ])
                ->withCount(['pesan as unread_messages' => function (Builder $query) use ($user) {
                    $query->where('is_read', false)
                        ->where('id_user', '!=', $user->id_user);
                }])
                ->latest('updated_at')
                ->get();
            
            // Transform the data to include last_message for easier frontend access
            $chatRooms->transform(function ($room) {
                // Get the last message
                $lastMessage = $room->pesan->first();
                
                // Add last_message as a separate property
                $room->last_message = $lastMessage;
                
                // Keep the pesan relationship as is for backward compatibility
                // but limit it to just the last message for performance
                $room->setRelation('pesan', $room->pesan->take(1));
                
                return $room;
            });
            
            return response()->json([
                'success' => true,
                'data' => $chatRooms
            ]);
        } catch (\Exception $e) {
            Log::error("Error fetching chat rooms: {$e->getMessage()}");
            
            return response()->json([
                'success' => false,
                'message' => "Failed to load chat rooms: {$e->getMessage()}"
            ], 500);
        }
    }

    /**
     * Create a new chat room or find existing one
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            Log::info("Creating chat room for user {$user->id_user}", $request->all());
            
            $validated = $request->validate([
                'id_penjual' => 'required|exists:users,id_user',
                'id_barang' => 'nullable|exists:barang,id_barang',
            ]);
            
            // Buyer is the current user
            $buyerId = $user->id_user;
            $sellerId = $validated['id_penjual'];
            
            // Cannot chat with yourself
            if ($buyerId === $sellerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot create a chat with yourself'
                ], 400);
            }
            
            // Check if chat room already exists for this product
            if (isset($validated['id_barang'])) {
                $existingRoom = RuangChat::where('id_pembeli', $buyerId)
                    ->where('id_penjual', $sellerId)
                    ->where('id_barang', $validated['id_barang'])
                    ->with(['pembeli', 'penjual', 'barang'])
                    ->first();
                
                if ($existingRoom) {
                    // Update timestamp
                    $existingRoom->touch();
                    
                    return response()->json([
                        'success' => true,
                        'data' => $existingRoom,
                        'message' => 'Using existing chat room'
                    ]);
                }
            }
            
            // Check if any chat room exists between these users
            $existingRoom = RuangChat::where('id_pembeli', $buyerId)
                ->where('id_penjual', $sellerId)
                ->with(['pembeli', 'penjual', 'barang'])
                ->first();
        
            if ($existingRoom) {
                // Update product if provided
                if (isset($validated['id_barang']) && $existingRoom->id_barang !== $validated['id_barang']) {
                    $existingRoom->id_barang = $validated['id_barang'];
                    $existingRoom->save();
                    
                    // Create a system message
                    if ($validated['id_barang']) {
                        $product = \App\Models\Barang::find($validated['id_barang']);
                        $systemMessage = new \App\Models\Pesan();
                        $systemMessage->id_ruang_chat = $existingRoom->id_ruang_chat;
                        $systemMessage->id_user = $user->id_user;
                        $systemMessage->tipe_pesan = 'System';
                        $systemMessage->isi_pesan = "Changed product for discussion to {$product->nama_barang}";
                        $systemMessage->is_read = false;
                        $systemMessage->save();
                    }
                }
                
                // Update timestamp
                $existingRoom->touch();
                
                return response()->json([
                    'success' => true,
                    'data' => $existingRoom,
                    'message' => 'Using existing chat room'
                ]);
            }
            
            // Create new chat room
            $chatRoom = new RuangChat();
            $chatRoom->id_pembeli = $buyerId;
            $chatRoom->id_penjual = $sellerId;
            $chatRoom->id_barang = $validated['id_barang'] ?? null;
            $chatRoom->status = 'Active';
            $chatRoom->save();
            
            // Create welcome message
            $welcomeText = 'Chat room created';
            if (isset($validated['id_barang'])) {
                $product = \App\Models\Barang::find($validated['id_barang']);
                $welcomeText = "Chat room created for discussing: {$product->nama_barang}";
            }
            
            $systemMessage = new \App\Models\Pesan();
            $systemMessage->id_ruang_chat = $chatRoom->id_ruang_chat;
            $systemMessage->id_user = $user->id_user;
            $systemMessage->tipe_pesan = 'System';
            $systemMessage->isi_pesan = $welcomeText;
            $systemMessage->is_read = false;
            $systemMessage->save();
            
            // Load the relationships
            $chatRoom->load(['pembeli', 'penjual', 'barang']);
            
            return response()->json([
                'success' => true,
                'data' => $chatRoom,
                'message' => 'Chat room created successfully'
            ], 201);
        } catch (\Exception $e) {
            Log::error("Error creating chat room: {$e->getMessage()}");
            
            return response()->json([
                'success' => false,
                'message' => "Failed to create chat room: {$e->getMessage()}"
            ], 500);
        }
    }

    /**
     * Get a specific chat room
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Get the chat room with its relationships
            $chatRoom = RuangChat::with(['pembeli', 'penjual', 'barang'])
                ->findOrFail($id);
            
            // Check if the user is authorized to access this room
            if ($chatRoom->id_pembeli !== $user->id_user && $chatRoom->id_penjual !== $user->id_user) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to access this chat room'
                ], 403);
            }
            
            return response()->json([
                'success' => true,
                'data' => $chatRoom
            ]);
        } catch (\Exception $e) {
            Log::error("Error fetching chat room {$id}: {$e->getMessage()}");
            
            return response()->json([
                'success' => false,
                'message' => "Failed to fetch chat room: {$e->getMessage()}"
            ], 500);
        }
    }

    /**
     * Update chat room status
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $chatRoom = RuangChat::findOrFail($id);
        
        // Check if user is authorized to update this chat room
        if ($chatRoom->id_pembeli != $user->id_user && $chatRoom->id_penjual != $user->id_user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to chat room'
            ], 403);
        }
        
        $validated = $request->validate([
            'status' => 'required|in:Active,Inactive,Archived',
        ]);

        $chatRoom->update($validated);
        
        return response()->json([
            'success' => true,
            'data' => $chatRoom,
            'message' => 'Chat room updated successfully'
        ]);
    }

    /**
     * Delete a chat room
     */
    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();
        $chatRoom = RuangChat::findOrFail($id);
        
        // Check if user is authorized to delete this chat room
        if ($chatRoom->id_pembeli != $user->id_user && $chatRoom->id_penjual != $user->id_user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to chat room'
            ], 403);
        }
        
        // Delete within a transaction to ensure consistency
        \DB::beginTransaction();
        try {
            // First delete associated messages
            Pesan::where('id_ruang_chat', $id)->delete();
            // Then delete the chat room
            $chatRoom->delete();
            \DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Chat room deleted successfully'
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete chat room: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all messages in a room as read
     */
    public function markAsRead(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            Log::info('Marking messages as read', ['user_id' => $user->id_user, 'room_id' => $id]);
            
            $chatRoom = RuangChat::findOrFail($id);
            
            // Ensure user is authorized to access this chat room
            if ($chatRoom->id_pembeli != $user->id_user && $chatRoom->id_penjual != $user->id_user) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to access this chat room'
                ], 403);
            }
            
            // Mark all messages from the other user as read
            $count = Pesan::where('id_ruang_chat', $id)
                ->where('id_user', '!=', $user->id_user)
                ->where('is_read', false)
                ->update(['is_read' => true]);
            
            return response()->json([
                'success' => true,
                'message' => "Marked $count messages as read"
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking messages as read', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'room_id' => $id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark messages as read: ' . $e->getMessage()
            ], 500);
        }
    }
}