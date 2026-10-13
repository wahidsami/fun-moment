import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:funmoments/view/utils/others_helper.dart';

/// Strongly-typed immutable model representing a currently published
/// Terms & Conditions document version fetched from the backend.
class PublishedTerms {
  final int documentId;
  final int versionId;
  final int versionNumber;
  final String role;
  final String language;
  final String title;
  final String content;
  final String contentHash;
  final String? publishedAt;

  const PublishedTerms({
    required this.documentId,
    required this.versionId,
    required this.versionNumber,
    required this.role,
    required this.language,
    required this.title,
    required this.content,
    required this.contentHash,
    this.publishedAt,
  });

  factory PublishedTerms.fromJson(Map<String, dynamic> json) {
    final docId = int.tryParse(json['document_id']?.toString() ?? '') ?? 0;
    final verId = int.tryParse(json['version_id']?.toString() ?? '') ?? 0;
    final verNum = int.tryParse(json['version_number']?.toString() ?? '') ?? 0;
    final role = json['role']?.toString() ?? '';
    final lang = json['language']?.toString() ?? '';
    final title = json['title']?.toString() ?? '';
    final content = json['content']?.toString() ?? '';
    final hash = json['content_hash']?.toString() ?? '';
    final pubAt = json['published_at']?.toString();

    if (verId <= 0 || title.isEmpty || content.isEmpty) {
      throw const FormatException('Malformed terms payload: missing required fields or empty content');
    }

    return PublishedTerms(
      documentId: docId,
      versionId: verId,
      versionNumber: verNum,
      role: role,
      language: lang,
      title: title,
      content: content,
      contentHash: hash,
      publishedAt: pubAt,
    );
  }

  Map<String, dynamic> toJson() => {
    'document_id': documentId,
    'version_id': versionId,
    'version_number': versionNumber,
    'role': role,
    'language': language,
    'title': title,
    'content': content,
    'content_hash': contentHash,
    'published_at': publishedAt,
  };
}

/// Thrown when terms are not published or unavailable (HTTP 404).
class TermsUnavailableException implements Exception {
  final String message;
  const TermsUnavailableException([this.message = 'Terms & Conditions are not currently available.']);

  @override
  String toString() => message;
}

/// Thrown for network failure or timeouts.
class TermsNetworkException implements Exception {
  final String message;
  const TermsNetworkException([this.message = 'Network error while loading Terms & Conditions.']);

  @override
  String toString() => message;
}

/// General terms exception.
class TermsException implements Exception {
  final String message;
  const TermsException([this.message = 'Failed to load Terms & Conditions.']);

  @override
  String toString() => message;
}

/// Reusable service for fetching published Terms & Conditions from the backend API.
class TermsService {
  /// Fetch the currently published Terms & Conditions for a given [role] ('buyer' | 'seller')
  /// and [lang] ('ar' | 'en').
  ///
  /// Never displays draft or fallback content.
  /// Does not cache stale content across separate registration attempts.
  Future<PublishedTerms> fetchCurrentTerms({
    required String role,
    required String lang,
    http.Client? client,
    Duration timeout = const Duration(seconds: 15),
  }) async {
    final sanitizedRole = role.trim().toLowerCase();
    final sanitizedLang = lang.trim().toLowerCase().startsWith('ar') ? 'ar' : 'en';

    if (sanitizedRole != 'buyer' && sanitizedRole != 'seller') {
      throw ArgumentError.value(role, 'role', 'Role must be either "buyer" or "seller"');
    }

    final queryParameters = {
      'role': sanitizedRole,
      'lang': sanitizedLang,
    };

    final uri = Uri.parse('$baseApi/terms/current').replace(
      queryParameters: queryParameters,
    );

    final httpClient = client ?? http.Client();
    final shouldCloseClient = client == null;

    try {
      final response = await httpClient
          .get(
            uri,
            headers: const {
              'Accept': 'application/json',
            },
          )
          .timeout(timeout);

      if (response.statusCode == 200) {
        final decoded = jsonDecode(response.body);
        if (decoded is Map<String, dynamic> && decoded['data'] is Map<String, dynamic>) {
          return PublishedTerms.fromJson(decoded['data'] as Map<String, dynamic>);
        } else {
          throw const FormatException('Invalid terms response format');
        }
      } else if (response.statusCode == 404) {
        String msg = 'Terms & Conditions are not currently available for this role and language.';
        try {
          final decoded = jsonDecode(response.body);
          if (decoded is Map && decoded['message'] != null) {
            msg = decoded['message'].toString();
          }
        } catch (_) {}
        throw TermsUnavailableException(msg);
      } else {
        String msg = 'Failed to load Terms & Conditions (${response.statusCode})';
        try {
          final decoded = jsonDecode(response.body);
          if (decoded is Map && decoded['message'] != null) {
            msg = decoded['message'].toString();
          }
        } catch (_) {}
        throw TermsException(msg);
      }
    } on SocketException catch (e) {
      debugPrint('[TermsService] SocketException: $e');
      throw const TermsNetworkException('Please check your internet connection.');
    } on TimeoutException catch (e) {
      debugPrint('[TermsService] TimeoutException: $e');
      throw const TermsNetworkException('Connection timed out. Please try again.');
    } on http.ClientException catch (e) {
      debugPrint('[TermsService] ClientException: $e');
      throw const TermsNetworkException('Network connection failed. Please try again.');
    } on TermsUnavailableException {
      rethrow;
    } on TermsException {
      rethrow;
    } on FormatException catch (e) {
      debugPrint('[TermsService] FormatException: $e');
      throw TermsException(e.message);
    } catch (e) {
      debugPrint('[TermsService] Unexpected error: $e');
      throw TermsException(e.toString());
    } finally {
      if (shouldCloseClient) {
        httpClient.close();
      }
    }
  }
}
