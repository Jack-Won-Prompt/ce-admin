// lib/screens/login_screen.dart

import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/auth_provider.dart';
import '../services/auth_service.dart';
import '../theme/app_theme.dart';
import '../widgets/common_widgets.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen>
    with SingleTickerProviderStateMixin {
  final _formKey      = GlobalKey<FormState>();
  final _emailCtrl    = TextEditingController(text: 'hong@ce-admin.co.kr');
  final _passwordCtrl = TextEditingController(text: '12345678');
  bool  _obscure      = true;

  /// 아이디·비밀번호 자리를 보일지. 서버 설정을 받기 전까지는 보인다 —
  /// 없다가 생기는 것보다 있다가 사라지는 편이 덜 놀랍다.
  bool _passwordLogin = true;

  /* 아이디·비밀번호 길이 닫혀 있어도 이 자리를 여덟 번 잇달아 누르면 칸이 나온다.
     SSO 가 말썽일 때 고칠 사람까지 갇히지 않도록 남겨 둔 뒷문이다. 나온다고
     아무나 들어오는 것은 아니다 — 서버가 관리자만 받는다.
     두 걸음 사이가 2초를 넘으면 처음부터 다시 센다. 어쩌다 눌린 것까지 세면
     뜻하지 않게 열린다. */
  static const _revealTaps = 8;
  int       _ssoTaps = 0;
  DateTime? _lastSsoTap;
  late AnimationController _animCtrl;
  late Animation<double> _fadeAnim;
  late Animation<Offset> _slideAnim;

  @override
  void initState() {
    super.initState();
    _animCtrl = AnimationController(
        vsync: this, duration: const Duration(milliseconds: 900));
    _fadeAnim =
        CurvedAnimation(parent: _animCtrl, curve: Curves.easeOut);
    _slideAnim = Tween<Offset>(
            begin: const Offset(0, 0.08), end: Offset.zero)
        .animate(CurvedAnimation(
            parent: _animCtrl, curve: Curves.easeOutCubic));
    _animCtrl.forward();

    // 서버가 이 길을 닫아 두었는지 물어본다
    Future(() async {
      final enabled =
          await ref.read(authServiceProvider).passwordLoginEnabled();
      if (mounted && enabled != _passwordLogin) {
        setState(() => _passwordLogin = enabled);
      }
    });
  }

  /// 뒷문 셈. 여덟 번을 채워 칸을 열었으면 true — 그때는 SSO 안내를 띄우지 않는다.
  bool _countTapToReveal() {
    final now  = DateTime.now();
    final last = _lastSsoTap;
    _lastSsoTap = now;

    _ssoTaps = (last == null || now.difference(last) > const Duration(seconds: 2))
        ? 1
        : _ssoTaps + 1;

    if (_ssoTaps < _revealTaps) return false;

    setState(() {
      _passwordLogin = true;
      _ssoTaps       = 0;
    });
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('관리자 로그인 칸을 엽니다.')),
    );
    return true;
  }

  @override
  void dispose() {
    _emailCtrl.dispose();
    _passwordCtrl.dispose();
    _animCtrl.dispose();
    super.dispose();
  }

  void _ssoLogin() {
    if (!_passwordLogin && _countTapToReveal()) return;

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: const Text('Microsoft SSO 로그인은 현재 준비 중입니다. IT 관리자에게 문의하세요.'),
        backgroundColor: const Color(0xFF1565C0),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        margin: const EdgeInsets.all(16),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authNotifierProvider);
    final isLoading = authState.isLoading;
    final size = MediaQuery.of(context).size;

    // 직접 로그인 완료 → 메인으로 이동
    ref.listen<AsyncValue<bool>>(authNotifierProvider, (prev, next) {
      next.whenOrNull(data: (loggedIn) {
        if (loggedIn) context.go('/prescriptions');
      });
    });

    // OTP 대기 상태가 채워지면 OTP 화면으로 이동
    ref.listen<OtpPendingData?>(otpPendingProvider, (prev, next) {
      if (next != null) {
        context.go(
          '/login/otp',
          extra: {'pendingToken': next.pendingToken, 'maskedPhone': next.maskedPhone},
        );
      }
    });

    // 이메일/비밀번호 오류 표시
    ref.listen<AsyncValue<bool>>(authNotifierProvider, (prev, next) {
      next.whenOrNull(
        error: (e, _) {
          final msg = e.toString().replaceFirst('Exception: ', '');
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(msg),
              backgroundColor: AppTheme.danger,
              behavior: SnackBarBehavior.floating,
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12)),
              margin: const EdgeInsets.all(16),
            ),
          );
        },
      );
    });

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
            top: -100,
            right: -100,
            child: Container(
              width: 360,
              height: 360,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: RadialGradient(colors: [
                  AppTheme.secondary.withOpacity(0.4),
                  AppTheme.secondary.withOpacity(0),
                ]),
              ),
            ),
          ),

          // Radial glow — mid left
          Positioned(
            top: size.height * 0.3,
            left: -110,
            child: Container(
              width: 280,
              height: 280,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: RadialGradient(colors: [
                  AppTheme.primary.withOpacity(0.35),
                  AppTheme.primary.withOpacity(0),
                ]),
              ),
            ),
          ),

          // Radial glow — bottom
          Positioned(
            bottom: -70,
            right: size.width * 0.15,
            child: Container(
              width: 220,
              height: 220,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: RadialGradient(colors: [
                  AppTheme.accent.withOpacity(0.25),
                  AppTheme.accent.withOpacity(0),
                ]),
              ),
            ),
          ),

          // Content
          SafeArea(
            child: SingleChildScrollView(
              padding: EdgeInsets.fromLTRB(
                  24, 0, 24, MediaQuery.of(context).viewInsets.bottom + 32),
              child: FadeTransition(
                opacity: _fadeAnim,
                child: SlideTransition(
                  position: _slideAnim,
                  child: Column(
                    children: [
                      const SizedBox(height: 52),

                      // App icon with glow
                      Stack(
                        alignment: Alignment.center,
                        children: [
                          Container(
                            width: 110,
                            height: 110,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              gradient: RadialGradient(colors: [
                                AppTheme.primary.withOpacity(0.35),
                                AppTheme.primary.withOpacity(0),
                              ]),
                            ),
                          ),
                          Container(
                            width: 80,
                            height: 80,
                            decoration: BoxDecoration(
                              gradient: AppTheme.primaryGradient,
                              borderRadius: BorderRadius.circular(26),
                              boxShadow: [
                                BoxShadow(
                                  color: AppTheme.primary.withOpacity(0.6),
                                  blurRadius: 28,
                                  offset: const Offset(0, 12),
                                ),
                              ],
                            ),
                            child: const Icon(
                              Icons.medical_services_rounded,
                              color: Colors.white,
                              size: 38,
                            ),
                          ),
                        ],
                      ),

                      const SizedBox(height: 18),

                      ShaderMask(
                        shaderCallback: (bounds) => const LinearGradient(
                          colors: [Colors.white, Color(0xFFB3D4FF)],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ).createShader(bounds),
                        child: const Text(
                          'Coloplast CE Admin',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 26,
                            fontWeight: FontWeight.w800,
                            letterSpacing: -0.5,
                          ),
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '의료기기 주문 · 청구 관리',
                        style: TextStyle(
                          color: Colors.white.withOpacity(0.5),
                          fontSize: 13,
                          letterSpacing: 0.4,
                        ),
                      ),

                      const SizedBox(height: 32),

                      // ── Microsoft SSO 버튼 ──────────────────────────────────
                      Material(
                        color: Colors.transparent,
                        child: InkWell(
                          onTap: _ssoLogin,
                          borderRadius: BorderRadius.circular(18),
                          child: Ink(
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(18),
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withOpacity(0.18),
                                  blurRadius: 16,
                                  offset: const Offset(0, 6),
                                ),
                              ],
                            ),
                            child: Padding(
                              padding:
                                  const EdgeInsets.symmetric(vertical: 15),
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  SizedBox(
                                    width: 22,
                                    height: 22,
                                    child: CustomPaint(
                                        painter: _MsLogoPainter()),
                                  ),
                                  const SizedBox(width: 12),
                                  const Text(
                                    'Microsoft 계정으로 로그인',
                                    style: TextStyle(
                                      color: Color(0xFF1A1A2E),
                                      fontSize: 15,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),

                      const SizedBox(height: 8),
                      Text(
                        '임직원은 Microsoft 계정(Entra ID)으로 로그인하세요',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: Colors.white.withOpacity(0.45),
                          fontSize: 11,
                        ),
                      ),

                      /* 아이디·비밀번호 길이 닫혀 있으면 구분선부터 로그인
                         단추까지 통째로 감춘다 — Microsoft 계정만 남는다. */
                      if (_passwordLogin) ...[
                      const SizedBox(height: 20),

                      // ── 구분선 ───────────────────────────────────────────────
                      Row(
                        children: [
                          Expanded(
                              child: Divider(
                                  color: Colors.white.withOpacity(0.2),
                                  thickness: 1)),
                          Padding(
                            padding:
                                const EdgeInsets.symmetric(horizontal: 16),
                            child: Text(
                              '또는 이메일로 로그인',
                              style: TextStyle(
                                color: Colors.white.withOpacity(0.4),
                                fontSize: 12,
                              ),
                            ),
                          ),
                          Expanded(
                              child: Divider(
                                  color: Colors.white.withOpacity(0.2),
                                  thickness: 1)),
                        ],
                      ),

                      const SizedBox(height: 20),

                      // ── 이메일 로그인 카드 ──────────────────────────────────
                      ClipRRect(
                        borderRadius: BorderRadius.circular(24),
                        child: BackdropFilter(
                          filter: ImageFilter.blur(sigmaX: 24, sigmaY: 24),
                          child: Container(
                            decoration: BoxDecoration(
                              color: Colors.white.withOpacity(0.1),
                              borderRadius: BorderRadius.circular(24),
                              border: Border.all(
                                color: Colors.white.withOpacity(0.18),
                                width: 1.5,
                              ),
                            ),
                            padding: const EdgeInsets.all(24),
                            child: Form(
                              key: _formKey,
                              child: Column(
                                crossAxisAlignment:
                                    CrossAxisAlignment.stretch,
                                children: [
                                  _buildField(
                                    controller: _emailCtrl,
                                    label: '이메일',
                                    icon: Icons.email_outlined,
                                    keyboardType:
                                        TextInputType.emailAddress,
                                    textInputAction:
                                        TextInputAction.next,
                                    validator: (v) => v == null ||
                                            v.trim().isEmpty
                                        ? '이메일을 입력해주세요.'
                                        : null,
                                  ),
                                  const SizedBox(height: 12),
                                  _buildField(
                                    controller: _passwordCtrl,
                                    label: '비밀번호',
                                    icon: Icons.lock_outlined,
                                    obscure: _obscure,
                                    toggleObscure: () => setState(
                                        () => _obscure = !_obscure),
                                    textInputAction:
                                        TextInputAction.done,
                                    onSubmitted: (_) => _submit(),
                                    validator: (v) => v == null ||
                                            v.isEmpty
                                        ? '비밀번호를 입력해주세요.'
                                        : null,
                                  ),
                                  const SizedBox(height: 14),

                                  // 2FA 안내 뱃지
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                        horizontal: 12, vertical: 8),
                                    decoration: BoxDecoration(
                                      color: Colors.white.withOpacity(0.12),
                                      borderRadius:
                                          BorderRadius.circular(10),
                                      border: Border.all(
                                          color: Colors.white
                                              .withOpacity(0.2)),
                                    ),
                                    child: Row(
                                      children: [
                                        Icon(Icons.shield_outlined,
                                            size: 15,
                                            color: Colors.white
                                                .withOpacity(0.8)),
                                        const SizedBox(width: 6),
                                        Text(
                                          '로그인 후 SMS 인증번호가 발송됩니다.',
                                          style: TextStyle(
                                            fontSize: 11,
                                            color: Colors.white
                                                .withOpacity(0.7),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),

                                  const SizedBox(height: 20),
                                  GradientButton(
                                    label: '로그인',
                                    onPressed: isLoading ? null : _submit,
                                    loading: isLoading,
                                    icon: Icons.arrow_forward_rounded,
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),

                      ],

                      const SizedBox(height: 24),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildField({
    required TextEditingController controller,
    required String label,
    required IconData icon,
    TextInputType? keyboardType,
    TextInputAction? textInputAction,
    bool obscure = false,
    VoidCallback? toggleObscure,
    String? Function(String?)? validator,
    ValueChanged<String>? onSubmitted,
  }) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      textInputAction: textInputAction,
      obscureText: obscure,
      style: const TextStyle(
          color: Colors.white, fontWeight: FontWeight.w500),
      decoration: InputDecoration(
        labelText: label,
        labelStyle: TextStyle(
            color: Colors.white.withOpacity(0.55), fontSize: 14),
        prefixIcon: Icon(icon,
            color: Colors.white.withOpacity(0.55), size: 20),
        suffixIcon: toggleObscure != null
            ? IconButton(
                icon: Icon(
                  obscure
                      ? Icons.visibility_off_outlined
                      : Icons.visibility_outlined,
                  color: Colors.white.withOpacity(0.45),
                  size: 20,
                ),
                onPressed: toggleObscure,
              )
            : null,
        filled: true,
        fillColor: Colors.white.withOpacity(0.08),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide:
              BorderSide(color: Colors.white.withOpacity(0.15)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide:
              BorderSide(color: Colors.white.withOpacity(0.15)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide:
              const BorderSide(color: AppTheme.secondary, width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: AppTheme.danger),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide:
              const BorderSide(color: AppTheme.danger, width: 2),
        ),
        errorStyle: const TextStyle(color: Color(0xFFFF8A80)),
        contentPadding: const EdgeInsets.symmetric(
            horizontal: 16, vertical: 15),
      ),
      validator: validator,
      onFieldSubmitted: onSubmitted,
    );
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    FocusScope.of(context).unfocus();
    await ref.read(authNotifierProvider.notifier).login(
          _emailCtrl.text.trim(),
          _passwordCtrl.text,
        );
  }

}

class _MsLogoPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final half = size.width / 2 - 1;
    const gap = 2.0;
    final rects = [
      Rect.fromLTWH(0, 0, half, half),
      Rect.fromLTWH(half + gap, 0, half, half),
      Rect.fromLTWH(0, half + gap, half, half),
      Rect.fromLTWH(half + gap, half + gap, half, half),
    ];
    final colors = [
      const Color(0xFFF25022),
      const Color(0xFF7FBA00),
      const Color(0xFF00A4EF),
      const Color(0xFFFFB900),
    ];
    for (int i = 0; i < 4; i++) {
      canvas.drawRect(rects[i], Paint()..color = colors[i]);
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
