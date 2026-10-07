<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 거래처에 붙는 서류함 (2026-10-07 지시 · SR #115ㆍ#123).
 *
 * 여태 서류는 **처방전에만** 붙었다(`prescription_attachments`). 그래서 종이로 받아 둔
 * 위임장을 올릴 자리가 없었다 —
 *
 *   「위임장 링크로 보냈으나 처리할 수가 없어서 문서로 받았으나 아직 처방전이 없는 경우
 *     해당 서류를 업로딩할 수 없습니다」 (SR #115)
 *   「서명동의 문서로 받을 경우, 거래처 관리에 업로드 및 주문등록에서 서명동의 완료로
 *     표시 필요」 (SR #123)
 *
 * 처방전이 서기 전에 받은 서류를 담을 그릇이 없으니, 담당자는 처방전이 생길 때까지
 * 종이를 들고 기다리거나 엉뚱한 처방전에 붙였다.
 *
 * ## 처방전 첨부와 따로 둔다
 *
 * `prescription_attachments` 의 `prescription_id` 를 비울 수 있게 하는 길도 있었지만,
 * 그 표를 읽는 자리가 모두 「처방전 한 건의 첨부」를 전제로 센다(목록의 첨부 칸ㆍ팩스에
 * 실을 서류ㆍOCR). 비울 수 있게 하면 그 자리들이 조용히 어긋난다.
 *
 * ## 서명일을 따로 받는다
 *
 * 위임 유효기간은 **서명한 날**에서 센다(DelegationGate::서명유효기간). 종이로 받은 것은
 * 올린 날과 서명한 날이 다르므로, 올릴 때 서명일을 함께 받는다. 그것이 없으면 기간을
 * 잴 수 없어 문이 열리지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('patient_documents')) {
            return;
        }

        Schema::create('patient_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');

            /* 갈래 — 위임장(delegation)ㆍ서명동의(consent)ㆍ신분증(id_card)ㆍ기타(etc).
               처방전 첨부의 `doc_type` 과 같은 말을 쓴다. 두 곳에서 이름이 다르면
               팩스에 실을 때 갈래로 가리는 자리가 어긋난다. */
            $table->string('doc_type', 40);
            $table->string('doc_label', 60)->nullable();

            $table->string('file_path', 500);
            $table->string('file_original_name', 255)->nullable();
            $table->string('file_mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            /* 종이에 서명한 날 — 위임 유효기간을 이 날에서 센다 */
            $table->date('signed_at')->nullable();

            /* 서명 그림 — 있으면 요양비 지급청구서의 서명란에 얹는다.
               (가) 길에서는 위임장을 우리가 다시 그리지 않으므로 없어도 된다. 다만
               기초ㆍ차상위 건의 지급청구서는 서명란이 비게 되므로 화면이 그것을 알린다. */
            $table->longText('signature_data')->nullable();

            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('patient_id');
            $table->index(['patient_id', 'doc_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_documents');
    }
};
