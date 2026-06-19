# Claude 웹 PoC 시연 파워포인트 생성 프롬프트

아래 전체 내용을 **claude.ai** 채팅창에 그대로 붙여넣기 하세요.
Claude web이 Python 코드 Artifact를 생성하고, 다운로드 버튼을 제공합니다.

---

## ▼ claude.ai에 붙여넣을 프롬프트 시작 ▼

---

아래 Python 코드를 실행해서 CE-ADMIN PoC 시연 시나리오 파워포인트 파일을 생성해주세요.
python-pptx 라이브러리를 사용합니다. 코드를 그대로 실행하고 결과 파일을 다운로드할 수 있게 해주세요.

```python
from pptx import Presentation
from pptx.util import Inches, Pt
from pptx.dml.color import RGBColor
from pptx.enum.text import PP_ALIGN

# ── 색상 팔레트 (Vuexy 디자인 시스템) ──────────────────────────────────────
C_PURPLE   = RGBColor(0x73, 0x67, 0xF0)
C_PURPLE_D = RGBColor(0x5E, 0x57, 0xC8)
C_BG       = RGBColor(0xF8, 0xF7, 0xFA)
C_WHITE    = RGBColor(0xFF, 0xFF, 0xFF)
C_DARK     = RGBColor(0x2F, 0x33, 0x49)
C_GRAY     = RGBColor(0x6E, 0x6B, 0x7B)
C_SUCCESS  = RGBColor(0x28, 0xC7, 0x6F)
C_WARNING  = RGBColor(0xFF, 0x9F, 0x43)
C_INFO     = RGBColor(0x00, 0xCF, 0xE8)
C_DANGER   = RGBColor(0xEA, 0x54, 0x55)
C_LIGHT_P  = RGBColor(0xED, 0xEB, 0xFD)

prs = Presentation()
prs.slide_width  = Inches(13.33)
prs.slide_height = Inches(7.5)
BLANK = prs.slide_layouts[6]

# ── 헬퍼 함수 ──────────────────────────────────────────────────────────────

def rect(slide, x, y, w, h, fill=None, line=None, lw=None):
    s = slide.shapes.add_shape(1, Inches(x), Inches(y), Inches(w), Inches(h))
    s.line.fill.background()
    if fill:
        s.fill.solid(); s.fill.fore_color.rgb = fill
    else:
        s.fill.background()
    if line:
        s.line.color.rgb = line
        if lw: s.line.width = lw
    else:
        s.line.fill.background()
    return s

def txt(slide, text, x, y, w, h, size=14, bold=False, color=None,
        align=PP_ALIGN.LEFT, italic=False):
    color = color or C_DARK
    tb = slide.shapes.add_textbox(Inches(x), Inches(y), Inches(w), Inches(h))
    tb.word_wrap = True
    tf = tb.text_frame; tf.word_wrap = True
    p = tf.paragraphs[0]; p.alignment = align
    r = p.add_run(); r.text = text
    r.font.size = Pt(size); r.font.bold = bold
    r.font.italic = italic; r.font.color.rgb = color
    r.font.name = "Malgun Gothic"
    return tb

def mtxt(slide, lines, x, y, w, h, size=12, color=None, bold=False,
         align=PP_ALIGN.LEFT):
    color = color or C_GRAY
    tb = slide.shapes.add_textbox(Inches(x), Inches(y), Inches(w), Inches(h))
    tb.word_wrap = True
    tf = tb.text_frame; tf.word_wrap = True
    for i, line in enumerate(lines):
        p = tf.paragraphs[0] if i == 0 else tf.add_paragraph()
        p.alignment = align
        r = p.add_run(); r.text = line
        r.font.size = Pt(size); r.font.bold = bold
        r.font.color.rgb = color; r.font.name = "Malgun Gothic"

def header(slide, title, subtitle=None, step=None):
    rect(slide, 0, 0, 13.33, 1.1, fill=C_PURPLE)
    if step:
        rect(slide, 0.3, 0.2, 0.65, 0.65, fill=C_WHITE)
        txt(slide, str(step), 0.3, 0.18, 0.65, 0.7,
            size=22, bold=True, color=C_PURPLE, align=PP_ALIGN.CENTER)
    tx = 1.1 if step else 0.4
    txt(slide, title, tx, 0.15, 10, 0.75, size=26, bold=True, color=C_WHITE)
    if subtitle:
        txt(slide, subtitle, tx, 0.75, 10, 0.4,
            size=13, color=RGBColor(0xD0,0xCC,0xFF))
    rect(slide, 0, 7.35, 13.33, 0.15, fill=C_PURPLE_D)

def card(slide, x, y, w, h, title, lines, icon="", accent=None, fsize=12):
    accent = accent or C_PURPLE
    rect(slide, x, y, w, h, fill=C_WHITE, line=RGBColor(0xE0,0xDE,0xF8))
    rect(slide, x, y, w, 0.07, fill=accent)
    txt(slide, f"{icon}  {title}", x+0.15, y+0.1, w-0.3, 0.42,
        size=14, bold=True, color=accent)
    mtxt(slide, lines, x+0.15, y+0.55, w-0.3, h-0.7, size=fsize)

def tbl_header(slide, cx_list, cw_list, headers, y, bg=None):
    bg = bg or C_DARK
    total_w = sum(cw_list)
    rect(slide, cx_list[0], y, total_w, 0.38, fill=bg)
    for hd, cx, cw in zip(headers, cx_list, cw_list):
        txt(slide, hd, cx, y+0.04, cw, 0.3,
            size=10, bold=True, color=C_WHITE, align=PP_ALIGN.CENTER)
    return y + 0.38

def tbl_row(slide, cx_list, cw_list, values, y, ri=0,
            col_colors=None, btn_cols=None):
    bg = C_WHITE if ri % 2 == 0 else RGBColor(0xF8,0xF7,0xFA)
    total_w = sum(cw_list)
    rect(slide, cx_list[0], y, total_w, 0.4,
         fill=bg, line=RGBColor(0xE0,0xDE,0xF8))
    btn_cols = btn_cols or {}
    col_colors = col_colors or {}
    for ci, (val, cx, cw) in enumerate(zip(values, cx_list, cw_list)):
        if ci in btn_cols:
            rect(slide, cx, y+0.07, cw-0.05, 0.26, fill=btn_cols[ci])
            txt(slide, val, cx, y+0.09, cw-0.05, 0.24,
                size=9, bold=True, color=C_WHITE, align=PP_ALIGN.CENTER)
        else:
            c = col_colors.get(val, col_colors.get(ci, C_DARK))
            txt(slide, val, cx, y+0.07, cw, 0.3,
                size=9, color=c, align=PP_ALIGN.CENTER)
    return y + 0.4

# ══════════════════════════════════════════════════════════════════════════════
# SLIDE 1 — 표지
# ══════════════════════════════════════════════════════════════════════════════
sl = prs.slides.add_slide(BLANK)
rect(sl, 0, 0, 13.33, 7.5, fill=C_DARK)
rect(sl, 0, 0, 0.25, 7.5, fill=C_PURPLE)
rect(sl, 0.3, 3.0, 0.08, 2.0, fill=RGBColor(0x9E,0x97,0xF5))
txt(sl, "CE-ADMIN", 1.0, 1.3, 11, 1.4, size=58, bold=True, color=C_WHITE)
txt(sl, "처방전 통합 관리 시스템", 1.0, 2.7, 11, 0.8,
    size=28, color=RGBColor(0xB0,0xAB,0xF8))
rect(sl, 1.0, 3.6, 5, 0.05, fill=C_PURPLE)
txt(sl, "PoC 시연 시나리오", 1.0, 3.8, 11, 0.7, size=22, color=C_WHITE)
txt(sl, "2026년 5월", 1.0, 4.5, 6, 0.5, size=16, color=C_GRAY)
rect(sl, 10.5, 0.5, 2.5, 2.5, fill=C_PURPLE_D)
rect(sl, 11.0, 1.0, 1.8, 1.8, fill=C_PURPLE)
txt(sl, "⚕", 11.1, 1.05, 1.6, 1.6, size=55, color=C_WHITE, align=PP_ALIGN.CENTER)
badges = ["AI OCR", "처방전 관리", "TodoWorks API", "Popbill API", "배송 추적"]
bx = 1.0
for b in badges:
    rect(sl, bx, 6.3, 1.7, 0.5, fill=C_PURPLE_D)
    txt(sl, b, bx+0.05, 6.35, 1.6, 0.4, size=13, bold=True,
        color=C_WHITE, align=PP_ALIGN.CENTER)
    bx += 1.85

# ══════════════════════════════════════════════════════════════════════════════
# SLIDE 2 — 전체 흐름 개요
# ══════════════════════════════════════════════════════════════════════════════
sl = prs.slides.add_slide(BLANK)
rect(sl, 0, 0, 13.33, 7.5, fill=C_BG)
header(sl, "전체 시연 흐름", "End-to-End 처방전 처리 프로세스")

steps10 = [
    ("①", "처방전\n등록",     C_PURPLE),
    ("②", "AI OCR\n판독",    C_INFO),
    ("③", "수기\n검증",      C_WARNING),
    ("④", "주소\n입력",      C_SUCCESS),
    ("⑤", "제품\n조회",      C_PURPLE),
    ("⑥", "주문\n확인",      C_INFO),
    ("⑦", "입금\n확인",      C_WARNING),
    ("⑧", "현금영수증\n발행", C_SUCCESS),
    ("⑨", "세금계산서\n발행", C_PURPLE),
    ("⑩", "전자팩스\n전송",  C_DANGER),
]
sx = 0.35
for i,(num,lbl,col) in enumerate(steps10):
    rect(sl, sx, 1.5, 1.08, 1.1, fill=col)
    txt(sl, num, sx, 1.5, 1.08, 0.45, size=18, bold=True,
        color=C_WHITE, align=PP_ALIGN.CENTER)
    txt(sl, lbl, sx, 1.9, 1.08, 0.7, size=11, bold=True,
        color=C_WHITE, align=PP_ALIGN.CENTER)
    if i < 9:
        txt(sl, "→", sx+1.08, 1.85, 0.22, 0.4, size=14,
            color=C_PURPLE, align=PP_ALIGN.CENTER)
    sx += 1.3

rect(sl, 0.35, 3.1, 13.0, 0.06, fill=RGBColor(0xD0,0xCC,0xFF))
txt(sl, "이후 배송완료까지 전체 흐름 실시간 추적 가능",
    0.35, 3.3, 13.0, 0.5, size=14, color=C_GRAY)

tbl_hdrs = ["단계", "기능", "연계 시스템", "비고"]
cx4 = [0.35, 1.6, 5.2, 8.8]; cw4 = [1.2, 3.5, 3.5, 2.5]
ry = 3.95
rect(sl, 0.35, ry, sum(cw4), 0.38, fill=C_PURPLE)
for hd,cx,cw in zip(tbl_hdrs,cx4,cw4):
    txt(sl, hd, cx+0.05, ry+0.04, cw-0.1, 0.3,
        size=12, bold=True, color=C_WHITE, align=PP_ALIGN.CENTER)
ry += 0.38
tbl_rows = [
    ["①~②", "처방전 등록 + AI OCR",         "모바일 앱 / 카메라",      ""],
    ["③~④", "수기 검증 + 주소입력",          "CE-Admin 웹 화면",        ""],
    ["⑤~⑥", "제품 조회 + 주문 확인",         "TodoWorks 판매주문 API",  ""],
    ["⑦",   "입금 확인",                     "내부 처리",               "미시연 (준비중)"],
    ["⑧~⑨", "현금영수증 / 세금계산서",       "Popbill API",             ""],
    ["⑩",   "보험공단 전자팩스",              "Popbill 팩스 API",        ""],
]
for ri,row in enumerate(tbl_rows):
    bg = C_WHITE if ri%2==0 else RGBColor(0xF8,0xF7,0xFA)
    rect(sl, 0.35, ry, sum(cw4), 0.38,
         fill=bg, line=RGBColor(0xE0,0xDE,0xF8))
    for ci,(val,cx,cw) in enumerate(zip(row,cx4,cw4)):
        c = C_DANGER if "준비중" in val else C_DARK
        txt(sl, val, cx+0.05, ry+0.05, cw-0.1, 0.3,
            size=11, color=c, align=PP_ALIGN.CENTER)
    ry += 0.38

# ══════════════════════════════════════════════════════════════════════════════
# SLIDE 3 — STEP 1: 처방전 등록
# ══════════════════════════════════════════════════════════════════════════════
sl = prs.slides.add_slide(BLANK)
rect(sl, 0, 0, 13.33, 7.5, fill=C_BG)
header(sl, "처방전 등록", "모바일 앱으로 처방전 사진 촬영 및 업로드", step="STEP 1")

# 폰 목업
rect(sl, 0.5, 1.4, 2.8, 5.4, fill=C_DARK)
rect(sl, 0.65, 1.6, 2.5, 4.8, fill=C_WHITE)
rect(sl, 1.7, 1.55, 0.6, 0.12, fill=C_GRAY)
rect(sl, 0.65, 1.6, 2.5, 0.55, fill=C_PURPLE)
txt(sl, "CE-Admin 앱", 0.7, 1.68, 2.4, 0.4, size=12, bold=True,
    color=C_WHITE, align=PP_ALIGN.CENTER)
sy = 2.25
for lbl, col in [("📷 촬영하기", C_PURPLE),
                 ("📁 갤러리 선택", C_INFO),
                 ("📋 처방전 목록", C_SUCCESS)]:
    rect(sl, 0.8, sy, 2.2, 0.55, fill=col)
    txt(sl, lbl, 0.8, sy+0.08, 2.2, 0.4, size=12, bold=True,
        color=C_WHITE, align=PP_ALIGN.CENTER)
    sy += 0.72
txt(sl, "처방전 미리보기", 0.8, 4.45, 2.2, 0.3, size=10,
    color=C_GRAY, align=PP_ALIGN.CENTER)
rect(sl, 0.8, 4.75, 2.2, 1.4, fill=RGBColor(0xF0,0xF0,0xF0))
txt(sl, "📄", 1.5, 4.85, 0.8, 1.0, size=34,
    color=C_GRAY, align=PP_ALIGN.CENTER)

card(sl, 3.7, 1.3, 4.3, 2.2, "등록 방법",
    ["• 모바일 앱에서 처방전 사진 촬영",
     "• 갤러리에서 기존 이미지 업로드",
     "• 실시간 서버 전송 및 저장",
     "• 등록 완료 시 알림 발송"],
    icon="📱", accent=C_PURPLE)
card(sl, 8.2, 1.3, 4.8, 2.2, "지원 형식",
    ["• JPG, PNG, PDF 처방전 이미지",
     "• 최대 10MB 파일 크기",
     "• 자동 이미지 최적화",
     "• 암호화 전송 보장"],
    icon="📎", accent=C_INFO)
card(sl, 3.7, 3.75, 9.3, 2.9, "처방전 등록 프로세스",
    ["① 환자가 모바일 앱 실행  →  ② 처방전 촬영 또는 갤러리 선택",
     "③ 이미지 미리보기 확인  →  ④ [전송] 버튼 클릭",
     "⑤ 서버 업로드 완료  →  ⑥ CE-Admin 관리자 화면에 수신 알림 표시",
     "⑦ 관리자 처방전 목록에서 신규 접수 확인 가능"],
    icon="🔄", accent=C_SUCCESS, fsize=13)

# ══════════════════════════════════════════════════════════════════════════════
# SLIDE 4 — STEP 2: AI OCR 판독
# ══════════════════════════════════════════════════════════════════════════════
sl = prs.slides.add_slide(BLANK)
rect(sl, 0, 0, 13.33, 7.5, fill=C_BG)
header(sl, "AI OCR 판독", "처방전 이미지 자동 텍스트 인식 및 데이터 추출", step="STEP 2")

flow_boxes = [
    (0.4,  "처방전\n이미지",  C_PURPLE),
    (3.0,  "AI OCR\n엔진",   C_INFO),
    (5.6,  "텍스트\n추출",   C_WARNING),
    (8.2,  "구조화\n데이터", C_SUCCESS),
    (10.8, "DB\n저장",       C_PURPLE),
]
for bx,lbl,col in flow_boxes:
    rect(sl, bx, 1.4, 2.0, 1.1, fill=col)
    txt(sl, lbl, bx, 1.4, 2.0, 1.1, size=15, bold=True,
        color=C_WHITE, align=PP_ALIGN.CENTER)
for ax in [2.4, 5.0, 7.6, 10.2]:
    txt(sl, "→", ax, 1.72, 0.6, 0.4, size=18, bold=True,
        color=C_PURPLE, align=PP_ALIGN.CENTER)

# OCR 결과 패널
rect(sl, 0.4, 2.85, 5.8, 4.3, fill=C_WHITE, line=RGBColor(0xE0,0xDE,0xF8))
rect(sl, 0.4, 2.85, 5.8, 0.45, fill=C_PURPLE)
txt(sl, "OCR 판독 결과", 0.5, 2.9, 5.6, 0.35, size=13, bold=True, color=C_WHITE)
ocr_fields = [
    ("환자명",       "홍길동",            C_SUCCESS),
    ("생년월일",     "1985-03-15",        C_INFO),
    ("처방일",       "2026-05-08",        C_PURPLE),
    ("병원명",       "서울내과의원",       C_PURPLE),
    ("의약품명",     "아목시실린 500mg",   C_WARNING),
    ("용량/횟수",    "1정 × 3회 × 7일",   C_WARNING),
    ("면허번호",     "12345",             C_GRAY),
]
fy = 3.45
for fname,fval,fc in ocr_fields:
    txt(sl, fname, 0.6, fy, 2.0, 0.3, size=11, color=C_GRAY)
    rect(sl, 2.65, fy+0.03, 3.3, 0.28, fill=RGBColor(0xF8,0xF7,0xFA))
    txt(sl, fval, 2.7, fy+0.03, 3.2, 0.28, size=11, bold=True, color=fc)
    fy += 0.42
rect(sl, 0.4, 6.75, 5.8, 0.4, fill=C_LIGHT_P)
txt(sl, "판독 신뢰도: 97.3%  ✓ 자동 처리 완료",
    0.5, 6.78, 5.6, 0.3, size=12, bold=True, color=C_PURPLE)

card(sl, 6.5, 2.85, 3.2, 2.0, "AI 모델 특징",
    ["• 딥러닝 기반 문자 인식",
     "• 한국어 의약 용어 특화",
     "• 손글씨 처방전 지원",
     "• 평균 97%+ 인식 정확도"],
    icon="🤖", accent=C_INFO)
card(sl, 9.9, 2.85, 3.1, 2.0, "추출 데이터",
    ["• 환자 기본정보 (성명, 생년월일)",
     "• 처방 의약품 목록",
     "• 처방 병원 및 의사 정보",
     "• 처방일 및 유효기간"],
    icon="📊", accent=C_SUCCESS)
card(sl, 6.5, 5.1, 6.5, 2.05, "처리 흐름",
    ["1. 업로드된 처방전 이미지 자동 감지",
     "2. AI OCR 엔진 호출 (비동기 처리)",
     "3. 필드별 데이터 추출 및 신뢰도 측정",
     "4. 신뢰도 95% 이상: 자동 저장",
     "5. 신뢰도 95% 미만: 수기 검증 이관"],
    icon="⚡", accent=C_WARNING)

# ══════════════════════════════════════════════════════════════════════════════
# SLIDE 5 — STEP 3: OCR 수기 검증
# ══════════════════════════════════════════════════════════════════════════════
sl = prs.slides.add_slide(BLANK)
rect(sl, 0, 0, 13.33, 7.5, fill=C_BG)
header(sl, "OCR 수기 검증", "AI 판독 결과 관리자 수동 검토 및 수정", step="STEP 3")

rect(sl, 0.4, 1.3, 4.2, 5.8, fill=C_WHITE, line=RGBColor(0xE0,0xDE,0xF8))
rect(sl, 0.4, 1.3, 4.2, 0.45, fill=C_WARNING)
txt(sl, "원본 처방전 이미지", 0.5, 1.35, 4.0, 0.35, size=13, bold=True, color=C_WHITE)
rect(sl, 0.5, 1.85, 4.0, 4.8, fill=RGBColor(0xF0,0xF0,0xF0))
txt(sl, "📄\n처방전 이미지", 1.2, 3.2, 2.5, 2.0, size=20,
    color=C_GRAY, align=PP_ALIGN.CENTER)

rect(sl, 4.8, 1.3, 8.2, 5.8, fill=C_WHITE, line=RGBColor(0xE0,0xDE,0xF8))
rect(sl, 4.8, 1.3, 8.2, 0.45, fill=C_PURPLE)
txt(sl, "수기 검증 편집 화면", 4.9, 1.35, 8.0, 0.35, size=13, bold=True, color=C_WHITE)
edit_fields = [
    ("환자명 *",    "홍길동",        True),
    ("생년월일 *",  "1985-03-15",   True),
    ("처방일 *",    "2026-05-08",   True),
    ("병원명",      "서울내과의원",  True),
    ("의약품명 *",  "아목시실린 500mg", False),
    ("처방수량",    "21정",          True),
]
ey = 1.95
for lbl,val,ok in edit_fields:
    txt(sl, lbl, 5.0, ey, 2.2, 0.28, size=11, color=C_GRAY)
    bc = RGBColor(0xE0,0xDE,0xF8) if ok else C_WARNING
    rect(sl, 7.3, ey+0.02, 5.4, 0.3, fill=RGBColor(0xF8,0xF7,0xFA), line=bc)
    txt(sl, val, 7.35, ey+0.04, 5.2, 0.28, size=11, color=C_DARK)
    txt(sl, "✅" if ok else "⚠️", 12.65, ey+0.02, 0.3, 0.3, size=11)
    ey += 0.5
rect(sl, 5.0, 6.4, 2.5, 0.45, fill=C_SUCCESS)
txt(sl, "✓  검증 완료", 5.0, 6.42, 2.5, 0.4, size=13, bold=True,
    color=C_WHITE, align=PP_ALIGN.CENTER)
rect(sl, 7.7, 6.4, 2.5, 0.45, fill=C_WARNING)
txt(sl, "↩  재판독 요청", 7.7, 6.42, 2.5, 0.4, size=13, bold=True,
    color=C_WHITE, align=PP_ALIGN.CENTER)
rect(sl, 10.4, 6.4, 2.3, 0.45, fill=C_DANGER)
txt(sl, "✕  처방전 반려", 10.4, 6.42, 2.3, 0.4, size=13, bold=True,
    color=C_WHITE, align=PP_ALIGN.CENTER)

# ══════════════════════════════════════════════════════════════════════════════
# SLIDE 6 — STEP 4: 주소 입력
# ══════════════════════════════════════════════════════════════════════════════
sl = prs.slides.add_slide(BLANK)
rect(sl, 0, 0, 13.33, 7.5, fill=C_BG)
header(sl, "주소 입력", "환자 배송 주소 등록 및 관리 (카카오 주소 API)", step="STEP 4")

card(sl, 0.4, 1.3, 6.5, 5.8, "주소 입력 화면",
    ["우편번호 검색 (카카오 주소 API)",
     "상세 주소 입력",
     "기본 배송지 설정",
     "주소 이력 관리 (자동완성)"],
    icon="📍", accent=C_SUCCESS)
rect(sl, 0.6, 2.2, 6.1, 0.38, fill=C_WHITE, line=RGBColor(0xE0,0xDE,0xF8))
txt(sl, "우편번호  06234", 0.7, 2.23, 4.0, 0.3, size=11, color=C_DARK)
rect(sl, 5.0, 2.22, 1.5, 0.35, fill=C_PURPLE)
txt(sl, "주소 검색", 5.0, 2.24, 1.5, 0.3, size=11, bold=True,
    color=C_WHITE, align=PP_ALIGN.CENTER)
rect(sl, 0.6, 2.7, 6.1, 0.38, fill=C_WHITE, line=RGBColor(0xE0,0xDE,0xF8))
txt(sl, "기본주소  서울특별시 강남구 테헤란로 123",
    0.7, 2.73, 6.0, 0.3, size=11, color=C_DARK)
rect(sl, 0.6, 3.2, 6.1, 0.38, fill=C_WHITE, line=RGBColor(0xE0,0xDE,0xF8))
txt(sl, "상세주소  강남빌딩 801호", 0.7, 3.23, 6.0, 0.3, size=11, color=C_DARK)
rect(sl, 0.6, 3.7, 6.1, 0.38, fill=C_SUCCESS)
txt(sl, "✓  주소 저장", 0.6, 3.72, 6.1, 0.34, size=12, bold=True,
    color=C_WHITE, align=PP_ALIGN.CENTER)

card(sl, 7.2, 1.3, 5.8, 2.6, "기능 요약",
    ["• 다음(카카오) 주소 API 팝업 연동",
     "• 도로명 / 지번 주소 모두 지원",
     "• 등록 후 주문 연동 자동 적용",
     "• 환자별 복수 배송지 관리"],
    icon="🗺️", accent=C_SUCCESS)
card(sl, 7.2, 4.1, 5.8, 3.0, "주소 이력 자동완성",
    ["• 서울특별시 강남구 테헤란로 123 (기본)",
     "• 서울특별시 서초구 반포대로 58",
     "• 경기도 성남시 분당구 판교로 256",
     "",
     "→ 선택 시 자동 입력 완성"],
    icon="🕐", accent=C_INFO)

# ══════════════════════════════════════════════════════════════════════════════
# SLIDE 7 — STEP 5: 제품 조회 (TodoWorks)
# ══════════════════════════════════════════════════════════════════════════════
sl = prs.slides.add_slide(BLANK)
rect(sl, 0, 0, 13.33, 7.5, fill=C_BG)
header(sl, "제품 조회", "TodoWorks 판매주문 API 연계 제품 검색 및 선택", step="STEP 5")

for bx,lbl,col in [(0.4,"CE-Admin\n처방전 화면",C_PURPLE),
                   (4.7,"TodoWorks\n판매주문 API",C_INFO),
                   (8.9,"제품 목록\n표시",C_SUCCESS)]:
    rect(sl, bx, 1.3, 3.0, 0.9, fill=col)
    txt(sl, lbl, bx, 1.3, 3.0, 0.9, size=13, bold=True,
        color=C_WHITE, align=PP_ALIGN.CENTER)
txt(sl, "→ API 요청", 3.45, 1.6, 1.2, 0.4, size=12, bold=True, color=C_PURPLE)
txt(sl, "← 응답",    7.95, 1.6, 1.2, 0.4, size=12, bold=True, color=C_INFO)

rect(sl, 0.4, 2.5, 12.6, 0.5, fill=C_WHITE, line=RGBColor(0xE0,0xDE,0xF8))
txt(sl, "🔍  제품명 또는 성분명 검색...",
    0.6, 2.55, 10.5, 0.38, size=13, color=C_GRAY)
rect(sl, 11.3, 2.52, 1.5, 0.44, fill=C_PURPLE)
txt(sl, "검색", 11.3, 2.54, 1.5, 0.38, size=13, bold=True,
    color=C_WHITE, align=PP_ALIGN.CENTER)

ph = ["제품코드","제품명","성분/규격","단가(원)","재고","선택"]
pcx = [0.4,1.8,4.0,8.5,10.5,12.0]
pcw = [1.3,2.1,4.4,1.9,1.4,1.0]
py = tbl_header(sl, pcx, pcw, ph, 3.15)
products = [
    ["AMX-500","아목시실린캡슐 500mg","아목시실린수화물 574.5mg","1,200","✓ 재고","선택"],
    ["CEF-250","세파클러캡슐 250mg","세파클러 250mg","980","✓ 재고","선택"],
    ["IBU-200","이부프로펜정 200mg","이부프로펜 200mg","450","✓ 재고","선택"],
    ["MET-500","메트포르민정 500mg","메트포르민염산염 500mg","320","⚠ 재고부족","선택"],
]
for ri,row in enumerate(products):
    bg = C_LIGHT_P if ri==0 else (C_WHITE if ri%2==0 else RGBColor(0xF8,0xF7,0xFA))
    rect(sl, pcx[0], py, sum(pcw), 0.42, fill=bg, line=RGBColor(0xE0,0xDE,0xF8))
    if ri==0: rect(sl, pcx[0], py, 0.08, 0.42, fill=C_PURPLE)
    for ci,(val,cx,cw) in enumerate(zip(row,pcx,pcw)):
        if val=="선택":
            rect(sl, cx, py+0.07, cw, 0.27, fill=C_PURPLE)
            txt(sl, "선택", cx, py+0.09, cw, 0.25, size=10, bold=True,
                color=C_WHITE, align=PP_ALIGN.CENTER)
        else:
            c = C_DANGER if "부족" in val else (C_SUCCESS if "✓" in val else C_DARK)
            txt(sl, val, cx, py+0.08, cw, 0.3, size=10, color=c, align=PP_ALIGN.CENTER)
    py += 0.42
txt(sl, "※ 처방전 의약품명 기준 자동 검색 후 관리자 확인 선택",
    0.4, py+0.1, 12.6, 0.35, size=11, color=C_GRAY, italic=True)

# ══════════════════════════════════════════════════════════════════════════════
# SLIDE 8 — STEP 6: TodoWorks 판매주문 리스트
# ══════════════════════════════════════════════════════════════════════════════
sl = prs.slides.add_slide(BLANK)
rect(sl, 0, 0, 13.33, 7.5, fill=C_BG)
header(sl, "TodoWorks 판매주문 리스트 확인",
       "CE-Admin ↔ TodoWorks 주문 현황 실시간 연동 화면", step="STEP 6")

stat_data = [
    ("전체 주문","1,284",C_PURPLE,"📋"),
    ("처리중","48",C_WARNING,"⏳"),
    ("배송중","156",C_INFO,"🚚"),
    ("완료","1,038",C_SUCCESS,"✅"),
    ("취소/반려","42",C_DANGER,"❌"),
]
sx = 0.4
for lbl,val,col,icon in stat_data:
    rect(sl, sx, 1.3, 2.3, 1.1, fill=C_WHITE, line=RGBColor(0xE0,0xDE,0xF8))
    rect(sl, sx, 1.3, 2.3, 0.07, fill=col)
    txt(sl, icon, sx+0.15, 1.4, 0.55, 0.55, size=22)
    txt(sl, val, sx+0.7, 1.4, 1.5, 0.55, size=22, bold=True,
        color=col, align=PP_ALIGN.RIGHT)
    txt(sl, lbl, sx+0.1, 1.95, 2.1, 0.35, size=11,
        color=C_GRAY, align=PP_ALIGN.CENTER)
    sx += 2.45

oh  = ["주문번호","환자명","제품명","수량","금액(원)","주문일시","상태","액션"]
ocx = [0.4,1.9,3.3,6.5,7.3,8.4,10.4,11.7]
ocw = [1.4,1.3,3.1,0.7,1.0,1.9,1.2,1.4]
oy = tbl_header(sl, ocx, ocw, oh, 2.65)
SC = {"처리중":C_WARNING,"배송중":C_INFO,"완료":C_SUCCESS,"취소":C_DANGER}
orders = [
    ["ORD-2458","홍길동","아목시실린캡슐 500mg × 21정","21","25,200","05-08 14:23","처리중","상세"],
    ["ORD-2457","김영희","이부프로펜정 200mg × 60정",  "60","27,000","05-08 13:55","배송중","추적"],
    ["ORD-2456","이철수","세파클러캡슐 250mg × 28정",  "28","27,440","05-08 12:10","완료","확인"],
    ["ORD-2455","박지현","메트포르민정 500mg × 90정",  "90","28,800","05-08 10:30","배송중","추적"],
    ["ORD-2454","최민준","아목시실린캡슐 500mg × 14정","14","16,800","05-07 16:45","완료","확인"],
    ["ORD-2453","정수진","이부프로펜정 200mg × 30정",  "30","13,500","05-07 14:20","취소","확인"],
]
for ri,row in enumerate(orders):
    bg = C_WHITE if ri%2==0 else RGBColor(0xF8,0xF7,0xFA)
    rect(sl, ocx[0], oy, sum(ocw), 0.4, fill=bg, line=RGBColor(0xE0,0xDE,0xF8))
    for ci,(val,cx,cw) in enumerate(zip(row,ocx,ocw)):
        if ci==6:
            rect(sl, cx, oy+0.07, cw, 0.26, fill=SC.get(val,C_GRAY))
            txt(sl, val, cx, oy+0.09, cw, 0.24, size=9, bold=True,
                color=C_WHITE, align=PP_ALIGN.CENTER)
        elif ci==7:
            rect(sl, cx, oy+0.07, cw, 0.26, fill=C_PURPLE)
            txt(sl, val, cx, oy+0.09, cw, 0.24, size=9, bold=True,
                color=C_WHITE, align=PP_ALIGN.CENTER)
        else:
            txt(sl, val, cx, oy+0.07, cw, 0.3, size=9,
                color=C_DARK, align=PP_ALIGN.CENTER)
    oy += 0.4
txt(sl, "※ TodoWorks API 실시간 연동 — 주문 상태 자동 갱신 (30초 폴링)",
    0.4, oy+0.05, 12.6, 0.3, size=10, color=C_GRAY, italic=True)

# ══════════════════════════════════════════════════════════════════════════════
# SLIDE 9 — STEP 7: 입금 확인 (미시연)
# ══════════════════════════════════════════════════════════════════════════════
sl = prs.slides.add_slide(BLANK)
rect(sl, 0, 0, 13.33, 7.5, fill=C_BG)
header(sl, "입금 확인", "결제 처리 현황 — 연계 준비중", step="STEP 7")

rect(sl, 1.5, 1.6, 10.0, 1.2, fill=RGBColor(0xFF,0xF5,0xE0))
rect(sl, 1.5, 1.6, 0.2, 1.2, fill=C_WARNING)
txt(sl, "⚠  미시연 항목 — 현재 연계 준비중입니다",
    1.8, 1.75, 9.5, 0.9, size=20, bold=True, color=C_WARNING)

card(sl, 0.4, 3.1, 6.0, 3.8, "구현 예정 기능",
    ["• 가상계좌 발급 및 입금 대기 처리",
     "• 무통장 입금 확인 (은행 API 연동)",
     "• 카드 결제 승인 조회",
     "• 실시간 입금 알림 (SMS/앱 푸시)",
     "• 입금 이력 관리 및 영수증 발행",
     "• 미입금 자동 취소 처리 (D+3)"],
    icon="💳", accent=C_WARNING)
card(sl, 6.7, 3.1, 6.3, 3.8, "연계 예정 시스템",
    ["• PG사 결제 API (토스페이먼츠/KG이니시스)",
     "• 은행 오픈뱅킹 API",
     "• 실시간 계좌 조회 서비스",
     "",
     "예상 완료: 2026년 Q3",
     "담당: 개발팀 협의 중"],
    icon="🏦", accent=C_INFO)

# ══════════════════════════════════════════════════════════════════════════════
# SLIDE 10 — STEP 8: 현금영수증
# ══════════════════════════════════════════════════════════════════════════════
sl = prs.slides.add_slide(BLANK)
rect(sl, 0, 0, 13.33, 7.5, fill=C_BG)
header(sl, "현금영수증 발행",
       "팝빌 현금영수증 API 연계 — 발행 및 이력 관리", step="STEP 8")

rect(sl, 0.4, 1.3, 3.0, 0.6, fill=RGBColor(0x00,0x6E,0xD2))
txt(sl, "POPBILL API", 0.4, 1.3, 3.0, 0.6, size=14, bold=True,
    color=C_WHITE, align=PP_ALIGN.CENTER)

rect(sl, 0.4, 2.1, 6.2, 4.8, fill=C_WHITE, line=RGBColor(0xE0,0xDE,0xF8))
rect(sl, 0.4, 2.1, 6.2, 0.45, fill=C_SUCCESS)
txt(sl, "현금영수증 발행", 0.55, 2.15, 6.0, 0.35, size=14, bold=True, color=C_WHITE)
crf = [("거래구분","소득공제용"),("거래일자","2026-05-08"),
       ("공급가액","22,909원"),("부가세액","2,291원"),
       ("합  계","25,200원"),("식별번호","010-1234-5678"),("거래유형","승인거래")]
fy = 2.7
for fn,fv in crf:
    txt(sl, fn, 0.6, fy, 2.3, 0.32, size=11, color=C_GRAY)
    rect(sl, 2.95, fy+0.02, 3.45, 0.3, fill=RGBColor(0xF8,0xF7,0xFA), line=RGBColor(0xE0,0xDE,0xF8))
    txt(sl, fv, 3.0, fy+0.04, 3.3, 0.28, size=11, bold=True, color=C_DARK)
    fy += 0.4
rect(sl, 0.55, 6.3, 2.6, 0.45, fill=C_SUCCESS)
txt(sl, "발행 요청", 0.55, 6.32, 2.6, 0.4, size=13, bold=True,
    color=C_WHITE, align=PP_ALIGN.CENTER)
rect(sl, 3.3, 6.3, 3.1, 0.45, fill=C_PURPLE)
txt(sl, "발행 이력 조회", 3.3, 6.32, 3.1, 0.4, size=13, bold=True,
    color=C_WHITE, align=PP_ALIGN.CENTER)

rect(sl, 6.8, 2.1, 6.2, 0.45, fill=C_SUCCESS)
txt(sl, "발행 이력", 6.95, 2.15, 6.0, 0.35, size=14, bold=True, color=C_WHITE)
crh = ["발행일시","금액","식별번호","상태"]
chx = [6.8,8.4,10.0,11.9]; chw = [1.55,1.55,1.85,1.25]
hy = tbl_header(sl, chx, chw, crh, 2.65)
cr_rows = [
    ["05-08 14:30","25,200원","010-1234-5678","발행완료"],
    ["05-07 11:15","18,500원","010-9876-5432","발행완료"],
    ["05-06 16:42","32,000원","010-1111-2222","발행완료"],
    ["05-05 09:20","12,900원","010-3333-4444","취소"],
    ["05-04 14:05","45,600원","010-5555-6666","발행완료"],
    ["05-03 10:33","8,000원", "010-7777-8888","발행완료"],
    ["05-02 15:58","29,400원","010-9999-0000","발행완료"],
]
for ri,row in enumerate(cr_rows):
    bg = C_WHITE if ri%2==0 else RGBColor(0xF8,0xF7,0xFA)
    rect(sl, chx[0], hy, sum(chw), 0.37, fill=bg, line=RGBColor(0xE0,0xDE,0xF8))
    for ci,(val,cx,cw) in enumerate(zip(row,chx,chw)):
        c = C_DANGER if val=="취소" else (C_SUCCESS if val=="발행완료" else C_DARK)
        txt(sl, val, cx, hy+0.05, cw, 0.28, size=9, color=c, align=PP_ALIGN.CENTER)
    hy += 0.37

# ══════════════════════════════════════════════════════════════════════════════
# SLIDE 11 — STEP 9: 세금계산서
# ══════════════════════════════════════════════════════════════════════════════
sl = prs.slides.add_slide(BLANK)
rect(sl, 0, 0, 13.33, 7.5, fill=C_BG)
header(sl, "세금계산서 발행",
       "팝빌 세금계산서 API 연계 — 전자 세금계산서 발행 및 이력", step="STEP 9")

rect(sl, 0.4, 1.3, 6.2, 5.8, fill=C_WHITE, line=RGBColor(0xE0,0xDE,0xF8))
rect(sl, 0.4, 1.3, 6.2, 0.45, fill=C_PURPLE)
txt(sl, "세금계산서 발행 양식", 0.55, 1.35, 6.0, 0.35, size=14, bold=True, color=C_WHITE)
tax_secs = [
    ("공급자",       [("사업자번호","123-45-67890"),("상호","(주)CE헬스케어"),("대표자","홍길동")]),
    ("공급받는자",   [("사업자번호","987-65-43210"),("상호","강남내과의원"),("이메일","clinic@example.com")]),
    ("공급 내역",    [("작성일자","2026-05-08"),("공급가액","22,909원"),("세액","2,291원"),("합계","25,200원")]),
]
sy2 = 1.95
for sec,fields in tax_secs:
    rect(sl, 0.5, sy2, 6.0, 0.32, fill=C_LIGHT_P)
    txt(sl, sec, 0.6, sy2+0.04, 5.8, 0.26, size=11, bold=True, color=C_PURPLE)
    sy2 += 0.32
    for fn,fv in fields:
        txt(sl, fn, 0.65, sy2+0.02, 2.2, 0.3, size=10, color=C_GRAY)
        rect(sl, 2.9, sy2+0.03, 3.5, 0.28, fill=RGBColor(0xF8,0xF7,0xFA), line=RGBColor(0xE0,0xDE,0xF8))
        txt(sl, fv, 2.95, sy2+0.05, 3.3, 0.26, size=10, color=C_DARK)
        sy2 += 0.34
rect(sl, 0.55, 6.55, 2.6, 0.4, fill=C_PURPLE)
txt(sl, "전자 발행", 0.55, 6.57, 2.6, 0.36, size=12, bold=True,
    color=C_WHITE, align=PP_ALIGN.CENTER)
rect(sl, 3.3, 6.55, 3.1, 0.4, fill=C_INFO)
txt(sl, "국세청 전송 확인", 3.3, 6.57, 3.1, 0.36, size=12, bold=True,
    color=C_WHITE, align=PP_ALIGN.CENTER)

rect(sl, 6.8, 1.3, 6.2, 5.8, fill=C_WHITE, line=RGBColor(0xE0,0xDE,0xF8))
rect(sl, 6.8, 1.3, 6.2, 0.45, fill=C_PURPLE)
txt(sl, "발행 이력", 6.95, 1.35, 6.0, 0.35, size=14, bold=True, color=C_WHITE)
txh = ["발행일","공급받는자","합계(원)","상태","국세청"]
thx = [6.8,7.95,9.6,10.8,11.95]; thw = [1.1,1.6,1.15,1.1,1.1]
thy = tbl_header(sl, thx, thw, txh, 1.85)
tx_rows = [
    ["2026-05-08","강남내과의원","25,200","발행완료","국세청승인"],
    ["2026-05-06","서초정형외과","68,000","발행완료","국세청승인"],
    ["2026-05-04","송파소아과","34,500","발행완료","국세청승인"],
    ["2026-05-02","강동안과","52,800","발행취소","취소처리"],
    ["2026-04-30","마포이비인후과","19,900","발행완료","국세청승인"],
    ["2026-04-28","용산내과","41,200","발행완료","국세청승인"],
    ["2026-04-25","은평외과","29,700","발행완료","국세청승인"],
    ["2026-04-23","성북산부인과","88,000","발행완료","국세청승인"],
    ["2026-04-21","도봉내과","15,500","발행완료","국세청승인"],
]
for ri,row in enumerate(tx_rows):
    bg = C_WHITE if ri%2==0 else RGBColor(0xF8,0xF7,0xFA)
    rect(sl, thx[0], thy, sum(thw), 0.38, fill=bg, line=RGBColor(0xE0,0xDE,0xF8))
    for ci,(val,cx,cw) in enumerate(zip(row,thx,thw)):
        if ci==3: c = C_DANGER if "취소" in val else C_SUCCESS
        elif ci==4: c = C_DANGER if "취소" in val else C_INFO
        else: c = C_DARK
        txt(sl, val, cx, thy+0.06, cw, 0.28, size=9, color=c, align=PP_ALIGN.CENTER)
    thy += 0.38

# ══════════════════════════════════════════════════════════════════════════════
# SLIDE 12 — STEP 10: 보험공단 전자팩스
# ══════════════════════════════════════════════════════════════════════════════
sl = prs.slides.add_slide(BLANK)
rect(sl, 0, 0, 13.33, 7.5, fill=C_BG)
header(sl, "보험공단 전자팩스 전송",
       "팝빌 팩스 API 연계 — 건강보험심사평가원 전자팩스 발송", step="STEP 10")

rect(sl, 0.4, 1.3, 6.0, 5.8, fill=C_WHITE, line=RGBColor(0xE0,0xDE,0xF8))
rect(sl, 0.4, 1.3, 6.0, 0.45, fill=C_DANGER)
txt(sl, "전자팩스 발송", 0.55, 1.35, 5.8, 0.35, size=14, bold=True, color=C_WHITE)
fax_fields = [
    ("발신번호",  "02-1234-5678"),
    ("수신번호",  "1644-0014 (보험공단)"),
    ("수신자명",  "건강보험심사평가원"),
    ("제목",      "처방전 청구서 ORD-2458"),
    ("전송문서",  "처방전.pdf (2.1MB)"),
    ("예약발송",  "즉시 발송"),
]
fy2 = 1.95
for fn,fv in fax_fields:
    txt(sl, fn, 0.6, fy2, 2.2, 0.32, size=11, color=C_GRAY)
    hl = fn=="수신번호"
    bc = C_DANGER if hl else RGBColor(0xE0,0xDE,0xF8)
    rect(sl, 2.85, fy2+0.02, 3.35, 0.3, fill=RGBColor(0xF8,0xF7,0xFA), line=bc)
    txt(sl, fv, 2.9, fy2+0.04, 3.25, 0.28, size=11, bold=hl, color=C_DARK)
    fy2 += 0.42
rect(sl, 0.5, 4.55, 5.7, 2.0, fill=RGBColor(0xF0,0xF0,0xF0))
txt(sl, "📄 처방전.pdf 미리보기", 0.6, 4.65, 5.5, 0.35, size=11, color=C_GRAY)
rect(sl, 0.6, 5.1, 5.5, 1.3, fill=C_WHITE)
txt(sl, "[처방전 이미지]", 2.0, 5.5, 2.6, 0.5, size=13,
    color=C_GRAY, align=PP_ALIGN.CENTER)
rect(sl, 0.55, 6.6, 2.4, 0.4, fill=C_DANGER)
txt(sl, "📠  팩스 전송", 0.55, 6.62, 2.4, 0.36, size=12, bold=True,
    color=C_WHITE, align=PP_ALIGN.CENTER)
rect(sl, 3.1, 6.6, 3.1, 0.4, fill=C_GRAY)
txt(sl, "전송 이력 조회", 3.1, 6.62, 3.1, 0.36, size=12, bold=True,
    color=C_WHITE, align=PP_ALIGN.CENTER)

rect(sl, 6.65, 1.3, 6.35, 5.8, fill=C_WHITE, line=RGBColor(0xE0,0xDE,0xF8))
rect(sl, 6.65, 1.3, 6.35, 0.45, fill=C_DANGER)
txt(sl, "전송 이력", 6.8, 1.35, 6.15, 0.35, size=14, bold=True, color=C_WHITE)
fxh = ["전송일시","주문번호","수신번호","상태","확인"]
fhx = [6.65,8.0,9.4,10.75,11.95]; fhw = [1.3,1.35,1.3,1.15,1.05]
fhy = tbl_header(sl, fhx, fhw, fxh, 1.85)
fax_rows = [
    ["05-08 14:35","ORD-2458","1644-0014","전송완료","수신확인"],
    ["05-08 11:20","ORD-2451","1644-0014","전송완료","수신확인"],
    ["05-07 16:55","ORD-2445","1644-0014","전송완료","수신확인"],
    ["05-07 10:30","ORD-2440","1644-0014","전송실패","재전송필요"],
    ["05-06 14:10","ORD-2435","1644-0014","전송완료","수신확인"],
    ["05-06 09:45","ORD-2430","1644-0014","전송완료","수신확인"],
    ["05-05 16:20","ORD-2425","1644-0014","전송완료","수신확인"],
    ["05-05 13:05","ORD-2420","1644-0014","전송완료","수신확인"],
    ["05-04 15:40","ORD-2415","1644-0014","전송완료","수신확인"],
]
for ri,row in enumerate(fax_rows):
    bg = C_WHITE if ri%2==0 else RGBColor(0xF8,0xF7,0xFA)
    rect(sl, fhx[0], fhy, sum(fhw), 0.38, fill=bg, line=RGBColor(0xE0,0xDE,0xF8))
    for ci,(val,cx,cw) in enumerate(zip(row,fhx,fhw)):
        if ci==3: c = C_DANGER if "실패" in val else C_SUCCESS
        elif ci==4: c = C_WARNING if "필요" in val else C_INFO
        else: c = C_DARK
        txt(sl, val, cx, fhy+0.06, cw, 0.28, size=9, color=c, align=PP_ALIGN.CENTER)
    fhy += 0.38

# ══════════════════════════════════════════════════════════════════════════════
# SLIDE 13 — 전체 흐름 타임라인
# ══════════════════════════════════════════════════════════════════════════════
sl = prs.slides.add_slide(BLANK)
rect(sl, 0, 0, 13.33, 7.5, fill=C_BG)
header(sl, "환자 주문 → 배송완료 전체 흐름",
       "처방전 접수부터 배송 완료까지 통합 현황 추적")

tl = [
    ("처방전\n접수",  "2026-05-08\n14:10",  C_PURPLE,  "✓"),
    ("OCR\n판독",     "14:11\n자동처리",    C_INFO,    "✓"),
    ("수기\n검증",    "14:15\n관리자확인",  C_WARNING, "✓"),
    ("주소\n확인",    "14:16\n자동매핑",    C_SUCCESS, "✓"),
    ("주문\n생성",    "14:23\nORD-2458",    C_PURPLE,  "✓"),
    ("입금\n확인",    "미연계\n준비중",     C_GRAY,    "○"),
    ("영수증\n발행",  "14:30\n현금영수증",  C_SUCCESS, "✓"),
    ("팩스\n전송",    "14:35\n수신확인",    C_DANGER,  "✓"),
    ("출고\n처리",    "15:00\nTodoWorks",   C_INFO,    "✓"),
    ("배송중",        "05-09\n14:22",       C_WARNING, "✓"),
    ("배송\n완료",    "05-10\n11:30",       C_SUCCESS, "⭐"),
]
tx = 0.35
for i,(lbl,ts,col,icon) in enumerate(tl):
    done = icon!="○"
    rect(sl, tx, 1.5, 1.0, 1.0, fill=col if done else C_GRAY)
    txt(sl, icon, tx, 1.5, 1.0, 0.5, size=18, bold=True,
        color=C_WHITE, align=PP_ALIGN.CENTER)
    txt(sl, lbl, tx, 1.95, 1.0, 0.55, size=10, bold=True,
        color=C_WHITE, align=PP_ALIGN.CENTER)
    txt(sl, ts, tx, 2.58, 1.0, 0.5, size=8,
        color=C_GRAY, align=PP_ALIGN.CENTER)
    if i < len(tl)-1:
        rect(sl, tx+1.0, 1.97, 0.15, 0.06, fill=col if done else C_GRAY)
    tx += 1.15

rect(sl, 0.4, 3.3, 12.6, 3.9, fill=C_WHITE, line=RGBColor(0xE0,0xDE,0xF8))
rect(sl, 0.4, 3.3, 12.6, 0.45, fill=C_DARK)
txt(sl, "주문 상세  [ORD-2458]  홍길동 환자",
    0.55, 3.35, 12.3, 0.35, size=14, bold=True, color=C_WHITE)
detail_cols = [
    ("환자 정보",  ["성명: 홍길동","생년: 1985-03-15","연락처: 010-1234-5678"]),
    ("처방 정보",  ["병원: 서울내과의원","약품: 아목시실린 500mg","처방일: 2026-05-08"]),
    ("주문 정보",  ["주문번호: ORD-2458","수량: 21정","금액: 25,200원"]),
    ("배송 정보",  ["수령인: 홍길동","주소: 강남구 테헤란로 123","송장: CJ123456789"]),
    ("처리 이력",  ["현금영수증: 발행완료","세금계산서: 발행완료","팩스전송: 수신확인"]),
]
dx = 0.55
for title,items in detail_cols:
    rect(sl, dx, 3.85, 2.35, 0.3, fill=C_LIGHT_P)
    txt(sl, title, dx+0.05, 3.88, 2.25, 0.25, size=11, bold=True, color=C_PURPLE)
    iy = 4.2
    for item in items:
        txt(sl, item, dx+0.05, iy, 2.25, 0.3, size=10, color=C_DARK)
        iy += 0.32
    dx += 2.5
rect(sl, 0.55, 6.7, 3.0, 0.4, fill=C_SUCCESS)
txt(sl, "✅  배송 완료", 0.55, 6.72, 3.0, 0.36, size=14, bold=True,
    color=C_WHITE, align=PP_ALIGN.CENTER)
txt(sl, "2026-05-10 11:30 수령 완료 확인",
    3.7, 6.75, 8.5, 0.35, size=13, color=C_GRAY)

# ══════════════════════════════════════════════════════════════════════════════
# SLIDE 14 — 클로징
# ══════════════════════════════════════════════════════════════════════════════
sl = prs.slides.add_slide(BLANK)
rect(sl, 0, 0, 13.33, 7.5, fill=C_DARK)
rect(sl, 0, 0, 0.3, 7.5, fill=C_PURPLE)
rect(sl, 0.35, 0, 0.1, 7.5, fill=RGBColor(0x9E,0x97,0xF5))

txt(sl, "CE-ADMIN PoC 시연 완료",
    1.0, 1.5, 11.5, 1.2, size=38, bold=True, color=C_WHITE)
txt(sl, "처방전 등록 → AI OCR → 수기검증 → 제품조회 → 주문 → 영수증/세금계산서/팩스 → 배송완료",
    1.0, 2.8, 11.5, 0.7, size=14, color=RGBColor(0xB0,0xAB,0xF8))
rect(sl, 1.0, 3.6, 5, 0.05, fill=C_PURPLE)
summary = [
    ("✅", "모바일 처방전 등록 + AI OCR 자동 판독",          C_SUCCESS),
    ("✅", "수기 검증 및 주소/제품 입력 관리",               C_SUCCESS),
    ("✅", "TodoWorks 판매주문 API 실시간 연동",              C_SUCCESS),
    ("✅", "Popbill 현금영수증 / 세금계산서 / 팩스 API 연동", C_SUCCESS),
    ("⚠️", "입금 확인 기능 — 연계 준비중 (예정: 2026 Q3)",   C_WARNING),
    ("✅", "주문 생성부터 배송완료까지 전 흐름 추적 가능",    C_SUCCESS),
]
sy3 = 3.9
for icon,text,col in summary:
    txt(sl, f"{icon}  {text}", 1.0, sy3, 11.0, 0.42, size=14, color=col)
    sy3 += 0.42
txt(sl, "감사합니다", 1.0, 6.6, 11.5, 0.6, size=22, bold=True, color=C_WHITE)

# ── 저장 ──────────────────────────────────────────────────────────────────────
import io
buf = io.BytesIO()
prs.save(buf)
buf.seek(0)

# Claude web artifact에서 다운로드 링크 제공
import base64
b64 = base64.b64encode(buf.read()).decode()
html = f"""
<html><body style="font-family:sans-serif;padding:30px;background:#f8f7fa;">
<h2 style="color:#7367f0;">CE-ADMIN PoC 시연 시나리오 PPTX 생성 완료</h2>
<p>총 <b>14장</b> 슬라이드가 생성되었습니다.</p>
<ul>
  <li>슬라이드 1: 표지</li>
  <li>슬라이드 2: 전체 흐름 개요</li>
  <li>슬라이드 3: STEP 1 처방전 등록</li>
  <li>슬라이드 4: STEP 2 AI OCR 판독</li>
  <li>슬라이드 5: STEP 3 수기 검증</li>
  <li>슬라이드 6: STEP 4 주소 입력</li>
  <li>슬라이드 7: STEP 5 제품 조회 (TodoWorks)</li>
  <li>슬라이드 8: STEP 6 판매주문 리스트</li>
  <li>슬라이드 9: STEP 7 입금 확인 (미시연)</li>
  <li>슬라이드 10: STEP 8 현금영수증</li>
  <li>슬라이드 11: STEP 9 세금계산서</li>
  <li>슬라이드 12: STEP 10 전자팩스</li>
  <li>슬라이드 13: 전체 흐름 타임라인</li>
  <li>슬라이드 14: 클로징</li>
</ul>
<a href="data:application/vnd.openxmlformats-officedocument.presentationml.presentation;base64,{b64}"
   download="CE-Admin_PoC_시연시나리오.pptx"
   style="display:inline-block;padding:14px 28px;background:#7367f0;color:#fff;
          border-radius:6px;text-decoration:none;font-weight:bold;font-size:16px;">
  ⬇ CE-Admin_PoC_시연시나리오.pptx 다운로드
</a>
</body></html>
"""
print(html)
```

---

## ▲ 붙여넣기 끝 ▲

## 사용 방법

1. **claude.ai** 접속 → 새 대화 시작
2. 위 내용을 채팅창에 전체 붙여넣기
3. Claude가 Python 코드 Artifact를 생성하고 실행
4. **"⬇ 다운로드"** 버튼으로 PPTX 파일 저장

> 만약 Claude web이 코드 실행 대신 코드만 표시하면:
> "이 코드를 실행해서 다운로드 링크를 만들어줘" 라고 추가 요청하세요.
