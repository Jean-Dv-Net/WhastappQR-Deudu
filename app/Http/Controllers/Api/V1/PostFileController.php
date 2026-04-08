<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Save a file in the storage public accessible for everyone.
 */
class PostFileController extends Controller
{
    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:16384', // 16MB Max 
        ]);

        $file = $request->file('file');
        $path = $file->store('uploads', 'public');

        return response()->json([
            'success' => true,
            'message' => 'File uploaded successfully',
            'url' => asset('storage/' . $path),
        ]);
    }
}
