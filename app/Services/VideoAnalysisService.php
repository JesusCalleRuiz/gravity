<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Support\Facades\Log;
use App\Events\VideoProgressUpdated;

class VideoAnalysisService
{
    public function analyze(Video $video): void
    {
        if (env('SIMULATE_ANALYSIS', true)) {
            $this->runSimulation($video);
        } else {
            $this->runPythonAnalysis($video);
        }
    }

    /**
     * Run simulated analysis for development / demo purposes.
     */
    protected function runSimulation(Video $video): void
    {
        Log::info("Starting taco error analysis SIMULATION on video ID: {$video->id}");

        $video->update([
            'status' => 'processing',
            'progress' => 0,
        ]);

        $this->broadcastProgress($video);

        for ($i = 10; $i <= 100; $i += 10) {
            sleep(1); 

            $video->update([
                'progress' => $i,
            ]);

            $this->broadcastProgress($video);

            Log::info("Video ID {$video->id} simulation progress: {$i}%");
        }

        $simulatedDetections = [
            [
                'timestamp' => 1.5,
                'error_type' => 'mal_enrollado',
                'description' => 'Taco mal enrollado o abierto en el extremo izquierdo.',
                'confidence' => 0.94,
                'bounding_box' => ['x' => 120, 'y' => 240, 'width' => 80, 'height' => 60]
            ],
            [
                'timestamp' => 3.2,
                'error_type' => 'rotura',
                'description' => 'Corte o rotura de la masa del taco durante la salida.',
                'confidence' => 0.88,
                'bounding_box' => ['x' => 310, 'y' => 242, 'width' => 75, 'height' => 58]
            ],
            [
                'timestamp' => 5.8,
                'error_type' => 'obstruccion',
                'description' => 'Acumulación/obstrucción en la rampa de caída.',
                'confidence' => 0.97,
                'bounding_box' => ['x' => 540, 'y' => 250, 'width' => 110, 'height' => 90]
            ],
        ];

        $video->update([
            'status' => 'completed',
            'progress' => 100,
            'result_data' => [
                'detections_count' => count($simulatedDetections),
                'detections' => $simulatedDetections,
                'duration_seconds' => 8.4,
                'tacos_analyzed' => 14,
                'accuracy' => 96.5,
            ]
        ]);

        $this->broadcastProgress($video);

        Log::info("Taco analysis simulation completed successfully for video ID: {$video->id}");
    }

    /**
     * Run real Python model analysis.
     */
    protected function runPythonAnalysis(Video $video): void
    {
        Log::info("Starting REAL Python model analysis on video ID: {$video->id}");
        
        $video->update([
            'status' => 'processing',
            'progress' => 0,
        ]);
        
        $this->broadcastProgress($video);

        $videoPath = public_path($video->file_path);
        $pythonScript = base_path('python/analyze_tacos.py');

        if (!file_exists($pythonScript)) {
            throw new \Exception("Script de Python no encontrado en: {$pythonScript}");
        }

        // Ejecutar el script real de Python usando Symfony Process
        $process = new \Symfony\Component\Process\Process([
            'python', 
            $pythonScript, 
            '--video', 
            $videoPath
        ]);
        
        // Timeout de 10 minutos para videos grandes
        $process->setTimeout(600);
        $process->start();
        
        foreach ($process as $type => $data) {
            if ($process::OUT === $type) {
                // Si el script escribe PROGRESS:X, actualizamos
                if (preg_match('/PROGRESS:(\d+)/', $data, $matches)) {
                    $progress = (int)$matches[1];
                    $video->update(['progress' => $progress]);
                    $this->broadcastProgress($video);
                }
            }
        }
        
        if ($process->isSuccessful()) {
            $output = json_decode($process->getOutput(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Salida de Python no es un JSON válido: " . $process->getOutput());
            }

            $video->update([
                'status' => 'completed',
                'progress' => 100,
                'result_data' => $output
            ]);
            
            $this->broadcastProgress($video);
        } else {
            throw new \Exception("Error en script de Python: " . $process->getErrorOutput());
        }
    }

    /**
     * Broadcast the progress update.
     */
    protected function broadcastProgress(Video $video): void
    {
        try {
            // Broadcasting de Laravel (WebSockets)
            event(new VideoProgressUpdated($video));
        } catch (\Exception $e) {
            // Evitamos que falle si el driver de Broadcast no está configurado del todo
            Log::warning("Broadcasting failed for video ID {$video->id}: " . $e->getMessage());
        }
    }

    /**
     * FUTURE ROADMAP (Para integrar Python fácilmente):
     *
     * public function analyzeWithPython(Video $video): void
     * {
     *     $videoPath = storage_path('app/private/' . $video->file_path);
     *     
     *     // Ejecutar script de Python con Symfony Process
     *     $process = new \Symfony\Component\Process\Process([
     *         'python', 
     *         base_path('python/analyze_tacos.py'), 
     *         '--video', 
     *         $videoPath
     *     ]);
     *     
     *     $process->start();
     *     
     *     // Leer salida en tiempo real
     *     foreach ($process as $type => $data) {
     *         if ($process::OUT === $type) {
     *             // Ej: El script de Python escupe "PROGRESS:45"
     *             if (preg_match('/PROGRESS:(\d+)/', $data, $matches)) {
     *                 $video->update(['progress' => (int)$matches[1]]);
     *                 $this->broadcastProgress($video);
     *             }
     *         }
     *     }
     *     
     *     // Al terminar, parsear el JSON de salida
     *     if ($process->isSuccessful()) {
     *         $output = json_decode($process->getOutput(), true);
     *         $video->update([
     *             'status' => 'completed',
     *             'result_data' => $output
     *         ]);
     *     } else {
     *         $video->update([
     *             'status' => 'failed',
     *             'error_message' => $process->getErrorOutput()
     *         ]);
     *     }
     * }
     */
}
