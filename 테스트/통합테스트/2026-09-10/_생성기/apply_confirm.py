# -*- coding: utf-8 -*-
"""확정까지 밟은 결과를 CASE 폴더의 기록.md 에 이어 붙인다.

기록.md 의 「## 겪은 것」 앞에 「8 · 위드웍스 판매주문 확정」 마디를 끼운다.
이미 끼워 넣은 자리가 있으면 갈아 끼운다 — 두 번 붙지 않게.
"""
import io, json, os, re

뿌리 = 'E:/xampp/htdocs/ce-admin/테스트/통합테스트/2026-09-10/2026-09-10'
확정 = json.load(io.open('E:/tmp/gen/confirm.json', encoding='utf-8'))
모두 = {x['no']: x for x in json.load(io.open('E:/tmp/all.json', encoding='utf-8'))}

머리 = '### 8 · 위드웍스 판매주문 확정 (2026-09-11 이어서 밟음)'


def 토막(r, a):
    청 = '지자체' if a['청구처'] == 'local' else '공단'
    본인 = a['본인']
    ㄱ = []
    ㄱ.append(머리 + '\n')
    ㄱ.append('정산/회계 화면에서 ［입금 확인］을 눌렀다. 한 번 누르는 것으로 증빙 발행과')
    ㄱ.append('창고 확정까지 한 줄로 이어진다.\n')
    ㄱ.append('| | |')
    ㄱ.append('|---|---|')
    ㄱ.append('| 입금 확인 | %s · **%s원** |' % (r['입금확인'], '{:,}'.format(r['입금액'])))
    ㄱ.append('| 주문 상태 | `pending` → **`%s`** |' % r['주문상태'])
    ㄱ.append('| 위드웍스 판매주문 | **%s** · %s |' % (r['판매번호'], r['창고상태']))
    ㄱ.append('| 출고 건 | **%s** · %s · 확정수량 %s |'
              % (r['출고번호'], r['출고창고'], r['확정수량']))
    ㄱ.append('| 세금계산서 | **%s** — %s 청구분 %s원 (시뮬레이션) |'
              % (r['세금계산서'], 청, '{:,}'.format(a['공단'])))
    ㄱ.append('| 거래명세서 | %s |' % r['명세서일'])
    ㄱ.append('| 현금영수증 | %s — 처방전 건은 청구전략에 없다 |'
              % ('미발행' if r['현금영수증'] == 'not_issued' else r['현금영수증']))
    ㄱ.append('')
    if 본인 == 0:
        ㄱ.append('- [x] **본인부담이 0 원이라 받을 것이 없다** — 그래도 ［입금 확인］으로 확정한다')
        ㄱ.append('      (목록에 「받을 금액 %s / 본인 부담금 0」으로 서 있었다)'
                  % '{:,}'.format(a['공단']))
    else:
        ㄱ.append('- [x] 본인부담 **%s원**을 담당자가 손으로 확인했다 (7.4)'
                  % '{:,}'.format(본인))
    ㄱ.append('- [x] 웹훅 `so.confirmed` 가 닿아 창고 상태가 **95 확정**으로 섰다')
    ㄱ.append('- [x] 활동 기록에 **「위드웍스 판매주문 확정 %s」** — 우리말 표기가 제대로 찍힌다'
              % r['판매번호'])
    ㄱ.append('- [x] 증빙은 **시뮬레이션**으로 돌았다 (`POPBILL_ISSUE_SIMULATE=true`)')
    ㄱ.append('')
    ㄱ.append('### 9 · 위드웍스 할당부터 — 아직 밟지 못함\n')
    ㄱ.append('출고 건 **%s** 을 열어 ［미할당］ 탭에서 ［부분 할당］까지 눌렀으나' % r['출고번호'])
    ㄱ.append('상태가 「신규」 그대로다. **데모 창고에 이 제품 재고가 없다.**\n')
    ㄱ.append('| 제품 | 재고 상세 | 재고 조정 |')
    ㄱ.append('|---|---|---|')
    for 코, 이 in [('5008', 'EasiCath CH8 paediatric'),
                  ('5356', 'EasiCath CH16 male'),
                  ('5376', 'EasiCath CH16 female')]:
        ㄱ.append('| %s %s | `No data` | 조정할 줄 자체가 없다 |' % (코, 이))
    ㄱ.append('')
    ㄱ.append('열일곱 건이 쓰는 양은 **9,180개**(5008 3,780 · 5356 2,700 · 5376 2,700)다.')
    ㄱ.append('재고 조정으로는 넣을 수 없고 **입고를 새로 잡아야** 한다 — 남의 시스템에')
    ㄱ.append('없던 재고를 만드는 일이라 여쭙고 멈췄다.')
    ㄱ.append('')
    ㄱ.append('할당ㆍ피킹ㆍ송장ㆍ출고 네 단계는 재고가 서면 이어서 밟는다.')
    ㄱ.append('밟는 차례는 같은 폴더의 「시나리오 실행 순서 및 세부 내용.txt」 [14]~[17] 에 있다.')
    ㄱ.append('')
    return '\n'.join(ㄱ)


고친것 = 0
for no, r in sorted(확정.items()):
    a = 모두[no]
    길 = os.path.join(뿌리, r['이름'], '기록.md')
    s = io.open(길, encoding='utf-8').read()

    # 이미 붙어 있으면 그 자리부터 「## 겪은 것」 앞까지를 갈아 끼운다
    if 머리 in s:
        s = re.sub(re.escape(머리) + r'.*?(?=## 겪은 것)', '', s, flags=re.S)

    표 = '## 겪은 것'
    assert 표 in s, '「겪은 것」을 못 찾음: ' + r['이름']
    s = s.replace(표, 토막(r, a) + 표, 1)
    io.open(길, 'w', encoding='utf-8', newline='').write(s)
    고친것 += 1
    print('CASE-%s %-4s → 기록.md  (%s · %s)' % (no, r['이름'], r['출고번호'], r['세금계산서']))

print('고친 기록 %d개' % 고친것)
