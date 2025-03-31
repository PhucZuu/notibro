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

        foreach ($files as $file) {
            $infoUrl = $this->getInfoUrl($file);
            try {
                $command = $this->s3Client->getCommand('PutObject', [
                    'Bucket' => $infoUrl['bucket'],
                    'Key'    => $infoUrl['path'],
                    'ContentType' => $infoUrl['mime'],
                    'ACL' => 'public-read',
                ]);

                $presignedRequest = $this->s3Client->createPresignedRequest($command, "+" . $infoUrl['expiry'] . " seconds");

                $presignedUrls[] = [
                    'url'  => (string) $presignedRequest->getUri(),
                    'path' => $infoUrl['path'],
                    'metadata' => [
                        'file_name'   => $infoUrl['uuid'],
                        'client_name' => $infoUrl['client_name'],
                        'extension'   => $infoUrl['extension'],
                        'size'        => $infoUrl['size'],
                        'mime'        => $infoUrl['mime'],
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
    protected function getInfoUrl($file)
    {   
        $bucket = env('MINIO_BUCKET');
        $expiry = env('PRESIGNED_URL_EXPIRY', 3600); // Mặc định 1 giờ
        $uuid = Str::uuid()->toString();
        $extension = $file->getClientOriginalExtension();
        $size = $file->getSize();
        $client_name = $file->getClientOriginalName();
        $mime = $file->getMimeType();
        $path = "uploads/{$uuid}/{$uuid}";

        return [
            'uuid' => $uuid,
            'extension' => $extension,
            'size' => $size,
            'client_name' => $client_name,
            'mime' => $mime,
            'path' => $path,
            'bucket' => $bucket,
            'expiry' => $expiry,
        ];
    }

    public function deleteFileFromS3( array $fileNames)
    {
        $bucket = env('MINIO_BUCKET');
        try {
            foreach ($fileNames as $file) {
                $filePath = "uploads/{$file}";
    
                // Xóa file trên MinIO
                $this->s3Client->deleteObject([
                    'Bucket' => $bucket,
                    'Key'    => $filePath,
                ]);
    
                Log::info("Deleted file: " . $filePath);
            }

            return true;
        } catch (AwsException $e) {
            Log::error("Failed to delete files from MinIO: " . $e->getMessage());
            return false;
        }
    }
}
