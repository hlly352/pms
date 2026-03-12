<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TimeRule extends Model {
    protected $guarded = [];
    public function items() {
        return $this->hasMany(TimeRuleItem::class);
    }
}