<?php

namespace App\Events;

use App\Models\Pesan;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public $message;
    public $roomId;

    /**
     * Create a new event instance.
     */
    public function __construct(Pesan $message)
    {
        $this->message = $message->load('user'); // Ensure user is loaded
        $this->roomId = $message->id_ruang_chat;
        
        \Log::info('📡 MessageSent event created for multiple channels', [
            'message_id' => $message->id_pesan,
            'room_id' => $this->roomId,
            'user_id' => $message->id_user,
            'message_content' => $message->isi_pesan
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     * Broadcast to both ChatRoom and ChatRoomList channels
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $chatRoomChannel = 'chat-room.' . $this->roomId;
        $chatListChannel = 'chat-list.' . $this->roomId;
        
        \Log::info('📡 Broadcasting on multiple channels', [
            'chat_room_channel' => $chatRoomChannel,
            'chat_list_channel' => $chatListChannel
        ]);
        
        return [
            new PrivateChannel($chatRoomChannel), // For ChatRoom component
            new PrivateChannel($chatListChannel), // For ChatRoomList component
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $data = [
            'id_pesan' => $this->message->id_pesan,
            'id_ruang_chat' => $this->message->id_ruang_chat,
            'id_user' => $this->message->id_user,
            'tipe_pesan' => $this->message->tipe_pesan,
            'isi_pesan' => $this->message->isi_pesan,
            'harga_tawar' => $this->message->harga_tawar,
            'status_penawaran' => $this->message->status_penawaran,
            'id_barang' => $this->message->id_barang,
            'is_read' => $this->message->is_read,
            'created_at' => $this->message->created_at->toISOString(),
            'updated_at' => $this->message->updated_at->toISOString(),
            'user' => [
                'id_user' => $this->message->user->id_user,
                'name' => $this->message->user->name,
            ],
        ];
        
        \Log::info('📡 Broadcasting message data to multiple channels', $data);
        
        return $data;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'MessageSent';
    }
}
