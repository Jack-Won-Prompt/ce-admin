// lib/screens/prescription_upload_screen.dart

import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import '../services/api_client.dart';
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
  bool    _isOcr          = false;  // true = 서버 OCR 처리 대기 중
  String? _resultMsg;
  bool    _success   = false;
  Map<String, dynamic>? _ocrResult;
  final _memoCtrl = TextEditingController();

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
      _isOcr            = false;
    });
    _memoCtrl.clear();
  }

  @override
  void dispose() {
    _memoCtrl.dispose();
    super.dispose();
  }

  Future<void> _upload() async {
    if (_selectedFile == null) return;
    setState(() {
      _uploading      = true;
      _uploadProgress = 0.0;
      _isOcr          = false;
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
        if (memo.isNotEmpty) 'memo': memo,
      });

      final resp = await dio.post(
        '/prescriptions/upload',
        data: form,
        onSendProgress: (sent, total) {
          if (total > 0 && mounted) {
            setState(() {
              _uploadProgress = sent / total;
              if (_uploadProgress >= 1.0) _isOcr = true;
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
        _isOcr     = false;
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
                            isOcr: _isOcr,
                          )
                        : GradientButton(
                            key: const ValueKey('btn'),
                            label: _success ? '추가 처방전 업로드' : '처방전 업로드',
                            icon: _success
                                ? Icons.add_photo_alternate_outlined
                                : Icons.cloud_upload_outlined,
                            onPressed: _success
                                ? _resetForm
                                : (_selectedFile == null ? null : _upload),
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

                  // OCR result
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
    final ocr = data['ocr_result'] as Map<String, dynamic>? ?? {};

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
              const Icon(Icons.document_scanner_outlined,
                  size: 18, color: AppTheme.primary),
              const SizedBox(width: 6),
              const Text('OCR 인식 결과',
                  style: TextStyle(
                      fontWeight: FontWeight.w700,
                      color: AppTheme.primary,
                      fontSize: 14)),
              const Spacer(),
              if (data['ocr_confidence'] != null)
                Container(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: AppTheme.primary.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    '${data['ocr_confidence']}%',
                    style: const TextStyle(
                        fontSize: 12,
                        color: AppTheme.primary,
                        fontWeight: FontWeight.w700),
                  ),
                ),
            ],
          ),
          const Divider(height: 16, color: AppTheme.border),
          _row('처방전 번호', data['prescription_id']),
          _row('환자명', ocr['patient_name']),
          _row('병원명', ocr['hospital_name']),
          _row('처방일', ocr['issued_date']),
          _row('상병명', ocr['disease_name']),
          _row('투여일수', ocr['total_days']?.toString()),
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
  final bool   isOcr;
  const _UploadProgressCard({
    super.key,
    required this.progress,
    required this.isOcr,
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
      child: widget.isOcr ? _buildOcr() : _buildUpload(),
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

  Widget _buildOcr() {
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
              const Text('OCR 처리 중...',
                  style: TextStyle(
                      fontWeight: FontWeight.w800,
                      color: AppTheme.primary,
                      fontSize: 16)),
              const SizedBox(height: 4),
              const Text('처방전을 분석하고 있습니다',
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
