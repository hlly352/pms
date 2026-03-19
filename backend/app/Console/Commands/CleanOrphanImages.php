<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CleanOrphanImages extends Command
{
    /**
     * 命令名称 (在终端运行的名字)
     * --dry-run : 可选参数，如果加上这个参数，只模拟执行，不真删
     */
    protected $signature = 'pms:clean-images {--dry-run : 仅模拟执行，不真正删除文件}';

    /**
     * 命令描述
     */
    protected $description = '清理未被任何文档引用的孤儿图片';

    /**
     * 执行逻辑
     */
    public function handle()
    {
        $this->info('🚀 开始扫描孤儿图片...');
        
        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->warn('⚠️  当前为 Dry-Run 模式，不会真正删除文件。');
        }

        // ==========================================
        // 1. 获取所有存在于硬盘上的图片
        // ==========================================
        // 假设图片存在 storage/app/public/uploads
        $allFiles = Storage::disk('public')->files('uploads');
        
        // 提取文件名 (例如: 'uploads/abc.png' -> 'abc.png')
        // 我们用文件名作为唯一标识来比对
        $diskFiles = collect($allFiles)->mapWithKeys(function ($path) {
            return [basename($path) => $path];
        });

        $this->info("硬盘上共有文件: " . $diskFiles->count() . " 个");

        // ==========================================
        // 2. 获取数据库中正在使用的图片
        // ==========================================
        // 你的表名是 recitations，内容字段是 content
        $contents = DB::table('recitations')->whereNotNull('content')->pluck('content');
        
        $usedFiles = [];

        // 正则表达式：匹配 Markdown 图片语法 ![xxx](url) 和 HTML <img> 标签
        // 这是一个比较通用的正则，能匹配绝大多数图片链接
        $regex = '/!\[.*?\]\((.*?)\)|<img.*?src=["\'](.*?)["\']/';

        foreach ($contents as $text) {
            preg_match_all($regex, $text, $matches);
            
            // $matches[1] 是 Markdown 的 URL, $matches[2] 是 HTML 的 URL
            // 合并所有匹配到的 URL
            $urls = array_merge($matches[1], $matches[2]);

            foreach ($urls as $url) {
                // URL 可能是 "/storage/uploads/abc.png"
                // 我们只需要提取文件名 "abc.png"
                $filename = basename($url);
                
                // 只有当这个文件名确实在我们的 uploads 目录下时，才算作“被占用”
                if ($diskFiles->has($filename)) {
                    $usedFiles[] = $filename;
                }
            }
        }

        // 去重 (因为一张图可能被多篇文章引用)
        $usedFiles = array_unique($usedFiles);
        $this->info("数据库中引用文件: " . count($usedFiles) . " 个");

        // ==========================================
        // 3. 找出差异 (孤儿文件)
        // ==========================================
        // 在硬盘上，但不在数据库里的，就是孤儿
        $orphanFiles = $diskFiles->except($usedFiles);

        if ($orphanFiles->isEmpty()) {
            $this->info('✅ 太棒了！没有发现孤儿图片，系统很干净。');
            return;
        }

        $this->error("发现 " . $orphanFiles->count() . " 个孤儿图片待清理！");

        // ==========================================
        // 4. 执行删除
        // ==========================================
        $bar = $this->output->createProgressBar($orphanFiles->count());
        $bar->start();

        foreach ($orphanFiles as $filename => $path) {
            if ($isDryRun) {
                // 模拟模式：只打印，不删
                $this->line("  [模拟删除] $path");
            } else {
                // 真实模式：删除文件
                Storage::disk('public')->delete($path);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($isDryRun) {
            $this->warn('🏁 模拟结束。如果要真删，请去掉 --dry-run 参数再次运行。');
        } else {
            $this->info('🎉 清理完成！空间已释放。');
        }
    }
}