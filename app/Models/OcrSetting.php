<?php
// app/Models/OcrSetting.php
// 처방전 OCR 공급자 설정 (단일 행). 값이 없으면 config('ocr.default_provider')로 시드.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class OcrSetting extends Model
{
    public const PROVIDERS = ['textract', 'ai'];

    protected $fillable = ['provider'];

    /** 단일 설정 행 반환 (없으면 config 기본값으로 생성). */
    public static function current(): self
    {
        return static::firstOr(function () {
            $default = config('ocr.default_provider', 'textract');
            return static::create([
                'provider' => in_array($default, self::PROVIDERS, true) ? $default : 'textract',
            ]);
        });
    }

    /**
     * 현재 활성 provider ('textract' | 'ai').
     * DB 접근 불가(마이그레이션 전 등) 시 config 기본값으로 안전 폴백.
     */
    public static function provider(): string
    {
        try {
            return static::current()->provider;
        } catch (\Throwable $e) {
            Log::warning('OcrSetting 조회 실패, config 기본값 사용', ['error' => $e->getMessage()]);
            $default = config('ocr.default_provider', 'textract');
            return in_array($default, self::PROVIDERS, true) ? $default : 'textract';
        }
    }
}
