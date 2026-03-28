<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Worker Pfad (außerhalb Webroot)
    |--------------------------------------------------------------------------
    */
    'ai_worker_path' => env('AI_WORKER_PATH', '/usr/home/admin/ai_worker'),

    'python_bin' => env('REDACTION_PYTHON', null), // null = {ai_worker_path}/env/bin/python

    'script_name' => 'detect_and_redact.py',

    'model_path' => env('REDACTION_MODEL_PATH', ''), // leer = {ai_worker_path}/models/plate_detector.onnx

    'face_model_path' => env('REDACTION_FACE_MODEL_PATH', ''), // optional: Gesichtserkennung; leer = nur Kennzeichen + manuelle Boxen

    /*
    |--------------------------------------------------------------------------
    | Standard-Methode und Blur-Stärke
    |--------------------------------------------------------------------------
    */
    'default_method' => env('REDACTION_METHOD', 'blur'), // blur | black_box

    'blur_strength' => (int) env('REDACTION_BLUR_STRENGTH', 151),

    /*
    |--------------------------------------------------------------------------
    | Storage-Pfade (relativ zum Disk 'public')
    |--------------------------------------------------------------------------
    */
    'original_prefix' => 'media/original',

    'redacted_prefix' => 'media/redacted',
];
