<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>{{ $doc->title }}</title>
    <style>
        /* =======================================================
           mPDF 基础排版样式
           注意：mPDF 对 CSS 的支持有限，尽量使用基础选择器和属性
           ======================================================= */
        body {
            /* 字体依赖于 mPDF 的 autoLangToFont 配置，通常设为 sans-serif 即可 */
            font-family: sans-serif; 
            font-size: 14px;
            line-height: 1.6;
            color: #333333;
        }

        /* 顶部标题区 */
        .doc-header {
            text-align: center;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .doc-title {
            font-size: 26px;
            font-weight: bold;
            color: #2c3e50;
            margin: 0 0 10px 0;
        }
        .doc-meta {
            font-size: 12px;
            color: #909399;
        }

        /* =======================================================
           Markdown 正文内容解析样式
           ======================================================= */
        .markdown-body {
            width: 100%;
            word-wrap: break-word;
        }

        /* 标题排版：避免标题单独留在页面底部 */
        .markdown-body h1, 
        .markdown-body h2, 
        .markdown-body h3, 
        .markdown-body h4 {
            color: #303133;
            margin-top: 24px;
            margin-bottom: 12px;
            font-weight: bold;
            page-break-after: avoid; 
        }
        .markdown-body h1 { font-size: 22px; border-bottom: 1px solid #eaecef; padding-bottom: 5px; }
        .markdown-body h2 { font-size: 20px; }
        .markdown-body h3 { font-size: 18px; }

        /* 段落与列表 */
        .markdown-body p { margin-top: 0; margin-bottom: 12px; }
        .markdown-body ul, .markdown-body ol { margin-top: 0; margin-bottom: 12px; padding-left: 20px; }
        .markdown-body li { margin-bottom: 4px; }

        /* 引用块 */
        .markdown-body blockquote {
            margin: 0 0 15px 0;
            padding: 10px 15px;
            color: #6a737d;
            border-left: 4px solid #dfe2e5;
            background-color: #f8f9fa;
        }
        .markdown-body blockquote p { margin-bottom: 0; }

        /* 代码块排版 */
        .markdown-body pre {
            background-color: #f6f8fa;
            border-radius: 4px;
            padding: 12px;
            overflow: auto;
            border: 1px solid #eaecef;
            page-break-inside: avoid; /* 防止代码块跨页被切断 */
        }
        .markdown-body code {
            font-family: 'Courier New', Courier, monospace;
            background-color: #f6f8fa;
            padding: 2px 4px;
            border-radius: 3px;
            font-size: 12px;
        }
        .markdown-body pre code {
            padding: 0;
            background-color: transparent;
        }

        /* 表格排版 */
        .markdown-body table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        .markdown-body table th,
        .markdown-body table td {
            border: 1px solid #dfe2e5;
            padding: 8px 10px;
            text-align: left;
        }
        .markdown-body table th {
            background-color: #f6f8fa;
            font-weight: bold;
        }

        /* 图片排版 */
        .markdown-body img {
            max-width: 100%;
            height: auto;
            border-radius: 4px;
            display: block;
            margin: 15px auto;
            page-break-inside: avoid; /* 防止图片跨页被切断 */
        }
        
        /* 强调文字 */
        .markdown-body strong { color: #303133; }
        .markdown-body em { color: #606266; }
    </style>
</head>
<body>

    <div class="doc-header">
        <h1 class="doc-title">{{ $doc->title }}</h1>
        <div class="doc-meta">
            创建时间：{{ $doc->created_at->format('Y-m-d H:i') }} 
            &nbsp;|&nbsp; 
            最后更新：{{ $doc->updated_at->format('Y-m-d H:i') }}
        </div>
    </div>

    <div class="markdown-body">
        {!! Illuminate\Support\Str::markdown($doc->content) !!}
    </div>

</body>
</html>