// lib/screens/prescription_camera_screen.dart
//
// 처방전 촬영 전용 카메라 화면
// - 처방전 비율(A4 portrait) 프레임 가이드 오버레이
// - 이미지 밝기 분석으로 문서 감지 → 촬영 버튼 활성화

import 'dart:io';
import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../theme/app_theme.dart';

class PrescriptionCameraScreen extends StatefulWidget {
  const PrescriptionCameraScreen({super.key});

  /// Navigator로 열고 촬영된 File을 반환받는 헬퍼
  static Future<File?> show(BuildContext context) {
    return Navigator.of(context).push<File?>(
      MaterialPageRoute(
        fullscreenDialog: true,
        builder: (_) => const PrescriptionCameraScreen(),
      ),
    );
  }

  @override
  State<PrescriptionCameraScreen> createState() =>
      _PrescriptionCameraScreenState();
}

class _PrescriptionCameraScreenState extends State<PrescriptionCameraScreen>
    with WidgetsBindingObserver {
  CameraController? _ctrl;
  bool _isReady    = false;
  bool _docInFrame = true; // 항상 촬영 가능
  bool _capturing  = false;
  bool _torchOn    = false;

  DateTime? _lastAnalysis;

  // 프레임 비율 (A4 portrait ≈ 1:1.414)
  static const double _frameWidthRatio = 0.82;
  static const double _frameAspect     = 1.414;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);
    _initCamera();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _ctrl?.dispose();
    SystemChrome.setPreferredOrientations(DeviceOrientation.values);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final ctrl = _ctrl;
    if (ctrl == null || !ctrl.value.isInitialized) return;
    if (state == AppLifecycleState.inactive) {
      ctrl.dispose();
    } else if (state == AppLifecycleState.resumed) {
      _initCamera();
    }
  }

  Future<void> _initCamera() async {
    try {
      final cameras = await availableCameras();
      if (cameras.isEmpty) return;

      final ctrl = CameraController(
        cameras.first,
        ResolutionPreset.high,
        enableAudio: false,
        imageFormatGroup: ImageFormatGroup.yuv420,
      );
      _ctrl = ctrl;
      await ctrl.initialize();
      if (!mounted) return;

      setState(() => _isReady = true);
    } catch (e) {
      debugPrint('Camera init error: $e');
    }
  }

  // ── 이미지 스트림 분석 ──────────────────────────────────────
  void _analyzeFrame(CameraImage image) {
    final now = DateTime.now();
    if (_lastAnalysis != null &&
        now.difference(_lastAnalysis!) < const Duration(milliseconds: 450)) return;
    _lastAnalysis = now;

    try {
      final plane = image.planes[0];
      final bytes = plane.bytes;
      final bpr   = plane.bytesPerRow;
      final w     = image.width;
      final h     = image.height;

      final y0 = (h * 0.30).toInt();
      final y1 = (h * 0.70).toInt();

      final topX0 = (w * 0.05).toInt();
      final topX1 = (w * 0.22).toInt();
      final botX0 = (w * 0.78).toInt();
      final botX1 = (w * 0.95).toInt();

      int topSum = 0, topCnt = 0;
      int botSum = 0, botCnt = 0;

      for (int y = y0; y < y1; y += 8) {
        final row = y * bpr;
        for (int x = topX0; x < topX1; x += 8) {
          final idx = row + x;
          if (idx < bytes.length) { topSum += bytes[idx] & 0xFF; topCnt++; }
        }
        for (int x = botX0; x < botX1; x += 8) {
          final idx = row + x;
          if (idx < bytes.length) { botSum += bytes[idx] & 0xFF; botCnt++; }
        }
      }

      final topAvg = topCnt > 0 ? topSum / topCnt : 0;
      final botAvg = botCnt > 0 ? botSum / botCnt : 0;
      final detected = topAvg > 130 && botAvg > 130;

      if (detected != _docInFrame && mounted) {
        setState(() => _docInFrame = detected);
      }
    } catch (_) {}
  }

  // ── 촬영 ─────────────────────────────────────────────────────
  Future<void> _capture() async {
    final ctrl = _ctrl;
    if (_capturing || ctrl == null || !ctrl.value.isInitialized) return;
    setState(() => _capturing = true);

    try {
      final xFile = await ctrl.takePicture();
      if (mounted) Navigator.of(context).pop(File(xFile.path));
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('촬영 실패: $e'),
            backgroundColor: AppTheme.danger,
          ),
        );
        setState(() => _capturing = false);
        await ctrl.startImageStream(_analyzeFrame);
      }
    }
  }

  // ── 플래시 토글 ───────────────────────────────────────────────
  Future<void> _toggleTorch() async {
    final ctrl = _ctrl;
    if (ctrl == null) return;
    _torchOn = !_torchOn;
    await ctrl.setFlashMode(_torchOn ? FlashMode.torch : FlashMode.off);
    setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.of(context).size;
    final fw   = size.width * _frameWidthRatio;
    final fh   = fw * _frameAspect;

    final frameColor = _docInFrame ? AppTheme.accent : Colors.white70;

    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        fit: StackFit.expand,
        children: [
          // ── 카메라 미리보기 ─────────────────────────────────
          if (_isReady && _ctrl != null)
            Center(child: CameraPreview(_ctrl!))
          else
            Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 64,
                    height: 64,
                    decoration: BoxDecoration(
                      gradient: AppTheme.primaryGradient,
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: const Icon(Icons.camera_alt_rounded,
                        color: Colors.white, size: 30),
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    '카메라 초기화 중...',
                    style: TextStyle(color: Colors.white70, fontSize: 14),
                  ),
                ],
              ),
            ),

          // ── 프레임 오버레이 ─────────────────────────────────
          if (_isReady)
            _FrameOverlay(
              frameWidth:  fw,
              frameHeight: fh,
              docInFrame:  _docInFrame,
            ),

          // ── 상단 바 ──────────────────────────────────────────
          Positioned(
            top: 0, left: 0, right: 0,
            child: Container(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [Colors.black87, Colors.transparent],
                ),
              ),
              child: SafeArea(
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 12, vertical: 8),
                  child: Row(
                    children: [
                      _CamIconButton(
                        icon: Icons.close,
                        onTap: () => Navigator.of(context).pop(),
                      ),
                      const Spacer(),
                      const Text(
                        '처방전 촬영',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 0.3,
                        ),
                      ),
                      const Spacer(),
                      _CamIconButton(
                        icon: _torchOn
                            ? Icons.flash_on_rounded
                            : Icons.flash_off_rounded,
                        onTap: _toggleTorch,
                        active: _torchOn,
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),

          // ── 하단: 안내 문구 + 촬영 버튼 ─────────────────────
          Positioned(
            bottom: 0, left: 0, right: 0,
            child: Container(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.bottomCenter,
                  end: Alignment.topCenter,
                  colors: [Colors.black87, Colors.transparent],
                ),
              ),
              child: SafeArea(
                top: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(24, 20, 24, 24),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      // 상태 안내 텍스트
                      AnimatedContainer(
                        duration: const Duration(milliseconds: 300),
                        padding: const EdgeInsets.symmetric(
                            horizontal: 16, vertical: 7),
                        decoration: BoxDecoration(
                          color: frameColor.withOpacity(0.15),
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(
                              color: frameColor.withOpacity(0.4)),
                        ),
                        child: Text(
                          _docInFrame
                              ? '문서가 감지되었습니다. 촬영하세요.'
                              : '처방전을 프레임 안에 맞추세요',
                          style: TextStyle(
                            color: frameColor,
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                      const SizedBox(height: 28),

                      // 촬영 버튼
                      GestureDetector(
                        onTap: (_docInFrame && !_capturing) ? _capture : null,
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 250),
                          width: 76,
                          height: 76,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            gradient: _docInFrame
                                ? AppTheme.accentGradient
                                : null,
                            color: _docInFrame ? null : Colors.white24,
                            border: Border.all(
                              color: _docInFrame
                                  ? AppTheme.accent
                                  : Colors.white38,
                              width: 3.5,
                            ),
                            boxShadow: _docInFrame
                                ? [
                                    BoxShadow(
                                      color:
                                          AppTheme.accent.withOpacity(0.45),
                                      blurRadius: 20,
                                      spreadRadius: 2,
                                    ),
                                  ]
                                : [],
                          ),
                          child: _capturing
                              ? const Padding(
                                  padding: EdgeInsets.all(20),
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2.5,
                                    color: Colors.white,
                                  ),
                                )
                              : Icon(
                                  Icons.camera_alt_rounded,
                                  color: _docInFrame
                                      ? Colors.white
                                      : Colors.white38,
                                  size: 30,
                                ),
                        ),
                      ),
                      const SizedBox(height: 8),
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
}

// ── 카메라 아이콘 버튼 ────────────────────────────────────────────
class _CamIconButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;
  final bool active;

  const _CamIconButton({
    required this.icon,
    required this.onTap,
    this.active = false,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        width: 40,
        height: 40,
        decoration: BoxDecoration(
          color: active
              ? AppTheme.accent.withOpacity(0.25)
              : Colors.white.withOpacity(0.15),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: active
                ? AppTheme.accent.withOpacity(0.6)
                : Colors.white.withOpacity(0.2),
          ),
        ),
        child: Icon(
          icon,
          color: active ? AppTheme.accent : Colors.white,
          size: 20,
        ),
      ),
    );
  }
}

// ── 프레임 가이드 오버레이 ────────────────────────────────────────
class _FrameOverlay extends StatelessWidget {
  final double frameWidth;
  final double frameHeight;
  final bool   docInFrame;

  const _FrameOverlay({
    required this.frameWidth,
    required this.frameHeight,
    required this.docInFrame,
  });

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      painter: _FramePainter(
        frameWidth:  frameWidth,
        frameHeight: frameHeight,
        docInFrame:  docInFrame,
      ),
      child: const SizedBox.expand(),
    );
  }
}

class _FramePainter extends CustomPainter {
  final double frameWidth;
  final double frameHeight;
  final bool   docInFrame;

  const _FramePainter({
    required this.frameWidth,
    required this.frameHeight,
    required this.docInFrame,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final fl = (size.width  - frameWidth)  / 2;
    final ft = (size.height - frameHeight) / 2;
    final frameRect = Rect.fromLTWH(fl, ft, frameWidth, frameHeight);
    const radius    = Radius.circular(12);

    // ── 반투명 어두운 배경 (프레임 바깥) ──
    canvas.saveLayer(
        Rect.fromLTWH(0, 0, size.width, size.height), Paint());
    canvas.drawRect(
      Rect.fromLTWH(0, 0, size.width, size.height),
      Paint()..color = Colors.black.withOpacity(0.55),
    );
    canvas.drawRRect(
      RRect.fromRectAndRadius(frameRect, radius),
      Paint()..blendMode = BlendMode.clear,
    );
    canvas.restore();

    // ── 프레임 테두리 ──
    final frameColor =
        docInFrame ? AppTheme.accent : Colors.white70;
    canvas.drawRRect(
      RRect.fromRectAndRadius(frameRect, radius),
      Paint()
        ..color       = frameColor.withOpacity(0.6)
        ..strokeWidth = 1.5
        ..style       = PaintingStyle.stroke,
    );

    // ── 모서리 마커 (L자형) ──
    final cp = Paint()
      ..color      = frameColor
      ..strokeWidth = 3.5
      ..strokeCap  = StrokeCap.round
      ..style      = PaintingStyle.stroke;
    const cLen = 24.0;

    // 좌상
    canvas.drawLine(Offset(fl, ft + cLen), Offset(fl, ft), cp);
    canvas.drawLine(Offset(fl, ft), Offset(fl + cLen, ft), cp);
    // 우상
    canvas.drawLine(
        Offset(fl + frameWidth - cLen, ft), Offset(fl + frameWidth, ft), cp);
    canvas.drawLine(
        Offset(fl + frameWidth, ft), Offset(fl + frameWidth, ft + cLen), cp);
    // 좌하
    canvas.drawLine(
        Offset(fl, ft + frameHeight - cLen), Offset(fl, ft + frameHeight), cp);
    canvas.drawLine(
        Offset(fl, ft + frameHeight), Offset(fl + cLen, ft + frameHeight), cp);
    // 우하
    canvas.drawLine(
        Offset(fl + frameWidth - cLen, ft + frameHeight),
        Offset(fl + frameWidth, ft + frameHeight),
        cp);
    canvas.drawLine(
        Offset(fl + frameWidth, ft + frameHeight),
        Offset(fl + frameWidth, ft + frameHeight - cLen),
        cp);

    // ── 프레임 라벨 ──
    final tp = TextPainter(
      text: TextSpan(
        text: '처방전',
        style: TextStyle(
          color: frameColor.withOpacity(0.85),
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
      ),
      textDirection: TextDirection.ltr,
    )..layout();
    tp.paint(canvas, Offset(fl + 10, ft + 10));
  }

  @override
  bool shouldRepaint(_FramePainter old) => old.docInFrame != docInFrame;
}
