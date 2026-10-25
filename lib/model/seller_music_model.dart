import 'dart:convert';

class SellerMusicConfig {
  final bool marketplaceEnabled;
  final bool sellerSubmissionsEnabled;
  final int maxAudioUploadMb;
  final int effectiveMaxUploadMb;
  final List<String> allowedAudioFormats;
  final bool offerFreeEnabled;
  final bool offerRentEnabled;
  final bool offerSaleEnabled;
  final int defaultRentalDurationDays;
  final List<String> supportedRightsDeclarations;

  SellerMusicConfig({
    required this.marketplaceEnabled,
    required this.sellerSubmissionsEnabled,
    required this.maxAudioUploadMb,
    required this.effectiveMaxUploadMb,
    required this.allowedAudioFormats,
    required this.offerFreeEnabled,
    required this.offerRentEnabled,
    required this.offerSaleEnabled,
    required this.defaultRentalDurationDays,
    required this.supportedRightsDeclarations,
  });

  factory SellerMusicConfig.fromJson(Map<String, dynamic> json) {
    return SellerMusicConfig(
      marketplaceEnabled: json['music_marketplace_enabled'] == true,
      sellerSubmissionsEnabled: json['seller_submissions_enabled'] == true,
      maxAudioUploadMb: json['max_audio_upload_mb'] is int
          ? json['max_audio_upload_mb']
          : int.tryParse(json['max_audio_upload_mb']?.toString() ?? '30') ?? 30,
      effectiveMaxUploadMb: json['effective_max_upload_mb'] is int
          ? json['effective_max_upload_mb']
          : int.tryParse(json['effective_max_upload_mb']?.toString() ?? '30') ?? 30,
      allowedAudioFormats: (json['allowed_audio_formats'] as List<dynamic>?)
              ?.map((e) => e.toString().toLowerCase())
              .toList() ??
          ['mp3', 'wav', 'flac', 'aac', 'm4a'],
      offerFreeEnabled: json['offer_free_enabled'] == true,
      offerRentEnabled: json['offer_rent_enabled'] == true,
      offerSaleEnabled: json['offer_sale_enabled'] == true,
      defaultRentalDurationDays: json['default_rental_duration_days'] is int
          ? json['default_rental_duration_days']
          : int.tryParse(json['default_rental_duration_days']?.toString() ?? '7') ?? 7,
      supportedRightsDeclarations: (json['supported_rights_declarations'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          ['exclusive', 'non_exclusive', 'public_domain'],
    );
  }
}

class SellerMusicGenre {
  final int id;
  final String name;
  final String slug;

  SellerMusicGenre({
    required this.id,
    required this.name,
    required this.slug,
  });

  factory SellerMusicGenre.fromJson(Map<String, dynamic> json) {
    return SellerMusicGenre(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      name: json['name']?.toString() ?? '',
      slug: json['slug']?.toString() ?? '',
    );
  }
}

class SellerMusicOffer {
  final int? id;
  final String offerType;
  final double price;
  final String currency;
  final int? rentalDurationDays;
  final bool isActive;

  SellerMusicOffer({
    this.id,
    required this.offerType,
    required this.price,
    this.currency = 'SAR',
    this.rentalDurationDays,
    this.isActive = true,
  });

  factory SellerMusicOffer.fromJson(Map<String, dynamic> json) {
    return SellerMusicOffer(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? ''),
      offerType: json['offer_type']?.toString().toLowerCase() ?? 'free',
      price: json['price'] is num
          ? (json['price'] as num).toDouble()
          : double.tryParse(json['price']?.toString() ?? '0') ?? 0.0,
      currency: json['currency']?.toString() ?? 'SAR',
      rentalDurationDays: json['rental_duration_days'] is int
          ? json['rental_duration_days']
          : int.tryParse(json['rental_duration_days']?.toString() ?? ''),
      isActive: json['is_active'] == true || json['is_active'] == 1,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      if (id != null) 'id': id,
      'offer_type': offerType,
      'price': price,
      'currency': currency,
      if (rentalDurationDays != null) 'rental_duration_days': rentalDurationDays,
      'is_active': isActive ? 1 : 0,
    };
  }
}

class SellerMusicTrack {
  final int id;
  final String title;
  final String? artistName;
  final String? description;
  final int? genreId;
  final String? genreName;
  final List<String> secondaryGenres;
  final String status;
  final String mediaStatus;
  final String? coverImageUrl;
  final int? durationSeconds;
  final String? audioFormat;
  final String? rejectionReason;
  final String? adminNotes;
  final String? processingError;
  final String? rightsDeclaration;
  final String? rightsNotes;
  final String? submittedAt;
  final String? createdAt;
  final List<SellerMusicOffer> offers;

  SellerMusicTrack({
    required this.id,
    required this.title,
    this.artistName,
    this.description,
    this.genreId,
    this.genreName,
    this.secondaryGenres = const [],
    required this.status,
    required this.mediaStatus,
    this.coverImageUrl,
    this.durationSeconds,
    this.audioFormat,
    this.rejectionReason,
    this.adminNotes,
    this.processingError,
    this.rightsDeclaration,
    this.rightsNotes,
    this.submittedAt,
    this.createdAt,
    this.offers = const [],
  });

  bool get isDraft => status == 'draft';
  bool get isPendingReview => status == 'pending';
  bool get isApproved => status == 'approved';
  bool get isRejected => status == 'rejected';
  bool get isSuspended => status == 'suspended';

  bool get isMediaProcessing => mediaStatus == 'processing' || mediaStatus == 'pending';
  bool get isMediaFailed => mediaStatus == 'failed';
  bool get isMediaReady => mediaStatus == 'ready';

  String get formattedDuration {
    if (durationSeconds == null || durationSeconds! <= 0) return '—';
    final m = durationSeconds! ~/ 60;
    final s = durationSeconds! % 60;
    return '$m:${s < 10 ? '0' : ''}$s';
  }

  factory SellerMusicTrack.fromJson(Map<String, dynamic> json) {
    List<String> secGenres = [];
    if (json['secondary_genres'] is List) {
      secGenres = (json['secondary_genres'] as List).map((e) => e.toString()).toList();
    } else if (json['secondary_genres'] is String && json['secondary_genres'].isNotEmpty) {
      try {
        final decoded = jsonDecode(json['secondary_genres']);
        if (decoded is List) {
          secGenres = decoded.map((e) => e.toString()).toList();
        }
      } catch (_) {}
    }

    List<SellerMusicOffer> parsedOffers = [];
    if (json['offers'] is List) {
      parsedOffers = (json['offers'] as List)
          .map((o) => SellerMusicOffer.fromJson(o as Map<String, dynamic>))
          .toList();
    }

    return SellerMusicTrack(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      title: json['title']?.toString() ?? '',
      artistName: json['artist_name']?.toString(),
      description: json['description']?.toString(),
      genreId: json['genre_id'] is int ? json['genre_id'] : int.tryParse(json['genre_id']?.toString() ?? ''),
      genreName: json['genre_name']?.toString() ?? (json['genre'] is Map ? json['genre']['name']?.toString() : null),
      secondaryGenres: secGenres,
      status: json['status']?.toString().toLowerCase() ?? 'pending',
      mediaStatus: json['media_status']?.toString().toLowerCase() ?? 'draft',
      coverImageUrl: json['cover_image_url']?.toString(),
      durationSeconds: json['duration_seconds'] is int
          ? json['duration_seconds']
          : int.tryParse(json['duration_seconds']?.toString() ?? ''),
      audioFormat: json['audio_format']?.toString(),
      rejectionReason: json['rejection_reason']?.toString(),
      adminNotes: json['admin_notes']?.toString(),
      processingError: json['processing_error']?.toString(),
      rightsDeclaration: json['rights_declaration']?.toString(),
      rightsNotes: json['rights_notes']?.toString(),
      submittedAt: json['submitted_at']?.toString(),
      createdAt: json['created_at']?.toString(),
      offers: parsedOffers,
    );
  }
}
