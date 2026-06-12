<?php

use Illuminate\Support\Env;

return [

    /*
    |--------------------------------------------------------------------------
    | Local speech-to-text (whisper.cpp)
    |--------------------------------------------------------------------------
    |
    | Speech input runs fully on-device: a bundled whisper.cpp binary (shipped
    | via NativePHP's extras/ mechanism) plus a Whisper model downloaded on
    | first enable. Model files live in the OS app-data directory so they
    | survive app updates; in plain `artisan serve` dev they fall back to
    | storage/app/speech.
    |
    */

    'model_dir' => Env::get('NATIVEPHP_APP_DATA_PATH')
        ? Env::get('NATIVEPHP_APP_DATA_PATH').DIRECTORY_SEPARATOR.'speech'
        : storage_path('app/speech'),

    'extras_dir' => Env::get('NATIVEPHP_EXTRAS_PATH') ?: base_path('extras'),

    // Hard ceiling for a single transcription run.
    'transcribe_timeout' => (int) env('SPEECH_TRANSCRIBE_TIMEOUT', 120),

    /*
    | Curated per hardware — Apple Silicon gets the large turbo model (Metal
    | makes it faster than realtime), everything else gets small so CPU-only
    | transcription stays responsive. sha256 values are the upstream LFS oids,
    | pinned so a tampered or truncated download can never be activated.
    */

    'models' => [
        'large-v3-turbo-q5_0' => [
            'label' => 'Whisper large-v3-turbo (quantized)',
            'file' => 'ggml-large-v3-turbo-q5_0.bin',
            'url' => 'https://huggingface.co/ggerganov/whisper.cpp/resolve/main/ggml-large-v3-turbo-q5_0.bin',
            'sha256' => '394221709cd5ad1f40c46e6031ca61bce88931e6e088c188294c6d5a55ffa7e2',
            'size_bytes' => 574041195,
        ],
        'small-q5_1' => [
            'label' => 'Whisper small (quantized)',
            'file' => 'ggml-small-q5_1.bin',
            'url' => 'https://huggingface.co/ggerganov/whisper.cpp/resolve/main/ggml-small-q5_1.bin',
            'sha256' => 'ae85e4a935d7a567bd102fe55afc16bb595bdb618e11b2fc7591bc08120411bb',
            'size_bytes' => 190085487,
        ],
    ],

];
