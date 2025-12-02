<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public const STATUS = [
        0 => ['label' => 'Mới tạo',     'class' => 'label-default'],
        2 => ['label' => 'Đang xử lý',  'class' => 'label-primary'],
        // 3 bước bạn đã làm xong
        3 => ['label' => 'Đã xác nhận', 'class' => 'label-info'],
        4 => ['label' => 'Đã giao',     'class' => 'label-warning'],
        1 => ['label' => 'Hoàn thành',  'class' => 'label-success'],
        5 => ['label' => 'Đã hủy',      'class' => 'label-danger'],
        6 => ['label' => 'Hoàn tiền',   'class' => 'label-warning'],
    ];

    // ✅ Sửa tại đây: Hoàn thành (1) không còn cho chuyển sang 6
    public const TRANSITIONS = [
        0 => [2,3,5],
        2 => [3,4,5],
        3 => [2,4,5],
        4 => [1,6],
        1 => [],     // <-- KHÓA TUYỆT ĐỐI, không hoàn tiền nữa
        5 => [],
        6 => [],
    ];

    protected $fillable = ['status'];
    protected $casts = ['status' => 'integer'];

    // Tương thích kép với cả App\User và App\Models\User
    protected static function userModelClass(): string
    {
        return class_exists(\App\Models\User::class)
            ? \App\Models\User::class
            : \App\User::class;
    }

    public function user(){ return $this->belongsTo(static::userModelClass(), 'user_id'); }
    public function payment_method(){ return $this->belongsTo(\App\Models\PaymentMethod::class); }
    public function order_details(){ return $this->hasMany(\App\Models\OrderDetail::class); }

    public function getStatusLabelAttribute(): string
    {
        $s = self::STATUS[$this->status] ?? ['label'=>'Không rõ','class'=>'label-default'];
        return "<span class=\"label {$s['class']}\">{$s['label']}</span>";
    }

    // ⬇️ Quan trọng: loại bỏ giá trị NULL/không tồn tại trong STATUS
    public function availableNextStatuses(): array
    {
        $candidates = array_merge([$this->status], self::TRANSITIONS[$this->status] ?? []);
        $valid = array_values(array_filter($candidates, function($v){
            return $v !== null && array_key_exists($v, self::STATUS);
        }));
        return array_values(array_unique($valid));
    }

    // (Tuỳ chọn an toàn, không phá tương thích vì không được gọi tự động)
    public function isLocked(): bool { return in_array((int)$this->status, [1,5,6], true); }
    public function canRefund(): bool { return (int)$this->status === 4; }
    public function canTransitionTo(int $to): bool
    {
        $from = (int)$this->status;
        if ($to === $from) return true;
        if ($this->isLocked()) return false;
        if ($to === 6 && !$this->canRefund()) return false;
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
}
