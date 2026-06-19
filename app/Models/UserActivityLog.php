<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'type', 'menu_name', 'route_name', 'url', 'ip_address', 'user_agent',
    ];

    // 라우트명 → 한국어 메뉴명 매핑
    public const MENU_NAMES = [
        'dashboard'                => '대시보드',
        'dashboard.index'          => '대시보드',
        'patients.index'           => '환자관리',
        'patients.show'            => '환자 상세',
        'prescriptions.upload'     => '처방전 업로드',
        'prescriptions.index'      => '처방전 목록',
        'prescriptions.show'       => '처방전 상세',
        'repurchase.index'         => '재구매 관리',
        'repurchase.day'           => '재구매 관리',
        'orders.index'             => '주문관리',
        'orders.show'              => '주문 상세',
        'nhis.index'               => 'NHIS 청구',
        'invoice.index'            => '계산서 발행',
        'settlement.index'         => '정산/회계',
        'dispatch.index'           => '발송/발행 내역',
        'notices.index'            => '공지사항',
        'notices.show'             => '공지사항 상세',
        'notices.create'           => '공지사항 작성',
        'inquiries.index'          => '문의하기',
        'inquiries.show'           => '문의 상세',
        'user-logs.index'          => '사용자 로그',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function menuName(string $routeName): string
    {
        return self::MENU_NAMES[$routeName] ?? $routeName;
    }
}
