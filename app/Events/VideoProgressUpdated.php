<?php

namespace App\Events;

use App\Models\Video;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VideoProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The video instance.
     *
     * @var Video
     */
    public $video;

    /**
     * Create a new event instance.
     */
    public function __construct(Video $video)
    {
        $this->video = $video->withoutRelations();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('video.' . $this->video->id),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->video->id,
            'status' => $this->video->status,
            'progress' => $this->video->progress,
            'result_data' => $this->video->result_data,
            'error_message' => $this->video->error_message,
        ];
    }
}
