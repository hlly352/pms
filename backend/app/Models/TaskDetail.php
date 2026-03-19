<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class TaskDetail extends Model
{
    protected $guarded = [];
    
    // protected $casts = [
    //     'task_time' => 'datetime',
    //     'finished_at' => 'datetime'
    // ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
    //默认情况下，laravel会返回UTC时间，加上如下函数则会返回北京时间
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}