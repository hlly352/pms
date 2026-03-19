<?php
namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'description', 'sort'];

    /**
     * 为 JSON 序列化准备日期格式
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    
}