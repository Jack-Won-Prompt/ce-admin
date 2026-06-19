// lib/screens/inquiry_create_screen.dart

import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import '../providers/inquiry_provider.dart';
import '../theme/app_theme.dart';
import '../widgets/common_widgets.dart';

class InquiryCreateScreen extends ConsumerStatefulWidget {
  const InquiryCreateScreen({super.key});

  @override
  ConsumerState<InquiryCreateScreen> createState() =>
      _InquiryCreateScreenState();
}

class _InquiryCreateScreenState extends ConsumerState<InquiryCreateScreen> {
  final _formKey   = GlobalKey<FormState>();
  final _titleCtrl = TextEditingController();
  final _bodyCtrl  = TextEditingController();
  String _category  = 'general';
  bool   _isLoading = false;
  XFile? _attachment;

  static const _categories = [
    ('general',   '일반'),
    ('technical', '기술'),
    ('other',     '기타'),
  ];

  @override
  void dispose() {
    _titleCtrl.dispose();
    _bodyCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickFromGallery() async {
    final file = await ImagePicker().pickImage(
        source: ImageSource.gallery, imageQuality: 85);
    if (file != null) setState(() => _attachment = file);
  }

  Future<void> _pickFromCamera() async {
    final file = await ImagePicker().pickImage(
        source: ImageSource.camera, imageQuality: 85);
    if (file != null) setState(() => _attachment = file);
  }

  void _showAttachSheet() {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (ctx) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 40,
                height: 4,
                margin: const EdgeInsets.only(top: 16, bottom: 16),
                decoration: BoxDecoration(
                    color: AppTheme.border,
                    borderRadius: BorderRadius.circular(2)),
              ),
              ListTile(
                leading: Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                      gradient: AppTheme.primaryGradient,
                      borderRadius: BorderRadius.circular(10)),
                  child: const Icon(Icons.photo_library_outlined,
                      color: Colors.white, size: 18),
                ),
                title: const Text('갤러리에서 선택',
                    style: TextStyle(fontWeight: FontWeight.w600)),
                onTap: () { Navigator.pop(ctx); _pickFromGallery(); },
              ),
              ListTile(
                leading: Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                      gradient: AppTheme.secondaryGradient,
                      borderRadius: BorderRadius.circular(10)),
                  child: const Icon(Icons.camera_alt_outlined,
                      color: Colors.white, size: 18),
                ),
                title: const Text('카메라로 촬영',
                    style: TextStyle(fontWeight: FontWeight.w600)),
                onTap: () { Navigator.pop(ctx); _pickFromCamera(); },
              ),
              if (_attachment != null)
                ListTile(
                  leading: Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                        gradient: AppTheme.dangerGradient,
                        borderRadius: BorderRadius.circular(10)),
                    child: const Icon(Icons.delete_outline,
                        color: Colors.white, size: 18),
                  ),
                  title: const Text('첨부 제거',
                      style: TextStyle(
                          color: AppTheme.danger,
                          fontWeight: FontWeight.w600)),
                  onTap: () {
                    setState(() => _attachment = null);
                    Navigator.pop(ctx);
                  },
                ),
              const SizedBox(height: 16),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _isLoading = true);
    try {
      final id = await ref.read(inquiryListProvider.notifier).create(
            title:          _titleCtrl.text.trim(),
            category:       _category,
            body:           _bodyCtrl.text.trim(),
            attachmentPath: _attachment?.path,
            attachmentName: _attachment?.name,
          );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text('문의가 등록되었습니다.'),
          backgroundColor: AppTheme.success,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12)),
          margin: const EdgeInsets.all(16),
        ),
      );
      context.pop();
      context.push('/inquiries/$id');
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('등록 실패: $e'),
          backgroundColor: AppTheme.danger,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12)),
          margin: const EdgeInsets.all(16),
        ),
      );
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      body: Column(
        children: [
          // ── Dark gradient header ─────────────────────────────────
          Container(
            decoration: const BoxDecoration(gradient: AppTheme.darkGradient),
            child: SafeArea(
              bottom: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(8, 8, 16, 20),
                child: Row(
                  children: [
                    IconButton(
                      icon: const Icon(Icons.arrow_back_ios_new_rounded,
                          color: Colors.white, size: 20),
                      onPressed: () => context.pop(),
                    ),
                    const SizedBox(width: 4),
                    Container(
                      width: 38,
                      height: 38,
                      decoration: BoxDecoration(
                        gradient: AppTheme.accentGradient,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Icon(Icons.edit_outlined,
                          color: Colors.white, size: 18),
                    ),
                    const SizedBox(width: 12),
                    const Text(
                      '문의 등록',
                      style: TextStyle(
                          color: Colors.white,
                          fontSize: 20,
                          fontWeight: FontWeight.w800),
                    ),
                    const Spacer(),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        const UserNameBadge(),
                        const SizedBox(height: 6),
                        GestureDetector(
                          onTap: _isLoading ? null : _submit,
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 16, vertical: 8),
                            decoration: BoxDecoration(
                              gradient: _isLoading
                                  ? null
                                  : AppTheme.accentGradient,
                              color: _isLoading
                                  ? Colors.white.withOpacity(0.2)
                                  : null,
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: _isLoading
                                ? const SizedBox(
                                    width: 16,
                                    height: 16,
                                    child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                        color: Colors.white))
                                : const Text('등록',
                                    style: TextStyle(
                                        color: Colors.white,
                                        fontSize: 14,
                                        fontWeight: FontWeight.w700)),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),

          // ── Form ─────────────────────────────────────────────────
          Expanded(
            child: Form(
              key: _formKey,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 20, 16, 40),
                children: [
                  // Category
                  _FormSection(
                    title: '유형',
                    child: Row(
                      children: _categories.map((c) {
                        final selected = _category == c.$1;
                        return Padding(
                          padding: const EdgeInsets.only(right: 8),
                          child: GestureDetector(
                            onTap: () =>
                                setState(() => _category = c.$1),
                            child: AnimatedContainer(
                              duration:
                                  const Duration(milliseconds: 180),
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 16, vertical: 8),
                              decoration: BoxDecoration(
                                gradient: selected
                                    ? AppTheme.accentGradient
                                    : null,
                                color: selected
                                    ? null
                                    : AppTheme.background,
                                borderRadius:
                                    BorderRadius.circular(20),
                                border: Border.all(
                                  color: selected
                                      ? Colors.transparent
                                      : AppTheme.border,
                                ),
                              ),
                              child: Text(
                                c.$2,
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
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

                  const SizedBox(height: 16),

                  // Title
                  _FormSection(
                    title: '제목',
                    child: TextFormField(
                      controller: _titleCtrl,
                      maxLength: 255,
                      style: const TextStyle(
                          color: AppTheme.textPrimary, fontSize: 14),
                      decoration: InputDecoration(
                        hintText: '문의 제목을 입력해주세요',
                        hintStyle: const TextStyle(
                            color: AppTheme.textMuted, fontSize: 14),
                        filled: true,
                        fillColor: AppTheme.surface,
                        counterText: '',
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
                      validator: (v) =>
                          (v == null || v.trim().isEmpty)
                              ? '제목을 입력해주세요.'
                              : null,
                    ),
                  ),

                  const SizedBox(height: 16),

                  // Body
                  _FormSection(
                    title: '내용',
                    child: TextFormField(
                      controller: _bodyCtrl,
                      maxLines: 7,
                      style: const TextStyle(
                          color: AppTheme.textPrimary, fontSize: 14),
                      decoration: InputDecoration(
                        hintText: '문의 내용을 자세히 입력해주세요',
                        hintStyle: const TextStyle(
                            color: AppTheme.textMuted, fontSize: 14),
                        filled: true,
                        fillColor: AppTheme.surface,
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
                      validator: (v) {
                        if ((v == null || v.trim().isEmpty) &&
                            _attachment == null) {
                          return '내용을 입력하거나 파일을 첨부해주세요.';
                        }
                        return null;
                      },
                    ),
                  ),

                  const SizedBox(height: 16),

                  // Attachment
                  _FormSection(
                    title: '파일 첨부',
                    child: _AttachmentBox(
                      attachment: _attachment,
                      onTap: _showAttachSheet,
                      onRemove: () =>
                          setState(() => _attachment = null),
                    ),
                  ),

                  const SizedBox(height: 28),

                  GradientButton(
                    label: '문의 등록하기',
                    icon: Icons.send_rounded,
                    onPressed: _isLoading ? null : _submit,
                    loading: _isLoading,
                    gradient: AppTheme.accentGradient,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _FormSection extends StatelessWidget {
  final String title;
  final Widget child;

  const _FormSection({required this.title, required this.child});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: AppTheme.cardDecoration(radius: 16),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              color: AppTheme.textSecondary,
            ),
          ),
          const SizedBox(height: 10),
          child,
        ],
      ),
    );
  }
}

class _AttachmentBox extends StatelessWidget {
  final XFile? attachment;
  final VoidCallback onTap;
  final VoidCallback onRemove;

  const _AttachmentBox({
    required this.attachment,
    required this.onTap,
    required this.onRemove,
  });

  bool get _isImage {
    final ext = attachment?.name.split('.').last.toLowerCase() ?? '';
    return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic'].contains(ext);
  }

  @override
  Widget build(BuildContext context) {
    if (attachment == null) {
      return GestureDetector(
        onTap: onTap,
        child: Container(
          height: 72,
          decoration: BoxDecoration(
            border:
                Border.all(color: AppTheme.border, style: BorderStyle.solid),
            borderRadius: BorderRadius.circular(12),
            color: AppTheme.background,
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  gradient: AppTheme.primaryGradient,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(Icons.attach_file_rounded,
                    color: Colors.white, size: 18),
              ),
              const SizedBox(width: 10),
              const Text(
                '갤러리 또는 카메라로 파일 첨부',
                style: TextStyle(
                    color: AppTheme.textSecondary,
                    fontSize: 13,
                    fontWeight: FontWeight.w500),
              ),
            ],
          ),
        ),
      );
    }

    return Container(
      decoration: BoxDecoration(
        border: Border.all(color: AppTheme.primary, width: 1.5),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Stack(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(11),
            child: _isImage
                ? Image.file(
                    File(attachment!.path),
                    width: double.infinity,
                    height: 160,
                    fit: BoxFit.cover,
                  )
                : Container(
                    height: 80,
                    color: AppTheme.background,
                    child: Row(
                      children: [
                        const SizedBox(width: 16),
                        const Icon(Icons.insert_drive_file_outlined,
                            size: 36, color: AppTheme.primary),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            attachment!.name,
                            style: const TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w500,
                                color: AppTheme.textPrimary),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        const SizedBox(width: 16),
                      ],
                    ),
                  ),
          ),
          Positioned(
            top: 6, right: 6,
            child: GestureDetector(
              onTap: onRemove,
              child: Container(
                width: 26,
                height: 26,
                decoration: const BoxDecoration(
                  color: Color(0xCC000000),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.close,
                    color: Colors.white, size: 16),
              ),
            ),
          ),
          Positioned(
            bottom: 6, right: 6,
            child: GestureDetector(
              onTap: onTap,
              child: Container(
                padding: const EdgeInsets.symmetric(
                    horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: const Color(0xCC000000),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.swap_horiz, color: Colors.white, size: 14),
                    SizedBox(width: 4),
                    Text('교체',
                        style: TextStyle(
                            color: Colors.white, fontSize: 11)),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
