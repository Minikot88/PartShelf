<?php

namespace App\Services;

use Aws\S3\S3Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ObjectFiles
{
    public function signedUrl(string $method, string $key, string $mime, int $seconds = 120, ?int $size = null): string
    {
        $config = config('filesystems.disks.r2');
        $client = new S3Client([
            'version' => 'latest', 'region' => $config['region'], 'endpoint' => $config['endpoint'],
            'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? false,
            'credentials' => ['key' => $config['key'], 'secret' => $config['secret']],
            'request_checksum_calculation' => 'when_required', 'response_checksum_validation' => 'when_required',
            'http' => ['connect_timeout' => 5, 'timeout' => 30],
        ]);
        $params = ['Bucket' => $config['bucket'], 'Key' => $key];
        if ($method === 'PutObject') {
            $params['ContentType'] = $mime;
            if ($size !== null) {
                $params['ContentLength'] = $size;
            }
        } else {
            $params['ResponseContentType'] = $mime;
        }

        return (string) $client->createPresignedRequest($client->getCommand($method, $params), '+'.$seconds.' seconds')->getUri();
    }

    public function consume(Request $request, string $token, string $kind, callable $callback): mixed
    {
        return DB::transaction(function () use ($request, $token, $kind, $callback) {
            $row = DB::table('file_transfers')->where('id', $token)->lockForUpdate()->first();
            abort_unless($row && $row->kind === $kind && (int) $row->user_id === (int) $request->user()->id
                && ! $row->consumed_at && now()->lt($row->expires_at), 422, 'Upload is expired, used or unavailable.');
            $stream = Storage::disk('r2')->readStream($row->object_key);
            abort_unless(is_resource($stream), 422, 'Upload is unavailable.');
            $path = tempnam(sys_get_temp_dir(), 'partshelf-upload-');
            if ($path === false) {
                fclose($stream);
                abort(503, 'Temporary storage unavailable.');
            }
            try {
                $out = fopen($path, 'wb');
                try {
                    $bytes = stream_copy_to_stream($stream, $out, 5242881);
                } finally {
                    fclose($stream);
                    fclose($out);
                }
                abort_unless($bytes === (int) $row->size && $bytes <= 5242880 && hash_equals($row->sha256, hash_file('sha256', $path)), 422, 'Upload size or checksum does not match.');
                $extension = $kind === 'workbook' ? 'xlsx' : match ($row->mime) {
                    'image/jpeg' => 'jpg', 'image/webp' => 'webp', default => 'png'
                };
                $file = new UploadedFile($path, 'upload.'.$extension, null, null, true);
                abort_if($kind === 'image' && $file->getMimeType() !== $row->mime, 422, 'The image contents do not match its declared type.');
                $result = $callback($file, $row);
                DB::table('file_transfers')->where('id', $token)->update(['consumed_at' => now()]);

                return $result;
            } finally {
                @unlink($path);
            }
        });
    }

    public function export(string $path, string $filename): JsonResponse
    {
        $id = (string) Str::uuid();
        $key = 'exports/'.$id.'/'.$filename;
        $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        try {
            DB::table('file_transfers')->insert([
                'id' => $id, 'user_id' => auth()->id(), 'kind' => 'export', 'object_key' => $key,
                'mime' => $mime, 'size' => filesize($path), 'sha256' => hash_file('sha256', $path),
                'expires_at' => now()->addHour(), 'created_at' => now(),
            ]);
            $stream = fopen($path, 'rb');
            try {
                Storage::disk('r2')->put($key, $stream, ['ContentType' => $mime]);
            } finally {
                fclose($stream);
            }

            return response()->json(['download_url' => $this->signedUrl('GetObject', $key, $mime), 'expires_in_seconds' => 120]);
        } finally {
            @unlink($path);
        }
    }
}
