<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ObjectFiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class FileTransferController extends Controller
{
    public function store(Request $request, ObjectFiles $files): JsonResponse
    {
        abort_unless(config('partshelf.file_transport') === 'object', 404);
        $data = $request->validate([
            'kind' => ['required', 'in:image,workbook'], 'size' => ['required', 'integer', 'min:1', 'max:5242880'],
            'mime' => ['required', 'in:image/png,image/jpeg,image/webp,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'sha256' => ['required', 'regex:/^[a-f0-9]{64}$/'],
        ]);
        abort_if(($data['kind'] === 'workbook') !== ($data['mime'] === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'), 422, 'File type does not match upload purpose.');
        $id = (string) Str::uuid();
        $key = 'uploads/'.$id;
        $ext = match ($data['mime']) {
            'image/jpeg' => 'jpg', 'image/webp' => 'webp', default => 'png'
        };
        DB::table('file_transfers')->insert([
            ...$data, 'id' => $id, 'user_id' => $request->user()->id, 'object_key' => $key,
            'final_key' => $data['kind'] === 'image' ? 'products/'.$id.'.'.$ext : null,
            'expires_at' => now()->addMinutes(15), 'created_at' => now(),
        ]);

        return response()->json(['token' => $id, 'upload_url' => $files->signedUrl('PutObject', $key, $data['mime'], 120, (int) $data['size']), 'headers' => ['Content-Type' => $data['mime']], 'expires_in_seconds' => 120], 201);
    }

    public function image(Product $product, ObjectFiles $files): Response
    {
        abort_unless(config('partshelf.image_disk') === 'r2' && $product->image_path, 404);
        $mime = match (pathinfo($product->image_path, PATHINFO_EXTENSION)) {
            'jpg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', default => 'image/png'
        };

        return redirect()->away($files->signedUrl('GetObject', $product->image_path, $mime, 60))->header('Cache-Control', 'no-store, private');
    }
}
