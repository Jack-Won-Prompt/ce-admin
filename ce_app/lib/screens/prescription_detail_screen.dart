// lib/screens/prescription_detail_screen.dart

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/prescription.dart';
import '../services/prescription_service.dart';
import '../theme/app_theme.dart';
import '../utils/constants.dart';
import '../widgets/common_widgets.dart';
import 'package:image_picker/image_picker.dart';
import 'prescription_camera_screen.dart';

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
  bool   _adding   = false;

  /* 서류를 더할 때 고르는 유형 — 업로드 화면과 같은 서버 목록이다.
     못 받아 오면 기본 넷으로 버틴다. */
  List<(String, String)> _docTypes = _fallbackDocTypes;
  static const _fallbackDocTypes = [
    ('prescription',      '처방전'),
    ('registration_form', '등록신청서'),
    ('test_result',       '결과지'),
    ('id_card',           '신분증'),
  ];

  /* 상단에 무엇을 보여 줄지. null 이면 처방전 그림이고, 값이 있으면 그 첨부다.
     목록에서 고른 것이 위에 크게 서야 무엇을 올렸는지 확인할 수 있다. */
  int? _viewingId;

  /* 그림을 내려받는 주소도 로그인을 확인한다. Image.network 는 Dio 인터셉터를
     타지 않아 토큰이 붙지 않으므로, 여기서 직접 붙인다. */
  Map<String, String> _authHeaders = const {};

  @override
  void initState() {
    super.initState();
    _loadAuthHeaders();
    _load();
    _loadDocTypes();
  }

  Future<void> _loadAuthHeaders() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString(AppConstants.keyAccessToken);
    if (token != null && token.isNotEmpty && mounted) {
      setState(() => _authHeaders = {'Authorization': 'Bearer $token'});
    }
  }

  /// 지금 상단에 보이는 그림의 주소. 고른 첨부가 지워졌으면 처방전 그림으로 돌아간다.
  String? _viewUrl(PrescriptionDetail d) {
    if (_viewingId == null) return d.imageUrl;

    final f = d.attachments.where((a) => a.id == _viewingId);
    return f.isEmpty ? d.imageUrl : f.first.url;
  }

  /// 지금 무엇을 보고 있는지. 그림만으로는 유형을 알 수 없다.
  String _viewLabel(PrescriptionDetail d) {
    if (_viewingId == null) return '처방전';

    final f = d.attachments.where((a) => a.id == _viewingId);
    return f.isEmpty ? '처방전' : f.first.docLabel;
  }

  /// 눌러서 크게 본다. 손가락으로 늘려 볼 수 있어야 글씨를 읽는다.
  void _openFullScreen(PrescriptionDetail d) {
    final url = _viewUrl(d);
    if (url == null) return;

    showDialog<void>(
      context: context,
      barrierColor: Colors.black,
      builder: (ctx) => Scaffold(
        backgroundColor: Colors.black,
        appBar: AppBar(
          backgroundColor: Colors.black,
          foregroundColor: Colors.white,
          elevation: 0,
          title: Text(_viewLabel(d), style: const TextStyle(fontSize: 15)),
        ),
        body: InteractiveViewer(
          maxScale: 6,
          child: Center(
            child: Image.network(
              url,
              headers: _authHeaders,
              fit: BoxFit.contain,
              errorBuilder: (c, _, __) => const Text('이미지를 불러올 수 없습니다.',
                  style: TextStyle(color: Colors.white70)),
            ),
          ),
        ),
      ),
    );
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

      // 지운 것을 보고 있었을 수 있다 — 처방전 그림으로 돌아간다
      setState(() => _viewingId = null);
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

  Future<void> _loadDocTypes() async {
    final list = await ref.read(prescriptionServiceProvider).getDocTypes();
    if (!mounted || list == null) return;
    setState(() => _docTypes = list);
  }

  /// 되물은 서류가 있으면 그 유형을 먼저 골라 둔다 — 대개 그것을 올리려고 연다.
  String? _suggestType(PrescriptionDetail d, List<(String, String)> types) {
    for (final r in d.requests) {
      for (final t in types) {
        if (t.$2 == r.docLabel) return t.$1;
      }
    }
    return types.isEmpty ? null : types.first.$1;
  }

  /// 이 건에 서류를 더한다 — 유형을 고르고, 찍거나 사진에서 고른다(2026-09-15 지시).
  ///
  /// 새 건을 만들지 않고 이 번호로 올린다. 검수 상태와 처방전 중복은 서버가
  /// 한 번 더 가린다. 되물은 서류를 올리면 그 요청은 서버에서 저절로 닫힌다.
  Future<void> _addDocument(PrescriptionDetail d) async {
    // 처방전은 한 건에 한 장 — 그림이 있으면 고를 수 없다(바꾸려면 먼저 지운다)
    final types = _docTypes
        .where((t) => t.$1 != 'prescription' || d.imageUrl == null)
        .toList();
    String? code = _suggestType(d, types);

    final choice = await showModalBottomSheet<(String, bool)>(
      context: context,
      showDragHandle: true,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setSheet) => SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('서류 추가',
                    style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
                const SizedBox(height: 4),
                Text('${d.rxNumber}에 더합니다. 서류 유형을 고르세요.',
                    style: const TextStyle(
                        fontSize: 12, color: AppTheme.textMuted)),
                const SizedBox(height: 14),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final t in types)
                      ChoiceChip(
                        label: Text(t.$2),
                        selected: code == t.$1,
                        onSelected: (_) => setSheet(() => code = t.$1),
                      ),
                  ],
                ),
                if (d.imageUrl != null) ...[
                  const SizedBox(height: 10),
                  const Text(
                      '처방전은 이미 있습니다. 바꾸려면 먼저 「처방전 지우기」를 누르세요.',
                      style: TextStyle(fontSize: 11, color: AppTheme.textMuted)),
                ],
                const SizedBox(height: 18),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: code == null
                            ? null
                            : () => Navigator.pop(ctx, (code!, true)),
                        icon: const Icon(Icons.photo_camera_outlined, size: 18),
                        label: const Text('카메라'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: FilledButton.icon(
                        onPressed: code == null
                            ? null
                            : () => Navigator.pop(ctx, (code!, false)),
                        icon: const Icon(Icons.photo_library_outlined, size: 18),
                        label: const Text('사진 선택'),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
    if (choice == null || !mounted) return;
    final (docType, useCamera) = choice;

    String path;
    String name;
    if (useCamera) {
      final f = await PrescriptionCameraScreen.show(context);
      if (f == null) return;
      path = f.path;
      name = f.path.split('/').last;
    } else {
      final x = await ImagePicker().pickImage(
          source: ImageSource.gallery, imageQuality: 85, maxWidth: 2048);
      if (x == null) return;
      path = x.path;
      name = x.name;
    }
    if (!mounted) return;

    setState(() => _adding = true);
    try {
      final message = await ref.read(prescriptionServiceProvider).addFile(
            d.rxNumber,
            path: path,
            fileName: name,
            docType: docType,
          );
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
      await _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))));
    } finally {
      if (mounted) setState(() => _adding = false);
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
    'review_requested': AppTheme.warning,
    // 검수자가 되물은 건과, 다시 올려 검수를 청한 건 (2026-09-15)
    'review_hold':    AppTheme.warning,
    'review_resent':  AppTheme.warning,
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
                // ── 다시 올려 달라는 요청 ─────────────────────────────
                if (d.requests.isNotEmpty) ...[
                  _RequestBanner(requests: d.requests),
                  const SizedBox(height: 12),
                ],

                // ── 보고 있는 그림 ──────────────────────────────────
                if (_viewUrl(d) != null) ...[
                  // 무엇을 보고 있는지 적는다 — 그림만으로는 유형을 알 수 없다
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8, left: 2),
                    child: Row(
                      children: [
                        const Icon(Icons.visibility_outlined,
                            size: 14, color: AppTheme.textSecondary),
                        const SizedBox(width: 5),
                        Text(_viewLabel(d),
                            style: const TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                                color: AppTheme.textSecondary)),
                      ],
                    ),
                  ),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(16),
                    child: GestureDetector(
                      // 작게는 글씨가 안 읽힌다 — 눌러서 크게 본다
                      onTap: () => _openFullScreen(d),
                      child: Image.network(
                      _viewUrl(d)!,
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
                  ),
                  /* 처방전 그림은 아래 목록에 줄이 없어 지울 자리가 여기뿐이다.
                     첨부는 저마다 목록에서 지운다. */
                  if (d.editable && _viewingId == null) _DeleteRow(
                    label: '처방전 지우기',
                    busy: _deleting,
                    onTap: () => _confirmDelete(
                      what: '처방전',
                      run: () => ref
                          .read(prescriptionServiceProvider)
                          .deleteImage(d.rxNumber),
                    ),
                  ),
                  const SizedBox(height: 12),
                ],

                // ── 올린 서류 ─────────────────────────────────────────
                if (d.attachments.isNotEmpty || d.imageUrl != null) ...[
                  _AttachmentsCard(
                    files: d.attachments,
                    headers: _authHeaders,
                    editable: d.editable,
                    busy: _deleting,
                    // 처방전 그림도 목록의 한 줄로 세운다 — 되돌아갈 길이 있어야 한다
                    hasPrescriptionImage: d.imageUrl != null,
                    viewingId: _viewingId,
                    requestFor: d.requestFor,
                    onSelect: (id) => setState(() => _viewingId = id),
                    onDelete: (f) => _confirmDelete(
                      what: f.docLabel,
                      run: () => ref
                          .read(prescriptionServiceProvider)
                          .deleteAttachment(d.rxNumber, f.id),
                    ),
                  ),
                  const SizedBox(height: 12),
                ],

                // ── 서류 추가 — 검수 완료 전까지. 새 건을 만들지 않고 이 건에 더한다
                if (d.editable) ...[
                  _AddDocButton(
                    adding: _adding,
                    disabled: _deleting,
                    onTap: () => _addDocument(d),
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

  /// 처방전 그림이 있는가. 있으면 목록 맨 위에 그 줄을 세운다 —
  /// 첨부를 보다가 처방전으로 되돌아갈 길이 있어야 한다.
  final bool hasPrescriptionImage;

  /// 지금 상단에 보이는 것. null 이면 처방전 그림이다.
  final int? viewingId;

  final void Function(int? id) onSelect;

  /// 이 서류를 물은 요청을 찾는다. null 을 넘기면 처방전 그림이다.
  final ReuploadRequest? Function(int? attachmentId) requestFor;

  const _AttachmentsCard({
    required this.files,
    required this.headers,
    required this.editable,
    required this.busy,
    required this.onDelete,
    required this.hasPrescriptionImage,
    required this.viewingId,
    required this.onSelect,
    required this.requestFor,
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
                // 처방전 그림도 한 줄로 세우므로 함께 센다
                child: Text('${files.length + (hasPrescriptionImage ? 1 : 0)}건',
                    style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                        color: AppTheme.primary)),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // 처방전 그림 줄 — 지우기는 위쪽에 있으므로 여기서는 고르기만 한다
          if (hasPrescriptionImage) ...[
            _FileTile(
              label: '처방전',
              subLabel: '처방전으로 접수된 그림',
              selected: viewingId == null,
              thumb: null,
              headers: headers,
              onTap: () => onSelect(null),
              onDelete: null,
              request: requestFor(null),
            ),
            const Divider(height: 20, color: AppTheme.border),
          ],

          for (var i = 0; i < files.length; i++) ...[
            if (i > 0) const Divider(height: 20, color: AppTheme.border),
            _FileTile(
              label: files[i].docLabel,
              subLabel: files[i].fileName,
              selected: viewingId == files[i].id,
              thumb: files[i].isPdf ? null : files[i].url,
              headers: headers,
              onTap: () => onSelect(files[i].id),
              onDelete: editable && !busy ? () => onDelete(files[i]) : null,
              request: requestFor(files[i].id),
            ),
          ],
        ],
      ),
    );
  }
}

/// 올린 서류 한 줄.
///
/// 누르면 위에서 크게 보이고, 휴지통은 지운다. 두 일을 한 줄에 두면서 서로
/// 헷갈리지 않게, 지우기는 오른쪽 끝 아이콘으로만 받는다 — 줄을 누르는 것이
/// 지우기로 이어지면 무섭다.
class _FileTile extends StatelessWidget {
  final String              label;
  final String              subLabel;
  final bool                selected;

  /// 썸네일 주소. null 이면 PDF 나 처방전 그림 줄이라 아이콘을 세운다.
  final String?             thumb;

  final Map<String, String> headers;
  final VoidCallback        onTap;
  final VoidCallback?       onDelete;

  /// 이 서류를 다시 올려 달라는 요청. 있으면 줄에 표시하고 사유ㆍ비고를 보인다.
  final ReuploadRequest?    request;

  const _FileTile({
    required this.label,
    required this.subLabel,
    required this.selected,
    required this.thumb,
    required this.headers,
    required this.onTap,
    required this.onDelete,
    this.request,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 2),
        child: Row(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(10),
                // 보고 있는 줄에 테를 둘러 어느 것을 보는지 알린다
                border: Border.all(
                  color: selected ? AppTheme.primary : Colors.transparent,
                  width: 2,
                ),
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: thumb == null
                    ? Container(
                        color: AppTheme.background,
                        child: Icon(
                          selected
                              ? Icons.description_rounded
                              : Icons.description_outlined,
                          size: 20,
                          color: selected
                              ? AppTheme.primary
                              : AppTheme.textMuted,
                        ),
                      )
                    : Image.network(
                        thumb!,
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
                  Row(
                    children: [
                      Flexible(
                        child: Text(label,
                            style: TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                                color: selected
                                    ? AppTheme.primary
                                    : AppTheme.textPrimary)),
                      ),
                      if (request != null) ...[
                        const SizedBox(width: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: AppTheme.danger.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: const Text('다시 올려 주세요',
                              style: TextStyle(
                                  fontSize: 10,
                                  fontWeight: FontWeight.w800,
                                  color: AppTheme.danger)),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 2),
                  Text(subLabel,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                          fontSize: 11, color: AppTheme.textMuted)),
                  if (request != null) ...[
                    const SizedBox(height: 4),
                    _RequestText(request: request!),
                  ],
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
        ),
      ),
    );
  }
}

/// 검수자가 다시 올려 달라고 한 것을 모아 보인다. 서류 줄마다에도 표시하지만,
/// 이미 지운 서류를 물은 것은 줄이 없으므로 여기서 한 번 더 보인다.
class _RequestBanner extends StatelessWidget {
  final List<ReuploadRequest> requests;
  const _RequestBanner({required this.requests});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppTheme.danger.withOpacity(0.06),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppTheme.danger.withOpacity(0.3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.upload_file_rounded,
                  size: 17, color: AppTheme.danger),
              const SizedBox(width: 6),
              Text('다시 올려 달라는 요청 ${requests.length}건',
                  style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: AppTheme.danger)),
            ],
          ),
          for (final r in requests)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: _RequestText(request: r, showLabel: true),
            ),
          const SizedBox(height: 10),
          const Text(
              '아래 「서류 추가」로 해당 서류를 다시 올리면 요청이 닫힙니다. 잘못 올린 서류는 🗑로 지웁니다.',
              style: TextStyle(fontSize: 11, color: AppTheme.textMuted)),
        ],
      ),
    );
  }
}

/// 요청 한 건 — 무엇을, 왜, 누가 언제.
class _RequestText extends StatelessWidget {
  final ReuploadRequest request;
  final bool showLabel;
  const _RequestText({required this.request, this.showLabel = false});

  @override
  Widget build(BuildContext context) {
    final r = request;
    final who = [r.requestedBy, r.requestedAt].whereType<String>().join(' · ');
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(showLabel ? '${r.docLabel} — ${r.reason}' : r.reason,
            style: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w700,
                color: AppTheme.danger)),
        if (r.memo != null && r.memo!.isNotEmpty)
          Text('비고: ${r.memo}',
              style: const TextStyle(
                  fontSize: 12, color: AppTheme.textPrimary)),
        if (who.isNotEmpty)
          Text(who,
              style: const TextStyle(fontSize: 11, color: AppTheme.textMuted)),
      ],
    );
  }
}

/// 서류 추가 단추 — 이 건에 더한다. 업로드 탭에서 올리면 새 처방전이 된다.
class _AddDocButton extends StatelessWidget {
  final bool         adding;
  final bool         disabled;
  final VoidCallback onTap;

  const _AddDocButton({
    required this.adding,
    required this.disabled,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: OutlinedButton.icon(
        onPressed: adding || disabled ? null : onTap,
        icon: adding
            ? const SizedBox(
                width: 16,
                height: 16,
                child: CircularProgressIndicator(strokeWidth: 2))
            : const Icon(Icons.add_a_photo_outlined, size: 18),
        label: Text(adding ? '올리는 중…' : '서류 추가 (카메라 · 사진 선택)',
            style: const TextStyle(fontWeight: FontWeight.w700)),
        style: OutlinedButton.styleFrom(
          foregroundColor: AppTheme.primary,
          side: const BorderSide(color: AppTheme.primary),
          padding: const EdgeInsets.symmetric(vertical: 14),
          shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14)),
        ),
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
