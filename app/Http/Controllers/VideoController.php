<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Jobs\GeneratePdfJob;
use App\Jobs\ProcessVideoJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class VideoController extends Controller
{
    /**
     * Display a listing of the videos.
     */
    public function index()
    {
        $user = Auth::user();
        $videos = Video::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Calcular estadísticas
        $totalVideos = $videos->count();
        $completedVideos = $videos->where('status', 'completed')->count();
        $processingVideos = $videos->where('status', 'processing')->count();
        $failedVideos = $videos->where('status', 'failed')->count();

        $totalErrorsDetected = 0;
        foreach ($videos as $video) {
            if ($video->status === 'completed' && isset($video->result_data['detections_count'])) {
                $totalErrorsDetected += $video->result_data['detections_count'];
            }
        }

        return view('videos.index', compact(
            'videos',
            'totalVideos',
            'completedVideos',
            'processingVideos',
            'failedVideos',
            'totalErrorsDetected'
        ));
    }

    /**
     * Show the form for importing/uploading a video.
     */
    public function import()
    {
        $recentVideos = \App\Models\Video::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->take(2)
            ->get();
        return view('videos.import', compact('recentVideos'));
    }

    /**
     * Store a newly uploaded video and queue it for analysis.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime,video/x-matroska|max:512000', // max 500MB
        ]);

        $videoFile = $request->file('video');
        
        // Creamos la carpeta en public/uploads/videos para acceso web rápido y robusto
        $destinationPath = public_path('uploads/videos');
        if (!File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true);
        }

        // Renombrar archivo para evitar colisiones
        $fileName = time() . '_' . uniqid() . '.' . $videoFile->getClientOriginalExtension();
        $videoFile->move($destinationPath, $fileName);
        
        $relativeFilePath = 'uploads/videos/' . $fileName;

        // Crear registro en base de datos
        $video = Video::create([
            'user_id' => Auth::id(),
            'title' => $request->title,
            'file_path' => $relativeFilePath,
            'status' => 'pending',
            'progress' => 0,
        ]);

        // Despachar el Job de procesamiento asíncrono
        ProcessVideoJob::dispatch($video);

        return redirect()->route('videos.show', $video->id)
            ->with('success', 'Video subido con éxito. El análisis de tacos ha comenzado.');
    }

    /**
     * Display the specified video analysis details.
     */
    public function show($id)
    {
        $video = Video::where('user_id', Auth::id())->findOrFail($id);
        
        return view('videos.show', compact('video'));
    }

    /**
     * API endpoint to get progress in real-time (for JS Polling fallback).
     */
    public function progressApi($id)
    {
        $video = Video::where('user_id', Auth::id())->find($id);

        if (!$video) {
            return response()->json(['error' => 'Video no encontrado.'], 404);
        }

        return response()->json([
            'id' => $video->id,
            'status' => $video->status,
            'progress' => $video->progress,
            'result_data' => $video->result_data,
            'error_message' => $video->error_message,
            'file_path' => $video->file_path,
        ]);
    }

    /**
     * Ruta histórica /videos/{id}/report: la vista HTML que servía aquí
     * (report.blade.php) mostraba métricas inventadas con el mismo mecanismo
     * de captura-a-PDF en el cliente que analyze_tacos.py — sustituida por
     * el PDF real del lado Python (reportPdf), esta ruta solo redirige.
     */
    public function report($id)
    {
        return redirect()->route('videos.report.pdf', $id);
    }

    /**
     * Genera y descarga el informe PDF de una sesión ya analizada.
     *
     * No repite el análisis: reutiliza el result_data ya guardado y los
     * landmarks ya cacheados de esa misma ejecución. La generación en sí
     * vive en core.feedback.generar_informe_pdf (entrenador), no aquí.
     */
    public function reportPdf($id)
    {
        $video = Video::where('user_id', Auth::id())->findOrFail($id);

        if ($video->status !== 'completed') {
            return redirect()->route('videos.show', $video->id)
                ->with('error', 'El informe estará disponible una vez que finalice el análisis.');
        }

        // Generado por el WORKER DE LA COLA (ver GeneratePdfJob), no aquí en
        // el proceso web: en esta máquina, lanzar el proceso Python desde
        // el proceso que atiende la petición HTTP fallaba de forma
        // consistente con "WinError 10106" al inicializar sockets,
        // mientras que el worker de la cola —un proceso PHP distinto—
        // lanza procesos Python equivalentes sin problema (es el que ya
        // analiza vídeos con éxito). Se despacha el job y se espera aquí a
        // que aparezca el archivo, para no cambiar la experiencia del
        // usuario (sigue siendo "pulsar el enlace y descargar").
        $outputPdfPath = GeneratePdfJob::rutaPdf($video->id);
        File::delete($outputPdfPath);
        GeneratePdfJob::dispatch($video);

        $limiteEsperaSegundos = 90;
        $esperado = 0;
        while (!File::exists($outputPdfPath) && $esperado < $limiteEsperaSegundos) {
            usleep(500000);
            $esperado += 0.5;
        }

        if (!File::exists($outputPdfPath)) {
            Log::error("PDF no disponible tras {$limiteEsperaSegundos}s de espera para video ID {$video->id}. ¿Está el worker de la cola (php artisan queue:work) en marcha?");
            return redirect()->route('videos.show', $video->id)
                ->with('error', 'No se ha podido generar el informe PDF a tiempo. Comprueba que el worker de la cola (php artisan queue:work) esté en marcha e inténtalo de nuevo.');
        }

        return response()->download($outputPdfPath, "informe_biomecanico_{$video->id}.pdf")->deleteFileAfterSend(true);
    }
}

