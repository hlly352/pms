<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class UploadController extends Controller
{
    // 📷 上传图片（直传七牛云）
    public function uploadImage(Request $request)
    {
        Log::info('---------- 开始上传图片到七牛云 ----------');

        // 1. 检查文件有效性
        if (!$request->hasFile('file')) {
            return response()->json(['message' => '未接收到文件'], 400);
        }

        $file = $request->file('file');

        if (!$file->isValid()) {
            Log::error('文件上传失败: ' . $file->getErrorMessage());
            return response()->json(['message' => '上传失败: ' . $file->getErrorMessage()], 400);
        }

        try {
            // 2. 🔴 核心修改点：把 'public' 换成 'qiniu'
            // 这行代码会自动把文件流传给七牛云，并不再保存在本地 storage 目录
            $path = $file->store('uploads', 'qiniu');

            if (!$path) {
                Log::error('store() 返回 false，七牛云上传失败，请检查 AK/SK 或网络');
                return response()->json(['message' => '上传到七牛云失败'], 500);
            }

            // 3. 获取七牛云的完整外网访问链接
            // 它会自动拼接你 .env 里的 QINIU_DOMAIN
            $fullUrl = Storage::disk('qiniu')->url($path);
            // 🔴 魔法在这里：强行给所有返回的链接加上七牛云限制宽度的参数
            // 比如默认最大宽度 800，超过 800 的会被缩小，不够 800 的保持原样
            $resizedUrl = $fullUrl . '?imageView2/2/h/400';

            Log::info('七牛云上传成功，外网链接: ' . $fullUrl);

            // 4. 返回给前端
            return response()->json([
                'url' => $resizedUrl, // 返回带缩放参数的链接 // 这里返回的是 http://你的域名/uploads/xxx.png
                'alt' => $file->getClientOriginalName()
            ]);

        } catch (\Exception $e) {
            Log::error('七牛云上传过程异常: ' . $e->getMessage());
            return response()->json(['message' => '服务器内部错误: ' . $e->getMessage()], 500);
        }
    }

    // 🗑️ 同步修改：如果想在编辑器删除图片时，也从七牛云删掉
    public function deleteImage(Request $request)
    {
        $url = $request->input('url');
        if (!$url) return response()->json(['message' => 'URL 不能为空'], 400);

        try {
            // 提取文件相对路径 (去掉前面的域名)
            $domain = rtrim(config('filesystems.disks.qiniu.domain'), '/');
            $path = str_replace($domain . '/', '', $url);

            // 🔴 核心修改点：从 qiniu 磁盘删除
            if (Storage::disk('qiniu')->exists($path)) {
                Storage::disk('qiniu')->delete($path);
                Log::info('七牛云图片已删除: ' . $path);
                return response()->json(['message' => '删除成功']);
            }

            return response()->json(['message' => '文件不存在'], 404);
        } catch (\Exception $e) {
            Log::error('七牛云删除失败: ' . $e->getMessage());
            return response()->json(['message' => '删除失败'], 500);
        }
    }
}