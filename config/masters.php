<?php

/**
 * 마스터 관리 — 카테고리 정의.
 *
 * 병원과 대리점은 담는 항목이 겹친다(이름·대표자·연락처·주소). 표를 따로 두면 화면도
 * 컨트롤러도 두 벌이 되므로 한 표에 category 로 나눠 담고, 카테고리마다 '어떤 칸을
 * 무슨 이름으로 보여줄지' 만 여기서 정한다.
 *
 * 카테고리를 늘릴 때 코드를 고칠 일이 없다 — 여기 한 항목만 더하면 탭이 생긴다.
 */
return [

    'categories' => [

        'hospital' => [
            'label'  => '병원',
            // 화면에 보일 칸과 그 이름. 여기 없는 칸은 그 카테고리에서 아예 다루지 않는다.
            'fields' => [
                'code'    => ['label' => '요양기관번호', 'width' => 130],
                'name'    => ['label' => '병원명',       'width' => 180, 'required' => true],
                'ceo'     => ['label' => '대표자(원장)', 'width' => 110],
                'phone'   => ['label' => '전화번호',     'width' => 130],
                'fax'     => ['label' => '팩스',         'width' => 130],
                'address' => ['label' => '주소',         'width' => 260],
                'note'    => ['label' => '비고',         'width' => 200],
            ],
        ],

        'dealer' => [
            'label'  => '대리점',
            'fields' => [
                'code'    => ['label' => '거래처코드',   'width' => 120],
                'name'    => ['label' => '상호',         'width' => 180, 'required' => true],
                'biz_no'  => ['label' => '사업자번호',   'width' => 130],
                'ceo'     => ['label' => '대표자',       'width' => 100],
                'manager' => ['label' => '담당자',       'width' => 100],
                'phone'   => ['label' => '연락처',       'width' => 130],
                'email'   => ['label' => '이메일',       'width' => 180],
                'address' => ['label' => '주소',         'width' => 240],
                'note'    => ['label' => '비고',         'width' => 180],
            ],
        ],

    ],

];
