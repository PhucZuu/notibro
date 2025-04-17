<?php

namespace App\Services;

use App\Models\FileEntry;
use App\Models\Task;

class UploadFileService
{
    public function maxFileEachTask($task_id)
    {
        // Lấy số lượng file đã tải lên cho task này
        $countFile = FileEntry::where('task_id', $task_id)->count(); {
            // Kiểm tra xem số lượng file đã tải lên có vượt quá giới hạn không
            if ($countFile >= 5) {
                return response()->json(['error' => 'Reached the maximum file limit for this operation, please delete a file to continue uploading.'], 422);
            }
        }
    }

    public function duplicateFile($task_id_old, $task_id_new)
    {
        $fileEntries = FileEntry::where('task_id', $task_id_old)->get();
        $newEntries = [];

        foreach ($fileEntries as $fileEntry) {
            $new = $fileEntry->replicate()->toArray();
            $new['task_id'] = $task_id_new;
            unset($new['id']); // nếu cần thiết
            $newEntries[] = $new;
        }

        FileEntry::insert($newEntries);
    }
}
