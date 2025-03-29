<?php

namespace App\Http\Controllers\Api\S3UploadFile;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


class S3SUploadController extends Controller
{
    protected $s3Client;

    public function __construct(Request $request)
    {
        // cấu hình Pre-signed URL
        $this->s3Client = new S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'endpoint' => env('MINIO_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => env('MINIO_ACCESS_KEY'),
                'secret' => env('MINIO_SECRET_KEY'),
            ],
        ]);
    }

    public function createPresignedUrl(Request $request)
    {
        Log::info('Start creating presigned URL');

        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|max:10240',
        ]);

        $files = $request->file('files', []);
        if (empty($files)) {
            return response()->json(['error' => 'No files uploaded'], 400);
        }

        $presignedUrls = [];
        $bucket = env('MINIO_BUCKET');
        $expiry = env('PRESIGNED_URL_EXPIRY', 3600); // Mặc định 1 giờ

        foreach ($files as $file) {
            $uuid = Str::uuid()->toString();
            $extension = $file->getClientOriginalExtension();
            $size = $file->getSize();
            $client_name = $file->getClientOriginalName();
            $mime = $file->getMimeType();
            $path = "uploads/{$uuid}/{$uuid}";

            try {
                $command = $this->s3Client->getCommand('PutObject', [
                    'Bucket' => $bucket,
                    'Key'    => $path,
                    'ContentType' => $mime
                ]);

                $presignedRequest = $this->s3Client->createPresignedRequest($command, "+" . $expiry . " seconds");

                $presignedUrls[] = [
                    'url'  => (string) $presignedRequest->getUri(),
                    'path' => $path,
                    'metadata' => [
                        'file_name'   => "{$uuid}.{$extension}",
                        'client_name' => $client_name,
                        'extension'   => $extension,
                        'size'        => $size,
                        'mime'        => $mime,
                    ],
                ];
            } catch (AwsException $e) {
                Log::error('An error occurred while generating presigned URL: ' . $e->getMessage());
                return response()->json([
                    'error' => 'Failed to generate presigned URL',
                    'message' => $e->getMessage(),
                ], 500);
            }
        }

        return response()->json([
            'presigned_urls' => $presignedUrls,
        ], 200);
    }
}
