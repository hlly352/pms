<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;

    // 允许这些字段被直接写入
    protected $fillable = [
        'title', 
        'author', 
        'category',
        'word_count', // 🆕 新增字数
        'page_count', // 🆕 新增页数
        'status', 
        'rating', 
        'cover_url'
    ];
    // 一本书有多条感悟
    public function readingNotes()
    {
        return $this->hasMany(ReadingNote::class)->orderBy('created_at', 'desc');
    }
}