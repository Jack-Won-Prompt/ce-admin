# -*- coding: utf-8 -*-
"""갈래별 txt 여덟 개에 「밟은 자취」 토막만 끼워 넣는다.

이 파일들은 손으로 손본 것이라 통째로 덮지 않는다. 마디 [12]~[17] 안에서
「원문 확인 항목」 앞자리에만 자취를 끼우고, 나머지는 건드리지 않는다.
이미 끼워져 있으면 갈아 끼운다 — 두 번 붙지 않게.
"""
import io, json, os, re, sys

폴더 = sys.argv[1] if len(sys.argv) > 1 else (
    'C:/Users/82103/Desktop/unicorn/03.테스트 시나리오/'
    '테스트 시나리오 엑셀/시나리오 실행 순서')
밟음 = json.load(io.open('E:/tmp/gen/confirm.json', encoding='utf-8'))

카 = 78


def 폭(글):
    import unicodedata
    return sum(2 if unicodedata.east_asian_width(ch) in 'WF' else 1 for ch in 글)


def 선(제목):
    머 = '  ─ %s ' % 제목
    return 머 + '─' * max(3, 카 - 폭(머))


def 자취(번, r):
    if 번 == '12':
        줄 = ['%s 에 ［입금 확인］ — %s원' % (r['입금확인'], '{:,}'.format(r['입금액'])),
              '주문 상태 %s · 세금계산서 %s (시뮬레이션) · 거래명세서 %s'
              % (r['주문상태'], r['세금계산서'], r['명세서일']),
              '현금영수증 미발행 — 처방전 건은 청구전략에 없다']
    elif 번 == '13':
        줄 = ['웹훅 so.confirmed 닿음 — 창고 상태 %s' % r['창고상태'],
              '출고 건 %s 가 섰다 (%s · 확정수량 %s)'
              % (r['출고번호'], r['출고창고'], r['확정수량']),
              '활동 기록 「위드웍스 판매주문 확정 %s」' % r['판매번호']]
    elif 번 in ('14', '15', '16', '17'):
        줄 = ['아직 밟지 못했다 — 데모 창고에 이 제품 재고가 없다',
              '재고 상세에서 5008ㆍ5356ㆍ5376 모두 No data · 재고 조정에도 줄이 없다',
              '열일곱 건이 쓰는 양은 9,180개 — 입고를 새로 잡아야 한다']
        if 번 == '14':
            줄.insert(1, '출고 건 %s 를 열어 ［미할당］ → ［부분 할당］ → Yes 까지 눌렀으나 '
                         '상태가 「신규」 그대로다' % r['출고번호'])
    else:
        return []
    ㄱ = ['', 선('밟은 자취 (2026-09-11)')]
    for x in 줄:
        조각 = [x[i:i + 68] for i in range(0, len(x), 68)] or ['']
        ㄱ.append('    ✔ %s' % 조각[0])
        for 뒤 in 조각[1:]:
            ㄱ.append('      %s' % 뒤)
    return ㄱ


고친것 = 0
for 이름 in sorted(os.listdir(폴더)):
    if not 이름.endswith('.txt'):
        continue
    if len(sys.argv) > 2 and sys.argv[2] not in 이름:
        continue
    길 = os.path.join(폴더, 이름)
    원 = io.open(길, encoding='utf-8', newline='').read()
    끝 = '\r\n' if '\r\n' in 원 else '\n'
    줄 = 원.replace('\r\n', '\n').split('\n')

    m = re.search(r'CASE-(\d+)', 줄[2] if len(줄) > 2 else '')
    if not m:
        print('  CASE 를 못 읽음: %s' % 이름)
        continue
    no = m.group(1)
    r = 밟음.get(no)
    if not r:
        print('  밟은 자취가 없음: CASE-%s' % no)
        continue

    # 마디 경계 — 「[NN] 」로 시작하는 줄
    자리 = [i for i, l in enumerate(줄) if re.match(r'^\[\d\d\] ', l)]
    자리.append(len(줄))

    새 = list(줄)
    끼움 = 0
    for k in range(len(자리) - 1, 0, -1):     # 뒤에서부터 — 앞 자리가 밀리지 않게
        시 = 자리[k - 1]
        끄 = 자리[k]
        번 = 새[시][1:3]
        토막 = 자취(번, r)
        if not 토막:
            continue
        덩 = 새[시:끄]

        # 이미 끼워져 있으면 걷어낸다
        기존 = [i for i, l in enumerate(덩) if '─ 밟은 자취' in l]
        if 기존:
            a = 기존[0] - 1 if 기존[0] > 0 and 덩[기존[0] - 1].strip() == '' else 기존[0]
            b = a + 1
            while b < len(덩) and not (덩[b].startswith('  ─ ') or 덩[b].startswith('[')):
                b += 1
            while b > a and 덩[b - 1].strip() == '':
                b -= 1
            덩 = 덩[:a] + 덩[b:]

        꽂 = next((i for i, l in enumerate(덩) if l.startswith('  ─ 원문 확인 항목')), None)
        if 꽂 is None:
            꽂 = len(덩)
            while 꽂 > 0 and 덩[꽂 - 1].strip() == '':
                꽂 -= 1
        else:
            while 꽂 > 0 and 덩[꽂 - 1].strip() == '':
                꽂 -= 1
        덩 = 덩[:꽂] + 토막 + 덩[꽂:]
        새 = 새[:시] + 덩 + 새[끄:]
        끼움 += 1

    io.open(길, 'w', encoding='utf-8', newline='').write(끝.join(새))
    고친것 += 1
    print('CASE-%s %-5s %-44s 마디 %d곳 · %d줄 → %d줄'
          % (no, r['이름'], 이름[:44], 끼움, len(줄), len(새)))

print('고친 파일 %d개' % 고친것)
