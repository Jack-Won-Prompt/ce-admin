// lib/screens/prescription_list_screen.dart

import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../models/prescription.dart';
import '../providers/prescription_provider.dart';
import '../services/prescription_service.dart';
import '../theme/app_theme.dart';
import '../widgets/common_widgets.dart';

class PrescriptionListScreen extends ConsumerStatefulWidget {
  const PrescriptionListScreen({super.key});

  @override
  ConsumerState<PrescriptionListScreen> createState() =>
      _PrescriptionListScreenState();
}

class _PrescriptionListScreenState
    extends ConsumerState<PrescriptionListScreen> {
  final _scrollCtrl = ScrollController();
  String _statusFilter = '';

  static const _statusOptions = [
    // 올리면 검수 필요 → 담당자가 다 적으면 검수 요청 → 검수자가 보면 검수 완료.
    // OCR 처리중ㆍOCR 완료는 더 만들지 않아 고르는 자리에서도 걷었다.
    ('',                 '전체'),
    ('review_needed',    '검수 필요'),
    ('review_requested', '검수 요청'),
    // 다시 올려 달라고 해 둔 건 — 걸러 볼 자리가 없었다 (2026-09-26 지시)
    ('review_hold',      '검수 보류'),
    ('approved',         '검수 완료'),
    ('rejected',       '반려'),
    ('ordered',        '주문 완료'),
  ];

  final _nameCtrl     = TextEditingController();
  final _dateFromCtrl = TextEditingController();
  final _dateToCtrl   = TextEditingController();
  DateTimeRange? _selectedRange;
  Timer? _nameDebounce;

  @override
  void initState() {
    super.initState();

    /* 기본 기간은 오늘 하루다(PrescriptionListNotifier). 입력칸도 같은 날을 보여
       주어야 한다 — 목록은 오늘 것만 있는데 칸이 비어 있으면 전체를 본다고 읽는다. */
    _applyRangeToFields(DateTime.now(), DateTime.now());

    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(prescriptionListProvider.notifier).load(refresh: true);
    });
    _scrollCtrl.addListener(_onScroll);
  }

  /// 지금 고른 기간이 기본값(오늘 하루)인가. 그렇다면 되돌릴 것이 없다.
  bool get _isDefaultRange {
    final today = DateFormat('yyyy-MM-dd').format(DateTime.now());
    return _dateFromCtrl.text == today && _dateToCtrl.text == today;
  }

  /// 고른 기간을 화면 입력칸에 옮겨 적는다.
  void _applyRangeToFields(DateTime start, DateTime end) {
    final f = DateFormat('yyyy-MM-dd');
    _selectedRange     = DateTimeRange(start: start, end: end);
    _dateFromCtrl.text = f.format(start);
    _dateToCtrl.text   = f.format(end);
  }

  void _onScroll() {
    if (_scrollCtrl.position.pixels >=
        _scrollCtrl.position.maxScrollExtent - 200) {
      ref.read(prescriptionListProvider.notifier).load();
    }
  }

  void _onNameChanged(String value) {
    _nameDebounce?.cancel();
    _nameDebounce = Timer(const Duration(milliseconds: 400), () {
      ref.read(prescriptionListProvider.notifier).setNameFilter(value.trim());
    });
  }

  // showDateRangePicker는 모바일 폭에서 항상 전체화면으로 뜬다(Material 스펙, 강제
  // 모달화 옵션 없음) — 대신 showDatePicker(작은 모달 다이얼로그)를 시작일 → 종료일
  // 순서로 두 번 띄운다. builder로 앱 색·모서리에 맞춰 다시 칠한다.
  Widget _themedDatePicker(BuildContext context, Widget? child) {
    final base = Theme.of(context);
    return Theme(
      data: base.copyWith(
        colorScheme: base.colorScheme.copyWith(
          primary:   AppTheme.primary,
          onPrimary: Colors.white,
          surface:   AppTheme.surface,
          onSurface: AppTheme.textPrimary,
        ),
        datePickerTheme: DatePickerThemeData(
          backgroundColor:      AppTheme.surface,
          shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(20)),
          headerBackgroundColor: AppTheme.primary,
          headerForegroundColor: Colors.white,
          headerHeadlineStyle: const TextStyle(
              fontWeight: FontWeight.w800, fontSize: 22),
          weekdayStyle: const TextStyle(
              color: AppTheme.textMuted,
              fontWeight: FontWeight.w600,
              fontSize: 12),
          todayBorder: const BorderSide(color: AppTheme.primary, width: 1.5),
          todayForegroundColor:
              const WidgetStatePropertyAll(AppTheme.primary),
          dayForegroundColor: WidgetStateProperty.resolveWith((states) =>
              states.contains(WidgetState.selected)
                  ? Colors.white
                  : AppTheme.textPrimary),
          dayBackgroundColor: WidgetStateProperty.resolveWith((states) =>
              states.contains(WidgetState.selected)
                  ? AppTheme.primary
                  : null),
          dayOverlayColor: WidgetStatePropertyAll(
              AppTheme.primary.withOpacity(0.08)),
          rangePickerBackgroundColor: AppTheme.surface,
          cancelButtonStyle: TextButton.styleFrom(
              foregroundColor: AppTheme.textMuted),
          confirmButtonStyle: TextButton.styleFrom(
              foregroundColor: AppTheme.primary),
        ),
      ),
      child: child!,
    );
  }

  Future<void> _pickDateRange() async {
    final now = DateTime.now();
    final start = await showDatePicker(
      context: context,
      initialDate: _selectedRange?.start ?? now,
      firstDate: DateTime(now.year - 2),
      lastDate: now,
      helpText: '시작 날짜',
      builder: _themedDatePicker,
    );
    if (start == null || !mounted) return;

    final end = await showDatePicker(
      context: context,
      initialDate: _selectedRange?.end ?? start,
      firstDate: start,
      lastDate: now,
      helpText: '종료 날짜',
      builder: _themedDatePicker,
    );
    if (end == null || !mounted) return;

    setState(() => _applyRangeToFields(start, end));
    ref.read(prescriptionListProvider.notifier).setDateRange(
          _dateFromCtrl.text,
          _dateToCtrl.text,
        );
  }

  /// 기간을 기본값(오늘 하루)으로 되돌린다. 비우지 않는다 — 비워 두면 전체가
  /// 나와, 기본 기간을 둔 뜻이 사라진다.
  void _clearDateRange() {
    setState(() => _applyRangeToFields(DateTime.now(), DateTime.now()));
    ref.read(prescriptionListProvider.notifier).setDateRange(null, null);
  }

  @override
  void dispose() {
    _scrollCtrl.dispose();
    _nameCtrl.dispose();
    _dateFromCtrl.dispose();
    _dateToCtrl.dispose();
    _nameDebounce?.cancel();
    super.dispose();
  }

  /// 이름ㆍ생년월일로 다른 사람이 올린 건을 찾는다 (2026-09-23 지시).
  ///
  /// 둘 다 맞아야 나온다 — 이름만으로는 서버가 내주지 않는다. 환자를 둘러보는
  /// 자리가 아니라, 아는 사람의 건을 확인하러 오는 자리다.
  Future<void> _openLookup() async {
    final nameCtrl  = TextEditingController(text: _nameCtrl.text.trim());
    final birthCtrl = TextEditingController();

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (sheetCtx) => Padding(
        padding: EdgeInsets.only(
            bottom: MediaQuery.of(sheetCtx).viewInsets.bottom),
        child: _LookupSheet(
          nameCtrl:  nameCtrl,
          birthCtrl: birthCtrl,
          onSearch:  (name, birth) =>
              ref.read(prescriptionServiceProvider).lookup(name, birth),
          onPick: (rx) {
            Navigator.of(sheetCtx).pop();
            context.push('/prescriptions/$rx');
          },
        ),
      ),
    );

    nameCtrl.dispose();
    birthCtrl.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(prescriptionListProvider);

    return Scaffold(
      backgroundColor: AppTheme.background,
      body: RefreshIndicator(
        onRefresh: () =>
            ref.read(prescriptionListProvider.notifier).load(refresh: true),
        color: AppTheme.primary,
        child: CustomScrollView(
          controller: _scrollCtrl,
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            // ── Dark gradient header ──────────────────────────────────
            SliverToBoxAdapter(
              child: Container(
                decoration: const BoxDecoration(gradient: AppTheme.darkGradient),
                child: SafeArea(
                  bottom: false,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Padding(
                        padding: const EdgeInsets.fromLTRB(24, 20, 24, 16),
                        child: Row(
                          children: [
                            Container(
                              width: 44,
                              height: 44,
                              decoration: BoxDecoration(
                                gradient: AppTheme.primaryGradient,
                                borderRadius: BorderRadius.circular(14),
                                border: Border.all(
                                    color: Colors.white.withOpacity(0.3),
                                    width: 2),
                              ),
                              child: const Icon(Icons.description_rounded,
                                  color: Colors.white, size: 22),
                            ),
                            const SizedBox(width: 14),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  '내 처방전',
                                  style: TextStyle(
                                      color: Colors.white,
                                      fontSize: 22,
                                      fontWeight: FontWeight.w800,
                                      letterSpacing: -0.3),
                                ),
                                Text(
                                  state.total > 0
                                      ? '본인이 등록한 처방전 ${state.total}건'
                                      : '본인이 등록한 처방전',
                                  style: TextStyle(
                                      color: Colors.white.withOpacity(0.6),
                                      fontSize: 12),
                                ),
                              ],
                            ),
                            const Spacer(),
                            const UserNameBadge(),
                          ],
                        ),
                      ),

                      // Filter chips
                      Container(
                        decoration: const BoxDecoration(
                          color: Colors.white,
                          borderRadius:
                              BorderRadius.vertical(top: Radius.circular(24)),
                        ),
                        child: SingleChildScrollView(
                          scrollDirection: Axis.horizontal,
                          padding: const EdgeInsets.fromLTRB(16, 14, 16, 12),
                          child: Row(
                            children: _statusOptions.map((opt) {
                              final selected = _statusFilter == opt.$1;
                              return Padding(
                                padding: const EdgeInsets.only(right: 8),
                                child: GestureDetector(
                                  onTap: () {
                                    setState(
                                        () => _statusFilter = opt.$1);
                                    ref
                                        .read(prescriptionListProvider
                                            .notifier)
                                        .setStatusFilter(opt.$1);
                                  },
                                  child: AnimatedContainer(
                                    duration:
                                        const Duration(milliseconds: 180),
                                    padding: const EdgeInsets.symmetric(
                                        horizontal: 14, vertical: 7),
                                    decoration: BoxDecoration(
                                      gradient: selected
                                          ? AppTheme.primaryGradient
                                          : null,
                                      color: selected
                                          ? null
                                          : Colors.grey.shade100,
                                      borderRadius:
                                          BorderRadius.circular(20),
                                      boxShadow: selected
                                          ? [
                                              BoxShadow(
                                                color: AppTheme.primary
                                                    .withOpacity(0.3),
                                                blurRadius: 8,
                                                offset: const Offset(0, 3),
                                              )
                                            ]
                                          : null,
                                    ),
                                    child: Text(
                                      opt.$2,
                                      style: TextStyle(
                                        fontSize: 12,
                                        fontWeight: selected
                                            ? FontWeight.w700
                                            : FontWeight.w500,
                                        color: selected
                                            ? Colors.white
                                            : AppTheme.textSecondary,
                                      ),
                                    ),
                                  ),
                                ),
                              );
                            }).toList(),
                          ),
                        ),
                      ),

                      // Name filter
                      Container(
                        color: Colors.white,
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
                        child: Row(
                          children: [
                            Expanded(
                              child: _FilterField(
                                hint: '이름',
                                controller: _nameCtrl,
                                icon: Icons.person_search_outlined,
                                onChanged: _onNameChanged,
                              ),
                            ),
                            const SizedBox(width: 8),
                            /* 이 목록은 내가 올린 것만 보인다. 다른 사람이 올린 건에
                               서류를 보태려면 이름ㆍ생년월일로 찾아 들어간다
                               (2026-09-23 지시). */
                            SizedBox(
                              height: 44,
                              child: OutlinedButton.icon(
                                onPressed: _openLookup,
                                icon: const Icon(Icons.search, size: 18),
                                label: const Text('처방전 조회',
                                    style: TextStyle(fontSize: 13)),
                                style: OutlinedButton.styleFrom(
                                  foregroundColor: AppTheme.primary,
                                  side: const BorderSide(
                                      color: AppTheme.primary, width: 1.2),
                                  shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(12)),
                                  padding:
                                      const EdgeInsets.symmetric(horizontal: 12),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),

                      // Upload date range filter
                      Container(
                        color: Colors.white,
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
                        child: Row(
                          children: [
                            Expanded(
                              child: _FilterField(
                                hint: '시작 날짜',
                                controller: _dateFromCtrl,
                                icon: Icons.event_outlined,
                                readOnly: true,
                                onTap: _pickDateRange,
                              ),
                            ),
                            const Padding(
                              padding: EdgeInsets.symmetric(horizontal: 6),
                              child: Text('~',
                                  style: TextStyle(color: AppTheme.textMuted)),
                            ),
                            Expanded(
                              child: _FilterField(
                                hint: '종료 날짜',
                                controller: _dateToCtrl,
                                icon: Icons.event_outlined,
                                readOnly: true,
                                onTap: _pickDateRange,
                                // 오늘로 돌아갈 것이 있을 때만 되돌리기를 보인다
                                onClear:
                                    _isDefaultRange ? null : _clearDateRange,
                              ),
                            ),
                            const SizedBox(width: 8),
                            GestureDetector(
                              onTap: _pickDateRange,
                              child: Container(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 12, vertical: 11),
                                decoration: BoxDecoration(
                                  color: AppTheme.primary.withOpacity(0.08),
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: const Icon(Icons.calendar_month_rounded,
                                    size: 18, color: AppTheme.primary),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),

            // ── Body ──────────────────────────────────────────────────
            if (state.isLoading && state.items.isEmpty)
              const SliverFillRemaining(child: LoadingWidget())
            else if (state.error != null && state.items.isEmpty)
              SliverFillRemaining(
                child: Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Container(
                        width: 64,
                        height: 64,
                        decoration: BoxDecoration(
                          color: AppTheme.danger.withOpacity(0.08),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: const Icon(Icons.error_outline_rounded,
                            color: AppTheme.danger, size: 28),
                      ),
                      const SizedBox(height: 12),
                      const Text('데이터를 불러오지 못했습니다.',
                          style: TextStyle(
                              color: AppTheme.textMuted,
                              fontWeight: FontWeight.w500)),
                      if (state.error != null)
                        Padding(
                          padding: const EdgeInsets.fromLTRB(24, 6, 24, 0),
                          child: Text(
                            state.error!,
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                                fontSize: 11, color: AppTheme.danger),
                          ),
                        ),
                      const SizedBox(height: 16),
                      GestureDetector(
                        onTap: () => ref
                            .read(prescriptionListProvider.notifier)
                            .load(refresh: true),
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 20, vertical: 10),
                          decoration: BoxDecoration(
                            gradient: AppTheme.primaryGradient,
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: const Text('다시 시도',
                              style: TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w700)),
                        ),
                      ),
                    ],
                  ),
                ),
              )
            else if (state.items.isEmpty)
              const SliverFillRemaining(
                child: EmptyWidget(
                  message: '등록한 처방전이 없습니다.',
                  icon: Icons.description_outlined,
                ),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
                sliver: SliverList(
                  delegate: SliverChildBuilderDelegate(
                    (ctx, i) {
                      if (i == state.items.length) {
                        return const Padding(
                          padding: EdgeInsets.all(16),
                          child: Center(
                              child: CircularProgressIndicator(
                                  color: AppTheme.primary)),
                        );
                      }
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _PrescriptionCard(
                          prescription: state.items[i],
                          onTap: () => context.push(
                              '/prescriptions/${state.items[i].rxNumber}'),
                        ),
                      );
                    },
                    childCount:
                        state.items.length + (state.hasMore ? 1 : 0),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

// ── Prescription Card ─────────────────────────────────────────────────────────
class _PrescriptionCard extends StatelessWidget {
  final Prescription prescription;
  final VoidCallback onTap;

  const _PrescriptionCard({
    required this.prescription,
    required this.onTap,
  });

  static const _statusColors = {
    'pending':        Color(0xFF9E9E9E),
    'ocr_processing': AppTheme.warning,
    'ocr_done':       AppTheme.secondary,
    'review_needed':  AppTheme.danger,
    /* 빠져 있던 셋 — 회색 빈 동그라미로 떨어져 「다시 올려 달라」는 건과 아직 손대지
       않은 건이 한 모양으로 보였다 (2026-09-26 지시) */
    'review_requested': AppTheme.warning,
    'review_hold':      Color(0xFFEF6C00),
    'review_resent':    AppTheme.warning,
    'approved':       AppTheme.success,
    'rejected':       Color(0xFFB71C1C),
    'ordered':        AppTheme.primary,
  };

  static const _statusIcons = {
    'pending':        Icons.hourglass_empty_rounded,
    'ocr_processing': Icons.auto_fix_high_rounded,
    'ocr_done':       Icons.check_circle_outline_rounded,
    'review_needed':  Icons.warning_amber_rounded,
    'review_requested': Icons.schedule_rounded,
    'review_hold':      Icons.upload_rounded,
    'review_resent':    Icons.schedule_rounded,
    'approved':       Icons.verified_rounded,
    'rejected':       Icons.cancel_outlined,
    'ordered':        Icons.shopping_bag_outlined,
  };

  @override
  Widget build(BuildContext context) {
    final color = _statusColors[prescription.status] ?? Colors.grey;
    final icon  = _statusIcons[prescription.status] ?? Icons.circle_outlined;

    return GestureDetector(
      onTap: onTap,
      child: Container(
        decoration: AppTheme.cardDecoration(radius: 16),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header
              Row(
                children: [
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: color.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(icon, size: 20, color: color),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      prescription.rxNumber,
                      style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 14,
                          color: AppTheme.textPrimary),
                    ),
                  ),
                  /* 다시 올려 달라는 것이 남아 있으면 목록에서 바로 보인다
                     (2026-09-26 지시). 상태 배지만으로는 「검수 보류」라 적힐 뿐
                     몇 건을 다시 올려야 하는지 알 수 없었다. */
                  if (prescription.reuploadOpen > 0) ...[
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 9, vertical: 4),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFFF3E0),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: const Color(0xFFFFCC80)),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.upload_rounded,
                              size: 13, color: Color(0xFFE65100)),
                          const SizedBox(width: 3),
                          Text(
                            '다시 올릴 서류 ${prescription.reuploadOpen}건',
                            style: const TextStyle(
                              fontSize: 11,
                              color: Color(0xFFE65100),
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 6),
                  ],
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: color.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: color.withOpacity(0.3)),
                    ),
                    child: Text(
                      prescription.statusLabel,
                      style: TextStyle(
                        fontSize: 11,
                        color: color,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),

              // Info row
              Row(
                children: [
                  _InfoChip(
                    icon: Icons.person_outline,
                    text: prescription.patientName ?? '-',
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _InfoChip(
                      icon: Icons.local_hospital_outlined,
                      text: prescription.hospital ?? '-',
                    ),
                  ),
                ],
              ),

              if (prescription.diseaseName != null) ...[
                const SizedBox(height: 6),
                _InfoChip(
                  icon: Icons.medical_information_outlined,
                  text: prescription.diseaseName!,
                ),
              ],

              const SizedBox(height: 10),
              const Divider(height: 1, color: AppTheme.border),
              const SizedBox(height: 10),

              // Footer
              Row(
                children: [
                  if (prescription.issuedDate != null) ...[
                    const Icon(Icons.calendar_today_outlined,
                        size: 11, color: AppTheme.textMuted),
                    const SizedBox(width: 3),
                    Text(
                      '발급 ${prescription.issuedDate}',
                      style: const TextStyle(
                          fontSize: 11, color: AppTheme.textMuted),
                    ),
                    const SizedBox(width: 10),
                  ],
                  const Spacer(),
                  Text(
                    prescription.createdAt,
                    style: const TextStyle(
                        fontSize: 11, color: AppTheme.textMuted),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ── Name / date filter field ────────────────────────────────────────────────
class _FilterField extends StatelessWidget {
  final String hint;
  final TextEditingController controller;
  final IconData icon;
  final bool readOnly;
  final VoidCallback? onTap;
  final VoidCallback? onClear;
  final ValueChanged<String>? onChanged;

  const _FilterField({
    required this.hint,
    required this.controller,
    required this.icon,
    this.readOnly = false,
    this.onTap,
    this.onClear,
    this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      readOnly: readOnly,
      onTap: onTap,
      onChanged: onChanged,
      style: const TextStyle(fontSize: 13, color: AppTheme.textPrimary),
      decoration: InputDecoration(
        isDense: true,
        hintText: hint,
        hintStyle: const TextStyle(fontSize: 13, color: AppTheme.textMuted),
        prefixIcon: Icon(icon, size: 17, color: AppTheme.textMuted),
        prefixIconConstraints: const BoxConstraints(minWidth: 34),
        suffixIcon: onClear != null
            ? GestureDetector(
                onTap: onClear,
                child: const Icon(Icons.close_rounded,
                    size: 16, color: AppTheme.textMuted),
              )
            : null,
        // prefixIcon과 같은 크기로 고정 — 안 그러면 suffixIcon이 나타날 때(clear
        // 버튼 등장) 기본 최소 높이(48)가 적용돼 필드 전체 높이가 갑자기 커진다.
        suffixIconConstraints: const BoxConstraints(minWidth: 34, minHeight: 34),
        filled: true,
        fillColor: AppTheme.background,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: AppTheme.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: AppTheme.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: AppTheme.primary, width: 1.5),
        ),
      ),
    );
  }
}

class _InfoChip extends StatelessWidget {
  final IconData icon;
  final String text;

  const _InfoChip({required this.icon, required this.text});

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, size: 13, color: AppTheme.textMuted),
      const SizedBox(width: 4),
      Flexible(
        child: Text(
          text,
          style: const TextStyle(
              fontSize: 13, color: AppTheme.textSecondary),
          overflow: TextOverflow.ellipsis,
        ),
      ),
    ],
  );
}


/// 이름ㆍ생년월일로 건을 찾는 자리 (2026-09-23 지시).
///
/// 찾은 건에는 누가 올렸는지 적어 둔다 — 내 것이 아니면 그 자리에서 알아야
/// 「왜 지우기가 없지」로 헤매지 않는다.
class _LookupSheet extends StatefulWidget {
  final TextEditingController nameCtrl;
  final TextEditingController birthCtrl;
  final Future<List<Prescription>> Function(String name, String birth) onSearch;
  final void Function(String rxNumber) onPick;

  const _LookupSheet({
    required this.nameCtrl,
    required this.birthCtrl,
    required this.onSearch,
    required this.onPick,
  });

  @override
  State<_LookupSheet> createState() => _LookupSheetState();
}

class _LookupSheetState extends State<_LookupSheet> {
  bool _busy = false;
  String? _error;
  List<Prescription>? _result;

  Future<void> _pickBirth() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: DateTime(now.year - 40),
      firstDate: DateTime(1900),
      lastDate: now,
      helpText: '생년월일 선택',
    );

    if (picked != null) {
      widget.birthCtrl.text = DateFormat('yyyy-MM-dd').format(picked);
      setState(() {});
    }
  }

  Future<void> _search() async {
    final name  = widget.nameCtrl.text.trim();
    final birth = widget.birthCtrl.text.trim();

    if (name.isEmpty || birth.isEmpty) {
      setState(() => _error = '이름과 생년월일을 모두 입력해 주십시오.');
      return;
    }

    setState(() {
      _busy   = true;
      _error  = null;
      _result = null;
    });

    try {
      final list = await widget.onSearch(name, birth);
      if (mounted) {
        setState(() {
          _result = list;
          _busy   = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e.toString().replaceFirst('Exception: ', '');
          _busy  = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 14, 20, 20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: const Color(0xFFDDE1E6),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            const SizedBox(height: 14),
            const Text('처방전 조회',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
            const SizedBox(height: 6),
            const Text(
              '이름과 생년월일이 모두 일치해야 조회됩니다. '
              '다른 담당자가 등록한 처방전에도 서류를 추가할 수 있습니다.',
              style: TextStyle(fontSize: 12.5, color: AppTheme.textSecondary),
            ),
            const SizedBox(height: 14),
            TextField(
              controller: widget.nameCtrl,
              decoration: const InputDecoration(
                labelText: '환자 이름',
                prefixIcon: Icon(Icons.person_outline),
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 10),
            /* 손으로 적어도 되고 달력에서 골라도 된다 — 아는 날짜를 적는 편이
               빠른 사람이 있고, 달력이 편한 사람이 있다. */
            TextField(
              controller: widget.birthCtrl,
              keyboardType: TextInputType.datetime,
              decoration: InputDecoration(
                labelText: '생년월일',
                hintText: 'YYYY-MM-DD',
                prefixIcon: const Icon(Icons.cake_outlined),
                suffixIcon: IconButton(
                  icon: const Icon(Icons.calendar_month_outlined),
                  tooltip: '달력에서 선택',
                  onPressed: _pickBirth,
                ),
                border: const OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              height: 48,
              child: ElevatedButton.icon(
                onPressed: _busy ? null : _search,
                icon: _busy
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                            strokeWidth: 2, color: Colors.white))
                    : const Icon(Icons.search),
                label: Text(_busy ? '찾는 중' : '찾기'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.primary,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12)),
                ),
              ),
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(_error!,
                  style: const TextStyle(color: AppTheme.danger, fontSize: 13)),
            ],
            if (_result != null) ...[
              const SizedBox(height: 14),
              if (_result!.isEmpty)
                const Text(
                  '조회된 처방전이 없습니다. 이름과 생년월일을 다시 확인해 주십시오. '
                  '검수가 완료된 처방전은 조회되지 않습니다.',
                  style: TextStyle(fontSize: 13, color: AppTheme.textSecondary),
                )
              else
                ConstrainedBox(
                  constraints: BoxConstraints(
                      maxHeight: MediaQuery.of(context).size.height * 0.4),
                  child: ListView.separated(
                    shrinkWrap: true,
                    itemCount: _result!.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 8),
                    itemBuilder: (_, i) {
                      final p = _result![i];
                      final summary = [
                        if (p.patientName != null) p.patientName!,
                        if (p.birthDate != null) p.birthDate!,
                        if (p.fileCount != null) '서류 ${p.fileCount}장',
                      ].join(' · ');

                      return InkWell(
                        onTap: () => widget.onPick(p.rxNumber),
                        borderRadius: BorderRadius.circular(12),
                        child: Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            border: Border.all(color: const Color(0xFFE3E8EF)),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(p.rxNumber,
                                        style: const TextStyle(
                                            fontWeight: FontWeight.w700,
                                            fontSize: 15)),
                                  ),
                                  Text(p.statusLabel,
                                      style: const TextStyle(
                                          fontSize: 12,
                                          color: AppTheme.textSecondary)),
                                ],
                              ),
                              const SizedBox(height: 4),
                              Text(summary,
                                  style: const TextStyle(
                                      fontSize: 13,
                                      color: AppTheme.textSecondary)),
                              const SizedBox(height: 2),
                              Text(
                                p.isMine
                                    ? '본인이 등록한 처방전'
                                    : '등록자: ${p.ownerName ?? '-'}',
                                style: TextStyle(
                                  fontSize: 12,
                                  color: p.isMine
                                      ? AppTheme.primary
                                      : AppTheme.textSecondary,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),
            ],
          ],
        ),
      ),
    );
  }
}
