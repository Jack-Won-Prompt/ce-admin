// lib/screens/inquiry_detail_screen.dart

import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../providers/inquiry_provider.dart';
import '../models/inquiry.dart';
import '../utils/constants.dart';
import '../theme/app_theme.dart';
import '../widgets/common_widgets.dart';

class InquiryDetailScreen extends ConsumerStatefulWidget {
  final int inquiryId;
  const InquiryDetailScreen({super.key, required this.inquiryId});

  @override
  ConsumerState<InquiryDetailScreen> createState() =>
      _InquiryDetailScreenState();
}

class _InquiryDetailScreenState extends ConsumerState<InquiryDetailScreen> {
  final _inputCtrl        = TextEditingController();
  final _scrollController = ScrollController();
  int?   _myUserId;
  bool   _isSending  = false;
  XFile? _attachment;

  @override
  void initState() {
    super.initState();
    _loadUserId();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(inquiryDetailProvider(widget.inquiryId).notifier).load();
    });
  }

  Future<void> _loadUserId() async {
    final prefs = await SharedPreferences.getInstance();
    setState(() => _myUserId = prefs.getInt(AppConstants.keyUserId));
  }

  @override
  void dispose() {
    _inputCtrl.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOut,
        );
      }
    });
  }

  Future<void> _pickImage(ImageSource source) async {
    final file = await ImagePicker().pickImage(
        source: source, imageQuality: 85);
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
                  child:
                      const Icon(Icons.photo_library_outlined, color: Colors.white, size: 18),
                ),
                title: const Text('갤러리에서 선택',
                    style: TextStyle(fontWeight: FontWeight.w600)),
                onTap: () {
                  Navigator.pop(ctx);
                  _pickImage(ImageSource.gallery);
                },
              ),
              ListTile(
                leading: Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                      gradient: AppTheme.secondaryGradient,
                      borderRadius: BorderRadius.circular(10)),
                  child:
                      const Icon(Icons.camera_alt_outlined, color: Colors.white, size: 18),
                ),
                title: const Text('카메라로 촬영',
                    style: TextStyle(fontWeight: FontWeight.w600)),
                onTap: () {
                  Navigator.pop(ctx);
                  _pickImage(ImageSource.camera);
                },
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
                          color: AppTheme.danger, fontWeight: FontWeight.w600)),
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

  Future<void> _send() async {
    final body = _inputCtrl.text.trim();
    if (body.isEmpty && _attachment == null) return;
    setState(() => _isSending = true);
    try {
      await ref
          .read(inquiryDetailProvider(widget.inquiryId).notifier)
          .sendMessage(
            body,
            attachmentPath: _attachment?.path,
            attachmentName: _attachment?.name,
          );
      _inputCtrl.clear();
      setState(() => _attachment = null);
      _scrollToBottom();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('전송 실패: $e'),
            backgroundColor: AppTheme.danger,
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12)),
            margin: const EdgeInsets.all(16),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isSending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(inquiryDetailProvider(widget.inquiryId));

    if (state.isLoading && state.detail == null) {
      return Scaffold(
        backgroundColor: AppTheme.background,
        body: Column(
          children: [_buildHeader(null), const Expanded(child: Center(child: CircularProgressIndicator(color: AppTheme.primary)))],
        ),
      );
    }

    final detail = state.detail;
    if (detail == null) {
      return Scaffold(
        backgroundColor: AppTheme.background,
        body: Column(
          children: [
            _buildHeader(null),
            Expanded(
              child: Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(Icons.error_outline_rounded,
                        size: 48, color: AppTheme.danger),
                    const SizedBox(height: 8),
                    Text(state.error ?? '불러오기 실패',
                        style: const TextStyle(color: AppTheme.textMuted)),
                    TextButton(
                      onPressed: () => ref
                          .read(inquiryDetailProvider(widget.inquiryId).notifier)
                          .load(),
                      child: const Text('다시 시도'),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      );
    }

    WidgetsBinding.instance.addPostFrameCallback((_) => _scrollToBottom());

    return Scaffold(
      backgroundColor: AppTheme.background,
      body: Column(
        children: [
          _buildHeader(detail),

          // Message list
          Expanded(
            child: detail.messages.isEmpty
                ? Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Container(
                          width: 56,
                          height: 56,
                          decoration: BoxDecoration(
                            color: AppTheme.primary.withOpacity(0.08),
                            borderRadius: BorderRadius.circular(18),
                          ),
                          child: const Icon(Icons.chat_bubble_outline_rounded,
                              color: AppTheme.primary, size: 24),
                        ),
                        const SizedBox(height: 10),
                        const Text('메시지가 없습니다.',
                            style: TextStyle(
                                fontSize: 13,
                                color: AppTheme.textMuted,
                                fontWeight: FontWeight.w500)),
                      ],
                    ),
                  )
                : ListView.builder(
                    controller: _scrollController,
                    padding: const EdgeInsets.symmetric(
                        horizontal: 16, vertical: 16),
                    itemCount: detail.messages.length,
                    itemBuilder: (ctx, i) => _MessageBubble(
                      msg: detail.messages[i],
                      isMine: _myUserId != null &&
                          detail.messages[i].userId == _myUserId &&
                          !detail.messages[i].isAdmin,
                    ),
                  ),
          ),

          // Input bar
          _InputBar(
            ctrl: _inputCtrl,
            isSending: _isSending,
            isAnswered: detail.isAnswered,
            attachment: _attachment,
            onSend: _send,
            onAttach: _showAttachSheet,
            onRemoveAttach: () => setState(() => _attachment = null),
          ),
        ],
      ),
    );
  }

  Widget _buildHeader(InquiryDetail? detail) {
    return Container(
      decoration: const BoxDecoration(gradient: AppTheme.darkGradient),
      child: SafeArea(
        bottom: false,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(8, 8, 24, 0),
              child: Row(
                children: [
                  IconButton(
                    icon: const Icon(Icons.arrow_back_ios_new_rounded,
                        color: Colors.white, size: 20),
                    onPressed: () => Navigator.pop(context),
                  ),
                  const Spacer(),
                  const UserNameBadge(),
                  if (detail != null) ...[
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: detail.isAnswered
                            ? AppTheme.success.withOpacity(0.2)
                            : AppTheme.warning.withOpacity(0.2),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(
                          color: detail.isAnswered
                              ? AppTheme.success.withOpacity(0.4)
                              : AppTheme.warning.withOpacity(0.4),
                        ),
                      ),
                      child: Text(
                        detail.isAnswered ? '답변완료' : '답변대기',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          color: detail.isAnswered
                              ? AppTheme.success
                              : AppTheme.warning,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 8, 24, 20),
              child: detail != null
                  ? Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          detail.title,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                            height: 1.4,
                          ),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 6),
                        Text(
                          detail.categoryLabel,
                          style: TextStyle(
                              color: Colors.white.withOpacity(0.6),
                              fontSize: 12),
                        ),
                      ],
                    )
                  : const Text('문의 상세',
                      style: TextStyle(
                          color: Colors.white,
                          fontSize: 20,
                          fontWeight: FontWeight.w800)),
            ),
          ],
        ),
      ),
    );
  }
}

// ── Message Bubble ────────────────────────────────────────────────────────────
class _MessageBubble extends StatelessWidget {
  final InquiryMessage msg;
  final bool isMine;

  const _MessageBubble({required this.msg, required this.isMine});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        mainAxisAlignment:
            isMine ? MainAxisAlignment.end : MainAxisAlignment.start,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          if (!isMine) ...[
            Container(
              width: 32,
              height: 32,
              decoration: BoxDecoration(
                gradient: msg.isAdmin
                    ? AppTheme.primaryGradient
                    : AppTheme.infoGradient,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Center(
                child: Text(
                  msg.isAdmin ? '관' : msg.userName.substring(0, 1),
                  style: const TextStyle(
                    fontSize: 12,
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ),
            const SizedBox(width: 8),
          ],

          Flexible(
            child: Column(
              crossAxisAlignment:
                  isMine ? CrossAxisAlignment.end : CrossAxisAlignment.start,
              children: [
                if (!isMine)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 4, left: 2),
                    child: Text(
                      msg.isAdmin ? '관리자' : msg.userName,
                      style: const TextStyle(
                          fontSize: 11, color: AppTheme.textMuted),
                    ),
                  ),
                Container(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 14, vertical: 10),
                  decoration: BoxDecoration(
                    gradient:
                        isMine ? AppTheme.primaryGradient : null,
                    color: isMine
                        ? null
                        : AppTheme.surface,
                    borderRadius: BorderRadius.only(
                      topLeft: const Radius.circular(16),
                      topRight: const Radius.circular(16),
                      bottomLeft: Radius.circular(isMine ? 16 : 4),
                      bottomRight: Radius.circular(isMine ? 4 : 16),
                    ),
                    border: isMine
                        ? null
                        : Border.all(color: AppTheme.border),
                    boxShadow: const [
                      BoxShadow(
                          color: AppTheme.cardShadow,
                          blurRadius: 6,
                          offset: Offset(0, 2)),
                    ],
                  ),
                  constraints: BoxConstraints(
                    maxWidth: MediaQuery.of(context).size.width * 0.72,
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: isMine
                        ? CrossAxisAlignment.end
                        : CrossAxisAlignment.start,
                    children: [
                      if (msg.body != null && msg.body!.isNotEmpty)
                        Text(
                          msg.body!,
                          style: TextStyle(
                            fontSize: 14,
                            height: 1.5,
                            color: isMine
                                ? Colors.white
                                : AppTheme.textPrimary,
                          ),
                        ),
                      if (msg.attachmentPath != null) ...[
                        if (msg.body != null && msg.body!.isNotEmpty)
                          const SizedBox(height: 8),
                        if (msg.isImage)
                          ClipRRect(
                            borderRadius: BorderRadius.circular(8),
                            child: Image.network(
                              msg.attachmentPath!.startsWith('http')
                                  ? msg.attachmentPath!
                                  : '${AppConstants.storageUrl}${msg.attachmentPath}',
                              width: double.infinity,
                              fit: BoxFit.cover,
                              errorBuilder: (_, __, ___) => Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(Icons.broken_image_outlined,
                                      size: 16,
                                      color: isMine
                                          ? Colors.white70
                                          : AppTheme.textMuted),
                                  const SizedBox(width: 4),
                                  Text(
                                    msg.attachmentName ?? '이미지',
                                    style: TextStyle(
                                      fontSize: 12,
                                      color: isMine
                                          ? Colors.white70
                                          : AppTheme.textMuted,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          )
                        else
                          Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.attach_file,
                                  size: 14,
                                  color: isMine
                                      ? Colors.white70
                                      : AppTheme.textMuted),
                              const SizedBox(width: 4),
                              Flexible(
                                child: Text(
                                  msg.attachmentName ?? '첨부파일',
                                  style: TextStyle(
                                    fontSize: 13,
                                    color: isMine
                                        ? Colors.white
                                        : AppTheme.textPrimary,
                                  ),
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            ],
                          ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  msg.createdAt,
                  style: const TextStyle(
                      fontSize: 10, color: AppTheme.textMuted),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ── Input Bar ─────────────────────────────────────────────────────────────────
class _InputBar extends StatelessWidget {
  final TextEditingController ctrl;
  final bool isSending;
  final bool isAnswered;
  final XFile? attachment;
  final VoidCallback onSend;
  final VoidCallback onAttach;
  final VoidCallback onRemoveAttach;

  const _InputBar({
    required this.ctrl,
    required this.isSending,
    required this.isAnswered,
    required this.attachment,
    required this.onSend,
    required this.onAttach,
    required this.onRemoveAttach,
  });

  @override
  Widget build(BuildContext context) {
    final hasAttach = attachment != null;
    final isImg = hasAttach &&
        ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic']
            .contains(attachment!.name.split('.').last.toLowerCase());

    return SafeArea(
      child: Container(
        decoration: BoxDecoration(
          color: AppTheme.surface,
          border: const Border(top: BorderSide(color: AppTheme.border)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (hasAttach)
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
                child: Stack(
                  children: [
                    Container(
                      width: double.infinity,
                      constraints: const BoxConstraints(maxHeight: 100),
                      decoration: BoxDecoration(
                        color: AppTheme.background,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: AppTheme.border),
                      ),
                      child: isImg
                          ? ClipRRect(
                              borderRadius: BorderRadius.circular(9),
                              child: Image.file(
                                File(attachment!.path),
                                fit: BoxFit.cover,
                              ),
                            )
                          : Padding(
                              padding: const EdgeInsets.all(10),
                              child: Row(
                                children: [
                                  const Icon(
                                      Icons.insert_drive_file_outlined,
                                      size: 28,
                                      color: AppTheme.primary),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      attachment!.name,
                                      style: const TextStyle(
                                          fontSize: 12,
                                          color: AppTheme.textSecondary),
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                    ),
                    Positioned(
                      top: 4, right: 4,
                      child: GestureDetector(
                        onTap: onRemoveAttach,
                        child: Container(
                          width: 22,
                          height: 22,
                          decoration: const BoxDecoration(
                            color: Color(0xCC000000),
                            shape: BoxShape.circle,
                          ),
                          child: const Icon(Icons.close,
                              color: Colors.white, size: 13),
                        ),
                      ),
                    ),
                  ],
                ),
              ),

            Padding(
              padding: const EdgeInsets.symmetric(
                  horizontal: 12, vertical: 8),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  IconButton(
                    onPressed: isSending ? null : onAttach,
                    icon: Icon(
                      Icons.attach_file_rounded,
                      color: hasAttach
                          ? AppTheme.primary
                          : AppTheme.textMuted,
                    ),
                    padding: EdgeInsets.zero,
                    constraints: const BoxConstraints(
                        minWidth: 36, minHeight: 36),
                  ),
                  const SizedBox(width: 4),
                  Expanded(
                    child: TextField(
                      controller: ctrl,
                      maxLines: 5,
                      minLines: 1,
                      style: const TextStyle(
                          fontSize: 14, color: AppTheme.textPrimary),
                      decoration: InputDecoration(
                        hintText: isAnswered
                            ? '추가 문의 내용을 입력하세요 (재문의)'
                            : '내용을 입력하세요…',
                        hintStyle: const TextStyle(
                            color: AppTheme.textMuted, fontSize: 13),
                        filled: true,
                        fillColor: AppTheme.background,
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(20),
                          borderSide: BorderSide.none,
                        ),
                        contentPadding: const EdgeInsets.symmetric(
                            horizontal: 16, vertical: 10),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  isSending
                      ? const SizedBox(
                          width: 40,
                          height: 40,
                          child: Center(
                            child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: AppTheme.primary),
                          ),
                        )
                      : GestureDetector(
                          onTap: onSend,
                          child: Container(
                            width: 40,
                            height: 40,
                            decoration: BoxDecoration(
                              gradient: AppTheme.primaryGradient,
                              borderRadius: BorderRadius.circular(12),
                              boxShadow: [
                                BoxShadow(
                                  color: AppTheme.primary.withOpacity(0.3),
                                  blurRadius: 8,
                                  offset: const Offset(0, 3),
                                ),
                              ],
                            ),
                            child: const Icon(Icons.send_rounded,
                                color: Colors.white, size: 18),
                          ),
                        ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
