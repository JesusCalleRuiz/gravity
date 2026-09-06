<?php

namespace App\Jobs;

use App\Models\Video;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class GeneratePdfJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Por qué esto vive en un job de cola y no se genera directamente en el
     * controlador (como antes): en esta máquina, lanzar el proceso Python
     * de generar_pdf.py desde el proceso del SERVIDOR WEB (el que atiende
     * la petición HTTP) fallaba de forma consistente con
     * "WinError 10106" (fallo al inicializar el proveedor de sockets de
     * Winsock) en cuanto ese script tocaba cualquier import que arrastrara
     * asyncio — primero scikit-learn/joblib, después yt_dlp; ambos ya se
     * arreglaron para no importarse sin necesidad, pero el fallo es
     * sistémico del contexto de ESE proceso concreto, no de una librería en
     * particular. El worker de la cola (php artisan queue:work) es un
     * proceso PHP distinto que YA lanza procesos Python equivalentes
     * (analizar_salida.py) con total fiabilidad para analizar vídeos —
     * así que generar el PDF ahí, en vez de en el proceso web, evita el
     * problema en vez de perseguir cada nueva librería que lo dispare.
     */
    public $timeout = 180;
    public $tries = 1;

    protected $video;

    public function __construct(Video $video)
    {
        $this->video = $video;
    }

    public static function rutaPdf(int $videoId): string
    {
        return storage_path("app/tmp/informe_generado_{$videoId}.pdf");
    }

    public function handle(): void
    {
        $tmpDir = storage_path('app/tmp');
        if (!File::exists($tmpDir)) {
            File::makeDirectory($tmpDir, 0755, true);
        }

        $resultJsonPath = $tmpDir . "/result_pdf_{$this->video->id}.json";
        $outputPdfPath = self::rutaPdf($this->video->id);
        // Python escribe aquí, NUNCA directamente en $outputPdfPath: el
        // controlador espera a que $outputPdfPath exista para servirlo, y
        // matplotlib.PdfPages abre/crea el archivo de salida nada más
        // empezar, escribiendo el contenido real poco a poco hasta que el
        // "with" se cierra al terminar. Si Python escribiera directamente
        // en la ruta final, File::exists() podía dar true con el PDF a
        // medias — exactamente lo que pasó: "error al cargar el documento
        // PDF" al abrirlo. Se escribe en un nombre provisional y solo se
        // renombra (operación atómica) al terminar con éxito.
        $outputPdfPathProvisional = $tmpDir . "/informe_generado_{$this->video->id}.tmp.pdf";
        File::delete($outputPdfPathProvisional);
        File::put($resultJsonPath, json_encode($this->video->result_data));

        $pythonBinary = \App\Support\PythonRuntime::binario();
        $pythonScript = base_path('python/generar_pdf.py');

        $process = new \Symfony\Component\Process\Process([
            $pythonBinary,
            $pythonScript,
            '--result-json', $resultJsonPath,
            '--output', $outputPdfPathProvisional,
            '--titulo', $this->video->title,
        ]);
        $process->setEnv(\App\Support\PythonRuntime::entornoProceso());
        $process->setTimeout(150);
        $process->run();

        File::delete($resultJsonPath);

        if (!$process->isSuccessful() || !File::exists($outputPdfPathProvisional)) {
            Log::error("Fallo generando PDF (cola) para video ID {$this->video->id}: " . $process->getErrorOutput());
            File::delete($outputPdfPathProvisional);
            return;
        }

        File::move($outputPdfPathProvisional, $outputPdfPath);
    }
}
