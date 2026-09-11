<?php
$root="E:/xampp/htdocs/ce-admin"; require $root."/vendor/autoload.php"; $app=require $root."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$나옴=[];
foreach(json_decode(file_get_contents('E:/tmp/cases.json'),true) as $c){
  $p=\App\Models\Patient::where("name","like","%".$c['이름']."%")->latest("id")->first();
  $rx=\App\Models\Prescription::where("patient_id",$p->id)->latest("id")->first();
  $o=\App\Models\Order::where("prescription_id",$rx->id)->latest("id")->first();
  $cs=\App\Models\PrescriptionConsent::where("prescription_id",$rx->id)->latest("id")->first();
  $첨부=\App\Models\PrescriptionAttachment::where("prescription_id",$rx->id)
        ->selectRaw("doc_type, count(*) n")->groupBy("doc_type")->pluck("n","doc_type")->all();
  $d=fn($v)=>$v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v;
  $나옴[$c['no']]=[
    'no'=>$c['no'],'이름'=>$c['이름'],'자격'=>$c['자격'],'결제'=>$c['결제'],'미성년'=>$c['미성년'],
    '환자'=>array_intersect_key($p->getAttributes(), array_flip([
      'id','name','care_type','sb_sci','gender','birth_date','resident_no_masked','mobile','phone',
      'main_contact','email','fax','postcode','address','address_detail','remitter_name',
      'cash_receipt_type','cash_receipt_no','marketing_consent','contact_channel','contact_status',
      'nhis_reg_status','nhis_reg_date','nhis_renew','nhis_renew_due','basic_reeval','basic_reeval_due',
      'agree_start','agree_end','guardian_relation','guardian_name','guardian_birth','guardian_phone',
      'pay_method','note'])),
    '처방'=>array_intersect_key($rx->getAttributes(), array_flip([
      'rx_number','status','review_status','reviewed_by','reviewed_at','hospital_name','hospital_code',
      'doctor_name','license_no','specialty','disease_code','disease_name','disease_grade','diagnosis_date',
      'uro_date','purchase_type','daily_count','rx_days','rx_total','issue_date','rx_end_date',
      'benefit_start_date','benefit_end_date','repurchase_date','benefit_class','claim_agency',
      'billing_office_id','billing_office_name','pay_date','buy_date','use_start_date','last_qty',
      'inmarket_due','order_manager','reference_note','acc_add_type','five_program','reason'])),
    '주문'=>$o? array_intersect_key($o->getAttributes(), array_flip([
      'order_number','product_name','product_code','device_code','quantity','unit_price','nhis_amount',
      'patient_copay','total_amount','shipping_fee','pay_method','so_type','withworks_so_no',
      'withworks_so_id','withworks_status','withworks_status_label','withworks_warehouse',
      'shipping_recipient','shipping_postcode','shipping_address','shipping_address_detail',
      'estimated_delivery','status','settle_status','nhis_claim_status','claim_missing',
      'tax_invoice_status','tax_invoice_type','cash_receipt_status','cash_receipt_type'])):null,
    '동의'=>$cs? array_intersect_key($cs->getAttributes(), array_flip([
      'token','status','patient_name','guardian_name','guardian_relation','signed_at','agreed_at',
      'verify_method','id_card_path'])):null,
    '첨부'=>$첨부,
  ];
}
file_put_contents('E:/tmp/gen/dump.json', json_encode($나옴, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
echo "받음 ".count($나옴)."건\n";
