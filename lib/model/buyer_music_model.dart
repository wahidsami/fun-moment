import 'dart:convert';
import 'package:funmoments/view/utils/others_helper.dart';

class CatalogGenreModel {
  final int id;
  final String nameEn;
  final String nameAr;
  final String slug;
  final String? icon;
  final int sortOrder;

  CatalogGenreModel({
    required this.id,
    String? name,
    String? nameEn,
    String? nameAr,
    required this.slug,
    this.icon,
    this.sortOrder = 0,
  })  : nameEn = (nameEn != null && nameEn.trim().isNotEmpty)
            ? nameEn.trim()
            : ((name != null && name.trim().isNotEmpty) ? name.trim() : ''),
        nameAr = (nameAr != null && nameAr.trim().isNotEmpty)
            ? nameAr.trim()
            : ((name != null && name.trim().isNotEmpty) ? name.trim() : '');

  /// Localized name based on active language, falling back to other language, then slug.
  String displayName(bool isArabic) {
    if (isArabic) {
      if (nameAr.isNotEmpty) return nameAr;
      if (nameEn.isNotEmpty) return nameEn;
    } else {
      if (nameEn.isNotEmpty) return nameEn;
      if (nameAr.isNotEmpty) return nameAr;
    }
    return slug;
  }

  /// Default getter for backward compatibility or when locale is unknown.
  String get name {
    if (nameEn.isNotEmpty) return nameEn;
    if (nameAr.isNotEmpty) return nameAr;
    return slug;
  }

  factory CatalogGenreModel.fromJson(Map<String, dynamic> json) {
    final idVal = json['id'] is int
        ? json['id'] as int
        : int.tryParse(json['id']?.toString() ?? '0') ?? 0;
    final nameVal = json['name']?.toString().trim();
    final nameEnVal = (json['name_en'] ?? json['nameEn'])?.toString().trim();
    final nameArVal = (json['name_ar'] ?? json['nameAr'])?.toString().trim();
    final slugVal = json['slug']?.toString().trim() ?? '';
    final iconVal = json['icon']?.toString().trim();
    final sortVal = json['sort_order'] is int
        ? json['sort_order'] as int
        : int.tryParse(json['sort_order']?.toString() ?? '0') ?? 0;

    return CatalogGenreModel(
      id: idVal,
      name: nameVal,
      nameEn: nameEnVal,
      nameAr: nameArVal,
      slug: slugVal,
      icon: iconVal,
      sortOrder: sortVal,
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'name_en': nameEn,
    'name_ar': nameAr,
    'slug': slug,
    'icon': icon,
    'sort_order': sortOrder,
  };
}

class CatalogOfferModel {
  final int id;
  final String offerType; // 'free', 'rent', 'sale'
  final double price;
  final String currency;
  final int? rentalDurationDays;
  final bool isActive;

  CatalogOfferModel({
    required this.id,
    required this.offerType,
    required this.price,
    required this.currency,
    this.rentalDurationDays,
    required this.isActive,
  });

  factory CatalogOfferModel.fromJson(Map<String, dynamic> json) {
    return CatalogOfferModel(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      offerType: json['offer_type']?.toString().toLowerCase() ?? 'free',
      price: json['price'] is num
          ? (json['price'] as num).toDouble()
          : double.tryParse(json['price']?.toString() ?? '0') ?? 0.0,
      currency: json['currency']?.toString() ?? 'SAR',
      rentalDurationDays: json['rental_duration_days'] != null
          ? (json['rental_duration_days'] is int
              ? json['rental_duration_days']
              : int.tryParse(json['rental_duration_days'].toString()))
          : null,
      isActive: json['is_active'] == true || json['is_active'] == 1,
    );
  }

  String get formattedPrice {
    if (offerType == 'free' || price == 0) {
      return 'Free';
    }
    return '${price.toStringAsFixed(2)} $currency';
  }

  bool get isFree => offerType == 'free';
  bool get isRent => offerType == 'rent';
  bool get isSale => offerType == 'sale';
}

class CatalogSellerModel {
  final int id;
  final String name;
  final String? image;

  CatalogSellerModel({
    required this.id,
    required this.name,
    this.image,
  });

  factory CatalogSellerModel.fromJson(Map<String, dynamic> json) {
    return CatalogSellerModel(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      name: json['name']?.toString() ?? 'Artist',
      image: json['image']?.toString(),
    );
  }
}

class CatalogAlbumModel {
  final int id;
  final String title;
  final String titleEn;
  final String titleAr;
  final String? artistName;
  final String? coverImageUrl;
  final int tracksCount;
  final String? descriptionEn;
  final String? descriptionAr;
  final String? releasedAt;
  final CatalogGenreModel? genre;
  final List<CatalogTrackModel> tracks;

  CatalogAlbumModel({
    required this.id,
    required this.title,
    required this.titleEn,
    required this.titleAr,
    this.artistName,
    this.coverImageUrl,
    this.tracksCount = 0,
    this.descriptionEn,
    this.descriptionAr,
    this.releasedAt,
    this.genre,
    this.tracks = const [],
  });

  factory CatalogAlbumModel.fromJson(Map<String, dynamic> json) {
    final rawCover = json['cover_image_url'] ?? json['cover_image'];
    final resolvedCover = CatalogTrackModel.resolveCoverUrl(rawCover);

    List<CatalogTrackModel> parsedTracks = [];
    if (json['tracks'] is List) {
      parsedTracks = (json['tracks'] as List)
          .whereType<Map<String, dynamic>>()
          .map((t) => CatalogTrackModel.fromJson(t))
          .toList()
        ..sort((a, b) => (a.trackNumber ?? 0).compareTo(b.trackNumber ?? 0));
    }

    CatalogGenreModel? parsedGenre;
    if (json['genre'] is Map<String, dynamic>) {
      parsedGenre = CatalogGenreModel.fromJson(json['genre'] as Map<String, dynamic>);
    }

    final dynamic rawCount = json['tracks_count'] ?? json['tracksCount'];
    final int resolvedCount = parsedTracks.isNotEmpty
        ? parsedTracks.length
        : (rawCount is int ? rawCount : int.tryParse(rawCount?.toString() ?? '0') ?? 0);

    final String? effectiveCover = resolvedCover ??
        (parsedTracks.isNotEmpty ? parsedTracks.first.coverArtUrl : null);

    return CatalogAlbumModel(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      title: json['title']?.toString() ?? json['title_en']?.toString() ?? '',
      titleEn: json['title_en']?.toString() ?? '',
      titleAr: json['title_ar']?.toString() ?? '',
      artistName: json['artist_name']?.toString(),
      coverImageUrl: effectiveCover,
      tracksCount: resolvedCount,
      descriptionEn: json['description_en']?.toString(),
      descriptionAr: json['description_ar']?.toString(),
      releasedAt: json['released_at']?.toString(),
      genre: parsedGenre,
      tracks: parsedTracks,
    );
  }

  String displayTitle(bool isArabic) {
    if (isArabic && titleAr.isNotEmpty) return titleAr;
    if (titleEn.isNotEmpty) return titleEn;
    if (title.isNotEmpty) return title;
    return titleAr;
  }

  String displayDescription(bool isArabic) {
    if (isArabic && (descriptionAr?.isNotEmpty ?? false)) return descriptionAr!;
    if (descriptionEn?.isNotEmpty ?? false) return descriptionEn!;
    return '';
  }

  String get displayArtist =>
      (artistName != null && artistName!.isNotEmpty) ? artistName! : 'FUN MOMENT Originals';

  int get totalDurationSeconds {
    if (tracks.isEmpty) return 0;
    return tracks.fold<int>(0, (sum, t) => sum + t.durationSeconds);
  }

  String get formattedTotalDuration {
    final totalSec = totalDurationSeconds;
    if (totalSec <= 0) return '';
    final m = totalSec ~/ 60;
    final s = totalSec % 60;
    return '${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
  }
}

class CatalogOriginalTrackModel {
  final int id;
  final String title;
  final String titleEn;
  final String titleAr;
  final String artistName;

  CatalogOriginalTrackModel({
    required this.id,
    required this.title,
    required this.titleEn,
    required this.titleAr,
    required this.artistName,
  });

  factory CatalogOriginalTrackModel.fromJson(Map<String, dynamic> json) {
    return CatalogOriginalTrackModel(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      title: json['title']?.toString() ?? json['title_en']?.toString() ?? '',
      titleEn: json['title_en']?.toString() ?? '',
      titleAr: json['title_ar']?.toString() ?? '',
      artistName: json['artist_name']?.toString() ?? '',
    );
  }
}

class CatalogTrackModel {
  final int id;
  final String titleEn;
  final String titleAr;
  final String artistName;
  final int durationSeconds;
  final int previewDurationSeconds;
  final int? bpm;
  final String? musicalKey;
  final String? coverImageUrl;
  String? get coverArtUrl => coverImageUrl;
  final String? waveformUrl;
  final String streamTicketUrl;
  bool isFavorite;
  final int playCount;
  final CatalogGenreModel? primaryGenre;
  final List<CatalogGenreModel> genres;
  final List<CatalogOfferModel> offers;
  final String? rightsDeclaration;
  final CatalogSellerModel? seller;
  final String? releasedAt;
  final bool isFirstParty;
  final String? badge;
  final CatalogAlbumModel? album;
  final int? trackNumber;
  final CatalogOriginalTrackModel? originalTrack;
  final int? playlistPosition;
  final TrackAccessStatusModel? accessStatus;

  CatalogTrackModel({
    required this.id,
    required this.titleEn,
    required this.titleAr,
    required this.artistName,
    required this.durationSeconds,
    required this.previewDurationSeconds,
    this.bpm,
    this.musicalKey,
    String? coverImageUrl,
    String? coverArtUrl,
    this.waveformUrl,
    required this.streamTicketUrl,
    this.isFavorite = false,
    this.playCount = 0,
    this.primaryGenre,
    this.genres = const [],
    this.offers = const [],
    this.rightsDeclaration,
    this.seller,
    this.releasedAt,
    this.isFirstParty = false,
    this.badge,
    this.album,
    this.trackNumber,
    this.originalTrack,
    this.playlistPosition,
    this.accessStatus,
  }) : coverImageUrl = (coverImageUrl != null && coverImageUrl.isNotEmpty)
            ? coverImageUrl
            : ((coverArtUrl != null && coverArtUrl.isNotEmpty) ? coverArtUrl : null);

  static String? resolveCoverUrl(dynamic rawUrl, {String? albumCover}) {
    String? raw = rawUrl?.toString().trim();
    if (raw == null || raw.isEmpty) {
      raw = albumCover?.toString().trim();
    }
    if (raw == null || raw.isEmpty) {
      return null;
    }
    if (raw.startsWith('http://') || raw.startsWith('https://')) {
      return raw;
    }
    final clean = raw.startsWith('/') ? raw.substring(1) : raw;
    final path = clean.startsWith('storage/') ? clean : 'storage/$clean';
    final origin = baseApi.replaceAll(RegExp(r'/api/v1/?$'), '');
    return '$origin/$path';
  }

  factory CatalogTrackModel.fromJson(Map<String, dynamic> json) {
    CatalogGenreModel? primary;
    if (json['primary_genre'] is Map<String, dynamic>) {
      primary = CatalogGenreModel.fromJson(json['primary_genre']);
    }

    List<CatalogGenreModel> genreList = [];
    if (json['genres'] is List) {
      genreList = (json['genres'] as List)
          .whereType<Map<String, dynamic>>()
          .map((g) => CatalogGenreModel.fromJson(g))
          .toList();
    }
    if (json['secondary_genres'] is List) {
      final secondary = (json['secondary_genres'] as List)
          .whereType<Map<String, dynamic>>()
          .map((g) => CatalogGenreModel.fromJson(g))
          .toList();
      for (final s in secondary) {
        if (!genreList.any((g) => g.id == s.id)) {
          genreList.add(s);
        }
      }
    }
    if (primary != null && !genreList.any((g) => g.id == primary!.id)) {
      genreList.insert(0, primary);
    }

    List<CatalogOfferModel> offerList = [];
    if (json['offers'] is List) {
      offerList = (json['offers'] as List)
          .whereType<Map<String, dynamic>>()
          .map((o) => CatalogOfferModel.fromJson(o))
          .toList();
    }

    CatalogSellerModel? sellerInfo;
    if (json['seller'] is Map<String, dynamic>) {
      sellerInfo = CatalogSellerModel.fromJson(json['seller']);
    }

    final isFirstParty = json['is_first_party'] == true || json['owner_type'] == 'admin';
    final badge = json['badge']?.toString() ?? (isFirstParty ? 'FUN MOMENT Original' : null);
    final album = json['album'] is Map<String, dynamic>
        ? CatalogAlbumModel.fromJson(json['album'])
        : null;
    final trackNumber = json['track_number'] != null
        ? int.tryParse(json['track_number'].toString())
        : null;
    final originalTrack = json['original_track'] is Map<String, dynamic>
        ? CatalogOriginalTrackModel.fromJson(json['original_track'])
        : null;

    final rawCover = json['cover_image_url'] ?? json['cover_art_url'] ?? json['cover_image'];
    final resolvedCover = CatalogTrackModel.resolveCoverUrl(rawCover, albumCover: album?.coverImageUrl);

    final position = json['position'] != null
        ? int.tryParse(json['position'].toString())
        : (json['playlist_position'] != null ? int.tryParse(json['playlist_position'].toString()) : null);
    final access = json['access_status'] is Map<String, dynamic>
        ? TrackAccessStatusModel.fromJson(json['access_status'] as Map<String, dynamic>)
        : null;

    return CatalogTrackModel(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      titleEn: json['title_en']?.toString() ?? '',
      titleAr: json['title_ar']?.toString() ?? '',
      artistName: json['artist_name']?.toString() ?? '',
      durationSeconds: json['duration_seconds'] is int
          ? json['duration_seconds']
          : int.tryParse(json['duration_seconds']?.toString() ?? '0') ?? 0,
      previewDurationSeconds: json['preview_duration_seconds'] is int
          ? json['preview_duration_seconds']
          : int.tryParse(json['preview_duration_seconds']?.toString() ?? '30') ?? 30,
      bpm: json['bpm'] != null ? int.tryParse(json['bpm'].toString()) : null,
      musicalKey: json['key']?.toString(),
      coverImageUrl: resolvedCover,
      waveformUrl: json['waveform_url']?.toString(),
      streamTicketUrl: json['stream_ticket_url']?.toString() ?? '',
      isFavorite: json['is_favorite'] == true || json['is_favorite'] == 1,
      playCount: json['play_count'] is int
          ? json['play_count']
          : int.tryParse(json['play_count']?.toString() ?? '0') ?? 0,
      primaryGenre: primary,
      genres: genreList,
      offers: offerList,
      rightsDeclaration: json['rights_declaration']?.toString(),
      seller: sellerInfo,
      releasedAt: json['released_at']?.toString(),
      isFirstParty: isFirstParty,
      badge: badge,
      album: album,
      trackNumber: trackNumber,
      originalTrack: originalTrack,
      playlistPosition: position,
      accessStatus: access,
    );
  }

  String get title => titleEn.trim().isNotEmpty ? titleEn : (titleAr.trim().isNotEmpty ? titleAr : 'Untitled Track');

  String displayTitle(bool isRtl) {
    if (isRtl && titleAr.trim().isNotEmpty) {
      return titleAr;
    }
    if (titleEn.trim().isNotEmpty) {
      return titleEn;
    }
    return titleAr.trim().isNotEmpty ? titleAr : 'Untitled Track';
  }

  String get formattedDuration {
    final minutes = durationSeconds ~/ 60;
    final seconds = durationSeconds % 60;
    return '${minutes.toString().padLeft(2, '0')}:${seconds.toString().padLeft(2, '0')}';
  }

  String get formattedPreviewDuration {
    final minutes = previewDurationSeconds ~/ 60;
    final seconds = previewDurationSeconds % 60;
    return '${minutes.toString().padLeft(2, '0')}:${seconds.toString().padLeft(2, '0')}';
  }

  bool get hasFreeOffer => offers.any((o) => o.offerType == 'free' && o.isActive);
  bool get hasRentOffer => offers.any((o) => o.offerType == 'rent' && o.isActive);
  bool get hasSaleOffer => offers.any((o) => o.offerType == 'sale' && o.isActive);

  CatalogOfferModel? get freeOffer {
    final list = offers.where((o) => o.offerType == 'free' && o.isActive).toList();
    return list.isNotEmpty ? list.first : null;
  }

  CatalogOfferModel? get rentOffer {
    final list = offers.where((o) => o.offerType == 'rent' && o.isActive).toList();
    return list.isNotEmpty ? list.first : null;
  }

  CatalogOfferModel? get saleOffer {
    final list = offers.where((o) => o.offerType == 'sale' && o.isActive).toList();
    return list.isNotEmpty ? list.first : null;
  }

  bool get isRemix => originalTrack != null;

  String displayBadge(bool isRtl) {
    if (!isFirstParty) return '';
    return isRtl ? 'أصلي من FUN MOMENT' : 'FUN MOMENT Original';
  }

  CatalogTrackModel copyWith({
    bool? isFavorite,
    int? playCount,
    bool? isFirstParty,
    String? badge,
    String? coverImageUrl,
    String? coverArtUrl,
    CatalogAlbumModel? album,
    int? trackNumber,
    CatalogOriginalTrackModel? originalTrack,
    int? playlistPosition,
    TrackAccessStatusModel? accessStatus,
  }) {
    return CatalogTrackModel(
      id: id,
      titleEn: titleEn,
      titleAr: titleAr,
      artistName: artistName,
      durationSeconds: durationSeconds,
      previewDurationSeconds: previewDurationSeconds,
      bpm: bpm,
      musicalKey: musicalKey,
      coverImageUrl: coverImageUrl ?? coverArtUrl ?? this.coverImageUrl,
      waveformUrl: waveformUrl,
      streamTicketUrl: streamTicketUrl,
      isFavorite: isFavorite ?? this.isFavorite,
      playCount: playCount ?? this.playCount,
      primaryGenre: primaryGenre,
      genres: genres,
      offers: offers,
      rightsDeclaration: rightsDeclaration,
      seller: seller,
      releasedAt: releasedAt,
      isFirstParty: isFirstParty ?? this.isFirstParty,
      badge: badge ?? this.badge,
      album: album ?? this.album,
      trackNumber: trackNumber ?? this.trackNumber,
      originalTrack: originalTrack ?? this.originalTrack,
      playlistPosition: playlistPosition ?? this.playlistPosition,
      accessStatus: accessStatus ?? this.accessStatus,
    );
  }
}

class TrackAccessStatusModel {
  final String status; // 'free', 'purchased', 'rented', 'rental_expired', 'purchase_required', 'unavailable'
  final String labelEn;
  final String labelAr;
  final bool isPlayable;
  final bool canStreamFull;
  final String? expiresAt;

  TrackAccessStatusModel({
    required this.status,
    required this.labelEn,
    required this.labelAr,
    required this.isPlayable,
    required this.canStreamFull,
    this.expiresAt,
  });

  factory TrackAccessStatusModel.fromJson(Map<String, dynamic> json) {
    return TrackAccessStatusModel(
      status: json['status']?.toString() ?? 'unavailable',
      labelEn: json['label_en']?.toString() ?? 'Unavailable',
      labelAr: json['label_ar']?.toString() ?? 'غير متاح',
      isPlayable: json['is_playable'] == true || json['is_playable'] == 1,
      canStreamFull: json['can_stream_full'] == true || json['can_stream_full'] == 1,
      expiresAt: json['expires_at']?.toString(),
    );
  }

  String displayLabel(bool isArabic) {
    return isArabic ? labelAr : labelEn;
  }

  bool get isFree => status == 'free';
  bool get isPurchased => status == 'purchased';
  bool get isRented => status == 'rented';
  bool get isRentalExpired => status == 'rental_expired';
  bool get isPurchaseRequired => status == 'purchase_required';
  bool get isUnavailable => status == 'unavailable';
}

class MusicPlaylistModel {
  final int id;
  final String name;
  final String? description;
  final String? coverImageUrl;
  final bool isPublic;
  final int tracksCount;
  final int totalDurationSeconds;
  final String? createdAt;
  final String? updatedAt;
  final List<CatalogTrackModel> tracks;
  final bool containsTrack;

  MusicPlaylistModel({
    required this.id,
    required this.name,
    this.description,
    this.coverImageUrl,
    this.isPublic = false,
    this.tracksCount = 0,
    this.totalDurationSeconds = 0,
    this.createdAt,
    this.updatedAt,
    this.tracks = const [],
    this.containsTrack = false,
  });

  factory MusicPlaylistModel.fromJson(Map<String, dynamic> json) {
    final rawCover = json['cover_image_url'] ?? json['cover_image'];
    final resolvedCover = CatalogTrackModel.resolveCoverUrl(rawCover);

    List<CatalogTrackModel> parsedTracks = [];
    if (json['tracks'] is List) {
      parsedTracks = (json['tracks'] as List)
          .whereType<Map<String, dynamic>>()
          .map((t) => CatalogTrackModel.fromJson(t))
          .toList();
    }

    final count = json['tracks_count'] is int
        ? json['tracks_count'] as int
        : (parsedTracks.isNotEmpty ? parsedTracks.length : (int.tryParse(json['tracks_count']?.toString() ?? '0') ?? 0));

    final duration = json['total_duration_seconds'] is int
        ? json['total_duration_seconds'] as int
        : (int.tryParse(json['total_duration_seconds']?.toString() ?? '0') ?? 0);

    return MusicPlaylistModel(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      name: json['name']?.toString() ?? 'Untitled Playlist',
      description: json['description']?.toString(),
      coverImageUrl: resolvedCover,
      isPublic: json['is_public'] == true || json['is_public'] == 1,
      tracksCount: count,
      totalDurationSeconds: duration,
      createdAt: json['created_at']?.toString(),
      updatedAt: json['updated_at']?.toString(),
      tracks: parsedTracks,
      containsTrack: json['contains_track'] == true || json['contains_track'] == 1,
    );
  }

  String get formattedTotalDuration {
    if (totalDurationSeconds <= 0) return '';
    final m = totalDurationSeconds ~/ 60;
    final s = totalDurationSeconds % 60;
    return '${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
  }
}


class CatalogResponse {
  final List<CatalogTrackModel> tracks;
  final int currentPage;
  final int lastPage;
  final int total;
  final bool marketplaceEnabled;

  CatalogResponse({
    required this.tracks,
    required this.currentPage,
    required this.lastPage,
    required this.total,
    this.marketplaceEnabled = true,
  });

  factory CatalogResponse.fromJson(Map<String, dynamic> json) {
    final data = json['data'] is Map<String, dynamic> ? json['data'] : <String, dynamic>{};
    final rawList = data['data'] is List ? data['data'] as List : [];
    final tracks = rawList
        .whereType<Map<String, dynamic>>()
        .map((t) => CatalogTrackModel.fromJson(t))
        .toList();

    return CatalogResponse(
      tracks: tracks,
      currentPage: data['current_page'] is int
          ? data['current_page']
          : int.tryParse(data['current_page']?.toString() ?? '1') ?? 1,
      lastPage: data['last_page'] is int
          ? data['last_page']
          : int.tryParse(data['last_page']?.toString() ?? '1') ?? 1,
      total: data['total'] is int
          ? data['total']
          : int.tryParse(data['total']?.toString() ?? '0') ?? 0,
      marketplaceEnabled: json['marketplace_enabled'] != false,
    );
  }
}
