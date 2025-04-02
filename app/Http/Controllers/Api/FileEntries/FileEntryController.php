<?php

namespace App\Http\Controllers\Api\FileEntries;

use App\Http\Controllers\Api\S3UploadFile\S3SUploadController;
use App\Http\Controllers\Controller;
use App\Models\FileEntry;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FileEntryController extends Controller
{
    /**
     * Validate file request
     */
    protected function validateFiles(Request $request)
    {
        return $request->validate([
            'files'              => 'required|array',
            'files.*.file_name'   => 'required|uuid|max:255',
            'files.*.client_name' => 'required|string|max:255',
            'files.*.extension'   => 'required|string|max:10',
            'files.*.size'        => 'required|integer|min:1',
            'files.*.mime'        => 'required|string|max:50',
            'files.*.task_id'     => 'required|integer|exists:tasks,id',
        ]);
    }

    /**
     * Save file metadata
     */
    public function saveFile(Request $request)
    {
        $validatedData = $this->validateFiles($request);
        $ownerId = auth()->id();

        $fileEntries = collect($validatedData['files'])->map(function ($file) use ($ownerId) {
            return [
                'file_name'   => $file['file_name'],
                'client_name' => $file['client_name'],
                'extension'   => $file['extension'],
                'size'        => $file['size'],
                'mime'        => $file['mime'],
                'task_id'     => $file['task_id'],
                'owner_id'    => $ownerId,
                'created_at'  => Carbon::now(),
                'updated_at'  => Carbon::now(),
            ];
        })->toArray();

        try {
            FileEntry::insert($fileEntries);

            return response()->json([
                'message' => 'File metadata saved successfully',
                'files'   => $fileEntries,
            ], 201);
        } catch (\Throwable $th) {
            Log::error('Error saving file metadata: ' . $th->getMessage());
            return response()->json([
                'error'   => 'Failed to save file metadata',
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of files for a task
     */
    public function getListFile($taskId)
    {
        try {

            $taskExists = Task::where('id', $taskId)->exists();
            if (!$taskExists) {
                return response()->json(['data' => '[]'], 200);
            }

            $files = FileEntry::select(['id', 'file_name', 'client_name', 'extension', 'size', 'task_id', 'owner_id', 'created_at'])
                ->where('task_id', $taskId)
                ->get();

            if ($files->isEmpty()) {
                return response()->json(['data' => '[]'], 200);
            }

            $fileUrls = app(S3SUploadController::class)->getUrlPreviewFile($files);

            return response()->json(['files' => $fileUrls], 200);
        } catch (\Throwable $th) {
            Log::error("Error fetching files for task $taskId: " . $th->getMessage());
            return response()->json([
                'error'   => 'Failed to fetch files',
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function deleteFiles(Request $request)
    {
        $request->validate([
            'file_names' => 'required|array',
            'file_names.*' => 'required|uuid|max:255',
        ]);

        $fileDels = $request->input('file_names');

        $delS3Success = app(S3SUploadController::class)->deleteFileFromS3($fileDels);

        try {
            // Xóa file trong database
            $deleted = FileEntry::whereIn('file_name', $fileDels)->delete();
    
            if ($deleted > 0) {
                return response()->json(['message' => 'Files deleted successfully.'], 200);
            }
    
            return response()->json(['messages' => 'Files not found.'], 200);
        } catch (\Throwable $th) {
            Log::error('Error deleting files: ' . $th->getMessage());
            return response()->json([
                'error'   => 'Failed to delete files',
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
