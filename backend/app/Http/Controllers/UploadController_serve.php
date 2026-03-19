<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log; // 👈 引入日志

class UploadController extends Controller {
public function uploadImage(Request $request)
    {
        // 1. 检查是否有文件
        if (!$request->hasFile('file')) {
            return response()->json(['message' => '未接收到文件'], 400);
        }

        $file = $request->file('file');

        // 2. 🔥 核心诊断：检查文件是否上传成功
        // isValid() 会检查 $_FILES['file']['error'] 是否为 0
        if (!$file->isValid()) {
            $errorMessage = $file->getErrorMessage(); // 获取具体错误信息
            Log::error('文件上传失败，原因: ' . $errorMessage);
            return response()->json(['message' => '上传失败: ' . $errorMessage], 400);
        }

        try {
            // 4. 保存文件
            $path = $file->store('uploads', 'public');

            if (!$path) {
                return response()->json(['message' => '文件写入硬盘失败'], 500);
            }

            return response()->json([
                'url' => Storage::url($path),
                'alt' => $file->getClientOriginalName()
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => '服务器内部错误'], 500);
        }
    }
}
