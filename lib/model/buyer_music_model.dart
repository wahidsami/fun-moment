import 'dart:convert';

class CatalogGenreModel {
  final int id;
  final String name;
  final String slug;

  CatalogGenreModel({
    required this.id,
    required this.name,
    required this.slug,
  });

  factory CatalogGenreModel.fromJson(Map<String, dynamic> json) {
    return CatalogGenreModel(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      name: json['name']?.toString() ?? '',
      slug: json['slug']?.toString() ?? '',
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'slug': slug,
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

class CatalogTrackModel {
  final int id;
  final String titleEn;
  final String titleAr;
  final String artistName;
  final int durationSeconds;
  final int previewDurationSeconds;
  final int? bpm;
  final String? musicalKey;
  final String? coverArtUrl;
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

  CatalogTrackModel({
    required this.id,
    required this.titleEn,
    required this.titleAr,
    required this.artistName,
    required this.durationSeconds,
    required this.previewDurationSeconds,
    this.bpm,
    this.musicalKey,
    this.coverArtUrl,
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
  });

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
      coverArtUrl: json['cover_art_url']?.toString(),
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

  CatalogTrackModel copyWith({bool? isFavorite, int? playCount}) {
    return CatalogTrackModel(
      id: id,
      titleEn: titleEn,
      titleAr: titleAr,
      artistName: artistName,
      durationSeconds: durationSeconds,
      previewDurationSeconds: previewDurationSeconds,
      bpm: bpm,
      musicalKey: musicalKey,
      coverArtUrl: coverArtUrl,
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
    );
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
