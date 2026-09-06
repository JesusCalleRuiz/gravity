<?php

namespace App\Support;

/**
 * Localiza el binario de Python a usar para todos los scripts de python/.
 *
 * En Windows (desarrollo) se usaba una ruta fija al Python portable del
 * proyecto; en el servidor de producción (Ubuntu) eso no existe y el
 * binario se llama "python3", no "python" (ese comando no existe en Ubuntu
 * salvo que se instale el paquete python-is-python3). En vez de adivinar
 * el sistema operativo, la ruta se puede fijar explícitamente en el .env
 * con PYTHON_BINARY — así cada entorno declara la suya sin que el código
 * tenga que llevar rutas de un sistema operativo concreto.
 */
class PythonRuntime
{
    public static function binario(): string
    {
        $configurado = env('PYTHON_BINARY');
        if ($configurado) {
            return $configurado;
        }

        // Ruta del Python portable usado en el entorno de desarrollo
        // (Windows). Se mantiene como fallback para no romper ese flujo si
        // alguien no ha puesto PYTHON_BINARY en su .env local.
        if (file_exists('C:/APPS/python-3.11.1-embed-amd64/python.exe')) {
            return 'C:/APPS/python-3.11.1-embed-amd64/python.exe';
        }

        // Fallback razonable para Linux: "python" no existe en una Ubuntu
        // estándar (solo "python3"), así que ese es el que se prueba aquí
        // si no hay nada más configurado.
        return 'python3';
    }

    /**
     * Ruta del proyecto "entrenador" (paquete core/), configurable por
     * .env con RUTA_ENTRENADOR — los scripts de python/ la leen ellos
     * mismos como variable de entorno, pero centralizarla aquí evita tener
     * el mismo valor por defecto duplicado en cada sitio de PHP que lanza
     * un proceso Python.
     */
    public static function rutaEntrenador(): string
    {
        return env('RUTA_ENTRENADOR', 'C:\var\www\html\entrenador');
    }

    /**
     * Variables de entorno a pasar al proceso hijo de Python, además de las
     * que Symfony Process ya hereda del proceso PHP actual.
     */
    public static function entornoProceso(): array
    {
        return ['RUTA_ENTRENADOR' => self::rutaEntrenador()];
    }
}
