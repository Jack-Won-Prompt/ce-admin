<?php

/*
 * 제품관리번호(장비코드) — 품번 → 공단 등록 장비코드.
 *
 * 공단 요양기관정보마당에 청구할 때는 우리 품번이 아니라 이 번호로 조회한다.
 * 담당자가 매번 다른 표를 열어 찾고 있어, 제품이 서는 자리마다 함께 세운다.
 *
 * 출처: 「제품관리번호(장비코드)-20 May 2026」(유니콘 제공 탭).
 *       공단 등록 목록 https://www.nhis.or.kr/nhis/policy/retrieveJagadonyoProductList.do
 *       사용하지 않는(N) 줄과 품번이 없는 묶음 줄은 뺐다 — 40줄.
 *
 * 정본은 위드웍스다. 그쪽 품목에 이 값을 넣으면 items API 가 함께 주고, 그때부터
 * 이 표는 쓰이지 않는다(App\Support\DeviceCode 가 API 값을 먼저 본다). 그때까지
 * 화면이 비지 않도록 여기 둔다.
 */

return [
    '28410' => 'NBC0121000005',  // SpeediCath(Male,Female,Pediatric,Boy)
    '28412' => 'NBC0121000005',  // SpeediCath(Male,Female,Pediatric,Boy)
    '28610' => 'NBC0121000005',  // SpeediCath(Male,Female,Pediatric,Boy)
    '28612' => 'NBC0121000005',  // SpeediCath(Male,Female,Pediatric,Boy)
    '28510' => 'NBC0121000005',  // SpeediCath(Male,Female,Pediatric,Boy)
    '28512' => 'NBC0121000005',  // SpeediCath(Male,Female,Pediatric,Boy)
    '28514' => 'NBC0121000005',  // SpeediCath(Male,Female,Pediatric,Boy)
    '28414' => 'NBC0121000005',  // SpeediCath(Male,Female,Pediatric,Boy)
    '28706' => 'NBC0121000005',  // SpeediCath(Male,Female,Pediatric,Boy)
    '28508' => 'NBC0121000005',  // SpeediCath(Male,Female,Pediatric,Boy)
    '5088' => 'NBC0121000006',   // EasiCath Nalaton(male,female,Tiemann,pediatric)
    '5008' => 'NBC0121000006',   // EasiCath Nalaton(male,female,Tiemann,pediatric)
    '5006' => 'NBC0121000006',   // EasiCath Nalaton(male,female,Tiemann,pediatric)
    '5382' => 'NBC0121000006',   // EasiCath Nalaton(male,female,Tiemann,pediatric)
    '5376' => 'NBC0121000006',   // EasiCath Nalaton(male,female,Tiemann,pediatric)
    '5374' => 'NBC0121000006',   // EasiCath Nalaton(male,female,Tiemann,pediatric)
    '5372' => 'NBC0121000006',   // EasiCath Nalaton(male,female,Tiemann,pediatric)
    '5370' => 'NBC0121000006',   // EasiCath Nalaton(male,female,Tiemann,pediatric)
    '5368' => 'NBC0121000006',   // EasiCath Nalaton(male,female,Tiemann,pediatric)
    '5356' => 'NBC0121000006',   // EasiCath Nalaton(male,female,Tiemann,pediatric)
    '5354' => 'NBC0121000006',   // EasiCath Nalaton(male,female,Tiemann,pediatric)
    '5352' => 'NBC0121000006',   // EasiCath Nalaton(male,female,Tiemann,pediatric)
    '5350' => 'NBC0121000006',   // EasiCath Nalaton(male,female,Tiemann,pediatric)
    '28578' => 'NBC0112000001',  // 스피디캐스 컴팩 여성용 8FR
    '28580' => 'NBC0122000001',  // 스피디캐스 컴팩 여성용 10FR
    '28582' => 'NBC0122000002',  // 스피디캐스 컴팩 여성용 12FR
    '28584' => 'NBC0122000003',  // 스피디캐스 컴팩 여성용 14FR
    '28692' => 'NBC0112000002',  // 스피디캐스 컴팩 남성용 12FR
    '28520' => 'NBC0122000005',  // 스피디캐스 컴팩트 여성용세트
    '28522' => 'NBC0122000006',  // 스피디캐스 컴팩트 여성용세트
    '28524' => 'NBC0122000007',  // 스피디캐스 컴팩트 여성용세트
    '28422' => 'NBC0122000004',  // 스피디캐스 컴팩트 남성용세트
    '28810' => 'NBC0122000373',  // 스피디캐스 컴팩트 플러스 10FR
    '28812' => 'NBC0122000374',  // 스피디캐스 컴팩트 플러스 12FR
    '28814' => 'NBC0122000375',  // 스피디캐스 컴팩트 플러스 14FR
    '29010' => 'NBC0112000010',  // 스피디캐스 네비 29010
    '29012' => 'NBC0112000011',  // 스피디캐스 네비 29012
    '29014' => 'NBC0112000012',  // 스피디캐스 네비 29014
    '29021' => 'NBC0122000164',  // 스피디캐스 네비 29021
    '29022' => 'NBC0122000165',  // 스피디캐스 네비 29022
];
