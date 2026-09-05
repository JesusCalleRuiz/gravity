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
     * El análisis (MediaPipe + TensorFlow sobre el vídeo completo) puede
     * tardar varios minutos: VideoAnalysisService da hasta 600s al proceso
     * Python. El timeout por defecto de Laravel (60s) mataría el worker a
     * mitad de un análisis normal, dejando el vídeo en "processing" para
     * siempre sin ningún error visible. Debe quedar por encima de ese
     * margen, y retry_after (config/queue.php) por encima de este timeout,
     * o la cola reencola el job mientras el primero sigue vivo (doble
     * procesamiento del mismo vídeo).
     */
    public $timeout = 900;

    /**
     * Un fallo de análisis casi siempre es determinista (vídeo rechazado
     * por calidad, bundle no disponible, excepción del propio pipeline):
     * reintentar no lo arregla, solo duplica el trabajo y retrasa que el
     * usuario vea el error.
     */
    public $tries = 1;

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
        } catch (\Throwable $e) {
            // \Throwable, no \Exception: un TypeError/Error de PHP (p. ej.
            // al indexar un result_data con una forma inesperada) no es un
            // \Exception y antes escapaba sin capturar, dejando el vídeo
            // atascado en 'processing' sin ningún mensaje de error.
            Log::error("Error processing video ID {$this->video->id}: " . $e->getMessage());

            $this->video->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'progress' => 0,
            ]);

            // Disparar broadcast de fallo
            try {
                event(new \App\Events\VideoProgressUpdated($this->video));
            } catch (\Throwable $broadcastEx) {
                Log::warning("Failed to broadcast error state: " . $broadcastEx->getMessage());
            }
        }
    }

    /**
     * Red de seguridad si el propio framework mata el job desde fuera de
     * handle() (p. ej. al superar $timeout): sin esto, el vídeo se queda en
     * 'processing' para siempre porque el catch de handle() nunca llega a
     * ejecutarse.
     */
    public function failed(\Throwable $e): void
    {
        Log::error("ProcessVideoJob fallido (fuera de handle) para el vídeo ID {$this->video->id}: " . $e->getMessage());

        $this->video->update([
            'status' => 'failed',
            'error_message' => $e->getMessage(),
            'progress' => 0,
        ]);

        try {
            event(new \App\Events\VideoProgressUpdated($this->video));
        } catch (\Throwable $broadcastEx) {
            Log::warning("Failed to broadcast error state: " . $broadcastEx->getMessage());
        }
    }
}
