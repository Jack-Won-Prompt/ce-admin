// lib/screens/otp_screen.dart

import 'dart:async';
import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/auth_provider.dart';
import '../theme/app_theme.dart';
import '../widgets/common_widgets.dart';

class OtpScreen extends ConsumerStatefulWidget {
  final String pendingToken;
  final String maskedPhone;

  const OtpScreen({
    super.key,
    required this.pendingToken,
    required this.maskedPhone,
  });

  @override
  ConsumerState<OtpScreen> createState() => _OtpScreenState();
}

class _OtpScreenState extends ConsumerState<OtpScreen>
    with SingleTickerProviderStateMixin {
  final List<TextEditingController> _controllers =
      List.generate(6, (_) => TextEditingController());
  final List<FocusNode> _focusNodes = List.generate(6, (_) => FocusNode());

  int _cooldown = 0;
  Timer? _timer;
  late String _pendingToken;
  late AnimationController _animCtrl;
  late Animation<double> _fadeAnim;
  late Animation<Offset> _slideAnim;

  @override
  void initState() {
    super.initState();
    _pendingToken = widget.pendingToken;
    _startCooldown();

    // 백스페이스: 현재 칸이 비어있으면 이전 칸으로 포커스 이동 후 삭제
    for (var i = 0; i < 6; i++) {
      final idx = i;
      _focusNodes[idx].onKeyEvent = (_, event) {
        if (event is KeyDownEvent &&
            event.logicalKey == LogicalKeyboardKey.backspace &&
            _controllers[idx].text.isEmpty &&
            idx > 0) {
          _controllers[idx - 1].clear();
          _focusNodes[idx - 1].requestFocus();
          return KeyEventResult.handled;
        }
        return KeyEventResult.ignored;
      };
    }

    _animCtrl = AnimationController(
        vsync: this, duration: const Duration(milliseconds: 800));
    _fadeAnim = CurvedAnimation(parent: _animCtrl, curve: Curves.easeOut);
    _slideAnim = Tween<Offset>(
            begin: const Offset(0, 0.08), end: Offset.zero)
        .animate(CurvedAnimation(parent: _animCtrl, curve: Curves.easeOutCubic));
    _animCtrl.forward();
  }

  @override
  void dispose() {
    for (final c in _controllers) c.dispose();
    for (final f in _focusNodes) f.dispose();
    _timer?.cancel();
    _animCtrl.dispose();
    super.dispose();
  }

  void _startCooldown() {
    setState(() => _cooldown = 60);
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (_cooldown <= 1) {
        t.cancel();
        setState(() => _cooldown = 0);
      } else {
        setState(() => _cooldown--);
      }
    });
  }

  String get _enteredCode => _controllers.map((c) => c.text).join();

  Future<void> _submit() async {
    final code = _enteredCode;
    if (code.length < 6) {
      _showError('인증번호 6자리를 모두 입력해주세요.');
      return;
    }
    FocusScope.of(context).unfocus();

    await ref.read(authNotifierProvider.notifier).verifyOtp(_pendingToken, code);

    if (!mounted) return;
    final authState = ref.read(authNotifierProvider);
    authState.when(
      data: (loggedIn) {
        if (loggedIn) context.go('/prescriptions');
      },
      error: (e, _) {
        _clearInputs();
        _showError(e.toString().replaceFirst('Exception: ', ''));
      },
      loading: () {},
    );
  }

  Future<void> _resend() async {
    if (_cooldown > 0) return;
    try {
      final newToken = await ref
          .read(authNotifierProvider.notifier)
          .resendOtp(_pendingToken);
      setState(() => _pendingToken = newToken);
      _clearInputs();
      _startCooldown();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: const Text('인증번호를 재발송했습니다.'),
            backgroundColor: AppTheme.success,
            behavior: SnackBarBehavior.floating,
            shape:
                RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            margin: const EdgeInsets.all(16),
          ),
        );
      }
    } catch (e) {
      if (mounted) _showError('재발송에 실패했습니다. 잠시 후 다시 시도해주세요.');
    }
  }

  void _clearInputs() {
    for (final c in _controllers) c.clear();
    _focusNodes.first.requestFocus();
  }

  void _showError(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(msg),
        backgroundColor: AppTheme.danger,
        behavior: SnackBarBehavior.floating,
        shape:
            RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        margin: const EdgeInsets.all(16),
      ),
    );
  }

  void _onDigitChanged(int index, String value) {
    if (value.length == 1 && index < 5) {
      _focusNodes[index + 1].requestFocus();
    }
    if (value.isEmpty && index > 0) {
      _focusNodes[index - 1].requestFocus();
    }
    if (index == 5 && value.length == 1) {
      _submit();
    }
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authNotifierProvider);
    final isLoading = authState.isLoading;
    final size = MediaQuery.of(context).size;

    return Scaffold(
      resizeToAvoidBottomInset: true,
      body: Stack(
        children: [
          // Gradient background
          Container(
            width: size.width,
            height: size.height,
            decoration: const BoxDecoration(gradient: AppTheme.darkGradient),
          ),

          // Radial glow — top right
          Positioned(
            top: -80,
            right: -80,
            child: Container(
              width: 300,
              height: 300,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: RadialGradient(colors: [
                  AppTheme.secondary.withOpacity(0.35),
                  AppTheme.secondary.withOpacity(0),
                ]),
              ),
            ),
          ),

          SafeArea(
            child: Column(
              children: [
                // Back button
                Align(
                  alignment: Alignment.centerLeft,
                  child: IconButton(
                    icon: const Icon(Icons.arrow_back_ios_new_rounded,
                        color: Colors.white),
                    onPressed: () {
                      ref.read(otpPendingProvider.notifier).state = null;
                      context.go('/login');
                    },
                  ),
                ),

                Expanded(
                  child: SingleChildScrollView(
                    padding: EdgeInsets.fromLTRB(
                        24,
                        8,
                        24,
                        MediaQuery.of(context).viewInsets.bottom + 32),
                    child: FadeTransition(
                      opacity: _fadeAnim,
                      child: SlideTransition(
                        position: _slideAnim,
                        child: Column(
                          children: [
                            // Icon with glow
                            Stack(
                              alignment: Alignment.center,
                              children: [
                                Container(
                                  width: 110,
                                  height: 110,
                                  decoration: BoxDecoration(
                                    shape: BoxShape.circle,
                                    gradient: RadialGradient(colors: [
                                      AppTheme.secondary.withOpacity(0.3),
                                      AppTheme.secondary.withOpacity(0),
                                    ]),
                                  ),
                                ),
                                Container(
                                  width: 76,
                                  height: 76,
                                  decoration: BoxDecoration(
                                    gradient: AppTheme.secondaryGradient,
                                    borderRadius: BorderRadius.circular(24),
                                    boxShadow: [
                                      BoxShadow(
                                        color: AppTheme.secondary
                                            .withOpacity(0.5),
                                        blurRadius: 24,
                                        offset: const Offset(0, 10),
                                      ),
                                    ],
                                  ),
                                  child: const Icon(
                                    Icons.sms_outlined,
                                    color: Colors.white,
                                    size: 36,
                                  ),
                                ),
                              ],
                            ),

                            const SizedBox(height: 20),

                            ShaderMask(
                              shaderCallback: (bounds) =>
                                  const LinearGradient(
                                colors: [Colors.white, Color(0xFFB3D4FF)],
                                begin: Alignment.topLeft,
                                end: Alignment.bottomRight,
                              ).createShader(bounds),
                              child: const Text(
                                'SMS 인증',
                                style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 28,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: -0.5,
                                ),
                              ),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              '${widget.maskedPhone}으로\n발송된 6자리 인증번호를 입력하세요.',
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                color: Colors.white.withOpacity(0.55),
                                fontSize: 13,
                                height: 1.6,
                              ),
                            ),

                            const SizedBox(height: 36),

                            // Glass card
                            ClipRRect(
                              borderRadius: BorderRadius.circular(24),
                              child: BackdropFilter(
                                filter: ImageFilter.blur(
                                    sigmaX: 24, sigmaY: 24),
                                child: Container(
                                  decoration: BoxDecoration(
                                    color: Colors.white.withOpacity(0.1),
                                    borderRadius:
                                        BorderRadius.circular(24),
                                    border: Border.all(
                                      color:
                                          Colors.white.withOpacity(0.18),
                                      width: 1.5,
                                    ),
                                  ),
                                  padding: const EdgeInsets.all(24),
                                  child: Column(
                                    children: [
                                      // OTP digit boxes
                                      Row(
                                        mainAxisAlignment:
                                            MainAxisAlignment.spaceBetween,
                                        children: List.generate(
                                            6, (i) => _buildDigitBox(i)),
                                      ),

                                      const SizedBox(height: 20),

                                      // 2FA info badge
                                      Container(
                                        padding: const EdgeInsets.symmetric(
                                            horizontal: 12, vertical: 8),
                                        decoration: BoxDecoration(
                                          color:
                                              Colors.white.withOpacity(0.1),
                                          borderRadius:
                                              BorderRadius.circular(10),
                                          border: Border.all(
                                              color: Colors.white
                                                  .withOpacity(0.15)),
                                        ),
                                        child: Row(
                                          mainAxisSize: MainAxisSize.min,
                                          children: [
                                            Icon(Icons.info_outline,
                                                size: 15,
                                                color: Colors.white
                                                    .withOpacity(0.7)),
                                            const SizedBox(width: 6),
                                            Text(
                                              '인증번호는 발송 후 5분간 유효합니다.',
                                              style: TextStyle(
                                                fontSize: 11,
                                                color: Colors.white
                                                    .withOpacity(0.65),
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),

                                      const SizedBox(height: 20),

                                      GradientButton(
                                        label: '인증하기',
                                        icon: Icons.check_circle_outline_rounded,
                                        onPressed:
                                            isLoading ? null : _submit,
                                        loading: isLoading,
                                        gradient: AppTheme.secondaryGradient,
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            ),

                            const SizedBox(height: 20),

                            // Resend
                            Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Text(
                                  '인증번호를 받지 못하셨나요? ',
                                  style: TextStyle(
                                      fontSize: 13,
                                      color:
                                          Colors.white.withOpacity(0.5)),
                                ),
                                GestureDetector(
                                  onTap: _cooldown > 0 ? null : _resend,
                                  child: Text(
                                    _cooldown > 0
                                        ? '재발송 (${_cooldown}s)'
                                        : '재발송',
                                    style: TextStyle(
                                      fontSize: 13,
                                      fontWeight: FontWeight.w700,
                                      color: _cooldown > 0
                                          ? Colors.white.withOpacity(0.3)
                                          : Colors.white,
                                    ),
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
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDigitBox(int index) {
    return SizedBox(
      width: 44,
      height: 54,
      child: TextFormField(
        controller: _controllers[index],
        focusNode: _focusNodes[index],
        textAlign: TextAlign.center,
        keyboardType: TextInputType.number,
        inputFormatters: [
          FilteringTextInputFormatter.digitsOnly,
          LengthLimitingTextInputFormatter(1),
        ],
        style: const TextStyle(
          fontSize: 22,
          fontWeight: FontWeight.w800,
          color: Colors.white,
        ),
        decoration: InputDecoration(
          filled: true,
          fillColor: Colors.white.withOpacity(0.12),
          counterText: '',
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide:
                BorderSide(color: Colors.white.withOpacity(0.2), width: 1.5),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide:
                const BorderSide(color: AppTheme.secondary, width: 2.5),
          ),
          contentPadding: EdgeInsets.zero,
        ),
        onChanged: (v) => _onDigitChanged(index, v),
      ),
    );
  }
}
