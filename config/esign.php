<?php

return [
    // Max upload size in kilobytes.
    'max_upload_kb' => (int) env('ESIGN_MAX_UPLOAD_KB', 20480),

    // Optional: path to Ghostscript, used to convert PDFs the parser cannot read.
    'ghostscript_path' => env('GHOSTSCRIPT_PATH'),
];
