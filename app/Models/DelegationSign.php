<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * 운영 데이터 › 위임장 서명 (2026-09-11 지시).
 *
 * 기존 처방ㆍ주문ㆍ거래처와 잇지 않는 별도 기능이다. 받는 사람의 이름과 번호를
 * 제 칸으로 들고, 다른 표를 하나도 참조하지 않는다 — 표 하나와 폴더 하나만
 * 운영 서버로 옮기면 되도록.
 */
class DelegationSign extends Model
{
    use HasFactory;

    /**
     * 서명 그림을 두는 곳 — 폴더째 옮긴다. 웹에서 바로 열리지 않는다.
     *
     * 이 디스크의 뿌리가 곧 storage/app/private/delegation-signs 다. 표에 적는
     * 길은 그 안에서의 길(2026/09/…png)이라, 폴더를 통째로 옮겨 놓으면 그대로
     * 이어진다 — 앞에 붙은 자리 이름을 함께 옮겨 적을 일이 없다.
     */
    public const 디스크 = 'delegation';

    /**
     * 위임을 받는 곳 — 늘 한 곳이다 (2026-09-11 지시).
     *
     * 명단이 적어 보낸 판매처는 하이메드ㆍ(주)서호메디코로 갈리지만, 링크를 받은
     * 사람이 위임하는 상대는 콜로플라스트 코리아뿐이다. 서명을 받고 나면 그 사람의
     * 판매처는 여기로 바뀐다 — 위임이 끝난 뒤의 판매처는 대리점이 아니다.
     *
     * 적는 말은 명단이 쓰던 것을 그대로 따른다. 「콜로플라스트 코리아(주)」로
     * 달리 적었더니 판매처 고르개에 닮은 이름이 두 줄로 섰다.
     */
    public const 위임받는곳 = '콜로플라스트 코리아 주식회사';

    /**
     * 어디서 온 줄인가 (2026-09-14 지시).
     *
     * direct 는 담당자가 이름ㆍ번호를 적어 보낸 줄이다. 받는 사람에게 무엇이
     * 가는지 보려고 제 번호로 보내 본 것이라, 명단의 「보낼 사람」과 섞이면 안 된다.
     */
    public const 갈래 = [
        'list'   => '명단',
        'direct' => '직접 발송',
    ];

    /**
     * 링크를 어느 번호로 보내는가 (2026-09-14 지시).
     *
     * 환자가 문자를 받지 못하는 건이 있다. 그때 보호자에게 보내되, 어느 쪽으로
     * 보내는지는 보내기 전에 화면에서 정해 둔다.
     */
    public const 연락 = [
        'patient'  => '환자',
        'guardian' => '보호자',
    ];

    protected $fillable = [
        'customer_name', 'phone', 'guardian_phone', 'main_contact',
        /* 명단이 들고 오는 값 (2026-09-15 지시) — 위임은 만 19세 미만이면 법정대리인이
           대신 한다. 보내기 전에 그것을 알아야 미성년에게 헛걸음하지 않는다.
           주민등록번호는 평문 자리(resident_no)에 넣으면 모델이 암호화해 담는다. */
        'resident_no', 'resident_no_masked', 'birth_date',
        'guardian_name', 'guardian_relation', 'guardian_birth_date',
        /* 보호자 서명과 신분증 (2026-09-15 지시) — 미성년의 위임은 법정대리인이 한다.
           칸 이름은 주문 등록 쪽(prescription_consents)과 같게 둔다. */
        'guardian_signature_data', 'guardian_sign_path', 'guardian_id_path', 'guardian_id_mime',
        'src_no', 'source', 'dealer_name', 'next_repurchase_at', 'last_register_at', 'rx_days',
        'last_confirm_at', 'src_status', 'rx_type', 'benefit_class', 'last_sale_status',
        'token', 'sent_to', 'sent_by_id', 'sent_by_name', 'sent_at', 'expires_at',
        'status',
        'agree_delegation', 'agree_privacy', 'agree_marketing',
        'signed_at', 'sign_path', 'sign_filename', 'sign_base64',
        'nice_verified_at', 'nice_name', 'nice_birthdate', 'nice_gender',
        'nice_mobile', 'nice_ci', 'nice_di',
        'ip', 'user_agent',
    ];

    protected $casts = [
        /* 날짜는 형식을 지닌 채 새긴다 — 그냥 date 로 두면 JSON 으로 나갈 때
           UTC ISO 로 적혀 아홉 시간 어긋난 값이 화면에 선다 (2026-09-14 확인) */
        'birth_date'          => 'date:Y-m-d',
        'guardian_birth_date' => 'date:Y-m-d',
        'sent_at'          => 'datetime',
        'expires_at'       => 'datetime',
        'signed_at'        => 'datetime',
        'nice_verified_at' => 'datetime',
        'next_repurchase_at' => 'date',
        'last_register_at'   => 'date',
        'last_confirm_at'    => 'date',
        'agree_delegation' => 'boolean',
        'agree_privacy'    => 'boolean',
        'agree_marketing'  => 'boolean',
    ];

    public const 상태 = [
        'pending'  => '발송 전',
        'sent'     => '서명 대기',
        'signed'   => '서명 완료',
        'declined' => '동의 거절',
    ];

    /**
     * 받는 사람이 보는 이름 — (E) 를 뗀다 (2026-09-14 지시).
     *
     * (E) 는 사업부가 IC 라는 우리 쪽 표시다. 문자와 서명 화면은 **환자가 보는
     * 자리**라 그 표시가 설 곳이 아니다 — 「(E)이소예님」으로 문자가 나갔다.
     *
     * 떼는 규칙은 Patient 가 이미 들고 있어 그것을 쓴다. 같은 정규식을 여기에
     * 한 번 더 적으면 한쪽만 고칠 때 어긋난다. 표를 참조하는 것이 아니라 글자를
     * 다루는 함수라, 이 기능이 표 하나로 닫힌다는 것과 어긋나지 않는다.
     */
    /* ── 주민등록번호 (2026-09-15 지시) ──────────────────────────────────

       처방전이 쓰는 것과 같은 자리를 쓴다(App\Support\ResidentNo). 평문을 표에 두지
       않는다 — 넣을 때 암호화하고, 화면에는 가린 값만 내보낸다.

       가린 값만으로도 생년월일ㆍ성별ㆍ성년 여부를 읽을 수 있다. 뒷자리 첫 숫자가
       세기를 말해 주기 때문이다. 그래서 이 세 가지를 물을 때는 복호화하지 않는다. */
    public function setResidentNoAttribute($value): void
    {
        $값 = trim((string) $value);

        if ($값 === '') {
            $this->attributes['resident_no']        = null;
            $this->attributes['resident_no_masked'] = null;

            return;
        }

        /* 가려진 값이 되돌아오면 적어 둔 것을 그대로 둔다 (2026-09-17, 거래처와 같다).
           화면이 「120315-3******」로 보여 주므로, 손대지 않고 저장하면 그 글이
           그대로 온다 — 번호로 알고 다시 암호화하면 적어 둔 번호가 별표로 덮인다. */
        if (str_contains($값, '*')) {
            return;
        }

        $this->attributes['resident_no']        = \App\Support\ResidentNo::encrypt($값);
        $this->attributes['resident_no_masked'] = \App\Support\ResidentNo::mask($값);

        /* 생년월일도 함께 세운다 — 나이와 성년 판정이 이 값을 본다. 명단이 나이를
           따로 들고 오지만 그것은 뽑은 날의 나이라 해가 바뀌면 어긋난다. */
        if ($생 = \App\Support\ResidentNo::birthDateFromMasked($this->attributes['resident_no_masked'])) {
            $this->attributes['birth_date'] = $생->toDateString();
        }
    }

    /** 화면에 적는 주민등록번호 — 가린 값이다 */
    public function 주민번호(): string
    {
        return (string) ($this->resident_no_masked ?? '');
    }

    /** 만 나이 — 생년월일에서 센다. 못 읽으면 null */
    public function 나이(): ?int
    {
        return $this->birth_date?->age;
    }

    /**
     * 성년인가 미성년인가 (2026-09-15 지시).
     *
     * 주민등록번호로 가른다. 모르면 빈칸으로 둔다 — 「모른다」를 「성년」으로 적어 두면
     * 미성년에게 보호자 없이 링크가 나가고, 그 링크는 보호자 칸에서 멈춘다.
     */
    public function 성년구분(): string
    {
        $미성년 = \App\Support\ResidentNo::isMinorByMasked($this->resident_no_masked);

        return match ($미성년) {
            true    => '미성년',
            false   => '성년',
            default => '',
        };
    }

    public function 미성년인가(): bool
    {
        return \App\Support\ResidentNo::isMinorByMasked($this->resident_no_masked) === true;
    }

    public function 이름(): string
    {
        return Patient::bare($this->customer_name);
    }

    /**
     * 지금 링크가 갈 번호 — Main contact 가 가리키는 쪽 (2026-09-14 지시).
     *
     * 보호자로 정해 두었는데 보호자 번호가 비어 있으면 환자 번호로 보내지 않는다.
     * 「보호자에게 보내기로 했다」는 뜻을 조용히 뒤집는 셈이라, 차라리 보내지 않고
     * 화면에서 ［수정］을 눌러 채우게 한다.
     */
    public function 보낼번호(): ?string
    {
        $번호 = $this->main_contact === 'guardian' ? $this->guardian_phone : $this->phone;
        $숫자 = preg_replace('/\D/', '', (string) $번호);

        return strlen($숫자) >= 9 && strlen($숫자) <= 11 ? $숫자 : null;
    }

    /** 지금 이 링크로 서명할 수 있는가 */
    public function 열려있나(): bool
    {
        return $this->status === 'sent'
            && $this->token
            && $this->expires_at
            && $this->expires_at->isFuture();
    }

    /**
     * 서명 그림 — 파일을 먼저 보고, 없으면 표에 담아 둔 것으로 그린다.
     *
     * 두 벌을 함께 남기는 뜻이 여기서 드러난다. 폴더를 옮기지 못했거나 파일이
     * 어긋나도 표 하나로 서명이 되살아난다.
     */
    public function 서명그림(): ?string
    {
        /* 서명을 마친 줄만 그림을 내준다 (2026-09-16 지시).

           재발송하면 상태는 「서명 대기」로 돌아가지만 서명 칸은 그대로 남는다
           (보내기() 가 보낸 자취만 덮는다). 그래서 목록에 「서명 대기」라고 적힌
           줄에 지난번 서명 그림이 함께 보였다 — 다시 받아야 하는 건인데 이미 받아
           둔 것처럼 읽히고, 그 그림이 공단에 내는 서류로 실려 나갈 수 있었다.

           담긴 값은 지우지 않는다. 그때 실제로 받은 서명이라 자취로는 남아야 한다 —
           내주지 않을 뿐이다. */
        if ($this->status !== 'signed') {
            return null;
        }

        if ($this->sign_path && Storage::disk(self::디스크)->exists($this->sign_path)) {
            return Storage::disk(self::디스크)->get($this->sign_path);
        }

        if (! $this->sign_base64) {
            return null;
        }

        $조각 = preg_replace('~^data:image/\w+;base64,~', '', $this->sign_base64);

        return base64_decode($조각, true) ?: null;
    }

    /** 목록에 세우는 말 — 「서명함 / 아직」처럼 읽히게 */
    public function 동의말(string $칸): string
    {
        if ($this->status !== 'signed') {
            return '—';
        }

        return $this->{$칸} ? '동의함' : '동의하지 않음';
    }
}
