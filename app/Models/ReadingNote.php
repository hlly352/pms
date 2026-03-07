<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ReadingNote extends Model
{
    protected $fillable = ['book_id', 'content'];

    // 一条感悟属于一本书
    public function book()
    {
        return $this->belongsTo(Book::class);
    }
}