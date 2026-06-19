import 'dart:ui';
import 'package:flutter/material.dart';

class AppTheme {
  AppTheme._();

  // ── Core Colors ──────────────────────────────────────────────────────────
  static const Color primary   = Color(0xFF1565C0);
  static const Color secondary = Color(0xFF0288D1);
  static const Color accent    = Color(0xFF26C6DA);
  static const Color success   = Color(0xFF2E7D32);
  static const Color warning   = Color(0xFFF57C00);
  static const Color danger    = Color(0xFFC62828);
  static const Color info      = Color(0xFF0277BD);

  // ── Background / Surface ─────────────────────────────────────────────────
  static const Color background   = Color(0xFFF5F7FA);
  static const Color surface      = Colors.white;
  static const Color border       = Color(0xFFE0E6F0);
  static const Color cardShadow   = Color(0x14000000);

  // ── Text ─────────────────────────────────────────────────────────────────
  static const Color textPrimary   = Color(0xFF0D1B3E);
  static const Color textSecondary = Color(0xFF546E7A);
  static const Color textMuted     = Color(0xFF90A4AE);

  // ── Gradients ────────────────────────────────────────────────────────────
  static const LinearGradient darkGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF0A1628), Color(0xFF1565C0)],
  );

  static const LinearGradient primaryGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF1565C0), Color(0xFF1E88E5)],
  );

  static const LinearGradient secondaryGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF0288D1), Color(0xFF29B6F6)],
  );

  static const LinearGradient accentGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF00838F), Color(0xFF26C6DA)],
  );

  static const LinearGradient successGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF2E7D32), Color(0xFF43A047)],
  );

  static const LinearGradient warningGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFFF57C00), Color(0xFFFFA726)],
  );

  static const LinearGradient dangerGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFFC62828), Color(0xFFE53935)],
  );

  static const LinearGradient infoGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF0277BD), Color(0xFF0288D1)],
  );

  // ── Card Decoration ───────────────────────────────────────────────────────
  static BoxDecoration cardDecoration({double radius = 16}) => BoxDecoration(
    color: surface,
    borderRadius: BorderRadius.circular(radius),
    border: Border.all(color: border, width: 1),
    boxShadow: const [
      BoxShadow(color: cardShadow, blurRadius: 12, offset: Offset(0, 4)),
    ],
  );
}
