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

    /**
     * 받는 사람이 보는 이름 — (E) 와 **동명이인 꼬리**를 모두 뗀다.
     *
     * 2026-09-14 에 (E) 만 뗐는데, 꼬리(김예나A 의 A)가 남아 문자에 그대로 찍혔다 —
     * 2026-10-12 기준 86건이 그렇게 나갔다. 꼬리는 우리 쪽 표시이지 법적 이름이 아니다.
     */
    public function 이름(): string
    {
        return Patient::실명($this->customer_name);
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
        /* **미성년이면 보호자가 받는다** (2026-10-15 지시).

           만 19세 미만의 위임은 법정대리인이 한다. 링크를 아이 번호로 보내면 보호자
           칸에서 멈추고, 아이가 제 휴대전화로 본인확인을 할 수도 없다. Main contact
           가 무엇으로 서 있든 이 경우에는 보호자가 먼저다.

           보호자 번호가 비어 있으면 환자 번호로 **물러나지 않는다** — 그러면 미성년에게
           링크가 가고, 그 링크는 어차피 보호자 칸에서 멈춘다. 보내지 않고 화면에서
           ［수정］으로 채우게 한다(2026-09-14 에 Main contact 를 그렇게 다룬 것과 같다). */
        $보호자쪽 = $this->미성년인가() || $this->main_contact === 'guardian';
        $번호 = $보호자쪽 ? $this->guardian_phone : $this->phone;
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

    /**
     * 이 거래처의 서명을 **원본에서** 찾는다 — 없으면 null (2026-10-01 지시).
     *
     * 이관(2026-09-29)은 한 번 돌린 것이라, 그 뒤에 서명한 사람은 이 표에 들어오지
     * 못한다. 그래서 운영 데이터 › 위임장 서명에는 「서명 완료」로 보이는데 주문
     * 등록 화면은 「서명 동의 대기중」이었다 — 두 화면이 서로 다른 표를 읽은 것이다.
     * 2026-10-01 네 사람이 그랬다(최경범ㆍ김석현ㆍ박상서ㆍ황유진).
     *
     * 이제 옮겨 담은 표에서 못 찾으면 여기로 와서 원본을 본다. 이관이 다시 돌아도
     * 같은 답이 나오도록 **맞추는 잣대를 이관 명령과 같게** 둔다
     * (MigratePatientsFromWithworksCommand) — 이름을 맞추고, 가린 주민번호로 좁히고,
     * 둘 이상이면 쓰지 않는다. 사람을 잘못 이으면 남의 서명으로 위임장이 나간다.
     *
     * 이관 명령은 운영 고객(ww_customers)을 거쳐 이었지만 여기서는 거래처를 손에
     * 들고 있다. 가린 주민번호가 없는 원본은 생년월일로 좁히고, 그것도 없으면
     * 그 이름이 **원본에도 거래처에도 하나뿐일 때만** 잇는다.
     *
     * **이름으로 못 찾으면 연락처로 한 번 더 본다** (2026-10-12 지시).
     * 거래처 이름이 바뀌면 원본에는 서명한 그날의 이름이 그대로 남아 두 이름이
     * 갈린다 — (E)팜티인 님이 (E)하나인 으로 개명한 건이 그랬다. 운영 데이터에는
     * 「서명 완료」인데 주문 등록 화면은 「서명 동의 대기중」이고, 첨부 파일에도
     * 서명이 보이지 않았다. 자세한 잣대는 연락처것() 에 적는다.
     *
     * @param string $동의칸 agree_delegation | agree_privacy — 무엇에 동의한 줄을 찾는가
     */
    public static function 거래처것(?Patient $patient, string $동의칸 = 'agree_delegation'): ?self
    {
        if (! $patient?->id || ! in_array($동의칸, ['agree_delegation', 'agree_privacy'], true)) {
            return null;
        }

        $이름들 = static::찾을이름들($patient);

        if ($이름들) {
            $후보 = static::서명된줄($동의칸)
                ->whereIn('customer_name', $이름들)
                ->orderByDesc('signed_at')->orderByDesc('id')
                ->get();

            if ($후보->isNotEmpty()
                && $찾음 = static::좁히기($후보, $patient, Patient::whereIn('name', $이름들)->count())) {
                return $찾음;
            }
        }

        return static::연락처것($patient, $동의칸);
    }

    /**
     * 연락처로 찾는다 — 개명한 사람을 잇는 길 (2026-10-12 지시).
     *
     * 이름이 바뀌어도 번호ㆍ주민번호ㆍ생년월일은 그대로다. 그래서 번호로 줄을
     * 모으고, **가린 주민번호나 생년월일이 맞아야만** 잇는다(좁히기엄격).
     *
     * 번호만으로는 절대 잇지 않는다. 집 전화 한 대를 식구가 함께 쓰고, 보호자
     * 번호를 제 번호 칸에 적어 둔 줄도 있다 — 번호 하나로 이으면 남의 서명으로
     * 위임장이 공단에 나간다. 이름만으로 잇는 걸음(좁히기 ③)도 여기서는 쓰지
     * 않는다: 그 걸음은 「이름이 하나뿐」이라는 근거로 서는데, 여기 모인 줄은
     * 이름이 애초에 다른 줄이다.
     *
     * 보는 칸은 번호가 담기는 셋 — `phone`(명단의 연락처)ㆍ`sent_to`(링크를 보낸
     * 곳)ㆍ`nice_mobile`(본인확인에 쓴 번호). 뒤의 둘은 **그 사람 제 번호가 아닐
     * 수 있다**: 보호자가 제 번호로 받아 본인확인까지 하는 건이 있다(서명 1827 이
     * 그렇다 — 본인확인 이름ㆍ생년월일이 거래처와 다르다). 그래도 모으는 데는
     * 쓴다. 짝이 맞는지는 **그 줄의 거래처 칸**(resident_no_maskedㆍbirth_date)으로
     * 다시 보므로, 보호자를 거래처로 잘못 잇지 않는다.
     *
     * `main_contact` 는 보지 않는다 — 번호가 아니라 `patient`ㆍ`guardian` 둘만 담는
     * 구분 칸이다(운영 3,648 대 9).
     * `guardian_phone` 도 보지 않는다 — 보호자 번호다.
     */
    private static function 연락처것(Patient $patient, string $동의칸): ?self
    {
        $번호들 = static::찾을번호들($patient);

        if (! $번호들) {
            return null;
        }

        $후보 = static::서명된줄($동의칸)
            ->where(function ($q) use ($번호들) {
                foreach (['phone', 'sent_to', 'nice_mobile'] as $칸) {
                    $q->orWhereIn(
                        \Illuminate\Support\Facades\DB::raw("REPLACE(REPLACE(IFNULL(`{$칸}`, ''), '-', ''), ' ', '')"),
                        $번호들
                    );
                }
            })
            ->orderByDesc('signed_at')->orderByDesc('id')
            ->get();

        return $후보->isEmpty() ? null : static::좁히기엄격($후보, $patient);
    }

    /**
     * 서명을 마쳤고, 그 동의에 체크했고, 그림이 남아 있는 줄 — 찾는 자리 모두 이 조건이다.
     *
     * 그림이 없는 줄을 받아 주면 위임장이 빈 서명란으로 공단에 나간다. 옮겨 담은
     * 표에 넣을 때 쓰던 조건 그대로다.
     */
    private static function 서명된줄(string $동의칸): \Illuminate\Database\Eloquent\Builder
    {
        return static::query()
            ->where('status', 'signed')
            ->where($동의칸, true)
            ->where(fn ($q) => $q->where('sign_base64', '!=', '')->whereNotNull('sign_base64')
                                 ->orWhere(fn ($w) => $w->where('sign_path', '!=', '')->whereNotNull('sign_path')));
    }

    /**
     * 이 명단 줄이 가리키는 **거래처의 지금 이름** — 못 고르면 null (2026-10-12 · SR #118).
     *
     * 명단에는 **보낼 때의 이름**이 남는다. 개명하면 옛 이름으로 문자가 나가고,
     * 환자는 「내 이름이 아닌데」로 읽는다 — (E)팜티인 → (E)하나인 이 그랬다.
     *
     * 잣대는 연락처것() 과 같다. 번호로 모으고 **가린 주민번호나 생년월일이 맞아
     * 하나로 좁혀질 때만** 쓴다. 번호만으로는 잇지 않는다 — 집 전화를 식구가 함께
     * 쓰는 건이 있고, 그때 남의 이름으로 문자가 나간다.
     */
    public function 거래처지금이름(): ?string
    {
        $숫자 = preg_replace('/\D/', '', (string) $this->phone);

        if (strlen((string) $숫자) < 9) {
            return null;
        }

        $후보 = Patient::query()
            ->whereRaw("REPLACE(REPLACE(IFNULL(mobile, ''), '-', ''), ' ', '') = ?", [$숫자])
            ->orWhereRaw("REPLACE(REPLACE(IFNULL(phone, ''), '-', ''), ' ', '') = ?", [$숫자])
            ->get(['id', 'name', 'resident_no_masked', 'birth_date']);

        if ($후보->isEmpty()) {
            return null;
        }

        /* 가린 주민번호 → 생년월일 차례로 좁힌다. 하나로 좁혀지지 않으면 쓰지 않는다. */
        foreach ([
            fn ($p) => filled($this->resident_no_masked)
                    && $p->resident_no_masked === $this->resident_no_masked,
            fn ($p) => $this->birth_date && $p->birth_date
                    && $p->birth_date->format('Y-m-d') === $this->birth_date->format('Y-m-d'),
        ] as $좁히기) {
            $좁힘 = $후보->filter($좁히기)->values();

            if ($좁힘->isNotEmpty()) {
                return $좁힘->count() === 1 ? (Patient::실명($좁힘->first()->name) ?: null) : null;
            }
        }

        return null;
    }

    /** 원본에서 찾을 이름 — 맨이름과 「(E)맨이름」 둘. 이름이 없으면 빈 배열 */
    private static function 찾을이름들(Patient $patient): array
    {
        $맨이름 = Patient::bare((string) $patient->name);

        return $맨이름 === '' ? [] : array_values(array_unique([$맨이름, '(E)' . $맨이름]));
    }

    /**
     * 거래처에 적힌 번호 모두 — 숫자만. 너무 짧은 것은 버린다.
     *
     * 거래처에는 번호가 둘이고(휴대전화ㆍ전화) 어느 쪽에 적어 두었는지는 사람마다
     * 다르다 — 한 칸만 보면 보조 칸에 적힌 사람이 그대로 끊긴다.
     *
     * 아홉 자 미만은 버린다. 「0」ㆍ「-」만 적힌 칸이 섞여 있고, 그런 값으로 모으면
     * 남남이 한 줄에 묶인다.
     */
    private static function 찾을번호들(Patient $patient): array
    {
        $번호들 = [];

        foreach ([$patient->mobile, $patient->phone] as $번호) {
            $숫자 = preg_replace('/\D/', '', (string) $번호);

            if (strlen((string) $숫자) >= 9) {
                $번호들[] = $숫자;
            }
        }

        return array_values(array_unique($번호들));
    }

    /**
     * 여러 거래처의 서명을 **한 번에** — `[거래처 id => 서명]` (2026-10-12 · SR #108).
     *
     * 잣대는 거래처것() 과 같다(좁히기 를 함께 쓴다). 목록 화면은 한 번에 500줄을
     * 그리므로 줄마다 묻게 두면 쓸 수 없다 — 한 번 읽어 메모리에서 좁힌다.
     *
     * 이 자리가 없어서 목록(OrderGridExtras)은 옮겨 담은 표만 보고 원본은 보지
     * 않았다. 그래서 이관 뒤에 서명한 사람이 **상세에서는 완료, 목록에서는 빈칸**
     * 으로 갈렸다 — (E)이정현A 님이 그랬다.
     *
     * @param  iterable<Patient>  $patients
     * @return array<int, self>
     */
    public static function 거래처들것($patients, string $동의칸 = 'agree_delegation'): array
    {
        if (! in_array($동의칸, ['agree_delegation', 'agree_privacy'], true)) {
            return [];
        }

        /* 두 걸음(이름ㆍ연락처)이 같은 목록을 거닐어야 하므로 한 번 담아 둔다 —
           부르는 쪽이 제너레이터를 건네면 두 번째 걸음에서 빈 목록이 된다. */
        $사람들 = collect($patients)->filter(fn ($p) => $p?->id)->values();

        if ($사람들->isEmpty()) {
            return [];
        }

        $이름맵 = [];          // 맨이름 => [거래처, …]

        foreach ($사람들 as $p) {
            $맨 = Patient::bare((string) $p->name);

            if ($맨 !== '') {
                $이름맵[$맨][] = $p;
            }
        }

        $답 = [];

        if ($이름맵) {
            $찾을이름 = [];

            foreach (array_keys($이름맵) as $맨) {
                $찾을이름[] = $맨;
                $찾을이름[] = '(E)' . $맨;
            }

            $찾을이름 = array_values(array_unique($찾을이름));

            $후보들 = static::서명된줄($동의칸)
                ->whereIn('customer_name', $찾을이름)
                ->orderByDesc('signed_at')->orderByDesc('id')
                ->get()
                ->groupBy(fn ($d) => Patient::bare((string) $d->customer_name));

            /* ③ 걸음이 쓰는 잣대 — 거래처에 그 이름이 하나뿐인가 */
            $거래처수 = Patient::whereIn('name', $찾을이름)->get(['name'])
                ->groupBy(fn ($p) => Patient::bare((string) $p->name))
                ->map->count();

            foreach ($이름맵 as $맨 => $같은이름들) {
                $후보 = $후보들->get($맨);

                if (! $후보 || $후보->isEmpty()) {
                    continue;
                }

                foreach ($같은이름들 as $p) {
                    if ($찾음 = static::좁히기($후보, $p, (int) ($거래처수[$맨] ?? 0))) {
                        $답[(int) $p->id] = $찾음;
                    }
                }
            }
        }

        /* 이름으로 못 찾은 사람만 연락처로 한 번 더 본다 (2026-10-12 지시).

           거래처것() 과 같은 길을 걸어야 한다 — 상세가 보는 길과 목록이 보는 길이
           갈리면 (E)이정현A 때처럼 「상세는 완료, 목록은 빈칸」이 다시 생긴다. */
        foreach (static::연락처들것($사람들->reject(fn ($p) => isset($답[(int) $p->id])), $동의칸) as $pid => $찾음) {
            $답[$pid] = $찾음;
        }

        return $답;
    }

    /**
     * 여러 거래처의 것을 연락처로 **한 번에** — `[거래처 id => 서명]` (2026-10-12 지시).
     *
     * 잣대는 연락처것() 과 같다(좁히기엄격 을 함께 쓴다). 목록 화면이 줄마다 묻지
     * 않도록 번호를 모아 한 번 읽는다.
     *
     * @param  iterable<Patient>  $patients
     * @return array<int, self>
     */
    private static function 연락처들것($patients, string $동의칸): array
    {
        $번호맵 = [];   // 번호(숫자만) => [거래처, …]

        foreach ($patients as $p) {
            foreach (static::찾을번호들($p) as $번호) {
                $번호맵[$번호][] = $p;
            }
        }

        if (! $번호맵) {
            return [];
        }

        $번호들 = array_keys($번호맵);

        $줄들 = static::서명된줄($동의칸)
            ->where(function ($q) use ($번호들) {
                foreach (['phone', 'sent_to', 'nice_mobile'] as $칸) {
                    $q->orWhereIn(
                        \Illuminate\Support\Facades\DB::raw("REPLACE(REPLACE(IFNULL(`{$칸}`, ''), '-', ''), ' ', '')"),
                        $번호들
                    );
                }
            })
            ->orderByDesc('signed_at')->orderByDesc('id')
            ->get();

        if ($줄들->isEmpty()) {
            return [];
        }

        /* 한 줄이 번호를 셋까지 들고 있어 번호마다 다시 묶는다 — 어느 칸으로 걸렸는지는
           따지지 않는다. 짝이 맞는지는 좁히기엄격 이 주민번호ㆍ생년월일로 다시 본다. */
        $번호별 = [];

        foreach ($줄들 as $줄) {
            foreach (['phone', 'sent_to', 'nice_mobile'] as $칸) {
                $숫자 = preg_replace('/\D/', '', (string) $줄->{$칸});

                if ($숫자 !== '' && isset($번호맵[$숫자])) {
                    $번호별[$숫자][$줄->id] = $줄;
                }
            }
        }

        $답 = [];

        foreach ($번호별 as $번호 => $줄모음) {
            $후보 = collect(array_values($줄모음));

            foreach ($번호맵[$번호] as $p) {
                if (isset($답[(int) $p->id])) {
                    continue;
                }

                if ($찾음 = static::좁히기엄격($후보, $p)) {
                    $답[(int) $p->id] = $찾음;
                }
            }
        }

        return $답;
    }

    /**
     * 주민번호ㆍ생년월일이 **맞아야만** 잇는다 — 이름을 믿을 수 없을 때 쓴다.
     *
     * 좁히기() 의 ①ㆍ② 걸음만 쓰고 ③(이름이 하나뿐)은 쓰지 않는다. ③ 은 이름이
     * 같다는 데서 근거를 얻는 걸음인데, 연락처로 모은 줄은 이름이 애초에 다르다.
     * 둘을 맞출 길이 하나도 없으면 잇지 않는다 — 모르면 잇지 않는 쪽이 맞다.
     */
    private static function 좁히기엄격($후보, Patient $patient): ?self
    {
        if (filled($patient->resident_no_masked)) {
            $좁힘 = $후보->where('resident_no_masked', $patient->resident_no_masked)->values();

            if ($좁힘->isNotEmpty()) {
                return $좁힘->count() === 1 ? $좁힘->first() : null;
            }
        }

        if ($patient->birth_date) {
            $생년 = $patient->birth_date instanceof \DateTimeInterface
                ? $patient->birth_date->format('Y-m-d')
                : substr((string) $patient->birth_date, 0, 10);

            $좁힘 = $후보->filter(fn ($d) => $d->birth_date
                && $d->birth_date->format('Y-m-d') === $생년)->values();

            if ($좁힘->isNotEmpty()) {
                return $좁힘->count() === 1 ? $좁힘->first() : null;
            }
        }

        return null;
    }

    /**
     * 후보 가운데 이 거래처의 것을 가린다 — 거래처것()ㆍ거래처들것() 이 함께 쓴다.
     *
     * 좁히는 걸음이 두 벌이 되면 목록과 상세가 또 갈린다. 한 곳에 둔다.
     */
    private static function 좁히기($후보, Patient $patient, int $같은이름거래처수): ?self
    {
        // ① 가린 주민번호로 좁힌다 — 이관 명령의 name_birth 와 같은 자리다
        if (filled($patient->resident_no_masked)) {
            $좁힘 = $후보->where('resident_no_masked', $patient->resident_no_masked)->values();

            if ($좁힘->isNotEmpty()) {
                return $좁힘->count() === 1 ? $좁힘->first() : null;
            }
        }

        // ② 생년월일로 좁힌다 — 원본에 가린 주민번호가 없는 줄이 있다
        if ($patient->birth_date) {
            $생년 = $patient->birth_date instanceof \DateTimeInterface
                ? $patient->birth_date->format('Y-m-d')
                : substr((string) $patient->birth_date, 0, 10);

            $좁힘 = $후보->filter(fn ($d) => $d->birth_date
                && $d->birth_date->format('Y-m-d') === $생년)->values();

            if ($좁힘->isNotEmpty()) {
                return $좁힘->count() === 1 ? $좁힘->first() : null;
            }
        }

        /* ③ 이름만 — 원본에도 거래처에도 그 이름이 하나뿐일 때만 잇는다.
              동명이인이 하나라도 있으면 쓰지 않는다. */
        if ($후보->count() !== 1) {
            return null;
        }

        return $같은이름거래처수 === 1 ? $후보->first() : null;
    }
}
