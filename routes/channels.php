<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\RuangChat;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to control if an authenticated user can listen to the channel.
|
*/

// Authorization for both ChatRoom and ChatRoomList channels
Broadcast::channel('chat-room.{roomId}', function ($user, $roomId) {
    \Log::info('Authorizing chat-room channel', [
        'user_id' => $user->id_user,
        'room_id' => $roomId
    ]);
    
    $chatRoom = RuangChat::find($roomId);
    
    if (!$chatRoom) {
        \Log::warning('Chat room not found for authorization', ['room_id' => $roomId]);
        return false;
    }
    
    $authorized = $chatRoom->id_pembeli === $user->id_user || $chatRoom->id_penjual === $user->id_user;
    
    \Log::info('Chat-room authorization result', [
        'authorized' => $authorized,
        'user_id' => $user->id_user,
        'room_id' => $roomId,
        'pembeli_id' => $chatRoom->id_pembeli,
        'penjual_id' => $chatRoom->id_penjual
    ]);
    
    return $authorized;
});

Broadcast::channel('chat-list.{roomId}', function ($user, $roomId) {
    \Log::info('Authorizing chat-list channel', [
        'user_id' => $user->id_user,
        'room_id' => $roomId
    ]);
    
    $chatRoom = RuangChat::find($roomId);
    
    if (!$chatRoom) {
        \Log::warning('Chat room not found for authorization', ['room_id' => $roomId]);
        return false;
    }
    
    $authorized = $chatRoom->id_pembeli === $user->id_user || $chatRoom->id_penjual === $user->id_user;
    
    \Log::info('Chat-list authorization result', [
        'authorized' => $authorized,
        'user_id' => $user->id_user,
        'room_id' => $roomId,
        'pembeli_id' => $chatRoom->id_pembeli,
        'penjual_id' => $chatRoom->id_penjual
    ]);
    
    return $authorized;
});

// Keep the original chat channel for backward compatibility
Broadcast::channel('chat.{roomId}', function ($user, $roomId) {
    \Log::info('Authorizing legacy chat channel', [
        'user_id' => $user->id_user,
        'room_id' => $roomId
    ]);
    
    $chatRoom = RuangChat::find($roomId);
    
    if (!$chatRoom) {
        \Log::warning('Chat room not found for authorization', ['room_id' => $roomId]);
        return false;
    }
    
    return $chatRoom->id_pembeli === $user->id_user || $chatRoom->id_penjual === $user->id_user;
});

// User specific channel
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// User specific channel for notifications
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int)$user->id_user === (int)$userId;
});
