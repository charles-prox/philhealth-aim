<?php

use App\Models\ProcurementFolder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$folder = ProcurementFolder::first();
if (!$folder) {
    echo "No folder found.\n";
    exit;
}
try {
    $storagePath = "pr/{$folder->pr_number}.pdf";
    $disk = Storage::disk('public');

    echo "Generating PDF for PR: {$folder->pr_number}...\n";
    Pdf::view('pdf.pr-form', ['folder' => $folder])
        ->save($disk->path($storagePath));

    echo 'Success! PDF saved to: ' . $disk->path($storagePath) . "\n";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
