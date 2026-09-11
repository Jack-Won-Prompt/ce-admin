# -*- coding: utf-8 -*-
"""CASE 마다 「시나리오 실행 순서 및 세부 내용.txt」를 쓴다.

    python txt_make.py         # 열일곱 건 모두
    python txt_make.py 09      # 한 건만

상세.md 의 본문 절을 하나도 빠뜨리지 않는다 — 마디에 배정하고, 밟지 않는 것은
까닭을 적어 남긴다. 마지막에 원문 대조표로 세어 보고, 수가 맞지 않으면 멈춘다.
"""
import io, json, os, re, sys

sys.path.insert(0, 'E:/tmp/gen')
from plan import 마디, 어느마디, 없음표          # noqa: E402
from steps import 만들기                          # noqa: E402

뿌리 = 'E:/xampp/htdocs/ce-admin/테스트/통합테스트/2026-09-10/2026-09-10'
절들 = json.load(io.open('E:/tmp/gen/md.json', encoding='utf-8'))
자료 = json.load(io.open('E:/tmp/gen/dump.json', encoding='utf-8'))

# 실제로 밟은 자취 — 있으면 마디 밑에 적는다. 없으면 적지 않는다.
try:
    밟음 = json.load(io.open('E:/tmp/gen/confirm.json', encoding='utf-8'))
except Exception:
    밟음 = {}

청구처표 = {
 'nhis':  {'보임': '공단', '기관': '국민건강보험공단 중구지사', '부서': '보험급여부',
           '팩스': '02-0000-0000', '주소': '서울특별시 중구 소공로 70 포스트타워 8층'},
 'local': {'보임': '지자체', '기관': '서울특별시 중구청', '부서': '복지정책과 의료급여팀',
           '팩스': '(없음 — 등기)', '주소': '[우.04558] 서울특별시 중구 창경궁로 17'},
}

카 = 78


def 폭(글):
    """한글ㆍ전각은 두 칸으로 센다 — 선이 넘치지 않게."""
    import unicodedata
    return sum(2 if unicodedata.east_asian_width(ch) in 'WF' else 1 for ch in 글)


def 채움(글, n):
    """폭 n 이 되도록 오른쪽을 띄운다."""
    return 글 + ' ' * max(0, n - 폭(글))


def 선(제목, ch='─'):
    머 = '  ─ %s ' % 제목
    return 머 + ch * max(3, 카 - 폭(머))


def 머리(번, 이름, 화면, 주소, 구분):
    return ['', '━' * 카,
            '[%s] %s' % (번, 이름),
            '━' * 카,
            '  화면 : %s' % 화면,
            '  주소 : %s' % 주소,
            '  구분 : %s' % 구분]


def 값줄(표):
    머 = '  ┌─ 넣을 값 '
    ㄱ = ['', 머 + '─' * max(3, 카 - 폭(머))]
    for k, v in 표:
        v = str(v)
        조각 = v.split('   ← ')
        ㄱ.append('  │ %s %s' % (채움(k, 24), 조각[0]))
        for 뒤 in 조각[1:]:
            ㄱ.append('  │ %s   ← %s' % (' ' * 24, 뒤))
    ㄱ.append('  └' + '─' * (카 - 3))
    return ㄱ


def 목록(제목, 것들, 기호='·'):
    if not 것들:
        return []
    ㄱ = ['', 선(제목)]
    for x in 것들:
        조각 = [x[i:i + 68] for i in range(0, len(x), 68)] or ['']
        ㄱ.append('    %s %s' % (기호, 조각[0]))
        for 뒤 in 조각[1:]:
            ㄱ.append('      %s' % 뒤)
    return ㄱ


def 치환(글, c):
    """원문의 자리표를 이 건의 값으로 바꾼다. 뜻이 달라질 만한 것은 건드리지 않는다."""
    주 = c['주문']; 처 = c['처방']
    for 옛, 새 in [
        ('RX-YYYYMMDD-NNN', 처['rx_number']),
        ('RX-…', 처['rx_number']),
        ('EUD…', 주['order_number']),
        ('S…', 주.get('withworks_so_no') or 'S…'),
    ]:
        글 = 글.replace(옛, 새)
    return 글


def 자취(번, c):
    """그 마디를 실제로 밟았으면 무엇이 남았는지. 밟지 않았으면 왜인지."""
    r = 밟음.get(c['no'])
    if not r:
        return []
    if 번 == '12':
        return ['%s 에 ［입금 확인］ — %s원' % (r['입금확인'], '{:,}'.format(r['입금액'])),
                '주문 상태 %s · 세금계산서 %s (시뮬레이션) · 거래명세서 %s'
                % (r['주문상태'], r['세금계산서'], r['명세서일']),
                '현금영수증 미발행 — 처방전 건은 청구전략에 없다']
    if 번 == '13':
        return ['웹훅 so.confirmed 닿음 — 창고 상태 %s' % r['창고상태'],
                '출고 건 %s 가 섰다 (%s · 확정수량 %s)'
                % (r['출고번호'], r['출고창고'], r['확정수량']),
                '활동 기록 「위드웍스 판매주문 확정 %s」' % r['판매번호']]
    if 번 in ('14', '15', '16', '17'):
        막 = ['아직 밟지 못했다 — 데모 창고에 이 제품 재고가 없다',
              '재고 상세에서 5008ㆍ5356ㆍ5376 모두 No data · 재고 조정에도 줄이 없다',
              '열일곱 건이 쓰는 양은 9,180개 — 입고를 새로 잡아야 한다']
        if 번 == '14':
            막.insert(1, '출고 건 %s 를 열어 ［미할당］ → ［부분 할당］ → Yes 까지 눌렀으나 '
                         '상태가 「신규」 그대로다' % r['출고번호'])
        return 막
    return []


def 쓰기(c):
    이름 = c['이름']
    처 = c['처방']; 주 = c['주문']
    c['청구처'] = 청구처표[처['claim_agency']]
    c['자료수'] = len([f for f in os.listdir(os.path.join(뿌리, 이름))
                      if f.endswith('_TEST.jpg')])
    묶음 = 만들기(c)
    없 = 없음표(c)

    # 절을 마디에 나눠 담는다
    담김 = {번: [] for 번, *_ in 마디}
    for 절 in 절들:
        담김[어느마디(절)].append(절)

    본 = c['본인']
    ㄱ = []
    ㄱ.append('=' * 카)
    ㄱ.append('  CE Admin 통합 테스트 — 시나리오 실행 순서 및 세부 내용')
    ㄱ.append('  CASE-%s  %s' % (c['no'], 이름))
    ㄱ.append('=' * 카)
    for k, v in [
        ('회차', '통합테스트 2026-09-10 (17 CASE)'),
        ('대상', 'CE Admin(ceadmin.co.kr) · 위드웍스 WMS(demoworks.co.kr)'),
        ('자격', '%s — 본인 %s%% / %s %s%%' % (
            c['자격'], 0 if 본 == 0 else 10, c['청구처']['보임'], 100 if 본 == 0 else 90)),
        ('결제', c['결제'] or '없음 (본인부담 0원)'),
        ('나이', ('미성년 — 보호자 %s(%s) 가 위임 서명'
                 % (c['환자'].get('guardian_name'), c['환자'].get('guardian_relation')))
                if c['미성년'] else '성년 — 본인이 서명'),
        ('거래처', '#%s %s' % (c['환자']['id'], c['환자']['name'])),
        ('처방번호', 처['rx_number']),
        ('주문번호', 주['order_number']),
        ('위드웍스 판매번호', 주.get('withworks_so_no') or '—'),
        ('자료', '%d장' % c['자료수']),
        ('수행 방식', '100% 브라우저 수동 — DB 직접 조작 금지 · 모든 칸을 빠짐없이 채운다'),
        ('원문', '테스트/테스트_시나리오_상세.md (본문 절 %d개 · 확인 %d개)'
                 % (len(절들), sum(len(x['확인']) for x in 절들))),
    ]:
        ㄱ.append('  %-18s %s' % (k, v))
    ㄱ.append('')
    ㄱ.append('  구분 다섯 갈래')
    ㄱ.append('    등록     새로 세우는 것')
    ㄱ.append('    수정     이미 선 것을 고치는 것')
    ㄱ.append('    전송     사람이 눌러 내보내는 것')
    ㄱ.append('    자동전송 저장ㆍ사건에 딸려 저절로 나가는 것')
    ㄱ.append('    연계     저쪽 시스템과 주고받는 것')
    ㄱ.append('')
    ㄱ.append('  밟는 차례')
    for 번, 이, 화, 주소, 구 in 마디:
        ㄱ.append('    [%s] %-26s %s' % (번, 이, 구))

    밟은절 = 0
    건너뛴절 = 0
    대조 = []

    for 번, 이, 화, 주소, 구 in 마디:
        조 = 묶음.get(번, {})
        화2 = 화.replace('{RX}', 처['rx_number'])
        주소2 = (주소.replace('{RX}', 처['rx_number'])
                    .replace('{TOKEN}', (c['동의'] or {}).get('token') or '—')
                    .replace('{ORDER_ID}', str(주.get('id') or '')))
        ㄱ += 머리(번, 이, 화2, 주소2, 구)
        if 조.get('값'):
            ㄱ += 값줄(조['값'])
        if 조.get('새병원'):
            ㄱ += 값줄([('[새 병원 등록] ' + k, v) for k, v in 조['새병원']])
        ㄱ += 목록('누를 것', 조.get('누름') or [])
        ㄱ += 목록('저절로 일어나는 것', 조.get('알림') or [])
        ㄱ += 목록('선 값', ['%s : %s' % (k, v) for k, v in (조.get('결과') or [])])
        ㄱ += 목록('경고', 조.get('경고') or [], '⚠')
        ㄱ += 목록('밟은 자취 (2026-09-11)', 자취(번, c), '✔')

        절목록 = 담김[번]
        if 절목록:
            ㄱ.append('')
            ㄱ.append(선('원문 확인 항목'))
            for 절 in 절목록:
                키 = 절.get('번호') or 절['제목'][:20]
                까 = 없.get(절.get('번호'))
                제 = re.sub(r'^\d+(?:\.\d+)*\s*—\s*', '', 절['제목'])
                ㄱ.append('')
                ㄱ.append('  [%s] %s' % (절.get('번호') or '·', 제))
                if 까:
                    ㄱ.append('      → 해당 없음 — %s' % 까)
                    건너뛴절 += 1
                    대조.append((번, 키, 절['제목'], '해당 없음', 까))
                    continue
                밟은절 += 1
                대조.append((번, 키, 절['제목'], '밟음', ''))
                for s in 절['단계']:
                    ㄱ.append('      %s' % 치환(re.sub(r'\*\*', '', s), c))
                if not 절['확인']:
                    ㄱ.append('      (확인 항목 없음 — 읽고 넘어가는 설명)')
                for x in 절['확인']:
                    글 = 치환(re.sub(r'\*\*|`', '', x), c)
                    조각 = [글[i:i + 66] for i in range(0, len(글), 66)]
                    ㄱ.append('      [ ] %s' % 조각[0])
                    for 뒤 in 조각[1:]:
                        ㄱ.append('          %s' % 뒤)

    # ── 원문 대조표 ───────────────────────────────────
    ㄱ.append('')
    ㄱ.append('=' * 카)
    ㄱ.append('  원문 대조표 — 상세.md 본문 절이 모두 들어 있는가')
    ㄱ.append('=' * 카)
    ㄱ.append('  %s %s %s %s' % (채움('마디', 6), 채움('절', 10), 채움('제목', 46), '밟음/해당없음'))
    ㄱ.append('  ' + '-' * (카 - 2))
    for 번, 키, 제목, 판, 까 in 대조:
        ㄱ.append('  %s %s %s %s' % (채움(번, 6), 채움(키[:10], 10), 채움(제목[:34], 46), 판))
        if 까:
            ㄱ.append('  %s   → %s' % (' ' * 16, 까))
    ㄱ.append('  ' + '-' * (카 - 2))
    ㄱ.append('  본문 절 %d개 = 밟음 %d + 해당 없음 %d' % (len(절들), 밟은절, 건너뛴절))
    ㄱ.append('')
    ㄱ.append('  ※ 부록(2차ㆍ3차 회차 기록)은 시험할 것이 아니라 뺐다.')
    ㄱ.append('')

    if 밟은절 + 건너뛴절 != len(절들):
        raise SystemExit('절 수가 맞지 않는다: %d + %d ≠ %d'
                         % (밟은절, 건너뛴절, len(절들)))

    길 = os.path.join(뿌리, 이름, '시나리오 실행 순서 및 세부 내용.txt')
    io.open(길, 'w', encoding='utf-8', newline='\r\n').write('\n'.join(ㄱ))
    return 길, len(ㄱ), 밟은절, 건너뛴절


if __name__ == '__main__':
    고를것 = sys.argv[1:]
    for no in sorted(자료):
        if 고를것 and no not in 고를것:
            continue
        c = 자료[no]
        c['본인'] = int(float(c['주문']['patient_copay'] or 0))
        길, 줄수, 밟, 뺌 = 쓰기(c)
        print('CASE-%s %-4s → %d줄 · 절 밟음 %d · 해당 없음 %d'
              % (no, c['이름'], 줄수, 밟, 뺌))
