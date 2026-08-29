<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 「Unicorn 교환·반품 절차」에 맞춰 되돌리는 건의 표를 넓힌다.
 *
 * 지금까지는 접수 → 수거 → 검수 → 환불 정도만 담았다. 절차서는 그 사이에 네 가지를
 * 더 요구한다 — Unicorn 검수 확정, 전자 승인, 입금 완료 확인, 오더 확정. 누가 언제
 * 눌렀는지가 남지 않으면 「승인했다」는 말만 남고 근거가 없다.
 *
 * 부분 취소를 하려면 무엇을 얼마나 되돌리는지가 줄 단위로 있어야 한다. 지금은 제품명
 * 한 칸과 수량 한 칸뿐이라 두 품목 중 하나만 되돌릴 수가 없다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            /* 취소의 하위 갈래. 출고 전 취소와 일반 환불(자격 변경 등)은 둘 다 물건이
               움직이지 않지만, 일반 환불은 위드웍스에 금액조정 주문을 하나 세운다. */
            $table->string('subtype', 20)->nullable()->after('type');

            /* 기한의 출발점. 3PL 창고에 물건이 들어온 날이며, 여기서부터 검수 2영업일 ·
               출고 3영업일을 센다. 접수일이 아니다 — 고객이 늦게 보내면 창고 잘못이 된다. */
            $table->timestamp('arrived_at')->nullable()->after('collect_tracking_no');

            // Unicorn 검수 확정 — 3PL 검수 결과를 받아 Care team manager 가 확정한다
            $table->foreignId('inspect_confirmed_by')->nullable()->after('arrived_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('inspect_confirmed_at')->nullable()->after('inspect_confirmed_by');

            // 전자 승인 — 변심·반품·일반환불은 Consumer Care manager, 불량은 Consumer Operation manager
            $table->foreignId('approved_by')->nullable()->after('inspect_confirmed_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');

            // 입금 완료 확인 (변심 교환만) · 오더 확정
            $table->timestamp('payment_checked_at')->nullable()->after('approved_at');
            $table->timestamp('order_confirmed_at')->nullable()->after('payment_checked_at');

            /* 마이너스-환불 · 현금영수증 · 세금계산서 발행. 무엇이 되고 무엇이 안 됐는지를
               한 칸에 적어 둔다 — 실패한 것을 사람이 손으로 마무리해야 한다. */
            $table->timestamp('credit_issued_at')->nullable()->after('refunded_at');
            $table->string('credit_note', 500)->nullable()->after('credit_issued_at');

            // 부분 취소인가 — 줄마다 수량이 원 주문보다 적으면 참이다
            $table->boolean('is_partial')->default(false)->after('credit_note');

            // 일반 환불의 금액조정 판매주문 (전산판매 1092)
            $table->string('adjust_so_no', 50)->nullable()->after('withworks_error');
            $table->timestamp('adjusted_at')->nullable()->after('adjust_so_no');
        });

        /**
         * 되돌리는 품목 — 부분 취소를 하려면 줄이 있어야 한다.
         *
         * 원 주문의 품목을 그대로 베껴 오되 수량만 줄여 담는다. 원 주문 줄을 가리키는
         * 값을 함께 두어, 같은 제품이 두 줄로 들어간 주문에서도 어느 줄인지 가린다.
         */
        Schema::create('order_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_return_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->string('product_code', 50)->nullable();
            $table->string('product_name', 200)->nullable();
            $table->integer('ordered_quantity')->default(0);   // 원 주문 수량 — 부분인지 가리는 잣대
            $table->integer('quantity')->default(0);           // 되돌리는 수량
            $table->integer('unit_price')->default(0);
            $table->integer('copay')->default(0);              // 환자부담 — 마이너스 발행 금액이 여기서 나온다
            $table->timestamps();

            $table->index('order_return_id');
        });

        // 전자 승인을 권한으로 가른다 — 지금까지 approve 는 config 에만 있고 칸이 없었다
        if (!Schema::hasColumn('permission_group_pages', 'can_approve')) {
            Schema::table('permission_group_pages', function (Blueprint $table) {
                $table->boolean('can_approve')->default(false)->after('can_send');
            });
        }

        // 일반 환불이 세우는 금액조정 주문의 판매유형 — 코드는 위드웍스 code_list 가 정한다
        if (!Schema::hasColumn('withworks_settings', 'adjust_so_type')) {
            Schema::table('withworks_settings', function (Blueprint $table) {
                $table->string('adjust_so_type', 10)->nullable()->after('exchange_so_type');
            });
            DB::table('withworks_settings')->update(['adjust_so_type' => '1092']);
        }

        /* 바로 나눠 줄 수 있게 승인 그룹을 하나 만들어 둔다. 화면에서 새로 만들 수도
           있지만, 승인은 이 기능의 핵심이라 빈 채로 두면 아무도 누를 수 없다. */
        $this->seedApproverGroup();
    }

    public function down(): void
    {
        Schema::dropIfExists('order_return_items');

        Schema::table('order_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inspect_confirmed_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'subtype', 'arrived_at', 'inspect_confirmed_at', 'approved_at',
                'payment_checked_at', 'order_confirmed_at',
                'credit_issued_at', 'credit_note', 'is_partial',
                'adjust_so_no', 'adjusted_at',
            ]);
        });

        if (Schema::hasColumn('permission_group_pages', 'can_approve')) {
            Schema::table('permission_group_pages', fn (Blueprint $t) => $t->dropColumn('can_approve'));
        }

        if (Schema::hasColumn('withworks_settings', 'adjust_so_type')) {
            Schema::table('withworks_settings', fn (Blueprint $t) => $t->dropColumn('adjust_so_type'));
        }
    }

    /** 「교환·반품 전자 승인」 그룹 — 이미 있으면 손대지 않는다 */
    private function seedApproverGroup(): void
    {
        $name = '교환·반품 전자 승인';

        if (DB::table('permission_groups')->where('name', $name)->exists()) {
            return;
        }

        $id = DB::table('permission_groups')->insertGetId([
            'name'           => $name,
            'description'    => '교환·반품·취소의 검수 확정과 전자 승인을 누를 수 있는 역할 '
                              . '(Consumer Care manager · Consumer Operation manager)',
            'is_full_access' => false,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        DB::table('permission_group_pages')->insert([
            [
                'permission_group_id' => $id,
                'page_key'   => 'order-returns',
                'can_view'   => true,
                'can_create' => false,
                'can_update' => true,
                'can_delete' => false,
                'can_send'   => false,
                'can_approve' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'permission_group_id' => $id,
                'page_key'   => 'orders',
                'can_view'   => true,
                'can_create' => false,
                'can_update' => false,
                'can_delete' => false,
                'can_send'   => false,
                'can_approve' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
};
