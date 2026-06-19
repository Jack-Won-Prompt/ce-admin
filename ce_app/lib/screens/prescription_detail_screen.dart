// lib/screens/prescription_detail_screen.dart

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/prescription.dart';
import '../services/prescription_service.dart';
import '../theme/app_theme.dart';
import '../widgets/common_widgets.dart';

class PrescriptionDetailScreen extends ConsumerStatefulWidget {
  final String rxNumber;
  const PrescriptionDetailScreen({super.key, required this.rxNumber});

  @override
  ConsumerState<PrescriptionDetailScreen> createState() =>
      _PrescriptionDetailScreenState();
}

class _PrescriptionDetailScreenState
    extends ConsumerState<PrescriptionDetailScreen> {
  PrescriptionDetail? _detail;
  bool   _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { _isLoading = true; _error = null; });
    try {
      final d = await ref
          .read(prescriptionServiceProvider)
          .getDetail(widget.rxNumber);
      setState(() { _detail = d; _isLoading = false; });
    } catch (e) {
      setState(() { _isLoading = false; _error = e.toString(); });
    }
  }

  static const _statusColors = {
    'pending':        Color(0xFF9E9E9E),
    'ocr_processing': AppTheme.warning,
    'ocr_done':       AppTheme.secondary,
    'review_needed':  AppTheme.danger,
    'approved':       AppTheme.success,
    'rejected':       Color(0xFFB71C1C),
    'ordered':        AppTheme.primary,
  };

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Scaffold(
        backgroundColor: AppTheme.background,
        body: Column(
          children: [_buildSimpleHeader(), const Expanded(child: LoadingWidget())],
        ),
      );
    }

    if (_error != null || _detail == null) {
      return Scaffold(
        backgroundColor: AppTheme.background,
        body: Column(
          children: [
            _buildSimpleHeader(),
            Expanded(
              child: Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(Icons.error_outline_rounded,
                        size: 48, color: AppTheme.danger),
                    const SizedBox(height: 8),
                    Text(_error ?? '불러오기 실패',
                        style: const TextStyle(color: AppTheme.textMuted)),
                    TextButton(onPressed: _load, child: const Text('다시 시도')),
                  ],
                ),
              ),
            ),
          ],
        ),
      );
    }

    final d = _detail!;
    final statusColor =
        _statusColors[d.status] ?? const Color(0xFF9E9E9E);

    return Scaffold(
      backgroundColor: AppTheme.background,
      body: CustomScrollView(
        slivers: [
          // ── Header ─────────────────────────────────────────────
          SliverToBoxAdapter(
            child: Container(
              decoration:
                  const BoxDecoration(gradient: AppTheme.darkGradient),
              child: SafeArea(
                bottom: false,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Padding(
                      padding: const EdgeInsets.fromLTRB(8, 8, 16, 0),
                      child: Row(
                        children: [
                          IconButton(
                            icon: const Icon(
                                Icons.arrow_back_ios_new_rounded,
                                color: Colors.white,
                                size: 20),
                            onPressed: () => Navigator.pop(context),
                          ),
                          const Spacer(),
                          const UserNameBadge(),
                          const SizedBox(width: 8),
                          GestureDetector(
                            onTap: _load,
                            child: Container(
                              width: 36,
                              height: 36,
                              decoration: BoxDecoration(
                                color: Colors.white.withOpacity(0.15),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: const Icon(Icons.refresh_rounded,
                                  color: Colors.white, size: 18),
                            ),
                          ),
                        ],
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            d.rxNumber,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 20,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 12, vertical: 5),
                            decoration: BoxDecoration(
                              color: statusColor.withOpacity(0.2),
                              borderRadius: BorderRadius.circular(20),
                              border: Border.all(
                                  color: statusColor.withOpacity(0.4)),
                            ),
                            child: Text(
                              d.statusLabel,
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w700,
                                color: statusColor,
                              ),
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

          // ── Body ───────────────────────────────────────────────
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 40),
            sliver: SliverList(
              delegate: SliverChildListDelegate([
                // OCR confidence
                if (d.ocrConfidence != null) ...[
                  _ConfidenceCard(d.ocrConfidence!),
                  const SizedBox(height: 12),
                ],

                // Image
                if (d.imageUrl != null) ...[
                  ClipRRect(
                    borderRadius: BorderRadius.circular(16),
                    child: Image.network(
                      d.imageUrl!,
                      fit: BoxFit.contain,
                      loadingBuilder: (ctx, child, progress) =>
                          progress == null
                              ? child
                              : Container(
                                  height: 200,
                                  decoration: BoxDecoration(
                                    color: AppTheme.background,
                                    borderRadius:
                                        BorderRadius.circular(16),
                                  ),
                                  child: const LoadingWidget(),
                                ),
                      errorBuilder: (ctx, _, __) => Container(
                        height: 120,
                        decoration: AppTheme.cardDecoration(radius: 16),
                        child: const Center(
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.broken_image_outlined,
                                  color: AppTheme.textMuted, size: 36),
                              SizedBox(height: 4),
                              Text('이미지를 불러올 수 없습니다.',
                                  style: TextStyle(
                                      color: AppTheme.textMuted,
                                      fontSize: 12)),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                ],

                _Section(
                  title: '환자 정보',
                  icon: Icons.person_outlined,
                  gradient: AppTheme.primaryGradient,
                  rows: [
                    ('성명', d.ocr.patientName),
                    ('주민번호', d.ocr.residentNo),
                    ('전화', d.ocr.phone ?? d.ocr.mobile),
                    if (d.ocr.isReissue) ('재발행', '예'),
                  ],
                ),
                _Section(
                  title: '의료기관',
                  icon: Icons.local_hospital_outlined,
                  gradient: AppTheme.infoGradient,
                  rows: [
                    ('병원명', d.ocr.hospitalName),
                    ('병원 코드', d.ocr.hospitalCode),
                    ('의사', d.ocr.doctorName),
                    ('진료과', d.ocr.department ?? d.ocr.specialty),
                    ('면허번호', d.ocr.licenseNo),
                    ('전문의번호', d.ocr.specialistNo),
                  ],
                ),
                _Section(
                  title: '상병 정보',
                  icon: Icons.medical_information_outlined,
                  gradient: AppTheme.accentGradient,
                  rows: [
                    ('상병명', d.ocr.diseaseName),
                    ('상병 코드', d.ocr.diseaseCode),
                  ],
                ),
                _Section(
                  title: '처방 내용',
                  icon: Icons.receipt_long_outlined,
                  gradient: AppTheme.successGradient,
                  rows: [
                    ('처방 기간', d.ocr.usagePeriod),
                    ('1일 횟수', d.ocr.dailyCount?.toString()),
                    ('총 일수', d.ocr.totalDays?.toString()),
                    ('총 수량', d.ocr.totalCount?.toString()),
                    ('발급일', d.ocr.issuedDate),
                    ('처방전 번호', d.ocr.registrationNo),
                    ('일련번호', d.ocr.serialNo),
                  ],
                ),
              ]),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSimpleHeader() {
    return Container(
      decoration: const BoxDecoration(gradient: AppTheme.darkGradient),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(8, 8, 16, 16),
          child: Row(
            children: [
              IconButton(
                icon: const Icon(Icons.arrow_back_ios_new_rounded,
                    color: Colors.white, size: 20),
                onPressed: () => Navigator.pop(context),
              ),
              const SizedBox(width: 4),
              const Text('처방전 상세',
                  style: TextStyle(
                      color: Colors.white,
                      fontSize: 20,
                      fontWeight: FontWeight.w800)),
            ],
          ),
        ),
      ),
    );
  }
}

class _ConfidenceCard extends StatelessWidget {
  final int confidence;
  const _ConfidenceCard(this.confidence);

  @override
  Widget build(BuildContext context) {
    final color = confidence >= 85
        ? AppTheme.success
        : confidence >= 60
            ? AppTheme.warning
            : AppTheme.danger;

    return Container(
      decoration: BoxDecoration(
        color: color.withOpacity(0.08),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color.withOpacity(0.3)),
      ),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.auto_fix_high_rounded, size: 16, color: color),
              const SizedBox(width: 6),
              Text('OCR 인식률',
                  style: TextStyle(
                      fontSize: 13,
                      color: color,
                      fontWeight: FontWeight.w700)),
              const Spacer(),
              Text('$confidence%',
                  style: TextStyle(
                      fontSize: 18,
                      color: color,
                      fontWeight: FontWeight.w800)),
            ],
          ),
          const SizedBox(height: 8),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: confidence / 100,
              minHeight: 6,
              backgroundColor: color.withOpacity(0.2),
              valueColor: AlwaysStoppedAnimation<Color>(color),
            ),
          ),
          const SizedBox(height: 6),
          Text(
            confidence >= 85
                ? '정확도가 높습니다.'
                : confidence >= 60
                    ? '일부 항목을 확인해주세요.'
                    : '수동 검수가 필요합니다.',
            style: TextStyle(fontSize: 11, color: color),
          ),
        ],
      ),
    );
  }
}

class _Section extends StatelessWidget {
  final String title;
  final IconData icon;
  final LinearGradient gradient;
  final List<(String, String?)> rows;

  const _Section({
    required this.title,
    required this.icon,
    required this.gradient,
    required this.rows,
  });

  @override
  Widget build(BuildContext context) {
    final visibleRows =
        rows.where((r) => r.$2 != null && r.$2!.isNotEmpty).toList();
    if (visibleRows.isEmpty) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Container(
        decoration: AppTheme.cardDecoration(radius: 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 14, 14, 10),
              child: Row(
                children: [
                  Container(
                    width: 30,
                    height: 30,
                    decoration: BoxDecoration(
                      gradient: gradient,
                      borderRadius: BorderRadius.circular(9),
                    ),
                    child: Icon(icon, size: 15, color: Colors.white),
                  ),
                  const SizedBox(width: 10),
                  Text(
                    title,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: AppTheme.textPrimary,
                    ),
                  ),
                ],
              ),
            ),
            const Divider(height: 1, color: AppTheme.border),
            ...visibleRows.asMap().entries.map((entry) {
              final isLast = entry.key == visibleRows.length - 1;
              return Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 14, vertical: 11),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        SizedBox(
                          width: 84,
                          child: Text(
                            entry.value.$1,
                            style: const TextStyle(
                                fontSize: 13,
                                color: AppTheme.textMuted),
                          ),
                        ),
                        Expanded(
                          child: Text(
                            entry.value.$2!,
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                              color: AppTheme.textPrimary,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (!isLast)
                    const Divider(
                        height: 1,
                        indent: 14,
                        endIndent: 14,
                        color: AppTheme.border),
                ],
              );
            }),
          ],
        ),
      ),
    );
  }
}
