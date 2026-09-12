// lib/screens/prescription_detail_screen.dart

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/prescription.dart';
import '../services/prescription_service.dart';
import '../theme/app_theme.dart';
import '../utils/constants.dart';
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
  bool   _deleting = false;

  /* 그림을 내려받는 주소도 로그인을 확인한다. Image.network 는 Dio 인터셉터를
     타지 않아 토큰이 붙지 않으므로, 여기서 직접 붙인다. */
  Map<String, String> _authHeaders = const {};

  @override
  void initState() {
    super.initState();
    _loadAuthHeaders();
    _load();
  }

  Future<void> _loadAuthHeaders() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString(AppConstants.keyAccessToken);
    if (token != null && token.isNotEmpty && mounted) {
      setState(() => _authHeaders = {'Authorization': 'Bearer $token'});
    }
  }

  /// 지우기 전에 한 번 묻는다 — 되돌릴 수 없다.
  Future<void> _confirmDelete({
    required String what,
    required Future<String> Function() run,
  }) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('지우시겠습니까?',
            style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17)),
        content: Text('$what을(를) 지웁니다.\n지운 자료는 되돌릴 수 없고, 다시 올려야 합니다.',
            style: const TextStyle(fontSize: 14, height: 1.6)),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('취소')),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('지우기',
                style: TextStyle(
                    color: AppTheme.danger, fontWeight: FontWeight.w800)),
          ),
        ],
      ),
    );

    if (ok != true || !mounted) return;

    setState(() => _deleting = true);
    try {
      final message = await run();
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
      await _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))));
    } finally {
      if (mounted) setState(() => _deleting = false);
    }
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
                // Image
                if (d.imageUrl != null) ...[
                  ClipRRect(
                    borderRadius: BorderRadius.circular(16),
                    child: Image.network(
                      d.imageUrl!,
                      headers: _authHeaders,
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
                  // 잘못 올렸으면 올린 사람이 스스로 지우고 다시 올린다
                  if (d.editable) _DeleteRow(
                    label: '처방전 그림 지우기',
                    busy: _deleting,
                    onTap: () => _confirmDelete(
                      what: '처방전 그림',
                      run: () => ref
                          .read(prescriptionServiceProvider)
                          .deleteImage(d.rxNumber),
                    ),
                  ),
                  const SizedBox(height: 12),
                ],

                // ── 올린 서류 ─────────────────────────────────────────
                if (d.attachments.isNotEmpty) ...[
                  _AttachmentsCard(
                    files: d.attachments,
                    headers: _authHeaders,
                    editable: d.editable,
                    busy: _deleting,
                    onDelete: (f) => _confirmDelete(
                      what: f.docLabel,
                      run: () => ref
                          .read(prescriptionServiceProvider)
                          .deleteAttachment(d.rxNumber, f.id),
                    ),
                  ),
                  const SizedBox(height: 12),
                ],

                /* 검수를 지난 건은 고칠 수 없다. 단추만 감추면 왜 없는지 알 수
                   없으므로 그 자리에 까닭을 적는다. */
                if (!d.editable &&
                    (d.imageUrl != null || d.attachments.isNotEmpty)) ...[
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: AppTheme.background,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.lock_outline,
                            size: 15, color: AppTheme.textMuted),
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            '「${d.statusLabel}」 상태에서는 자료를 고칠 수 없습니다. 담당자에게 문의하세요.',
                            style: const TextStyle(
                                fontSize: 12, color: AppTheme.textMuted),
                          ),
                        ),
                      ],
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

/// 지우기 한 줄. 눌러서 지우는 자리가 그림 바로 아래에 있어야 무엇을 지우는지
/// 헷갈리지 않는다.
class _DeleteRow extends StatelessWidget {
  final String       label;
  final bool         busy;
  final VoidCallback onTap;

  const _DeleteRow({
    required this.label,
    required this.busy,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.centerRight,
      child: TextButton.icon(
        onPressed: busy ? null : onTap,
        icon: const Icon(Icons.delete_outline, size: 17),
        label: Text(label, style: const TextStyle(fontWeight: FontWeight.w700)),
        style: TextButton.styleFrom(foregroundColor: AppTheme.danger),
      ),
    );
  }
}

/// 이 건에 올라가 있는 서류 목록.
///
/// 예전에는 앱에서 처방전 그림 한 장만 보였다. 무엇을 올렸는지 알 수 없으니
/// 잘못 올린 것을 가릴 수도 없었다.
class _AttachmentsCard extends StatelessWidget {
  final List<PrescriptionFile>     files;
  final Map<String, String>        headers;
  final bool                       editable;
  final bool                       busy;
  final void Function(PrescriptionFile) onDelete;

  const _AttachmentsCard({
    required this.files,
    required this.headers,
    required this.editable,
    required this.busy,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: AppTheme.cardDecoration(radius: 16),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.attach_file_rounded,
                  size: 16, color: AppTheme.textSecondary),
              const SizedBox(width: 6),
              const Text('올린 서류',
                  style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: AppTheme.textSecondary)),
              const SizedBox(width: 6),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(
                  color: AppTheme.primary.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text('${files.length}건',
                    style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                        color: AppTheme.primary)),
              ),
            ],
          ),
          const SizedBox(height: 12),
          for (var i = 0; i < files.length; i++) ...[
            if (i > 0) const Divider(height: 20, color: AppTheme.border),
            _AttachmentTile(
              file: files[i],
              headers: headers,
              onDelete: editable && !busy ? () => onDelete(files[i]) : null,
            ),
          ],
        ],
      ),
    );
  }
}

class _AttachmentTile extends StatelessWidget {
  final PrescriptionFile    file;
  final Map<String, String> headers;
  final VoidCallback?       onDelete;

  const _AttachmentTile({
    required this.file,
    required this.headers,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(10),
          child: SizedBox(
            width: 48,
            height: 48,
            child: file.isPdf
                ? Container(
                    color: AppTheme.background,
                    child: const Icon(Icons.picture_as_pdf_outlined,
                        size: 22, color: AppTheme.textMuted),
                  )
                : Image.network(
                    file.url,
                    headers: headers,
                    fit: BoxFit.cover,
                    errorBuilder: (ctx, _, __) => Container(
                      color: AppTheme.background,
                      child: const Icon(Icons.broken_image_outlined,
                          size: 20, color: AppTheme.textMuted),
                    ),
                  ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(file.docLabel,
                  style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: AppTheme.textPrimary)),
              const SizedBox(height: 2),
              Text(file.fileName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                      fontSize: 11, color: AppTheme.textMuted)),
            ],
          ),
        ),
        if (onDelete != null)
          IconButton(
            onPressed: onDelete,
            icon: const Icon(Icons.delete_outline,
                size: 19, color: AppTheme.danger),
            tooltip: '지우기',
          ),
      ],
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
