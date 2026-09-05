// lib/screens/prescription_upload_screen.dart

import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
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
  /* 유형을 고르고 찍어 담기를 되풀이한 뒤, 마지막에 한꺼번에 올린다.
     한 장씩 올리면 서류가 여럿인 환자는 화면을 여러 번 오가야 했다. */
  final List<_PendingDoc> _queue = [];

  bool    _uploading    = false;
  int     _uploadDone   = 0;      // 올라간 건수
  int     _uploadTotal  = 0;      // 올릴 건수
  double  _fileProgress = 0.0;    // 지금 보내는 한 건의 전송 진행률
  bool    _isSaving     = false;  // true = 올린 뒤 서버가 저장을 마치기를 기다리는 중
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

  /* 유형마다 한 번에 올릴 수 있는 수 — 웹 업로드(PrescriptionController::overDocLimit)와
     같은 규칙이다. 처방전이 두 장이면 처방전이 두 건으로 갈라지고 주문도 둘이 된다. */
  static const _docLimits = {'prescription': 1, 'registration_form': 1};
  static const _maxDocs   = 40;   // 웹 업로드의 전체 제한과 같다

  static String _labelOf(String code) =>
      _docTypes.firstWhere((t) => t.$1 == code, orElse: () => (code, code)).$2;

  /// 「처방전는」이 되지 않게 받침을 본다 — 웹의 hasFinalConsonant 와 같은 셈이다.
  static String _josa(String word, String withFinal, String without) {
    if (word.isEmpty) return without;
    final c = word.codeUnitAt(word.length - 1);
    if (c < 0xAC00 || c > 0xD7A3) return without;
    return (c - 0xAC00) % 28 != 0 ? withFinal : without;
  }

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
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 18, 20, 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('이미 등록된 환자 ${results.length}명',
                      style: const TextStyle(
                          fontSize: 16, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 4),
                  const Text('생년월일로 같은 사람인지 확인하고 고르십시오.',
                      style: TextStyle(
                          fontSize: 12, color: AppTheme.textSecondary)),
                ],
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
                    /* 이름만 보이면 동명이인을 가릴 수 없다. 생년월일을 앞에 세우고
                       연락처를 뒤에 둔다 — 둘 다 없으면 고르지 말라는 뜻이다. */
                    subtitle: Row(
                      children: [
                        Icon(Icons.cake_outlined,
                            size: 13,
                            color: p.birthDate == null
                                ? AppTheme.danger
                                : AppTheme.textMuted),
                        const SizedBox(width: 4),
                        Text(p.birthDate ?? '생년월일 없음',
                            style: TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                                color: p.birthDate == null
                                    ? AppTheme.danger
                                    : AppTheme.textPrimary)),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(p.mobile,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                  fontSize: 12, color: AppTheme.textMuted)),
                        ),
                      ],
                    ),
                    onTap: () => Navigator.pop(ctx, p),
                  );
                },
              ),
            ),
            const Divider(height: 1, color: AppTheme.border),
            /* 같은 이름이 있어도 이 사람이 아닐 수 있다. 여기서 길을 내지 않으면
               담당자는 남의 이름에 서류를 붙이거나 올리기를 그만둔다. */
            ListTile(
              leading: const Icon(Icons.person_add_alt_1_outlined,
                  color: AppTheme.primary),
              title: const Text('찾는 사람이 없습니다 — 새로 등록',
                  style: TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 14,
                      color: AppTheme.primary)),
              onTap: () => Navigator.pop(ctx, _registerInstead),
            ),
            const SizedBox(height: 12),
          ],
        ),
      ),
    );

    if (!mounted || picked == null) return;

    if (identical(picked, _registerInstead)) {
      await _showRegisterDialog(_nameCtrl.text.trim());
      return;
    }

    setState(() {
      _selectedPatient = picked;
      _nameCtrl.text   = picked.name;
    });
  }

  /// 시트에서 「새로 등록」을 고른 것을 알리는 표. 값 자체에는 뜻이 없다.
  static const _registerInstead =
      PatientOption(id: -1, name: '', mobile: '');

  Future<void> _showRegisterDialog(String initialName) async {
    final nameCtrl     = TextEditingController(text: initialName);
    final residentCtrl = TextEditingController();
    bool    submitting    = false;
    String? residentError;

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
                  inputFormatters: [_ResidentNoFormatter()],
                  decoration: InputDecoration(
                    labelText: '주민등록번호',
                    hintText: 'XXXXXX-XXXXXXX',
                    counterText: '',
                    errorText: residentError,
                  ),
                  onChanged: (_) {
                    if (residentError != null) {
                      setDialogState(() => residentError = null);
                    }
                  },
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

                        /* 빈칸은 그대로 둔다(서버도 받지 않아도 된다). 다만 적다 만
                           번호는 받지 않는다 — 열세 자리를 채워야 공단에 낼 수 있고,
                           반쪽 번호는 나중에 누가 다시 물어야 한다. */
                        final rrn = residentCtrl.text.trim();
                        final digits = rrn.replaceAll(RegExp(r'\D'), '');
                        if (digits.isNotEmpty && digits.length != 13) {
                          setDialogState(() =>
                              residentError = '주민등록번호 13자리를 모두 입력해 주세요.');
                          return;
                        }

                        setDialogState(() => submitting = true);
                        try {
                          final patient = await ref
                              .read(patientServiceProvider)
                              .create(
                                name: name,
                                residentNo: rrn,
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
    _addToQueue(file, file.path.split('/').last);
  }

  Future<void> _pickImage(ImageSource source) async {
    final picked = await ImagePicker().pickImage(
        source: source, imageQuality: 85, maxWidth: 2048);
    if (picked == null) return;
    _addToQueue(File(picked.path), picked.name);
  }

  Future<void> _pickFile() async {
    await _pickImage(ImageSource.gallery);
  }

  /// 지금 고른 유형으로 목록에 담는다. 담을 수 없으면 까닭을 알린다 —
  /// 올릴 때가 되어서야 서버에 막히면 찍은 것을 도로 버려야 한다.
  void _addToQueue(File file, String fileName) {
    final label = _labelOf(_docType);

    if (_queue.length >= _maxDocs) {
      _tell('한 번에 $_maxDocs장까지 담을 수 있습니다.');
      return;
    }

    final limit = _docLimits[_docType];
    if (limit != null) {
      final already = _queue.where((d) => d.docType == _docType).length;
      if (already >= limit) {
        _tell('$label${_josa(label, '은', '는')} 한 번에 $limit건까지 올릴 수 있습니다.');
        return;
      }
    }

    setState(() {
      _queue.add(_PendingDoc(
        file: file, fileName: fileName, docType: _docType, docLabel: label));
      _resultMsg = null;
      _ocrResult = null;
      _success   = false;
    });
  }

  void _removeFromQueue(int index) {
    setState(() => _queue.removeAt(index));
  }

  void _tell(String message) {
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }

  void _resetForm() {
    setState(() {
      _queue.clear();
      _resultMsg    = null;
      _ocrResult    = null;
      _success      = false;
      _uploadDone   = 0;
      _uploadTotal  = 0;
      _fileProgress = 0.0;
      _isSaving     = false;
      _docType      = 'registration_form';

      /* 환자도 지운다. 앞사람 이름이 남아 있으면 다음 사람 서류를 그대로 올려
         엉뚱한 환자에게 붙는다 — 되돌리려면 담당자가 웹에서 손으로 옮겨야 한다. */
      _selectedPatient = null;
    });
    _nameCtrl.clear();
    _memoCtrl.clear();
  }

  @override
  void dispose() {
    _memoCtrl.dispose();
    _nameCtrl.dispose();
    super.dispose();
  }

  Future<void> _uploadAll() async {
    if (_queue.isEmpty || _selectedPatient == null) return;

    /* 처방전을 맨 앞에 세운다. 서버는 처방전이 아닌 서류를 「이 환자의 가장 최근
       처방전」에 붙이므로, 첨부가 먼저 가면 오늘 것이 아니라 예전 처방전에 붙는다.
       그러면 오늘 올린 처방전과 갈라져, 공단 팩스에 첨부가 빠진 채로 나간다. */
    final ordered = [
      ..._queue.where((d) => d.docType == 'prescription'),
      ..._queue.where((d) => d.docType != 'prescription'),
    ];

    setState(() {
      _uploading    = true;
      _uploadDone   = 0;
      _uploadTotal  = ordered.length;
      _fileProgress = 0.0;
      _isSaving     = false;
      _resultMsg    = null;
      _ocrResult    = null;
    });

    final dio  = ref.read(dioProvider);
    final memo = _memoCtrl.text.trim();
    final sent = <_PendingDoc>[];
    Map<String, dynamic>? firstResult;
    String? failure;

    for (final doc in ordered) {
      try {
        if (mounted) {
          setState(() {
            _fileProgress = 0.0;
            _isSaving     = false;
          });
        }

        final form = FormData.fromMap({
          'prescription_image': await MultipartFile.fromFile(
            doc.file.path,
            filename: doc.fileName,
          ),
          'patient_id': _selectedPatient!.id,
          'doc_type':   doc.docType,
          // 메모는 첫 건에만 싣는다 — 건마다 보내면 같은 말이 여러 장에 남는다
          if (memo.isNotEmpty && sent.isEmpty) 'memo': memo,
        });

        final resp = await dio.post(
          '/prescriptions/upload',
          data: form,
          onSendProgress: (bytes, total) {
            if (total > 0 && mounted) {
              setState(() {
                _fileProgress = bytes / total;
                if (_fileProgress >= 1.0) _isSaving = true;
              });
            }
          },
        );

        sent.add(doc);
        final body = resp.data;
        if (body is Map) {
          firstResult ??= body['ocr_result'] as Map<String, dynamic>?;
        }

        if (mounted) setState(() => _uploadDone = sent.length);

      } on DioException catch (e) {
        final body = e.response?.data;
        failure = (body is Map ? body['message'] as String? : null) ??
            '${doc.docLabel} 업로드 실패: ${e.message}';
        break;
      } catch (e) {
        failure = '${doc.docLabel} 업로드 중 오류가 발생했습니다: $e';
        break;
      }
    }

    if (!mounted) return;

    setState(() {
      _uploading = false;
      _isSaving  = false;

      /* 올라간 것은 목록에서 덜어 낸다 — 그대로 두고 다시 누르면 같은 서류가
         두 번 올라간다. 남은 것은 남겨 두어 이어서 올릴 수 있게 한다. */
      _queue.removeWhere(sent.contains);

      if (failure == null) {
        _success   = true;
        _resultMsg = '${sent.length}건을 등록했습니다.';
        _ocrResult = firstResult;
        _memoCtrl.clear();
      } else {
        _success   = false;
        _resultMsg = sent.isEmpty
            ? failure
            : '$_uploadTotal건 가운데 ${sent.length}건을 올렸습니다. '
              '남은 ${_queue.length}건은 그대로 두었습니다 — $failure';
      }
    });
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

                  // 찍어 담는 자리
                  _ImagePickArea(
                    docLabel: _labelOf(_docType),
                    onCamera: _openCamera,
                    onGallery: _pickFile,
                  ),

                  // 담아 둔 서류
                  if (_queue.isNotEmpty) ...[
                    const SizedBox(height: 14),
                    _QueueCard(
                      docs: _queue,
                      onRemove: _uploading ? null : _removeFromQueue,
                    ),
                  ],
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
                            done: _uploadDone,
                            total: _uploadTotal,
                            fileProgress: _fileProgress,
                            isSaving: _isSaving,
                          )
                        : GradientButton(
                            key: const ValueKey('btn'),
                            label: _success
                                ? '새로 올리기'
                                : (_queue.isEmpty
                                    ? '처방전 업로드'
                                    : '처방전 업로드 (${_queue.length}건)'),
                            icon: _success
                                ? Icons.add_photo_alternate_outlined
                                : Icons.cloud_upload_outlined,
                            onPressed: _success
                                ? _resetForm
                                : ((_queue.isEmpty || _selectedPatient == null)
                                    ? null
                                    : _uploadAll),
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

/// 담아 둘 서류 한 장 — 파일과 그때 고른 유형을 함께 쥔다.
/// 유형은 담는 순간에 정해진다. 나중에 드롭다운을 바꿔도 이미 담은 것은 그대로다.
class _PendingDoc {
  final File   file;
  final String fileName;
  final String docType;
  final String docLabel;

  const _PendingDoc({
    required this.file,
    required this.fileName,
    required this.docType,
    required this.docLabel,
  });
}

/// 담아 둔 서류 목록 — 무엇을 몇 장 올릴지 올리기 전에 눈으로 본다.
class _QueueCard extends StatelessWidget {
  final List<_PendingDoc>   docs;
  final void Function(int)? onRemove;   // null 이면 올리는 중이라 뺄 수 없다

  const _QueueCard({required this.docs, required this.onRemove});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: AppTheme.cardDecoration(radius: 16),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Text('담은 서류',
                  style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: AppTheme.textSecondary)),
              const SizedBox(width: 6),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(
                  color: AppTheme.primary.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text('${docs.length}건',
                    style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                        color: AppTheme.primary)),
              ),
            ],
          ),
          const SizedBox(height: 12),
          for (var i = 0; i < docs.length; i++) ...[
            if (i > 0) const SizedBox(height: 8),
            _QueueTile(
              doc: docs[i],
              onRemove: onRemove == null ? null : () => onRemove!(i),
            ),
          ],
        ],
      ),
    );
  }
}

class _QueueTile extends StatelessWidget {
  final _PendingDoc    doc;
  final VoidCallback?  onRemove;

  const _QueueTile({required this.doc, required this.onRemove});

  @override
  Widget build(BuildContext context) {
    final isPdf = doc.fileName.toLowerCase().endsWith('.pdf');

    return Row(
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(10),
          child: SizedBox(
            width: 44,
            height: 44,
            child: isPdf
                ? Container(
                    color: AppTheme.background,
                    child: const Icon(Icons.picture_as_pdf_outlined,
                        size: 20, color: AppTheme.textMuted),
                  )
                : Image.file(doc.file, fit: BoxFit.cover),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(doc.docLabel,
                  style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: AppTheme.textPrimary)),
              const SizedBox(height: 2),
              Text(doc.fileName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                      fontSize: 11, color: AppTheme.textMuted)),
            ],
          ),
        ),
        if (onRemove != null)
          IconButton(
            onPressed: onRemove,
            icon: const Icon(Icons.close_rounded,
                size: 18, color: AppTheme.textMuted),
            tooltip: '빼기',
          ),
      ],
    );
  }
}

/// 찍거나 골라서 담는 자리. 담은 것은 아래 목록에 쌓이므로 여기서는 미리보기를
/// 두지 않는다 — 마지막에 담은 한 장만 크게 보이면 몇 장을 담았는지 알 수 없다.
class _ImagePickArea extends StatelessWidget {
  final String       docLabel;
  final VoidCallback onCamera;
  final VoidCallback onGallery;

  const _ImagePickArea({
    required this.docLabel,
    required this.onCamera,
    required this.onGallery,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 200,
      decoration: BoxDecoration(
        color: AppTheme.background,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppTheme.border),
      ),
      child: Column(
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
          Text('$docLabel(으)로 담습니다',
              style: const TextStyle(
                  color: AppTheme.textSecondary,
                  fontSize: 14,
                  fontWeight: FontWeight.w600)),
          const SizedBox(height: 4),
          const Text('유형을 바꿔 가며 여러 장을 담을 수 있습니다',
              style: TextStyle(color: AppTheme.textMuted, fontSize: 12)),
          const SizedBox(height: 14),
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
  final int    done;          // 올라간 건수
  final int    total;         // 올릴 건수
  final double fileProgress;  // 지금 보내는 한 건의 전송 진행률
  final bool   isSaving;
  const _UploadProgressCard({
    super.key,
    required this.done,
    required this.total,
    required this.fileProgress,
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
    /* 진행 막대는 건수로 센다 — 「몇 장 가운데 몇 장」이 올리는 사람이 알고 싶은
       것이다. 지금 보내는 한 건이 얼마나 갔는지는 그 한 칸 안을 채워 보인다. */
    final total = widget.total == 0 ? 1 : widget.total;
    final value = (widget.done + widget.fileProgress.clamp(0.0, 1.0)) / total;

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
                Text('서류 업로드 중...',
                    style: TextStyle(
                        fontWeight: FontWeight.w600,
                        color: AppTheme.textSecondary,
                        fontSize: 14)),
              ],
            ),
            Text('${widget.done} / ${widget.total}건',
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
            value: value.clamp(0.0, 1.0),
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
              // 여러 장을 잇달아 올리는 동안 몇 번째인지 잃지 않게 건수를 함께 둔다
              Text('${widget.done + 1} / ${widget.total}건째를 등록하고 있습니다',
                  style: const TextStyle(
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

/// 주민등록번호 입력 — 숫자만 받아 여섯 자리 뒤에 붙임표를 넣고 열세 자리에서 끊는다.
/// 웹 거래처 등록과 같은 모양(XXXXXX-XXXXXXX)으로 받는다. 붙임표를 사람이 치게
/// 두면 열네 자리를 넘겨 적거나 아예 빼먹어, 같은 번호가 두 모양으로 쌓인다.
class _ResidentNoFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(
      TextEditingValue oldValue, TextEditingValue newValue) {
    final digits = newValue.text.replaceAll(RegExp(r'\D'), '');
    final capped = digits.length > 13 ? digits.substring(0, 13) : digits;

    final text = capped.length > 6
        ? '${capped.substring(0, 6)}-${capped.substring(6)}'
        : capped;

    return TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
  }
}
