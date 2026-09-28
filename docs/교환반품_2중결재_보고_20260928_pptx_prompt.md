# 교환ㆍ반품 2중 결재 — 기능 기획(안) 한 장 보고서 파워포인트 생성 프롬프트

- 만든 날 : 2026-09-28 (화면에 맞춰 고침 — 아래 「고친 자리」 참고)
- 쓰는 법 : 아래 **「붙여넣을 프롬프트」 구역 전체**를 [claude.ai](https://claude.ai) 채팅창에 그대로 붙여넣습니다.
  Claude 가 코드를 돌려 `교환반품_2중결재_기획안_20260928.pptx` 를 만들고 내려받기 단추를 줍니다.
- 나오는 것 : **한 장**(16:9 · 13.33 × 7.5인치) · 맑은 고딕 · Vuexy 색

## 이야기 축

**「지금은 이렇게 되어 있지 않으니, 이렇게 만들어야 합니다」** 로 읽히는 기획(안)입니다.
한 사람 손에서 검수 확정과 전자 승인이 함께 끝나 **돈이 움직이는 건에 두 사람의 결재가
남지 않는다** 는 것이 문제이고, 그 문제를 푸는 화면과 흐름을 제안하는 꼴입니다.

**와이어프레임은 실제로 만들어진 화면과 같습니다** — 탭 일곱, 팝오버 둘, 금액 두 항목까지.
기획(안)의 말투는 그대로 두되 그리는 것은 화면 그대로여야, 보는 사람이 나중에 화면을
열었을 때 다른 것을 보지 않습니다.

## 한 장의 자리 나눔

```
┌─ 머리 (제목ㆍ부제ㆍ날짜) ────────────────────────────────────────────────┐
├─ 위 1/3 : 프로세스 플로우 ───────────────────────────────────────────────┤
│  창고 입고 검수 ▶ 검수 승인 요청 ▶ 책임자 검수 ▶ 최종승인자 서명 ▶ 실행 ▶ 완료 │
│  · 반려는 창고로 반송   · 지금의 문제 한 줄                                │
├─ 아래 2/3 ─────────────────────────────┬───────────────────────────────┤
│  화면 Layout 기획 (결재 판 와이어프레임) │  순서에 맞는 설명 ① ~ ⑦        │
│   └ 오른쪽에 서명 팝오버를 함께 그림     │                               │
└────────────────────────────────────────┴───────────────────────────────┘
```

> 글과 숫자를 고치실 때는 코드의 `# ── 값 ──` 구역만 손보면 됩니다.

---

## ▼ claude.ai 에 붙여넣을 프롬프트 시작 ▼

---

아래 Python 코드를 실행해서 파워포인트 한 장을 만들어 주세요.
`python-pptx` 를 씁니다. 코드를 그대로 실행하고 결과 파일을 내려받을 수 있게 해 주세요.
코드를 고치거나 내용을 바꾸지 말고, 그대로 실행해 주십시오.

```python
from pptx import Presentation
from pptx.util import Inches, Pt
from pptx.dml.color import RGBColor
from pptx.enum.text import PP_ALIGN

# ── 색 (Vuexy 디자인 시스템) ────────────────────────────────────────────────
C_PURPLE   = RGBColor(0x73, 0x67, 0xF0)
C_PURPLE_D = RGBColor(0x5E, 0x57, 0xC8)
C_LIGHT_P  = RGBColor(0xED, 0xEB, 0xFD)
C_WHITE    = RGBColor(0xFF, 0xFF, 0xFF)
C_DARK     = RGBColor(0x2F, 0x33, 0x49)
C_GRAY     = RGBColor(0x6E, 0x6B, 0x7B)
C_GRAY_L   = RGBColor(0x9A, 0x97, 0xA6)
C_BORDER   = RGBColor(0xDC, 0xDA, 0xE3)
C_PANEL    = RGBColor(0xF4, 0xF3, 0xF7)
C_SHADOW   = RGBColor(0xC9, 0xC6, 0xD4)
C_SUCCESS  = RGBColor(0x1F, 0xA3, 0x5B)
C_WARN     = RGBColor(0xC2, 0x76, 0x1F)
C_WARN_BG  = RGBColor(0xFE, 0xF3, 0xC7)
C_DANGER   = RGBColor(0xC0, 0x3B, 0x3C)
C_INFO     = RGBColor(0x0E, 0x7A, 0x8C)

FONT = "Malgun Gothic"
RECT = 1   # MSO_SHAPE.RECTANGLE
OVAL = 9   # MSO_SHAPE.OVAL

prs = Presentation()
prs.slide_width  = Inches(13.33)
prs.slide_height = Inches(7.5)
slide = prs.slides.add_slide(prs.slide_layouts[6])

# ── 헬퍼 ──────────────────────────────────────────────────────────────────

def shape(kind, x, y, w, h, fill=None, line=None, lw=Pt(1)):
    s = slide.shapes.add_shape(kind, Inches(x), Inches(y), Inches(w), Inches(h))
    if fill is not None:
        s.fill.solid(); s.fill.fore_color.rgb = fill
    else:
        s.fill.background()
    if line is not None:
        s.line.color.rgb = line; s.line.width = lw
    else:
        s.line.fill.background()
    s.shadow.inherit = False
    return s


def rect(x, y, w, h, fill=None, line=None, lw=Pt(1)):
    return shape(RECT, x, y, w, h, fill, line, lw)


def txt(text, x, y, w, h, size=12, bold=False, color=None,
        align=PP_ALIGN.LEFT, space=0):
    """글 한 줄 또는 여러 줄. 칸에 딱 맞추려고 여백을 0 으로 둔다."""
    color = color or C_DARK
    lines = text if isinstance(text, (list, tuple)) else [text]
    tb = slide.shapes.add_textbox(Inches(x), Inches(y), Inches(w), Inches(h))
    tf = tb.text_frame
    tf.word_wrap = True
    tf.margin_left = tf.margin_right = tf.margin_top = tf.margin_bottom = 0
    for i, line in enumerate(lines):
        p = tf.paragraphs[0] if i == 0 else tf.add_paragraph()
        p.alignment = align
        if i and space:
            p.space_before = Pt(space)
        r = p.add_run(); r.text = line
        r.font.size = Pt(size); r.font.bold = bold
        r.font.color.rgb = color; r.font.name = FONT
    return tb


def 구역표(label, x, y, w=7.0):
    txt(label, x, y, w, 0.26, size=12.5, bold=True, color=C_PURPLE_D)


def 번호(n, x, y, d=0.24):
    """와이어프레임과 오른쪽 설명을 짝짓는 번호 동그라미"""
    shape(OVAL, x, y, d, d, fill=C_PURPLE)
    txt(str(n), x, y + 0.02, d, d, size=9.5, bold=True,
        color=C_WHITE, align=PP_ALIGN.CENTER)


def 칩(text, x, y, w, 빛, h=0.20, 글=7.5):
    rect(x, y, w, h, fill=빛)
    txt(text, x, y + 0.02, w, h - 0.02, size=글, bold=True,
        color=C_WHITE, align=PP_ALIGN.CENTER)


def 칸(label, value, x, y, w, lw=0.72, h=0.22):
    """라벨 + 값이 든 입력 칸 한 벌"""
    txt(label, x, y + 0.03, lw, h, size=8, color=C_GRAY)
    rect(x + lw, y, w - lw, h, fill=C_WHITE, line=C_BORDER, lw=Pt(0.75))
    txt(value, x + lw + 0.06, y + 0.035, w - lw - 0.12, h,
        size=8, color=C_DARK if value else C_GRAY_L)


def 단추(text, x, y, w, 채움=None, h=0.24, 글=8):
    rect(x, y, w, h, fill=채움 or C_WHITE,
         line=C_BORDER if 채움 is None else 채움, lw=Pt(0.75))
    txt(text, x, y + 0.035, w, h - 0.04, size=글, bold=True,
        color=C_WHITE if 채움 else C_DARK, align=PP_ALIGN.CENTER)


def 줄(label, value, x, y, w, lw=0.90, 굵게=False, 값빛=None):
    """읽기만 하는 한 줄 (라벨 · 값)"""
    txt(label, x, y, lw, 0.18, size=8, color=C_GRAY)
    txt(value, x + lw, y, w - lw, 0.18, size=8, bold=굵게, color=값빛 or C_DARK)


# ── 값 ────────────────────────────────────────────────────────────────────
제목 = "교환ㆍ반품 2중 결재 — 기능 기획(안)"
부제 = "돈이 움직이는 건은 책임자가 보고, 최종승인자가 서명해야 실행되도록 만듭니다"
날짜 = "2026-09-28 · CE Admin"

흐름 = [
    ("창고 입고 검수",   "3PL",                  C_GRAY),
    ("검수 승인 요청",   "위드웍스 → CE Admin",  C_INFO),
    ("책임자 검수",      "책임자",               C_PURPLE),
    ("최종승인자 서명",  "최종승인자",           C_PURPLE_D),
    ("환불ㆍ차액 청구",  "토스페이먼츠",         C_SUCCESS),
    ("완료ㆍ재발송",     "Care team",            C_GRAY),
]

되돌림 = "◀ 반려하면 창고로 반송하여 재검수를 요청합니다 — 책임자ㆍ최종승인자 어느 쪽에서든"
문제   = ("지금의 문제 — 검수 확정과 전자 승인이 권한 하나로 묶여 한 사람 손에서 끝납니다. "
          "돌려줄 금액을 적는 사람과 승인하는 사람이 갈리지 않고, 창고가 청한 건과 우리가 옮긴 건도 섞입니다.")

탭들 = ["신청 내용", "환자 안내ㆍ환불", "결재", "진행 단계", "반품 품목", "발행ㆍ연계", "처리 이력"]

설명 = [
    ("「결재」 판을 새로 둡니다",
     ["탭 일곱 가운데 셋째 — 진행 단계 앞에 둡니다.",
      "단계를 옮기기 전에 결재가 끝나야 합니다"]),
    ("창고가 청한 건을 가릅니다",
     ["여태 「검수중」에 섞여 있었습니다. ［요청 내용 보기］로",
      "웹훅 원문(받은 수량 / 보낸 수량)까지 팝오버로 봅니다"]),
    ("검수 결과가 경로를 가릅니다",
     ["위드웍스가 보내면 미리 채우고,",
      "보내지 않으면 책임자가 선택합니다"]),
    ("차감 금액이 여기서 굳습니다",
     ["최종승인자가 서명할 숫자입니다.",
      "하자가 있으면 반드시 입력하게 막습니다"]),
    ("승인ㆍ반려를 함께 둡니다",
     ["승인하면 서명 걸음으로,",
      "반려하면 창고로 반송합니다"]),
    ("환불과 차액 입금을 두 항목으로 가릅니다",
     ["환불은 고객에게 지급, 차액 입금은 고객에게 청구.",
      "한 칸에 담으면 어느 쪽인지 가릴 수 없습니다"]),
    ("서명은 팝오버에서 받습니다",
     ["확인할 것과 금액을 한 자리에 모읍니다.",
      "SMS 링크(24시간ㆍ한 번만)와 담는 자리는 하나"]),
]

# ── 머리 ──────────────────────────────────────────────────────────────────
rect(0, 0, 13.33, 0.92, fill=C_PURPLE)
txt(제목, 0.45, 0.12, 9.2, 0.40, size=22, bold=True, color=C_WHITE)
txt(부제, 0.45, 0.55, 9.6, 0.28, size=11, color=C_LIGHT_P)
txt(날짜, 9.9, 0.53, 2.98, 0.28, size=10.5, color=C_LIGHT_P, align=PP_ALIGN.RIGHT)

# ══ 위 1/3 — 프로세스 플로우 ═══════════════════════════════════════════════
구역표("프로세스 플로우", 0.45, 1.02)

FW, FSTEP, FY, FH = 1.90, 2.08, 1.32, 0.76
for i, (이름, 누가, 빛) in enumerate(흐름):
    x = 0.45 + i * FSTEP
    rect(x, FY, FW, FH, fill=빛)
    txt(이름, x + 0.10, FY + 0.13, FW - 0.20, 0.26,
        size=10.5, bold=True, color=C_WHITE, align=PP_ALIGN.CENTER)
    txt(누가, x + 0.10, FY + 0.43, FW - 0.20, 0.24,
        size=8, color=C_LIGHT_P, align=PP_ALIGN.CENTER)
    if i < len(흐름) - 1:
        txt("▶", x + FW + 0.01, FY + 0.24, 0.16, 0.26,
            size=11, bold=True, color=C_GRAY_L, align=PP_ALIGN.CENTER)

# 셋째ㆍ넷째 걸음이 이 기획으로 새로 서는 자리다
칩("새로 만드는 자리", 4.61, FY - 0.26, 1.90, C_PURPLE, h=0.22, 글=8)
칩("새로 만드는 자리", 6.69, FY - 0.26, 1.90, C_PURPLE_D, h=0.22, 글=8)

txt(되돌림, 0.45, 2.16, 12.43, 0.24, size=9.5, bold=True, color=C_DANGER)
txt(문제,   0.45, 2.42, 12.43, 0.24, size=9.5, color=C_GRAY)

# ══ 아래 2/3 — 왼쪽 화면 Layout 기획 ═══════════════════════════════════════
구역표("화면 Layout 기획 — 교환/반품/취소 › 상세 › 「결재」 판", 0.45, 2.76, 8.0)

PX, PY, PW, PH = 0.45, 3.04, 7.75, 4.26
rect(PX, PY, PW, PH, fill=C_WHITE, line=C_BORDER, lw=Pt(1.25))

# 화면 머리
rect(PX, PY, PW, 0.30, fill=C_PANEL)
txt("교환/반품/취소  ›  RN20260928xxxx  ·  반품 및 환불  ·  (E)○○○",
    PX + 0.14, PY + 0.06, PW - 0.28, 0.20, size=8, color=C_GRAY)

# 가로 탭 — 「결재」가 새 탭이다 (실제 화면과 같이 일곱)
tx = PX + 0.20
for 이름 in 탭들:
    tw = 0.34 + len(이름) * 0.095
    if 이름 == "결재":
        rect(tx, PY + 0.38, tw, 0.26, fill=C_PURPLE)
        txt(이름, tx, PY + 0.42, tw, 0.20, size=8.5, bold=True,
            color=C_WHITE, align=PP_ALIGN.CENTER)
        번호(1, tx + tw - 0.08, PY + 0.29)
    else:
        txt(이름, tx, PY + 0.42, tw, 0.20, size=8.5, color=C_GRAY_L,
            align=PP_ALIGN.CENTER)
    tx += tw + 0.08

CX, CW = PX + 0.20, 4.55        # 카드는 왼쪽에 — 오른쪽은 팝오버 자리

# ── 카드 A : 창고 검수 요청 ────────────────────────────────────────────────
AY, AH = PY + 0.78, 0.56
rect(CX, AY, CW, AH, fill=C_WHITE, line=C_BORDER, lw=Pt(0.75))
txt("창고 검수 요청", CX + 0.12, AY + 0.08, 1.20, 0.20, size=9, bold=True)
칩("확인 전", CX + 1.38, AY + 0.08, 0.64, C_DANGER)
# 검수 결과 출처는 팝오버에 있다 — 카드에 두면 단추와 겹친다
txt("요청 일시 2026-09-28 20:05", CX + 0.12, AY + 0.33, 2.05, 0.18,
    size=7, color=C_GRAY)
단추("요청 내용 보기", CX + CW - 2.30, AY + 0.28, 1.10)
단추("확인했습니다",   CX + CW - 1.14, AY + 0.28, 1.02)
번호(2, CX - 0.13, AY + 0.16)

# ── 카드 B : ① 책임자 검수 ────────────────────────────────────────────────
BY, BH = AY + AH + 0.12, 1.50
rect(CX, BY, CW, BH, fill=C_WHITE, line=C_PURPLE, lw=Pt(1.1))
txt("① 책임자 검수", CX + 0.12, BY + 0.09, 1.60, 0.20, size=9, bold=True, color=C_PURPLE)
칩("대기", CX + CW - 0.66, BY + 0.09, 0.54, C_GRAY)

txt("검수 결과", CX + 0.12, BY + 0.38, 0.72, 0.18, size=8, color=C_GRAY)
txt("○ 이상 없음        ◉ 하자ㆍ수량 차이", CX + 0.84, BY + 0.38, 2.60, 0.18,
    size=8, color=C_DARK)
번호(3, CX + 3.52, BY + 0.35)

칸("수량 차이", "20",    CX + 0.12, BY + 0.64, 1.90)
칸("차감 금액", "3,000", CX + 2.14, BY + 0.64, 1.90)
번호(4, CX + 4.14, BY + 0.62)

칸("하자 내용", "포장 파손 20개 — 사용 불가", CX + 0.12, BY + 0.92, 4.30)

# 고른 값으로 무엇이 일어날지 — 승인 단추를 누르기 전에 읽는 자리
rect(CX + 0.12, BY + 1.14, 2.30, 0.32, fill=C_LIGHT_P)
txt("부분 환불 19,500원", CX + 0.18, BY + 1.175, 2.18, 0.16,
    size=7.5, bold=True, color=C_PURPLE_D)
txt("수납 22,500 − 차감 3,000", CX + 0.18, BY + 1.315, 2.18, 0.14,
    size=6.5, color=C_GRAY)

단추("책임자 승인", CX + 2.52, BY + 1.19, 1.05, C_PURPLE)
단추("반려",       CX + 3.65, BY + 1.19, 0.77)
번호(5, CX + 4.14, BY + 1.17)

# ── 카드 C : ② 최종승인자 서명 ────────────────────────────────────────────
DY, DH = BY + BH + 0.12, 1.14
rect(CX, DY, CW, DH, fill=C_WHITE, line=C_PURPLE_D, lw=Pt(1.1))
txt("② 최종승인자 서명", CX + 0.12, DY + 0.09, 1.90, 0.20, size=9, bold=True, color=C_PURPLE_D)
칩("서명 대기", CX + CW - 0.82, DY + 0.09, 0.70, C_WARN)

# 나가는 돈ㆍ들어오는 돈을 **두 항목으로** 가른다
rect(CX + 0.12, DY + 0.34, 2.10, 0.44, fill=C_LIGHT_P)
txt("환불 (고객에게 지급)", CX + 0.20, DY + 0.38, 1.94, 0.16, size=7, bold=True, color=C_GRAY)
txt("19,500원", CX + 0.20, DY + 0.54, 1.94, 0.22, size=12, bold=True, color=C_PURPLE_D)

rect(CX + 2.32, DY + 0.34, 2.10, 0.44, fill=C_PANEL, line=C_BORDER, lw=Pt(0.75))
txt("차액 입금 (고객에게 청구)", CX + 2.40, DY + 0.38, 1.94, 0.16, size=7, bold=True, color=C_GRAY)
txt("해당 없음", CX + 2.40, DY + 0.56, 1.94, 0.20, size=10, bold=True, color=C_GRAY_L)
번호(6, CX + 4.26, DY + 0.32)

단추("화면에서 서명 받기",   CX + 0.12, DY + 0.84, 1.42, C_PURPLE_D)
단추("서명 링크 문자 보내기", CX + 1.62, DY + 0.84, 1.60)
단추("반려",                CX + 3.30, DY + 0.84, 0.70)

# ── 팝오버 : ［화면에서 서명 받기］를 누르면 뜬다 ──────────────────────────
QX, QY, QW, QH = 5.42, 4.16, 2.62, 2.92
txt("［화면에서 서명 받기］를 누르면", QX, QY - 0.32, QW, 0.16, size=7.5,
    bold=True, color=C_GRAY, align=PP_ALIGN.CENTER)
txt("이 팝오버가 뜹니다", QX, QY - 0.16, QW, 0.16, size=7.5,
    bold=True, color=C_GRAY, align=PP_ALIGN.CENTER)

rect(QX + 0.05, QY + 0.05, QW, QH, fill=C_SHADOW)          # 그림자
rect(QX, QY, QW, QH, fill=C_WHITE, line=C_PURPLE_D, lw=Pt(1.25))

rect(QX, QY, QW, 0.28, fill=C_PANEL)
txt("최종승인자 서명", QX + 0.12, QY + 0.055, 1.60, 0.20, size=8.5, bold=True)
txt("✕", QX + QW - 0.34, QY + 0.055, 0.22, 0.20, size=8.5, color=C_GRAY,
    align=PP_ALIGN.CENTER)

qy = QY + 0.36
for 라벨, 값 in [("구분", "반품 및 환불"), ("주문번호", "EUD20260926…"),
                 ("고객", "(E)○○○"), ("입고 검수", "하자ㆍ수량 차이 · 20개"),
                 ("차감 금액", "3,000원"), ("책임자 승인", "○○○ 09-28 20:06")]:
    줄(라벨, 값, QX + 0.12, qy, QW - 0.24, lw=0.82,
       굵게=(라벨 == "차감 금액"), 값빛=C_DANGER if 라벨 == "차감 금액" else None)
    qy += 0.20

# 팝오버 안에도 두 항목을 나란히 — 서명하는 사람이 보는 것이 이 둘이다
rect(QX + 0.12, qy + 0.04, 1.16, 0.38, fill=C_LIGHT_P)
txt("환불 (지급)", QX + 0.16, qy + 0.07, 1.08, 0.14, size=6.5, bold=True, color=C_GRAY)
txt("19,500원", QX + 0.16, qy + 0.21, 1.08, 0.18, size=9, bold=True, color=C_PURPLE_D)

rect(QX + 1.34, qy + 0.04, 1.16, 0.38, fill=C_PANEL, line=C_BORDER, lw=Pt(0.75))
txt("차액 입금 (청구)", QX + 1.38, qy + 0.07, 1.08, 0.14, size=6.5, bold=True, color=C_GRAY)
txt("해당 없음", QX + 1.38, qy + 0.22, 1.08, 0.16, size=8, bold=True, color=C_GRAY_L)

qy += 0.46
rect(QX + 0.12, qy, QW - 0.24, 0.44, fill=C_WHITE, line=C_BORDER, lw=Pt(0.75))
txt("서명판 — 화면을 터치하거나 마우스로", QX + 0.12, qy + 0.16, QW - 0.24, 0.16,
    size=7, color=C_GRAY_L, align=PP_ALIGN.CENTER)
번호(7, QX + QW - 0.30, qy - 0.06)

단추("서명하고 승인", QX + 0.12, qy + 0.52, 1.20, C_PURPLE_D, h=0.22, 글=7.5)
단추("닫기",         QX + 1.40, qy + 0.52, 0.70, h=0.22, 글=7.5)

# ══ 아래 2/3 — 오른쪽 순서 설명 칸 ════════════════════════════════════════
EX, EW = 8.40, 4.48
구역표("순서에 맞는 설명", EX, 2.76, EW)
rect(EX, 3.04, EW, 4.26, fill=C_PANEL)

ey = 3.20
for i, (머리, 몸) in enumerate(설명, start=1):
    번호(i, EX + 0.16, ey + 0.01)
    txt(머리, EX + 0.48, ey, EW - 0.64, 0.22, size=10, bold=True, color=C_DARK)
    txt(몸,   EX + 0.48, ey + 0.23, EW - 0.64, 0.34, size=8.5, color=C_GRAY)
    ey += 0.585

prs.save("교환반품_2중결재_기획안_20260928.pptx")
print("saved: 교환반품_2중결재_기획안_20260928.pptx (1 slide)")
```

---

## ▲ 붙여넣을 프롬프트 끝 ▲

---

## 화면에 맞춰 고친 자리 (2026-09-28)

앞선 판은 오전에 만든 화면을 그렸습니다. 오후에 화면이 더 바뀌어 여섯 군데가 어긋났고,
이 판에서 모두 맞췄습니다.

| # | 앞선 판 | 이 판 (= 실제 화면) |
|---|---|---|
| 1 | 탭 여섯 | 탭 **일곱** — 반품 품목과 처리 이력 사이에 「발행ㆍ연계」 |
| 2 | 서명판이 카드 안 | **팝오버**로 뜨는 것을 함께 그림 (확인 항목 + 금액 두 칸 + 서명판) |
| 3 | 창고 카드에 ［확인했습니다］만 | ［**요청 내용 보기**］ 단추가 하나 더 |
| 4 | 금액 한 칸 | 「환불 (고객에게 지급)」ㆍ「차액 입금 (고객에게 청구)」 **두 항목** |
| 5 | 「받은 돈」ㆍ「창고가 보낸 값」ㆍ「검수를 청합니다」 | 「**수납**」ㆍ「**창고 전송**」ㆍ「창고로 **반송하여 재검수를 요청**합니다」 |
| 6 | 라디오 「하자 → 이상 없음」 | 「**이상 없음** → 하자ㆍ수량 차이」 (화면 차례) |

## 고칠 자리

코드의 `# ── 값 ──` 구역만 손보면 자리 나눔은 그대로 두고 내용이 바뀝니다.

| 이름 | 무엇 |
|---|---|
| `제목` `부제` `날짜` | 머리 글 |
| `흐름` | 위 1/3 의 프로세스 상자 — `(이름, 누가, 색)` |
| `되돌림` `문제` | 플로우 아래 두 줄. `문제` 가 「왜 만들어야 하나」를 말하는 자리입니다 |
| `탭들` | 화면의 가로 탭. 실제 화면과 같아야 합니다 |
| `설명` | 오른쪽 칸 — `(머리글, [몸글 1, 몸글 2])` |

### 조심할 것

- **`탭들` 은 일곱이 한계입니다.** 더 늘리면 오른쪽으로 넘칩니다. 너비는 글자 수로
  잡으므로(`0.34 + len × 0.095`) 긴 이름을 넣을 때도 넘칠 수 있습니다.
- **`흐름` 은 여섯으로 고정입니다.** 개수를 바꾸면 `FSTEP`(2.08)ㆍ`FW`(1.90)도 함께
  고쳐야 합니다. 「새로 만드는 자리」 칩은 셋째ㆍ넷째 자리(`4.61`ㆍ`6.69`)에 못박혀 있습니다.
- **`설명` 은 일곱 항목ㆍ몸글 두 줄이 한계입니다.** 줄 간격은 `ey += 0.585` 한 곳에서 잡습니다.
- **와이어프레임의 번호 ①~⑦ 은 오른쪽 설명 차례와 짝입니다.** ⑥⑦ 은 팝오버 안에 있습니다.
- **카드 셋의 높이는 꽉 차 있습니다.** `AH`(0.56)ㆍ`BH`(1.50)ㆍ`DH`(1.14) 와 틈 0.12 를
  더하면 바깥 테(`PH` 4.26)를 채웁니다. 하나를 키우려면 다른 하나를 줄이십시오.
- 색 상수 이름(`C_PURPLE` 등)을 그대로 쓰십시오. 문자열 `"#7367F0"` 를 넣으면 **파일이 깨집니다**.
- 글꼴은 맑은 고딕입니다. 바꾸면 글자 폭이 달라져 와이어프레임 안의 글이 칸을 넘칠 수 있습니다.
