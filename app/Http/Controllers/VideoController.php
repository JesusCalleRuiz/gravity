<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Jobs\ProcessVideoJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

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
        ]);
    }
}
