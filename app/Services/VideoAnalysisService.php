<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Support\Facades\Log;
use App\Events\VideoProgressUpdated;

class VideoAnalysisService
{
    public function analyze(Video $video): void
    {
        $this->runPythonAnalysis($video);
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
        $pythonScript = base_path('python/analizar_salida.py');

        if (!file_exists($pythonScript)) {
            throw new \Exception("Script de Python no encontrado en: {$pythonScript}");
        }

        // Detectar Python portable o estándar del sistema
        $pythonBinary = 'python';
        if (file_exists('C:/APPS/python-3.11.1-embed-amd64/python.exe')) {
            $pythonBinary = 'C:/APPS/python-3.11.1-embed-amd64/python.exe';
        }

        try {
            // Ejecutar el script real de Python usando Symfony Process
            $process = new \Symfony\Component\Process\Process([
                $pythonBinary, 
                $pythonScript, 
                '--video', 
                $videoPath
            ]);
            
            // Timeout de 10 minutos para videos grandes
            $process->setTimeout(600);
            // Acumular stdout y stderr manualmente: getOutput() puede devolver vacío
            // si el iterador foreach ya consumió el stream
            $rawOutput = '';
            $errOutput  = '';
            $process->start();
            foreach ($process as $type => $data) {
                if ($process::OUT === $type) {
                    $rawOutput .= $data;
                    if (preg_match('/PROGRESS:(\d+)/', $data, $matches)) {
                        $progress = (int)$matches[1];
                        $video->update(['progress' => $progress]);
                        $this->broadcastProgress($video);
                    }
                } else {
                    $errOutput .= $data;
                }
            }


            // Buscar el JSON válido en stdout independientemente del exit code
            // (TensorFlow Lite escribe a stderr causando exit code 1 aunque el análisis sea correcto)
            $lines = array_reverse(explode("\n", $rawOutput));
            $output = null;
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] !== '{') {
                    continue;
                }
                $decoded = json_decode($line, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['status'])) {
                    $output = $decoded;
                    break;
                }
            }

            if ($output === null) {
                Log::error("Python STDOUT para video ID {$video->id}:\n" . $rawOutput);
                Log::error("Python STDERR para video ID {$video->id}:\n" . $errOutput);
                throw new \Exception("Error en Python. STDERR: " . substr($errOutput, 0, 500));
            }

            // Manejar estado fallido devuelto de forma controlada en el JSON
            if (isset($output['status']) && $output['status'] === 'failed') {
                $video->update([
                    'status' => 'failed',
                    'progress' => 100,
                    'error_message' => $output['error_message'] ?? 'Error desconocido en el análisis biomecánico.'
                ]);
                $this->broadcastProgress($video);
                return;
            }

            // La puerta de calidad puede rechazar el vídeo con motivos concretos
            // (encuadre, nº de zancadas, etc.) en vez de un fallo técnico. La
            // vista actual todavía no distingue 'rejected' de 'failed' (eso es
            // la fase de pantalla de rechazo, pendiente) — de momento se mapea
            // a 'failed' para no romper la vista, pero el motivo real (no un
            // mensaje genérico) va en error_message, y el detalle completo de
            // 'quality' queda en result_data para cuando exista esa pantalla.
            if (isset($output['status']) && $output['status'] === 'rejected') {
                $quality = $output['quality'] ?? [];
                $motivos = $quality['motivos'] ?? [];
                $video->update([
                    'status' => 'failed',
                    'progress' => 100,
                    'error_message' => $motivos
                        ? ('Vídeo rechazado por la puerta de calidad: ' . implode('; ', $motivos) . '.')
                        : 'Vídeo rechazado por la puerta de calidad.',
                    'result_data' => $output,
                ]);
                $this->broadcastProgress($video);
                return;
            }

            // Ruta del vídeo anotado relativa a public/, en el mismo directorio
            // que el original (mismo criterio que usaba analyze_tacos.py).
            $processedRelativePath = null;
            if (isset($output['processed_video_filename'])) {
                $processedRelativePath = dirname($video->file_path) . '/' . $output['processed_video_filename'];
                $output['original_file_path'] = $video->file_path;
            }

            $videoData = [
                'status' => 'completed',
                'progress' => 100,
                'result_data' => $output,
            ];
            if ($processedRelativePath !== null) {
                $videoData['file_path'] = $processedRelativePath;
            }

            $video->update($videoData);
            $this->broadcastProgress($video);
        } catch (\Exception $e) {
            Log::error("Fallo de análisis en video ID {$video->id}: " . $e->getMessage());
            $video->update([
                'status' => 'failed',
                'progress' => 100,
                'error_message' => $e->getMessage()
            ]);
            $this->broadcastProgress($video);
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

}
