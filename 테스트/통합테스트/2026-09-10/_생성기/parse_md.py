# -*- coding: utf-8 -*-
"""테스트_시나리오_상세.md 를 절 단위로 쪼갠다.

각 절에서 「단계」ㆍ「입력」ㆍ「누름」ㆍ「확인」(체크상자)ㆍ표를 뽑아 둔다.
부록(지난 회차 기록)은 시험할 것이 아니므로 뺀다.
"""
import io, json, re

길 = 'E:/xampp/htdocs/ce-admin/테스트/테스트_시나리오_상세.md'
줄 = io.open(길, encoding='utf-8').read().split('\n')

절들 = []
장 = None
이번 = None

for i, l in enumerate(줄, 1):
    if l.startswith('# '):
        장 = l[2:].strip()
        continue
    if l.startswith('## ') or l.startswith('### '):
        이번 = {
            '줄': i,
            '깊이': 2 if l.startswith('## ') else 3,
            '장': 장,
            '제목': l.lstrip('#').strip(),
            '몸': [],
        }
        절들.append(이번)
        continue
    if 이번 is not None:
        이번['몸'].append(l)

# 부록과 목차는 뺀다 — 시험할 것이 아니다
본문 = [x for x in 절들
        if x['장'] and not x['장'].startswith('부록') and x['제목'] != '목차']


def 번호(제목):
    m = re.match(r'^(\d+(?:\.\d+)*)\s*—', 제목)
    return m.group(1) if m else None


for x in 본문:
    몸 = x['몸']
    x['번호'] = 번호(x['제목'])
    x['확인'] = [l.strip()[6:].strip() for l in 몸 if l.strip().startswith('- [ ]')]
    x['단계'] = [l.strip() for l in 몸
                 if re.match(r'^\*\*(단계|입력|누름|전제)\*\*', l.strip())]
    x['표'] = [l.rstrip() for l in 몸 if l.strip().startswith('|')]
    x['글'] = [l.rstrip() for l in 몸
               if l.strip() and not l.strip().startswith(('- [ ]', '|', '!['))]

io.open('E:/tmp/gen/md.json', 'w', encoding='utf-8').write(
    json.dumps(본문, ensure_ascii=False, indent=1))

print('본문 절 %d개 · 확인항목 %d개' % (len(본문), sum(len(x['확인']) for x in 본문)))
print('번호가 붙은 절 %d개' % len([x for x in 본문 if x['번호']]))
for x in 본문:
    if not x['번호']:
        print('   번호 없음: [%s] %s' % (x['장'][:14], x['제목'][:60]))
