<?php
namespace App\Models;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class ReadingPlan extends Model
{
    protected $fillable = ['book_id', 'speed_id', 'rule_id', 'daily_minutes', 'status'];
     /**
     * 为 JSON 序列化准备日期格式
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function book() 
    { 
        return $this->belongsTo(Book::class); 
    }
    public function speed()
    { 
        return $this->belongsTo(ReadingSpeed::class, 'speed_id'); 
    }
    // 假设你的规则模型叫 Rule
    public function rule() 
    { 
        return $this->belongsTo(Rule::class, 'rule_id'); 
    }
    
}