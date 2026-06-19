// lib/services/chat_service.dart

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'api_client.dart';
import '../models/chat_room.dart';
import '../models/chat_message.dart';

final chatServiceProvider = Provider<ChatService>((ref) {
  return ChatService(ref.read(dioProvider));
});

class ChatService {
  final Dio _dio;
  ChatService(this._dio);

  /// 방 목록 + 대화 가능 사용자 목록
  Future<Map<String, dynamic>> getRooms() async {
    final res = await _dio.get('/chat/rooms');
    return res.data as Map<String, dynamic>;
  }

  /// 1:1 또는 그룹 방 생성 (또는 기존 방 ID 반환)
  Future<int> createRoom({
    required String type,
    required List<int> userIds,
    String? name,
  }) async {
    final res = await _dio.post('/chat/rooms', data: {
      'type':     type,
      'user_ids': userIds,
      if (name != null) 'name': name,
    });
    return res.data['room_id'] as int;
  }

  /// 메시지 목록 (페이징)
  Future<Map<String, dynamic>> getMessages(int roomId, {int page = 1}) async {
    final res = await _dio.get('/chat/rooms/$roomId/messages',
        queryParameters: {'page': page});
    return res.data as Map<String, dynamic>;
  }

  /// 텍스트 메시지 전송
  Future<ChatMessage> sendText(int roomId, String body) async {
    final res = await _dio.post('/chat/rooms/$roomId/messages',
        data: {'body': body});
    return ChatMessage.fromJson(res.data as Map<String, dynamic>);
  }

  /// 파일 첨부 전송
  Future<ChatMessage> sendFile(int roomId, String filePath, String fileName) async {
    final form = FormData.fromMap({
      'attachment': await MultipartFile.fromFile(filePath, filename: fileName),
    });
    final res = await _dio.post('/chat/rooms/$roomId/messages', data: form);
    return ChatMessage.fromJson(res.data as Map<String, dynamic>);
  }

  /// 읽음 처리
  Future<void> markRead(int roomId) async {
    await _dio.post('/chat/rooms/$roomId/read');
  }
}
