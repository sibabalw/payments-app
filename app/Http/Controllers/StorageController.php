<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorageController extends Controller
{
    /**
     * Serve files from the public storage disk.
     * Use when storage:link symlink doesn't work (e.g. Oracle Cloud, restricted hosts).
     */
    public function __invoke(string $path): StreamedResponse
    {
        // Prevent path traversal and empty path
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    }
}
