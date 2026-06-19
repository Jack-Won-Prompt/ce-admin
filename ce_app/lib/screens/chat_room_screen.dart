// lib/screens/chat_room_screen.dart

import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import '../models/chat_message.dart';
import '../providers/auth_provider.dart';
import '../providers/chat_provider.dart';
import '../services/chat_notification_service.dart';
import '../utils/constants.dart';
import '../theme/app_theme.dart';
import '../widgets/common_widgets.dart';

class ChatRoomScreen extends ConsumerStatefulWidget {
  final int    roomId;
  final String roomName;

  const ChatRoomScreen(
      {super.key, required this.roomId, required this.roomName});

  @override
  ConsumerState<ChatRoomScreen> createState() => _ChatRoomScreenState();
}

class _ChatRoomScreenState extends ConsumerState<ChatRoomScreen> {
  final _inputCtrl  = TextEditingController();
  final _scrollCtrl = ScrollController();
  XFile? _pendingFile;
  bool   _isSending = false;

  @override
  void initState() {
    super.initState();
    ChatNotificationService.instance.activeRoomId = widget.roomId;
    ChatNotificationService.instance.onActiveRoomMessage = (msg) {
      if (mounted) {
        ref
            .read(chatMessagesProvider(widget.roomId).notifier)
            .addMessage(msg);
        _scrollToBottom();
      }
    };
    // 이 방의 Pusher 채널이 아직 구독되지 않은 경우 구독
    ChatNotificationService.instance.subscribeRoom(widget.roomId);

    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await ref.read(chatMessagesProvider(widget.roomId).notifier).load();
      _scrollToBottom();
      ref.read(chatRoomsProvider.notifier).clearUnread(widget.roomId);
    });
  }

  @override
  void dispose() {
    if (ChatNotificationService.instance.activeRoomId == widget.roomId) {
      ChatNotificationService.instance.activeRoomId = null;
      ChatNotificationService.instance.onActiveRoomMessage = null;
    }
    _inputCtrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollCtrl.hasClients) {
        _scrollCtrl.animateTo(
          _scrollCtrl.position.maxScrollExtent,
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOut,
        );
      }
    });
  }

  Future<void> _send() async {
    final body = _inputCtrl.text.trim();
    if (body.isEmpty && _pendingFile == null) return;
    setState(() => _isSending = true);
    try {
      if (_pendingFile != null) {
        await ref
            .read(chatMessagesProvider(widget.roomId).notifier)
            .sendFile(_pendingFile!.path, _pendingFile!.name);
        setState(() => _pendingFile = null);
      }
      if (body.isNotEmpty) {
        _inputCtrl.clear();
        await ref
            .read(chatMessagesProvider(widget.roomId).notifier)
            .sendText(body);
      }
      _scrollToBottom();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('메시지 전송 중 오류가 발생했습니다.')),
        );
      }
    } finally {
      if (mounted) setState(() => _isSending = false);
    }
  }

  Future<void> _pickFile() async {
    final file = await ImagePicker().pickImage(source: ImageSource.gallery);
    if (file != null) setState(() => _pendingFile = file);
  }

  @override
  Widget build(BuildContext context) {
    final state      = ref.watch(chatMessagesProvider(widget.roomId));
    final myUserId   = ref.watch(userIdProvider);
    final rooms      = ref.watch(chatRoomsProvider).rooms;
    final roomName   = rooms
        .where((r) => r.id == widget.roomId)
        .map((r) => r.name)
        .firstWhere((_) => true, orElse: () => widget.roomName.isNotEmpty ? widget.roomName : '채팅');

    ref.listen(chatMessagesProvider(widget.roomId), (prev, next) {
      if ((prev?.messages.length ?? 0) < next.messages.length) {
        _scrollToBottom();
      }
    });

    return Scaffold(
      backgroundColor: AppTheme.background,
      body: Column(
        children: [
          // ── Header ─────────────────────────────────────────────
          Container(
            decoration:
                const BoxDecoration(gradient: AppTheme.darkGradient),
            child: SafeArea(
              bottom: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(8, 8, 16, 14),
                child: Row(
                  children: [
                    IconButton(
                      icon: const Icon(Icons.arrow_back_ios_new_rounded,
                          color: Colors.white, size: 20),
                      onPressed: () => Navigator.pop(context),
                    ),
                    const SizedBox(width: 4),
                    Container(
                      width: 36,
                      height: 36,
                      decoration: BoxDecoration(
                        gradient: AppTheme.accentGradient,
                        borderRadius: BorderRadius.circular(11),
                      ),
                      child: Center(
                        child: Text(
                          roomName.isNotEmpty
                              ? roomName[0].toUpperCase()
                              : '?',
                          style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w800,
                              fontSize: 15),
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        roomName,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    GestureDetector(
                      onTap: () => ref
                          .read(chatMessagesProvider(widget.roomId)
                              .notifier)
                          .load(),
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
                    const SizedBox(width: 8),
                    const UserNameBadge(),
                  ],
                ),
              ),
            ),
          ),

          // ── Message list ─────────────────────────────────────
          Expanded(
            child: state.isLoading && state.messages.isEmpty
                ? const Center(
                    child: CircularProgressIndicator(
                        color: AppTheme.primary))
                : ListView.builder(
                    controller: _scrollCtrl,
                    padding: const EdgeInsets.symmetric(
                        horizontal: 16, vertical: 12),
                    itemCount:
                        (state.hasMore ? 1 : 0) + state.messages.length,
                    itemBuilder: (ctx, i) {
                      if (state.hasMore && i == 0) {
                        return GestureDetector(
                          onTap: () => ref
                              .read(chatMessagesProvider(widget.roomId)
                                  .notifier)
                              .loadMore(),
                          child: Center(
                            child: Container(
                              margin: const EdgeInsets.only(bottom: 12),
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 14, vertical: 6),
                              decoration: BoxDecoration(
                                color: AppTheme.primary.withOpacity(0.1),
                                borderRadius: BorderRadius.circular(20),
                              ),
                              child: const Text('이전 메시지 보기',
                                  style: TextStyle(
                                      fontSize: 12,
                                      color: AppTheme.primary,
                                      fontWeight: FontWeight.w600)),
                            ),
                          ),
                        );
                      }
                      final msgIdx = state.hasMore ? i - 1 : i;
                      return _MessageBubble(
                          message: state.messages[msgIdx],
                          myUserId: myUserId);
                    },
                  ),
          ),

          // ── Pending file preview ──────────────────────────────
          if (_pendingFile != null)
            Container(
              padding: const EdgeInsets.symmetric(
                  horizontal: 12, vertical: 8),
              color: AppTheme.surface,
              child: Row(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: Image.file(File(_pendingFile!.path),
                        width: 56, height: 56, fit: BoxFit.cover),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(_pendingFile!.name,
                        style: const TextStyle(
                            fontSize: 12,
                            color: AppTheme.textSecondary),
                        overflow: TextOverflow.ellipsis),
                  ),
                  GestureDetector(
                    onTap: () => setState(() => _pendingFile = null),
                    child: Container(
                      width: 24,
                      height: 24,
                      decoration: BoxDecoration(
                        color: AppTheme.danger.withOpacity(0.1),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(Icons.close,
                          size: 14, color: AppTheme.danger),
                    ),
                  ),
                ],
              ),
            ),

          // ── Input bar ────────────────────────────────────────
          SafeArea(
            top: false,
            child: Container(
              padding: const EdgeInsets.symmetric(
                  horizontal: 10, vertical: 8),
              decoration: const BoxDecoration(
                color: AppTheme.surface,
                border: Border(top: BorderSide(color: AppTheme.border)),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  IconButton(
                    icon: Icon(
                      Icons.attach_file_rounded,
                      color: _pendingFile != null
                          ? AppTheme.primary
                          : AppTheme.textMuted,
                    ),
                    onPressed: _pickFile,
                    padding: EdgeInsets.zero,
                    constraints: const BoxConstraints(
                        minWidth: 36, minHeight: 36),
                  ),
                  const SizedBox(width: 4),
                  Expanded(
                    child: TextField(
                      controller: _inputCtrl,
                      maxLines: 5,
                      minLines: 1,
                      keyboardType: TextInputType.multiline,
                      textInputAction: TextInputAction.newline,
                      style: const TextStyle(
                          fontSize: 14, color: AppTheme.textPrimary),
                      decoration: InputDecoration(
                        hintText: '메시지를 입력하세요',
                        hintStyle: const TextStyle(
                            color: AppTheme.textMuted, fontSize: 13),
                        filled: true,
                        fillColor: AppTheme.background,
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(20),
                          borderSide: BorderSide.none,
                        ),
                        contentPadding: const EdgeInsets.symmetric(
                            horizontal: 14, vertical: 10),
                        isDense: true,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  _isSending
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
                          onTap: _send,
                          child: Container(
                            width: 40,
                            height: 40,
                            decoration: BoxDecoration(
                              gradient: AppTheme.primaryGradient,
                              borderRadius: BorderRadius.circular(12),
                              boxShadow: [
                                BoxShadow(
                                  color:
                                      AppTheme.primary.withOpacity(0.3),
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
          ),
        ],
      ),
    );
  }
}

class _MessageBubble extends StatelessWidget {
  final ChatMessage message;
  final int? myUserId;
  const _MessageBubble({required this.message, this.myUserId});

  @override
  Widget build(BuildContext context) {
    final isMine = myUserId != null && message.userId == myUserId;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Column(
        crossAxisAlignment:
            isMine ? CrossAxisAlignment.end : CrossAxisAlignment.start,
        children: [
          if (!isMine)
            Padding(
              padding: const EdgeInsets.only(left: 42, bottom: 3),
              child: Text(message.userName,
                  style: const TextStyle(
                      fontSize: 11, color: AppTheme.textMuted)),
            ),
          Row(
            mainAxisAlignment:
                isMine ? MainAxisAlignment.end : MainAxisAlignment.start,
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              if (!isMine) ...[
                Container(
                  width: 32,
                  height: 32,
                  decoration: BoxDecoration(
                    gradient: AppTheme.accentGradient,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Center(
                    child: Text(
                      message.userName.isNotEmpty
                          ? message.userName[0].toUpperCase()
                          : '?',
                      style: const TextStyle(
                          color: Colors.white,
                          fontSize: 12,
                          fontWeight: FontWeight.w800),
                    ),
                  ),
                ),
                const SizedBox(width: 6),
              ],
              Flexible(
                child: Container(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 12, vertical: 9),
                  decoration: BoxDecoration(
                    gradient: isMine ? AppTheme.primaryGradient : null,
                    color: isMine ? null : AppTheme.surface,
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
                  child: _buildContent(context, isMine),
                ),
              ),
              if (isMine) ...[
                const SizedBox(width: 6),
                Container(
                  width: 32,
                  height: 32,
                  decoration: BoxDecoration(
                    gradient: AppTheme.successGradient,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Center(
                    child: Text(
                      message.userName.isNotEmpty
                          ? message.userName[0].toUpperCase()
                          : '?',
                      style: const TextStyle(
                          color: Colors.white,
                          fontSize: 12,
                          fontWeight: FontWeight.w800),
                    ),
                  ),
                ),
              ],
            ],
          ),
          Padding(
            padding: EdgeInsets.only(
                left: isMine ? 0 : 44,
                right: isMine ? 44 : 0,
                top: 3),
            child: Text(
              message.timeLabel,
              style: const TextStyle(
                  fontSize: 10, color: AppTheme.textMuted),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildContent(BuildContext context, bool isMine) {
    final textColor = isMine ? Colors.white : AppTheme.textPrimary;

    if (message.attachmentPath != null && message.isImage) {
      final url =
          '${AppConstants.baseUrl.replaceAll('/api', '')}/storage/${message.attachmentPath}';
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (message.body != null && message.body!.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Text(message.body!,
                  style: TextStyle(color: textColor, fontSize: 14)),
            ),
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: Image.network(url,
                width: 200,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) =>
                    const Icon(Icons.broken_image, size: 48)),
          ),
        ],
      );
    }

    if (message.attachmentPath != null) {
      return Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.insert_drive_file_outlined,
              color: textColor, size: 20),
          const SizedBox(width: 6),
          Flexible(
            child: Text(
              message.attachmentName ?? '파일',
              style: TextStyle(
                color: textColor,
                fontSize: 13,
                decoration: TextDecoration.underline,
              ),
            ),
          ),
        ],
      );
    }

    return Text(
      message.body ?? '',
      style: TextStyle(color: textColor, fontSize: 14, height: 1.4),
    );
  }
}
