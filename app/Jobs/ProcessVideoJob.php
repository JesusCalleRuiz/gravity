<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\VideoAnalysisService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessVideoJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The video instance.
     *
     * @var Video
     */
    protected $video;

    /**
     * Create a new job instance.
     */
    public function __construct(Video $video)
    {
        $this->video = $video;
    }

    /**
     * Execute the job.
     */
    public function handle(VideoAnalysisService $analysisService): void
    {
        try {
            $analysisService->analyze($this->video);
        } catch (\Exception $e) {
            Log::error("Error processing video ID {$this->video->id}: " . $e->getMessage());
            
            $this->video->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'progress' => 0,
            ]);
            
            // Disparar broadcast de fallo
            try {
                event(new \App\Events\VideoProgressUpdated($this->video));
            } catch (\Exception $broadcastEx) {
                Log::warning("Failed to broadcast error state: " . $broadcastEx->getMessage());
            }
        }
    }
}
