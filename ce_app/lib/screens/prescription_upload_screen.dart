// lib/screens/prescription_upload_screen.dart

import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import '../models/patient.dart';
import '../services/api_client.dart';
import '../services/patient_service.dart';
import '../theme/app_theme.dart';
import '../widgets/common_widgets.dart';
import 'prescription_camera_screen.dart';

class PrescriptionUploadScreen extends ConsumerStatefulWidget {
  const PrescriptionUploadScreen({super.key});

  @override
  ConsumerState<PrescriptionUploadScreen> createState() =>
      _PrescriptionUploadScreenState();
}

class _PrescriptionUploadScreenState
    extends ConsumerState<PrescriptionUploadScreen> {
  File?   _selectedFile;
  String? _selectedFileName;
  bool    _uploading      = false;
  double  _uploadProgress = 0.0;   // 0.0 ~ 1.0 파일 전송 진행률
  bool    _isSaving       = false;  // true = 올린 뒤 서버가 저장을 마치기를 기다리는 중
  String? _resultMsg;
  bool    _success   = false;
  Map<String, dynamic>? _ocrResult;
  final _memoCtrl = TextEditingController();

  // ── 환자 선택 ──────────────────────────────────────────
  final _nameCtrl        = TextEditingController();
  PatientOption? _selectedPatient;
  bool           _searchingPatient = false;

  // ── 서류 유형 ──────────────────────────────────────────
  static const _docTypes = [
    ('registration_form', '등록신청서'),
    ('prescription',      '처방전'),
    ('test_result',       '결과지'),
    ('id_card',           '신분증'),
    ('delegation',        '위임장'),
  ];
  String _docType = 'registration_form';

  Future<void> _searchPatient() async {
    final query = _nameCtrl.text.trim();
    if (query.length < 2) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('이름을 2글자 이상 입력해주세요.')),
      );
      return;
    }
    // 이름을 바꿔 다시 찾으면 이전 선택은 무효화한다
    setState(() { _searchingPatient = true; _selectedPatient = null; });
    try {
      final results = await ref.read(patientServiceProvider).search(query);
      if (!mounted) return;
      if (results.isEmpty) {
        await _showRegisterDialog(query);
      } else {
        await _showResultsSheet(results);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('검색 실패: $e')));
      }
    } finally {
      if (mounted) setState(() => _searchingPatient = false);
    }
  }

  Future<void> _showResultsSheet(List<PatientOption> results) async {
    final picked = await showModalBottomSheet<PatientOption>(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Padding(
              padding: EdgeInsets.fromLTRB(20, 18, 20, 8),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text('환자 선택',
                    style: TextStyle(
                        fontSize: 16, fontWeight: FontWeight.w800)),
              ),
            ),
            Flexible(
              child: ListView.separated(
                shrinkWrap: true,
                itemCount: results.length,
                separatorBuilder: (_, __) =>
                    const Divider(height: 1, color: AppTheme.border),
                itemBuilder: (ctx, i) {
                  final p = results[i];
                  return ListTile(
                    title: Text(p.name,
                        style: const TextStyle(fontWeight: FontWeight.w700)),
                    subtitle: Text(p.mobile,
                        style: const TextStyle(color: AppTheme.textMuted)),
                    onTap: () => Navigator.pop(ctx, p),
                  );
                },
              ),
            ),
            const SizedBox(height: 12),
          ],
        ),
      ),
    );
    if (picked != null && mounted) {
      setState(() {
        _selectedPatient = picked;
        _nameCtrl.text   = picked.name;
      });
    }
  }

  Future<void> _showRegisterDialog(String initialName) async {
    final nameCtrl     = TextEditingController(text: initialName);
    final residentCtrl = TextEditingController();
    bool  submitting   = false;

    final created = await showDialog<PatientOption>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialogState) {
          return AlertDialog(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
            title: const Text('환자 등록',
                style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17)),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('검색된 환자가 없습니다. 새로 등록할까요?',
                    style: TextStyle(fontSize: 13, color: AppTheme.textSecondary)),
                const SizedBox(height: 16),
                TextField(
                  controller: nameCtrl,
                  decoration: const InputDecoration(labelText: '이름'),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: residentCtrl,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(labelText: '주민번호 (선택)'),
                ),
              ],
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(ctx),
                child: const Text('취소'),
              ),
              TextButton(
                onPressed: submitting
                    ? null
                    : () async {
                        final name = nameCtrl.text.trim();
                        if (name.isEmpty) return;
                        setDialogState(() => submitting = true);
                        try {
                          final patient = await ref
                              .read(patientServiceProvider)
                              .create(
                                name: name,
                                residentNo: residentCtrl.text.trim(),
                              );
                          if (ctx.mounted) Navigator.pop(ctx, patient);
                        } catch (e) {
                          setDialogState(() => submitting = false);
                          if (ctx.mounted) {
                            ScaffoldMessenger.of(ctx).showSnackBar(
                              SnackBar(content: Text('등록 실패: $e')),
                            );
                          }
                        }
                      },
                child: const Text('등록'),
              ),
            ],
          );
        },
      ),
    );

    if (created != null && mounted) {
      setState(() {
        _selectedPatient = created;
        _nameCtrl.text   = created.name;
      });
    }
  }

  Future<void> _openCamera() async {
    final file = await PrescriptionCameraScreen.show(context);
    if (file == null) return;
    setState(() {
      _selectedFile     = file;
      _selectedFileName = file.path.split('/').last;
      _resultMsg        = null;
      _ocrResult        = null;
    });
  }

  Future<void> _pickImage(ImageSource source) async {
    final picked = await ImagePicker().pickImage(
        source: source, imageQuality: 85, maxWidth: 2048);
    if (picked == null) return;
    setState(() {
      _selectedFile     = File(picked.path);
      _selectedFileName = picked.name;
      _resultMsg        = null;
      _ocrResult        = null;
    });
  }

  Future<void> _pickFile() async {
    await _pickImage(ImageSource.gallery);
  }

  void _resetForm() {
    setState(() {
      _selectedFile     = null;
      _selectedFileName = null;
      _resultMsg        = null;
      _ocrResult        = null;
      _success          = false;
      _uploadProgress   = 0.0;
      _isSaving            = false;
      // 환자는 그대로 둔다 — 같은 환자 서류를 이어서 여러 건 올리는 경우가 많다
      _docType          = 'registration_form';
    });
    _memoCtrl.clear();
  }

  @override
  void dispose() {
    _memoCtrl.dispose();
    _nameCtrl.dispose();
    super.dispose();
  }

  Future<void> _upload() async {
    if (_selectedFile == null || _selectedPatient == null) return;
    setState(() {
      _uploading      = true;
      _uploadProgress = 0.0;
      _isSaving          = false;
      _resultMsg      = null;
      _ocrResult      = null;
    });

    try {
      final dio  = ref.read(dioProvider);
      final memo = _memoCtrl.text.trim();
      final form = FormData.fromMap({
        'prescription_image': await MultipartFile.fromFile(
          _selectedFile!.path,
          filename: _selectedFileName,
        ),
        'patient_id': _selectedPatient!.id,
        'doc_type':   _docType,
        if (memo.isNotEmpty) 'memo': memo,
      });

      final resp = await dio.post(
        '/prescriptions/upload',
        data: form,
        onSendProgress: (sent, total) {
          if (total > 0 && mounted) {
            setState(() {
              _uploadProgress = sent / total;
              if (_uploadProgress >= 1.0) _isSaving = true;
            });
          }
        },
      );
      final body = resp.data as Map<String, dynamic>;

      setState(() {
        _success   = true;
        _resultMsg = body['message'] as String? ?? '업로드 완료';
        _ocrResult = body['ocr_result'] as Map<String, dynamic>?;
      });
      _memoCtrl.clear();
    } on DioException catch (e) {
      final body = e.response?.data;
      setState(() {
        _success   = false;
        _resultMsg = (body is Map ? body['message'] : null) ??
            '업로드 실패: ${e.message}';
      });
    } catch (e) {
      setState(() {
        _success   = false;
        _resultMsg = '오류가 발생했습니다: $e';
      });
    } finally {
      setState(() {
        _uploading = false;
        _isSaving     = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      body: Column(
        children: [
          // ── Header ─────────────────────────────────────────────
          Container(
            decoration: const BoxDecoration(gradient: AppTheme.darkGradient),
            child: SafeArea(
              bottom: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(24, 20, 24, 28),
                child: Row(
                  children: [
                    Container(
                      width: 44,
                      height: 44,
                      decoration: BoxDecoration(
                        gradient: AppTheme.secondaryGradient,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(
                            color: Colors.white.withOpacity(0.3), width: 2),
                      ),
                      child: const Icon(Icons.upload_file_rounded,
                          color: Colors.white, size: 22),
                    ),
                    const SizedBox(width: 14),
                    const Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('처방전 업로드',
                            style: TextStyle(
                                color: Colors.white,
                                fontSize: 22,
                                fontWeight: FontWeight.w800,
                                letterSpacing: -0.3)),
                        Text('사진 또는 갤러리에서 선택',
                            style: TextStyle(
                                color: Colors.white60, fontSize: 12)),
                      ],
                    ),
                    const Spacer(),
                    const UserNameBadge(),
                  ],
                ),
              ),
            ),
          ),

          // ── Body ───────────────────────────────────────────────
          Expanded(
            child: SingleChildScrollView(
              padding:
                  const EdgeInsets.fromLTRB(16, 20, 16, 40),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // 환자 선택 + 서류 유형
                  Container(
                    decoration: AppTheme.cardDecoration(radius: 16),
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('이름',
                            style: TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                                color: AppTheme.textSecondary)),
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            Expanded(
                              child: TextField(
                                controller: _nameCtrl,
                                onChanged: (_) {
                                  if (_selectedPatient != null) {
                                    setState(() => _selectedPatient = null);
                                  }
                                },
                                style: const TextStyle(
                                    fontSize: 14, color: AppTheme.textPrimary),
                                decoration: InputDecoration(
                                  hintText: '환자 이름으로 검색',
                                  hintStyle: const TextStyle(
                                      color: AppTheme.textMuted, fontSize: 13),
                                  isDense: true,
                                  filled: true,
                                  fillColor: AppTheme.background,
                                  contentPadding: const EdgeInsets.symmetric(
                                      horizontal: 14, vertical: 12),
                                  border: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(12),
                                    borderSide:
                                        const BorderSide(color: AppTheme.border),
                                  ),
                                  enabledBorder: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(12),
                                    borderSide:
                                        const BorderSide(color: AppTheme.border),
                                  ),
                                  focusedBorder: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(12),
                                    borderSide: const BorderSide(
                                        color: AppTheme.primary, width: 2),
                                  ),
                                ),
                              ),
                            ),
                            const SizedBox(width: 8),
                            GestureDetector(
                              onTap: _searchingPatient ? null : _searchPatient,
                              child: Container(
                                height: 44,
                                padding:
                                    const EdgeInsets.symmetric(horizontal: 16),
                                decoration: BoxDecoration(
                                  gradient: AppTheme.primaryGradient,
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                alignment: Alignment.center,
                                child: _searchingPatient
                                    ? const SizedBox(
                                        width: 16,
                                        height: 16,
                                        child: CircularProgressIndicator(
                                            strokeWidth: 2, color: Colors.white),
                                      )
                                    : const Text('찾기',
                                        style: TextStyle(
                                            color: Colors.white,
                                            fontWeight: FontWeight.w700,
                                            fontSize: 13)),
                              ),
                            ),
                          ],
                        ),
                        if (_selectedPatient != null) ...[
                          const SizedBox(height: 6),
                          Row(
                            children: [
                              const Icon(Icons.check_circle_rounded,
                                  size: 14, color: AppTheme.success),
                              const SizedBox(width: 4),
                              Text('${_selectedPatient!.name} 님 선택됨',
                                  style: const TextStyle(
                                      fontSize: 12,
                                      color: AppTheme.success,
                                      fontWeight: FontWeight.w600)),
                            ],
                          ),
                        ],
                        const SizedBox(height: 16),
                        const Text('서류 유형',
                            style: TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                                color: AppTheme.textSecondary)),
                        const SizedBox(height: 8),
                        DropdownButtonFormField<String>(
                          value: _docType,
                          isExpanded: true,
                          icon: const Icon(Icons.expand_more_rounded,
                              color: AppTheme.primary),
                          borderRadius: BorderRadius.circular(16),
                          dropdownColor: AppTheme.surface,
                          elevation: 3,
                          menuMaxHeight: 320,
                          items: _docTypes.map((t) {
                            final selected = t.$1 == _docType;
                            return DropdownMenuItem(
                              value: t.$1,
                              child: Text(t.$2,
                                  style: TextStyle(
                                      fontSize: 14,
                                      fontWeight: selected
                                          ? FontWeight.w700
                                          : FontWeight.w500,
                                      color: selected
                                          ? AppTheme.primary
                                          : AppTheme.textPrimary)),
                            );
                          }).toList(),
                          onChanged: (v) {
                            if (v != null) setState(() => _docType = v);
                          },
                          decoration: InputDecoration(
                            isDense: true,
                            filled: true,
                            fillColor: AppTheme.background,
                            contentPadding: const EdgeInsets.symmetric(
                                horizontal: 14, vertical: 12),
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide:
                                  const BorderSide(color: AppTheme.border),
                            ),
                            enabledBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide:
                                  const BorderSide(color: AppTheme.border),
                            ),
                            focusedBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: const BorderSide(
                                  color: AppTheme.primary, width: 2),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),

                  // Image pick area
                  _ImagePickArea(
                    file: _selectedFile,
                    fileName: _selectedFileName,
                    onCamera: _openCamera,
                    onGallery: _pickFile,
                  ),
                  const SizedBox(height: 14),

                  // Memo
                  Container(
                    decoration: AppTheme.cardDecoration(radius: 16),
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('관리자 메모',
                            style: TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                                color: AppTheme.textSecondary)),
                        const SizedBox(height: 10),
                        TextField(
                          controller: _memoCtrl,
                          maxLines: 3,
                          maxLength: 500,
                          keyboardType: TextInputType.multiline,
                          style: const TextStyle(
                              fontSize: 14, color: AppTheme.textPrimary),
                          decoration: InputDecoration(
                            hintText:
                                '담당자에게 전달할 내용을 입력하세요\n예) 청구 관련 특이사항을 기재해주세요',
                            hintStyle: const TextStyle(
                                color: AppTheme.textMuted,
                                fontSize: 13,
                                height: 1.5),
                            alignLabelWithHint: true,
                            filled: true,
                            fillColor: AppTheme.background,
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: const BorderSide(
                                  color: AppTheme.border),
                            ),
                            enabledBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: const BorderSide(
                                  color: AppTheme.border),
                            ),
                            focusedBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: const BorderSide(
                                  color: AppTheme.primary, width: 2),
                            ),
                            contentPadding: const EdgeInsets.symmetric(
                                horizontal: 14, vertical: 12),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Upload button / progress
                  AnimatedSwitcher(
                    duration: const Duration(milliseconds: 300),
                    child: _uploading
                        ? _UploadProgressCard(
                            key: const ValueKey('progress'),
                            progress: _uploadProgress,
                            isSaving: _isSaving,
                          )
                        : GradientButton(
                            key: const ValueKey('btn'),
                            label: _success ? '추가 처방전 업로드' : '처방전 업로드',
                            icon: _success
                                ? Icons.add_photo_alternate_outlined
                                : Icons.cloud_upload_outlined,
                            onPressed: _success
                                ? _resetForm
                                : ((_selectedFile == null || _selectedPatient == null)
                                    ? null
                                    : _upload),
                            gradient: AppTheme.secondaryGradient,
                          ),
                  ),

                  // Result message
                  if (_resultMsg != null) ...[
                    const SizedBox(height: 16),
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: _success
                            ? AppTheme.success.withOpacity(0.08)
                            : AppTheme.danger.withOpacity(0.08),
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(
                          color: _success
                              ? AppTheme.success.withOpacity(0.3)
                              : AppTheme.danger.withOpacity(0.3),
                        ),
                      ),
                      child: Row(
                        children: [
                          Icon(
                            _success
                                ? Icons.check_circle_outline_rounded
                                : Icons.error_outline_rounded,
                            color: _success
                                ? AppTheme.success
                                : AppTheme.danger,
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              _resultMsg!,
                              style: TextStyle(
                                color: _success
                                    ? AppTheme.success
                                    : AppTheme.danger,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],

                  // 업로드 결과
                  if (_ocrResult != null) ...[
                    const SizedBox(height: 14),
                    _OcrResultCard(data: _ocrResult!),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ImagePickArea extends StatelessWidget {
  final File?   file;
  final String? fileName;
  final VoidCallback onCamera;
  final VoidCallback onGallery;

  const _ImagePickArea({
    required this.file,
    required this.fileName,
    required this.onCamera,
    required this.onGallery,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 220,
      decoration: BoxDecoration(
        color: file != null ? Colors.transparent : AppTheme.background,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: file != null
              ? AppTheme.primary.withOpacity(0.4)
              : AppTheme.border,
          width: file != null ? 2 : 1,
        ),
      ),
      child: file != null
          ? Stack(
              fit: StackFit.expand,
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(14),
                  child: Image.file(file!, fit: BoxFit.cover),
                ),
                Positioned(
                  bottom: 8,
                  right: 8,
                  child: GestureDetector(
                    onTap: onGallery,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(
                        color: const Color(0xCC000000),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.swap_horiz,
                              color: Colors.white, size: 16),
                          SizedBox(width: 4),
                          Text('변경',
                              style: TextStyle(
                                  color: Colors.white, fontSize: 12)),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            )
          : Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  width: 56,
                  height: 56,
                  decoration: BoxDecoration(
                    gradient: AppTheme.secondaryGradient,
                    borderRadius: BorderRadius.circular(18),
                  ),
                  child: const Icon(Icons.photo_library_outlined,
                      size: 26, color: Colors.white),
                ),
                const SizedBox(height: 12),
                const Text('처방전 이미지를 선택해주세요',
                    style: TextStyle(
                        color: AppTheme.textSecondary,
                        fontSize: 14,
                        fontWeight: FontWeight.w500)),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    _PickButton(
                      icon: Icons.camera_alt_outlined,
                      label: '카메라',
                      onTap: onCamera,
                      gradient: AppTheme.primaryGradient,
                    ),
                    const SizedBox(width: 10),
                    _PickButton(
                      icon: Icons.image_outlined,
                      label: '갤러리',
                      onTap: onGallery,
                      gradient: AppTheme.secondaryGradient,
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                const Text('JPG · PNG · PDF · HEIC (최대 10MB)',
                    style: TextStyle(
                        fontSize: 11, color: AppTheme.textMuted)),
              ],
            ),
    );
  }
}

class _PickButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final LinearGradient gradient;

  const _PickButton({
    required this.icon,
    required this.label,
    required this.onTap,
    required this.gradient,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 9),
        decoration: BoxDecoration(
          gradient: gradient,
          borderRadius: BorderRadius.circular(20),
          boxShadow: [
            BoxShadow(
              color: AppTheme.primary.withOpacity(0.25),
              blurRadius: 8,
              offset: const Offset(0, 3),
            ),
          ],
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: Colors.white, size: 16),
            const SizedBox(width: 6),
            Text(label,
                style: const TextStyle(
                    color: Colors.white,
                    fontSize: 13,
                    fontWeight: FontWeight.w600)),
          ],
        ),
      ),
    );
  }
}

class _OcrResultCard extends StatelessWidget {
  final Map<String, dynamic> data;
  const _OcrResultCard({required this.data});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AppTheme.primary.withOpacity(0.05),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppTheme.primary.withOpacity(0.2)),
      ),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.check_circle_outline,
                  size: 18, color: AppTheme.primary),
              const SizedBox(width: 6),
              const Text('업로드 완료',
                  style: TextStyle(
                      fontWeight: FontWeight.w700,
                      color: AppTheme.primary,
                      fontSize: 14)),
            ],
          ),
          const Divider(height: 16, color: AppTheme.border),
          // 값은 담당자가 웹 검수 화면에서 손으로 적는다 — 올린 쪽에서 보여 줄 것은
          // 번호와 지금 상태뿐이다.
          _row('처방전 번호', data['prescription_id']),
          _row('상태', data['status_label']),
        ],
      ),
    );
  }

  Widget _row(String label, String? value) {
    if (value == null || value.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 80,
            child: Text(label,
                style: const TextStyle(
                    fontSize: 12, color: AppTheme.textMuted)),
          ),
          Expanded(
            child: Text(value,
                style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: AppTheme.textPrimary)),
          ),
        ],
      ),
    );
  }
}

// ── Upload progress card ────────────────────────────────────────────────────

class _UploadProgressCard extends StatefulWidget {
  final double progress;
  final bool   isSaving;
  const _UploadProgressCard({
    super.key,
    required this.progress,
    required this.isSaving,
  });

  @override
  State<_UploadProgressCard> createState() => _UploadProgressCardState();
}

class _UploadProgressCardState extends State<_UploadProgressCard>
    with SingleTickerProviderStateMixin {
  late final AnimationController _pulse;
  late final Animation<double>   _opacity;

  @override
  void initState() {
    super.initState();
    _pulse = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    )..repeat(reverse: true);
    _opacity = Tween<double>(begin: 0.35, end: 1.0).animate(
      CurvedAnimation(parent: _pulse, curve: Curves.easeInOut),
    );
  }

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 22),
      decoration: AppTheme.cardDecoration(radius: 16),
      child: widget.isSaving ? _buildSaving() : _buildUpload(),
    );
  }

  Widget _buildUpload() {
    final pct = (widget.progress * 100).clamp(0, 100).toInt();
    return Column(
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Row(
              children: [
                Icon(Icons.cloud_upload_outlined,
                    color: AppTheme.primary, size: 18),
                SizedBox(width: 8),
                Text('이미지 업로드 중...',
                    style: TextStyle(
                        fontWeight: FontWeight.w600,
                        color: AppTheme.textSecondary,
                        fontSize: 14)),
              ],
            ),
            Text('$pct%',
                style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    color: AppTheme.primary,
                    fontSize: 15)),
          ],
        ),
        const SizedBox(height: 12),
        ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: LinearProgressIndicator(
            value: widget.progress,
            minHeight: 8,
            backgroundColor: AppTheme.border,
            valueColor:
                const AlwaysStoppedAnimation<Color>(AppTheme.primary),
          ),
        ),
      ],
    );
  }

  Widget _buildSaving() {
    return Column(
      children: [
        AnimatedBuilder(
          animation: _opacity,
          builder: (_, child) => Opacity(opacity: _opacity.value, child: child!),
          child: Column(
            children: [
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  gradient: AppTheme.primaryGradient,
                  borderRadius: BorderRadius.circular(16),
                  boxShadow: [
                    BoxShadow(
                      color: AppTheme.primary.withOpacity(0.3),
                      blurRadius: 12,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: const Icon(Icons.document_scanner_outlined,
                    color: Colors.white, size: 28),
              ),
              const SizedBox(height: 14),
              const Text('등록 중...',
                  style: TextStyle(
                      fontWeight: FontWeight.w800,
                      color: AppTheme.primary,
                      fontSize: 16)),
              const SizedBox(height: 4),
              const Text('처방전을 올리고 있습니다',
                  style: TextStyle(
                      color: AppTheme.textMuted, fontSize: 12)),
            ],
          ),
        ),
        const SizedBox(height: 16),
        ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: LinearProgressIndicator(
            minHeight: 6,
            backgroundColor: AppTheme.border,
            valueColor: AlwaysStoppedAnimation<Color>(
                AppTheme.primary.withOpacity(0.7)),
          ),
        ),
      ],
    );
  }
}
