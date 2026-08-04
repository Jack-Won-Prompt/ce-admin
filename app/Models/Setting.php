<?php
// app/Models/Setting.php
// settings 테이블의 한 줄 = 설정 항목 하나.
// 비밀값은 저장할 때 암호화하고 읽을 때 푼다. 원문은 DB 에 남지 않는다.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'is_secret'];

    protected $casts = ['is_secret' => 'boolean'];

    // 직렬화(toArray/toJson)로 비밀값이 새어나가지 않게 한다.
    protected $hidden = ['value'];

    /**
     * 저장된 원문. 비밀값이면 복호화해서 돌려준다.
     *
     * 복호화가 깨지는 경우 — APP_KEY 를 바꿨거나 다른 환경의 DB 를 붙였을 때 —
     * 예외를 그대로 올리면 화면 전체가 죽는다. 로그만 남기고 빈 값으로 둔다.
     * 관리자가 해당 항목을 다시 입력하면 정상으로 돌아온다.
     */
    public function plainValue(): ?string
    {
        if ($this->value === null || $this->value === '') {
            return $this->value;
        }
        if (! $this->is_secret) {
            return $this->value;
        }

        try {
            return Crypt::decryptString($this->value);
        } catch (\Throwable $e) {
            Log::warning('설정 복호화 실패 — 값을 비워 둔다', [
                'group' => $this->group,
                'key'   => $this->key,
            ]);
            return null;
        }
    }

    /** 원문을 넣는다. 비밀값이면 암호화해서 담는다. */
    public function setPlainValue(?string $plain, bool $secret): void
    {
        $this->is_secret = $secret;
        $this->value = ($secret && $plain !== null && $plain !== '')
            ? Crypt::encryptString($plain)
            : $plain;
    }
}
