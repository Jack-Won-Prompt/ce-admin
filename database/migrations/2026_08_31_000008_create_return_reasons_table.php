<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 되돌리는 사유 — 코드 표 (요청서 6쪽, 2026-08-31).
 *
 * 지금까지 사유는 코드 안의 배열이었다(OrderReturn::REASONS). 거기에는 배송비를 누가
 * 무는지만 적혀 있었는데, 요청서 6쪽이 사유마다 두 가지를 더 정해 달라 한다 —
 * 금액을 조정하는가, 발행 내역에 넣는가.
 *
 * 「단순변심 아닌 것은 조정도 발행도 없다」를 글자 그대로 옮기면 자격 변경까지 걸린다.
 * 그것은 이미 받은 돈을 돌려주는 건이라 조정이 반드시 있어야 한다. 그래서 한 줄로
 * 가르지 않고 사유마다 하나씩 정하도록 표로 옮긴다(2026-08-31 회신).
 *
 * 아래 값은 **처음 값**이다. 화면(마스터 관리 › 반품 사유)에서 사유마다 고칠 수 있다.
 */
return new class extends Migration
{
    /**
     * 처음 값 — 코드ㆍ이름ㆍ배송비 부담ㆍ금액조정ㆍ발행포함ㆍ차례.
     *
     * 가른 잣대는 「돈이 오갔는가」다. 물건만 바꿔 주는 교환은 돈이 그대로라 조정도
     * 발행도 없다. 돈이 돌아가는 건(반품ㆍ환불ㆍ자격 변경)은 조정도 발행도 있다.
     */
    private const SEED = [
        // code            label          burden      adjust include sort
        ['change_mind',   '단순 변심',   'customer',  true,  true,  10],
        ['size_exchange', '사이즈 교환', 'customer',  false, false, 20],
        ['defect',        '상품 불량',   'company',   false, false, 30],
        ['wrong_item',    '오배송',      'company',   false, false, 40],
        ['delay',         '배송 지연',   'company',   false, false, 50],
        ['eligibility',   '자격 변경',   null,        true,  true,  60],
        ['other',         '기타',        null,        true,  true,  70],
    ];

    public function up(): void
    {
        Schema::create('return_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('label', 60);
            /* 누가 배송비를 무는가. 비워 두면 사유만으로 정해지지 않아 접수하는 사람이
               고른다 — 자격 변경이 그렇다. */
            $table->string('burden', 20)->nullable();
            /* 금액을 조정하는가 (요청서 6쪽). 물건만 바꿔 주는 교환은 돈이 그대로라
               조정할 것이 없다. */
            $table->boolean('adjusts_amount')->default(true);
            /* 결제ㆍ현금영수증ㆍ세금계산서ㆍ카드 발행 내역에 넣는가 (요청서 6쪽).
               조정이 없으면 발행할 것도 없다 — 다만 둘을 한 칸으로 묶지 않는다.
               조정은 있는데 발행은 미루는 건이 있을 수 있다. */
            $table->boolean('includes_issue')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        foreach (self::SEED as [$code, $label, $burden, $adjust, $include, $sort]) {
            DB::table('return_reasons')->insert([
                'code' => $code, 'label' => $label, 'burden' => $burden,
                'adjusts_amount' => $adjust, 'includes_issue' => $include,
                'sort_order' => $sort, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('return_reasons');
    }
};
