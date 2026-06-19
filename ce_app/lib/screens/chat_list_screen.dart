// lib/screens/chat_list_screen.dart

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../models/chat_room.dart';
import '../providers/chat_provider.dart';
import '../theme/app_theme.dart';
import '../widgets/common_widgets.dart';

class ChatListScreen extends ConsumerStatefulWidget {
  const ChatListScreen({super.key});

  @override
  ConsumerState<ChatListScreen> createState() => _ChatListScreenState();
}

class _ChatListScreenState extends ConsumerState<ChatListScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(chatRoomsProvider.notifier).load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(chatRoomsProvider);

    return Scaffold(
      backgroundColor: AppTheme.background,
      body: RefreshIndicator(
        onRefresh: () => ref.read(chatRoomsProvider.notifier).load(),
        color: AppTheme.primary,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            // ── Header ───────────────────────────────────────────────
            SliverToBoxAdapter(
              child: Container(
                decoration:
                    const BoxDecoration(gradient: AppTheme.darkGradient),
                child: SafeArea(
                  bottom: false,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(24, 20, 24, 28),
                    child: Row(
                      children: [
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              width: 44,
                              height: 44,
                              decoration: BoxDecoration(
                                gradient: AppTheme.accentGradient,
                                borderRadius: BorderRadius.circular(14),
                                border: Border.all(
                                    color: Colors.white.withOpacity(0.3),
                                    width: 2),
                              ),
                              child: const Icon(Icons.chat_bubble_rounded,
                                  color: Colors.white, size: 22),
                            ),
                            const SizedBox(height: 16),
                            const Text(
                              '채팅',
                              style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 26,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: -0.5),
                            ),
                            Text(
                              '${state.rooms.length}개의 대화방',
                              style: TextStyle(
                                  color: Colors.white.withOpacity(0.6),
                                  fontSize: 13),
                            ),
                          ],
                        ),
                        const Spacer(),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            const UserNameBadge(),
                            const SizedBox(height: 8),
                            GestureDetector(
                              onTap: () =>
                                  _showNewChatSheet(context, state.users),
                              child: Container(
                                width: 44,
                                height: 44,
                                decoration: BoxDecoration(
                                  color: Colors.white.withOpacity(0.15),
                                  borderRadius: BorderRadius.circular(14),
                                  border: Border.all(
                                      color: Colors.white.withOpacity(0.3)),
                                ),
                                child: const Icon(Icons.edit_square,
                                    color: Colors.white, size: 20),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),

            // ── Body ─────────────────────────────────────────────────
            if (state.isLoading && state.rooms.isEmpty)
              const SliverFillRemaining(child: LoadingWidget())
            else if (state.rooms.isEmpty)
              SliverFillRemaining(
                child: Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Container(
                        width: 64,
                        height: 64,
                        decoration: BoxDecoration(
                          color: AppTheme.accent.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: const Icon(
                            Icons.chat_bubble_outline_rounded,
                            color: AppTheme.accent,
                            size: 28),
                      ),
                      const SizedBox(height: 12),
                      const Text('대화가 없습니다.',
                          style: TextStyle(
                              fontSize: 14,
                              color: AppTheme.textMuted,
                              fontWeight: FontWeight.w500)),
                      const SizedBox(height: 20),
                      GestureDetector(
                        onTap: () => _showNewChatSheet(
                            context, ref.read(chatRoomsProvider).users),
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 20, vertical: 12),
                          decoration: BoxDecoration(
                            gradient: AppTheme.accentGradient,
                            borderRadius: BorderRadius.circular(14),
                            boxShadow: [
                              BoxShadow(
                                color: AppTheme.accent.withOpacity(0.3),
                                blurRadius: 12,
                                offset: const Offset(0, 4),
                              ),
                            ],
                          ),
                          child: const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.add_rounded,
                                  color: Colors.white, size: 18),
                              SizedBox(width: 6),
                              Text('새 채팅 시작',
                                  style: TextStyle(
                                      color: Colors.white,
                                      fontWeight: FontWeight.w700,
                                      fontSize: 14)),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
                sliver: SliverList(
                  delegate: SliverChildBuilderDelegate(
                    (ctx, i) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _RoomTile(room: state.rooms[i]),
                    ),
                    childCount: state.rooms.length,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  void _showNewChatSheet(
      BuildContext ctx, List<Map<String, dynamic>> users) {
    showModalBottomSheet(
      context: ctx,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _NewChatSheet(users: users),
    );
  }
}

// ── Room Tile ──────────────────────────────────────────────────────────────────
class _RoomTile extends StatelessWidget {
  final ChatRoom room;
  const _RoomTile({required this.room});

  @override
  Widget build(BuildContext context) {
    final initials = room.name.isNotEmpty ? room.name[0].toUpperCase() : '?';
    final isGroup  = room.type == 'group';

    return GestureDetector(
      onTap: () => context.push('/chat/${room.id}',
          extra: {'name': room.name, 'type': room.type}),
      child: Container(
        decoration: AppTheme.cardDecoration(radius: 16),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  gradient: isGroup
                      ? AppTheme.secondaryGradient
                      : AppTheme.accentGradient,
                  borderRadius: BorderRadius.circular(15),
                ),
                child: Center(
                  child: Text(
                    initials,
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                      fontSize: 18,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        if (isGroup) ...[
                          const Icon(Icons.group_rounded,
                              size: 12, color: AppTheme.textMuted),
                          const SizedBox(width: 3),
                        ],
                        Expanded(
                          child: Text(
                            room.name,
                            style: const TextStyle(
                                fontWeight: FontWeight.w700,
                                fontSize: 14,
                                color: AppTheme.textPrimary),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                    if (room.latestBody != null) ...[
                      const SizedBox(height: 3),
                      Text(
                        room.latestBody!,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                            fontSize: 12, color: AppTheme.textMuted),
                      ),
                    ],
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  if (room.latestTime != null)
                    Text(
                      room.latestTime!,
                      style: const TextStyle(
                          fontSize: 11, color: AppTheme.textMuted),
                    ),
                  if (room.unread > 0) ...[
                    const SizedBox(height: 4),
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 7, vertical: 3),
                      decoration: BoxDecoration(
                        color: AppTheme.danger,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(
                        '${room.unread}',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 11,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ── New Chat Sheet ─────────────────────────────────────────────────────────────
class _NewChatSheet extends ConsumerStatefulWidget {
  final List<Map<String, dynamic>> users;
  const _NewChatSheet({required this.users});

  @override
  ConsumerState<_NewChatSheet> createState() => _NewChatSheetState();
}

class _NewChatSheetState extends ConsumerState<_NewChatSheet> {
  String         _type       = 'direct';
  final Set<int> _selected   = {};
  final _nameCtrl            = TextEditingController();
  bool           _isCreating = false;

  @override
  void dispose() {
    _nameCtrl.dispose();
    super.dispose();
  }

  Future<void> _create() async {
    if (_selected.isEmpty) return;
    if (_type == 'group' && _nameCtrl.text.trim().isEmpty) return;

    setState(() => _isCreating = true);
    try {
      final roomId = await ref.read(chatRoomsProvider.notifier).createRoom(
            type: _type,
            userIds: _selected.toList(),
            name: _type == 'group' ? _nameCtrl.text.trim() : null,
          );
      if (!mounted) return;
      final rooms    = ref.read(chatRoomsProvider).rooms;
      final roomName = rooms.firstWhere((r) => r.id == roomId,
              orElse: () => rooms.first)
          .name;
      Navigator.pop(context);
      context.push('/chat/$roomId', extra: {'name': roomName, 'type': _type});
    } finally {
      if (mounted) setState(() => _isCreating = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom,
        left: 24,
        right: 24,
        top: 24,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Handle
          Center(
            child: Container(
              width: 40,
              height: 4,
              margin: const EdgeInsets.only(bottom: 20),
              decoration: BoxDecoration(
                  color: AppTheme.border,
                  borderRadius: BorderRadius.circular(2)),
            ),
          ),
          const Text('새 채팅',
              style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w800,
                  color: AppTheme.textPrimary)),
          const SizedBox(height: 16),

          // Type selector
          Row(
            children: [
              _TypeChip(
                label: '1:1 채팅',
                selected: _type == 'direct',
                onTap: () => setState(() => _type = 'direct'),
              ),
              const SizedBox(width: 8),
              _TypeChip(
                label: '그룹 채팅',
                selected: _type == 'group',
                onTap: () => setState(() => _type = 'group'),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // Group name
          if (_type == 'group') ...[
            TextFormField(
              controller: _nameCtrl,
              decoration: const InputDecoration(
                labelText: '그룹 이름',
                prefixIcon: Icon(Icons.group_rounded),
              ),
            ),
            const SizedBox(height: 14),
          ],

          // Users
          Text('대화 상대',
              style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: AppTheme.textMuted)),
          const SizedBox(height: 8),
          ConstrainedBox(
            constraints: const BoxConstraints(maxHeight: 200),
            child: ListView.builder(
              shrinkWrap: true,
              itemCount: widget.users.length,
              itemBuilder: (ctx, i) {
                final u  = widget.users[i];
                final id = u['id'] as int;
                return CheckboxListTile(
                  dense: true,
                  activeColor: AppTheme.primary,
                  value: _selected.contains(id),
                  title: Text(u['name'] as String,
                      style: const TextStyle(
                          fontSize: 14, fontWeight: FontWeight.w600)),
                  subtitle: Text(u['role'] as String? ?? '',
                      style: const TextStyle(
                          fontSize: 11, color: AppTheme.textMuted)),
                  onChanged: (v) {
                    setState(() {
                      if (v == true) {
                        if (_type == 'direct') _selected.clear();
                        _selected.add(id);
                      } else {
                        _selected.remove(id);
                      }
                    });
                  },
                );
              },
            ),
          ),
          const SizedBox(height: 16),
          GradientButton(
            label: '채팅 시작',
            icon: Icons.chat_bubble_outline_rounded,
            onPressed: (_selected.isEmpty || _isCreating) ? null : _create,
            loading: _isCreating,
            gradient: AppTheme.accentGradient,
          ),
          const SizedBox(height: 24),
        ],
      ),
    );
  }
}

class _TypeChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _TypeChip(
      {required this.label, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        decoration: BoxDecoration(
          gradient: selected ? AppTheme.accentGradient : null,
          color: selected ? null : Colors.grey.shade100,
          borderRadius: BorderRadius.circular(20),
          boxShadow: selected
              ? [
                  BoxShadow(
                    color: AppTheme.accent.withOpacity(0.3),
                    blurRadius: 8,
                    offset: const Offset(0, 3),
                  )
                ]
              : null,
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: selected ? Colors.white : AppTheme.textSecondary,
          ),
        ),
      ),
    );
  }
}
