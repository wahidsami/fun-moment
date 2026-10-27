import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/model/buyer_music_model.dart';
import 'package:funmoments/service/buyer_music_service.dart';
import 'package:funmoments/service/music_player_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/music/components/add_to_playlist_sheet.dart';
import 'package:funmoments/view/music/components/mini_player_bar.dart';
import 'package:funmoments/view/music/components/music_artwork_widget.dart';
import 'package:funmoments/view/music/music_album_details_page.dart';
import 'package:funmoments/view/music/music_track_details_page.dart';
import 'package:funmoments/view/music/my_music_favorites_page.dart';
import 'package:funmoments/view/music/my_playlists_page.dart';

class MusicMarketplacePage extends StatefulWidget {
  const MusicMarketplacePage({Key? key}) : super(key: key);

  @override
  State<MusicMarketplacePage> createState() => _MusicMarketplacePageState();
}

class _MusicMarketplacePageState extends State<MusicMarketplacePage> {
  final TextEditingController _searchController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  final PageController _carouselPageController = PageController(viewportFraction: 0.88);
  int _carouselCurrentIndex = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final svc = Provider.of<BuyerMusicService>(context, listen: false);
      svc.fetchGenres();
      svc.fetchLatestReleases();
      svc.fetchAlbums();
      svc.fetchCatalog(isRefresh: true);
    });

    _scrollController.addListener(() {
      if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent - 200) {
        Provider.of<BuyerMusicService>(context, listen: false).loadNextPage();
      }
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    _scrollController.dispose();
    _carouselPageController.dispose();
    super.dispose();
  }

  Future<void> _onRefresh() async {
    final svc = Provider.of<BuyerMusicService>(context, listen: false);
    await Future.wait([
      svc.fetchLatestReleases(),
      svc.fetchAlbums(isRefresh: true),
      svc.fetchCatalog(isRefresh: true),
    ]);
  }

  void _onSearchSubmitted(String query) {
    Provider.of<BuyerMusicService>(context, listen: false).fetchCatalog(
      isRefresh: true,
      query: query,
    );
  }

  @override
  Widget build(BuildContext context) {
    final rtl = Provider.of<RtlService>(context);
    final isArabic = rtl.langSlug.startsWith('ar');
    final musicService = Provider.of<BuyerMusicService>(context);
    final player = Provider.of<MusicPlayerService>(context);

    return Scaffold(
      backgroundColor: FMColors.background,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: Colors.white),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text(
          isArabic ? 'سوق الموسيقى' : 'Music Marketplace',
          style: const TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w800,
            fontSize: 18,
          ),
        ),
        centerTitle: true,
        actions: [
          IconButton(
            icon: const Icon(Icons.queue_music_rounded, color: Color(0xFF2DE2FF)),
            tooltip: isArabic ? 'قوائم التشغيل' : 'Playlists',
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const MyPlaylistsPage()),
              );
            },
          ),
          IconButton(
            icon: const Icon(Icons.favorite_rounded, color: FMColors.magenta),
            tooltip: isArabic ? 'المفضلة' : 'Favorites',
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const MyMusicFavoritesPage()),
              );
            },
          ),
        ],
      ),
      body: Stack(
        children: [
          if (!musicService.isMarketplaceEnabled)
            _buildMarketplaceDisabledState(isArabic)
          else
            RefreshIndicator(
              color: FMColors.cyan,
              backgroundColor: FMColors.surfaceElevated,
              onRefresh: _onRefresh,
              child: SingleChildScrollView(
                controller: _scrollController,
                physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
                padding: EdgeInsets.fromLTRB(
                  0,
                  8,
                  0,
                  player.hasActiveTrack ? 100 : 32,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Search Bar
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      child: _buildSearchBar(isArabic),
                    ),
                    const SizedBox(height: 20),

                    // Latest Releases Carousel
                    _buildLatestReleasesSection(musicService, isArabic),
                    const SizedBox(height: 24),

                    // Albums & Collections Carousel
                    _buildAlbumsSection(musicService, isArabic),
                    const SizedBox(height: 24),

                    // Filter Chips: Genres
                    _buildGenreFilterChips(musicService, isArabic),
                    const SizedBox(height: 12),

                    // Filter Chips: Offer Type & Sort
                    _buildOfferAndSortBar(musicService, isArabic),
                    const SizedBox(height: 16),

                    // Catalog Tracks List
                    _buildCatalogList(musicService, player, isArabic),
                  ],
                ),
              ),
            ),

          // Docked Mini Player
          if (player.hasActiveTrack)
            const Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              child: MiniPlayerBar(),
            ),
        ],
      ),
    );
  }

  Widget _buildSearchBar(bool isArabic) {
    return Container(
      decoration: BoxDecoration(
        color: FMColors.surface,
        borderRadius: BorderRadius.circular(FMRadii.md),
        border: Border.all(color: FMColors.border),
      ),
      child: TextField(
        controller: _searchController,
        style: const TextStyle(color: Colors.white, fontSize: 14),
        textInputAction: TextInputAction.search,
        onSubmitted: _onSearchSubmitted,
        decoration: InputDecoration(
          hintText: isArabic ? 'ابحث عن مقطوعة، فنان أو نمط...' : 'Search tracks, artists, genres...',
          hintStyle: const TextStyle(color: FMColors.textMuted, fontSize: 13),
          prefixIcon: const Icon(Icons.search_rounded, color: FMColors.cyan, size: 22),
          suffixIcon: _searchController.text.isNotEmpty
              ? IconButton(
                  icon: const Icon(Icons.clear_rounded, color: FMColors.textMuted, size: 18),
                  onPressed: () {
                    _searchController.clear();
                    _onSearchSubmitted('');
                  },
                )
              : null,
          border: InputBorder.none,
          contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        ),
      ),
    );
  }

  Widget _buildLatestReleasesSection(BuyerMusicService musicService, bool isArabic) {
    final releases = musicService.latestReleases;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                isArabic ? 'أحدث الإصدارات' : 'Latest Releases',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 17,
                  fontWeight: FontWeight.w700,
                ),
              ),
              if (releases.length > 1)
                Row(
                  children: List.generate(releases.length, (idx) {
                    final bool isCurrent = idx == _carouselCurrentIndex;
                    return AnimatedContainer(
                      duration: const Duration(milliseconds: 250),
                      margin: const EdgeInsets.symmetric(horizontal: 2.5),
                      width: isCurrent ? 14 : 5,
                      height: 5,
                      decoration: BoxDecoration(
                        color: isCurrent ? FMColors.cyan : Colors.white24,
                        borderRadius: BorderRadius.circular(3),
                      ),
                    );
                  }),
                ),
            ],
          ),
        ),
        const SizedBox(height: 12),

        if (musicService.isReleasesLoading && releases.isEmpty)
          Container(
            height: 170,
            margin: const EdgeInsets.symmetric(horizontal: 16),
            decoration: BoxDecoration(
              color: FMColors.surface,
              borderRadius: BorderRadius.circular(FMRadii.lg),
            ),
            child: const Center(
              child: CircularProgressIndicator(
                valueColor: AlwaysStoppedAnimation<Color>(FMColors.cyan),
              ),
            ),
          )
        // Exactly Zero Tracks: graceful empty state
        else if (releases.isEmpty)
          Container(
            height: 100,
            margin: const EdgeInsets.symmetric(horizontal: 16),
            decoration: BoxDecoration(
              color: FMColors.surface.withOpacity(0.5),
              borderRadius: BorderRadius.circular(FMRadii.md),
              border: Border.all(color: FMColors.border.withOpacity(0.5)),
            ),
            child: Center(
              child: Text(
                isArabic ? 'لا توجد إصدارات حديثة معتمدة حالياً' : 'No new releases currently available',
                style: const TextStyle(color: FMColors.textMuted, fontSize: 13),
              ),
            ),
          )
        // Exactly One Track: render single card without carousel controls
        else if (releases.length == 1)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: _buildReleaseCard(releases.first, isArabic),
          )
        // Multiple Tracks: PageView carousel
        else
          SizedBox(
            height: 180,
            child: PageView.builder(
              controller: _carouselPageController,
              itemCount: releases.length,
              physics: const BouncingScrollPhysics(),
              onPageChanged: (idx) {
                setState(() {
                  _carouselCurrentIndex = idx;
                });
              },
              itemBuilder: (context, idx) {
                return Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 6),
                  child: _buildReleaseCard(releases[idx], isArabic),
                );
              },
            ),
          ),
      ],
    );
  }

  Widget _buildReleaseCard(CatalogTrackModel track, bool isArabic) {
    final player = Provider.of<MusicPlayerService>(context, listen: false);
    final isPlaying = player.currentTrack?.id == track.id && player.isPlaying;

    return GestureDetector(
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => MusicTrackDetailsPage(track: track)),
        );
      },
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(FMRadii.lg),
          gradient: const LinearGradient(
            colors: [FMColors.surfaceElevated, FMColors.surface],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          border: Border.all(color: FMColors.cyan.withOpacity(0.35)),
          boxShadow: const [
            BoxShadow(
              color: Colors.black45,
              blurRadius: 10,
              offset: Offset(0, 4),
            ),
          ],
        ),
        clipBehavior: Clip.antiAlias,
        child: Stack(
          children: [
            // Background artwork subtle overlay
            if (track.coverArtUrl != null && track.coverArtUrl!.isNotEmpty)
              Positioned.fill(
                child: Opacity(
                  opacity: 0.18,
                  child: Image.network(
                    track.coverArtUrl!,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => const SizedBox.shrink(),
                  ),
                ),
              ),

            Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  // Cover thumbnail
                  MusicArtworkWidget(
                    imageUrl: track.coverArtUrl,
                    width: 110,
                    height: 110,
                    borderRadius: FMRadii.md,
                    placeholderIconSize: 36,
                    placeholderBackgroundColor: FMColors.surface,
                  ),
                  const SizedBox(width: 16),
                  // Details
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Wrap(
                          spacing: 6,
                          runSpacing: 4,
                          children: [
                            if (track.isFirstParty)
                              Container(
                                margin: const EdgeInsets.only(bottom: 6),
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                decoration: BoxDecoration(
                                  color: FMColors.warning.withOpacity(0.15),
                                  borderRadius: BorderRadius.circular(FMRadii.sm),
                                  border: Border.all(color: FMColors.warning.withOpacity(0.4)),
                                ),
                                child: Text(
                                  track.displayBadge(isArabic),
                                  style: const TextStyle(
                                    color: FMColors.warning,
                                    fontSize: 10,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ),
                            if (track.isRemix)
                              Container(
                                margin: const EdgeInsets.only(bottom: 6),
                                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                decoration: BoxDecoration(
                                  color: Colors.purple.withOpacity(0.15),
                                  borderRadius: BorderRadius.circular(FMRadii.sm),
                                  border: Border.all(color: Colors.purple.withOpacity(0.4)),
                                ),
                                child: Text(
                                  isArabic ? 'ريمكس' : 'Remix',
                                  style: const TextStyle(
                                    color: Colors.purpleAccent,
                                    fontSize: 10,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ),
                            if (track.genres.isNotEmpty)
                              Container(
                                margin: const EdgeInsets.only(bottom: 6),
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                decoration: BoxDecoration(
                                  color: FMColors.cyan.withOpacity(0.15),
                                  borderRadius: BorderRadius.circular(FMRadii.sm),
                                  border: Border.all(color: FMColors.cyan.withOpacity(0.4)),
                                ),
                                child: Text(
                                  track.genres.first.displayName(isArabic),
                                  style: const TextStyle(
                                    color: FMColors.cyan,
                                    fontSize: 10,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ),
                          ],
                        ),
                        Text(
                          track.displayTitle(isArabic),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          track.artistName,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: FMColors.textMuted,
                            fontSize: 13,
                          ),
                        ),
                        const SizedBox(height: 10),
                        Row(
                          children: [
                            GestureDetector(
                              onTap: () {
                                if (player.currentTrack?.id == track.id) {
                                  player.togglePlayPause();
                                } else {
                                  player.playTrack(track);
                                }
                              },
                              child: Container(
                                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                                decoration: BoxDecoration(
                                  gradient: FMGradients.funGradient,
                                  borderRadius: BorderRadius.circular(FMRadii.pill),
                                ),
                                child: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Icon(
                                      isPlaying ? Icons.pause_rounded : Icons.play_arrow_rounded,
                                      color: Colors.white,
                                      size: 16,
                                    ),
                                    const SizedBox(width: 4),
                                    Text(
                                      isPlaying
                                          ? (isArabic ? 'إيقاف' : 'Pause')
                                          : (isArabic ? 'استماع' : 'Listen'),
                                      style: const TextStyle(
                                        color: Colors.white,
                                        fontSize: 12,
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                            const SizedBox(width: 8),
                            GestureDetector(
                              onTap: () => AddToPlaylistSheet.show(context, track),
                              child: Container(
                                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                decoration: BoxDecoration(
                                  color: FMColors.surfaceElevated,
                                  borderRadius: BorderRadius.circular(FMRadii.pill),
                                  border: Border.all(color: FMColors.cyan.withOpacity(0.5)),
                                ),
                                child: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    const Icon(Icons.playlist_add_rounded, color: FMColors.cyan, size: 16),
                                    const SizedBox(width: 4),
                                    Text(
                                      isArabic ? 'إضافة لقائمة' : 'Playlist',
                                      style: const TextStyle(
                                        color: FMColors.cyan,
                                        fontSize: 11,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                            const Spacer(),
                            Text(
                              track.formattedDuration,
                              style: const TextStyle(
                                color: FMColors.textMuted,
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAlbumsSection(BuyerMusicService musicService, bool isArabic) {
    final albums = musicService.albums;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                isArabic ? 'الألبومات والمجموعات' : 'Albums & Collections',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 17,
                  fontWeight: FontWeight.w700,
                ),
              ),
              if (albums.isNotEmpty)
                Text(
                  isArabic ? '${albums.length} ألبوم' : '${albums.length} albums',
                  style: const TextStyle(
                    color: FMColors.textMuted,
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: 12),

        if (musicService.isAlbumsLoading && albums.isEmpty)
          Container(
            height: 180,
            margin: const EdgeInsets.symmetric(horizontal: 16),
            decoration: BoxDecoration(
              color: FMColors.surface,
              borderRadius: BorderRadius.circular(FMRadii.md),
              border: Border.all(color: FMColors.border),
            ),
            child: const Center(
              child: CircularProgressIndicator(
                valueColor: AlwaysStoppedAnimation<Color>(FMColors.cyan),
              ),
            ),
          )
        else if (musicService.albumsError != null && albums.isEmpty)
          Container(
            margin: const EdgeInsets.symmetric(horizontal: 16),
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: FMColors.surface,
              borderRadius: BorderRadius.circular(FMRadii.md),
              border: Border.all(color: FMColors.magenta.withOpacity(0.4)),
            ),
            child: Row(
              children: [
                const Icon(Icons.error_outline_rounded, color: FMColors.magenta, size: 22),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    isArabic ? 'تعذر تحميل الألبومات' : 'Failed to load albums',
                    style: const TextStyle(color: Colors.white, fontSize: 13),
                  ),
                ),
                TextButton(
                  onPressed: () => musicService.fetchAlbums(isRefresh: true),
                  child: Text(
                    isArabic ? 'إعادة' : 'Retry',
                    style: const TextStyle(color: FMColors.cyan, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
          )
        else if (albums.isEmpty)
          Container(
            margin: const EdgeInsets.symmetric(horizontal: 16),
            padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 16),
            decoration: BoxDecoration(
              color: FMColors.surface,
              borderRadius: BorderRadius.circular(FMRadii.md),
              border: Border.all(color: FMColors.border),
            ),
            child: Row(
              children: [
                const Icon(Icons.album_outlined, color: FMColors.textMuted, size: 24),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    isArabic
                        ? 'لا توجد ألبومات معلنة حالياً في المكتبة.'
                        : 'No albums published in the library at this moment.',
                    style: const TextStyle(color: FMColors.textMuted, fontSize: 13),
                  ),
                ),
              ],
            ),
          )
        else
          SizedBox(
            height: 220,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              physics: const BouncingScrollPhysics(),
              itemCount: albums.length,
              separatorBuilder: (_, __) => const SizedBox(width: 14),
              itemBuilder: (context, index) {
                final album = albums[index];
                return GestureDetector(
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => MusicAlbumDetailsPage(
                          albumId: album.id,
                          initialAlbum: album,
                        ),
                      ),
                    );
                  },
                  child: SizedBox(
                    width: 140,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Album Cover
                        Hero(
                          tag: 'album-cover-${album.id}',
                          child: MusicArtworkWidget(
                            imageUrl: album.coverImageUrl,
                            width: 140,
                            height: 140,
                            borderRadius: FMRadii.md,
                            placeholderIcon: Icons.album_rounded,
                            placeholderIconSize: 42,
                            border: Border.all(color: FMColors.border),
                            boxShadow: const [
                              BoxShadow(
                                color: Colors.black45,
                                blurRadius: 8,
                                offset: Offset(0, 4),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 8),

                        // Title
                        Text(
                          album.displayTitle(isArabic),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 2),

                        // Artist Name
                        Text(
                          album.displayArtist,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: FMColors.textMuted,
                            fontSize: 11,
                          ),
                        ),
                        const SizedBox(height: 3),

                        // Tracks count badge
                        Text(
                          isArabic
                              ? '${album.tracksCount} ${album.tracksCount == 1 ? "مقطع" : "مقاطع"}'
                              : '${album.tracksCount} ${album.tracksCount == 1 ? "track" : "tracks"}',
                          style: const TextStyle(
                            color: FMColors.cyan,
                            fontSize: 10,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
      ],
    );
  }

  Widget _buildGenreFilterChips(BuyerMusicService musicService, bool isArabic) {
    final genres = musicService.genres;
    final selectedId = musicService.selectedGenreId;

    return SizedBox(
      height: 38,
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        itemCount: genres.length + 1,
        itemBuilder: (context, idx) {
          final isAll = idx == 0;
          final isSelected = isAll ? selectedId == null : selectedId == genres[idx - 1].id;
          final title = isAll ? (isArabic ? 'الكل' : 'All') : genres[idx - 1].displayName(isArabic);

          return GestureDetector(
            onTap: () {
              musicService.fetchCatalog(
                isRefresh: true,
                genreId: isAll ? 0 : genres[idx - 1].id,
              );
            },
            child: Container(
              margin: const EdgeInsets.only(right: 8),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              decoration: BoxDecoration(
                color: isSelected ? FMColors.cyan : FMColors.surface,
                borderRadius: BorderRadius.circular(FMRadii.pill),
                border: Border.all(
                  color: isSelected ? FMColors.cyan : FMColors.border,
                ),
              ),
              child: Center(
                child: Text(
                  title,
                  style: TextStyle(
                    color: isSelected ? Colors.black : FMColors.textSecondary,
                    fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
                    fontSize: 12,
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildOfferAndSortBar(BuyerMusicService musicService, bool isArabic) {
    final currentOffer = musicService.selectedOfferType;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Row(
        children: [
          // Offer chips
          _offerFilterChip('all', isArabic ? 'كل العروض' : 'All Offers', currentOffer == null, () {
            musicService.fetchCatalog(isRefresh: true, offerType: 'all');
          }),
          const SizedBox(width: 6),
          _offerFilterChip('free', isArabic ? 'مجاني' : 'Free', currentOffer == 'free', () {
            musicService.fetchCatalog(isRefresh: true, offerType: 'free');
          }),
          const SizedBox(width: 6),
          _offerFilterChip('rent', isArabic ? 'استئجار' : 'Rent', currentOffer == 'rent', () {
            musicService.fetchCatalog(isRefresh: true, offerType: 'rent');
          }),
          const SizedBox(width: 6),
          _offerFilterChip('sale', isArabic ? 'شراء' : 'Buy', currentOffer == 'sale', () {
            musicService.fetchCatalog(isRefresh: true, offerType: 'sale');
          }),
          const Spacer(),
          // Sort toggle
          PopupMenuButton<String>(
            icon: const Icon(Icons.sort_rounded, color: FMColors.cyan, size: 22),
            color: FMColors.surfaceElevated,
            onSelected: (val) {
              musicService.fetchCatalog(isRefresh: true, sort: val);
            },
            itemBuilder: (ctx) => [
              PopupMenuItem(
                value: 'latest',
                child: Text(
                  isArabic ? 'الأحدث' : 'Latest',
                  style: const TextStyle(color: Colors.white, fontSize: 13),
                ),
              ),
              PopupMenuItem(
                value: 'popular',
                child: Text(
                  isArabic ? 'الأكثر استماعاً' : 'Most Popular',
                  style: const TextStyle(color: Colors.white, fontSize: 13),
                ),
              ),
              PopupMenuItem(
                value: 'title',
                child: Text(
                  isArabic ? 'أبجدياً' : 'Alphabetical',
                  style: const TextStyle(color: Colors.white, fontSize: 13),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _offerFilterChip(String key, String label, bool isSelected, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
        decoration: BoxDecoration(
          color: isSelected ? FMColors.surfaceElevated : Colors.transparent,
          borderRadius: BorderRadius.circular(FMRadii.sm),
          border: Border.all(
            color: isSelected ? FMColors.magenta : FMColors.border,
            width: isSelected ? 1.5 : 1.0,
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            color: isSelected ? FMColors.magentaLight : FMColors.textMuted,
            fontSize: 11,
            fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
          ),
        ),
      ),
    );
  }

  Widget _buildCatalogList(
    BuyerMusicService musicService,
    MusicPlayerService player,
    bool isArabic,
  ) {
    final tracks = musicService.catalogTracks;

    if (musicService.isCatalogLoading && tracks.isEmpty) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 40),
        child: Center(
          child: CircularProgressIndicator(
            valueColor: AlwaysStoppedAnimation<Color>(FMColors.cyan),
          ),
        ),
      );
    }

    if (tracks.isEmpty) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 40, horizontal: 20),
        child: Center(
          child: Column(
            children: [
              const Icon(Icons.music_off_rounded, color: FMColors.textMuted, size: 48),
              const SizedBox(height: 12),
              Text(
                isArabic ? 'لا توجد مقاطع مطابقة للبحث' : 'No tracks found matching criteria',
                style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 6),
              Text(
                isArabic ? 'جرب تغيير فلاتر البحث أو النمط المختار' : 'Try adjusting your search query or filter tags',
                style: const TextStyle(color: FMColors.textMuted, fontSize: 13),
              ),
            ],
          ),
        ),
      );
    }

    return ListView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      padding: const EdgeInsets.symmetric(horizontal: 16),
      itemCount: tracks.length + (musicService.isCatalogLoading ? 1 : 0),
      itemBuilder: (context, idx) {
        if (idx == tracks.length) {
          return const Padding(
            padding: EdgeInsets.symmetric(vertical: 16),
            child: Center(
              child: CircularProgressIndicator(
                strokeWidth: 2,
                valueColor: AlwaysStoppedAnimation<Color>(FMColors.cyan),
              ),
            ),
          );
        }

        final track = tracks[idx];
        final isPlaying = player.currentTrack?.id == track.id && player.isPlaying;

        return Container(
          margin: const EdgeInsets.only(bottom: 12),
          decoration: BoxDecoration(
            color: FMColors.surface,
            borderRadius: BorderRadius.circular(FMRadii.md),
            border: Border.all(color: FMColors.border),
          ),
          child: ListTile(
            contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => MusicTrackDetailsPage(track: track)),
              );
            },
            leading: Stack(
              alignment: Alignment.center,
              children: [
                MusicArtworkWidget(
                  imageUrl: track.coverArtUrl,
                  width: 52,
                  height: 52,
                  borderRadius: FMRadii.sm,
                  placeholderIconSize: 24,
                  placeholderBackgroundColor: FMColors.surfaceElevated,
                ),
                GestureDetector(
                  onTap: () {
                    if (player.currentTrack?.id == track.id) {
                      player.togglePlayPause();
                    } else {
                      player.playTrack(track);
                    }
                  },
                  child: Container(
                    width: 32,
                    height: 32,
                    decoration: BoxDecoration(
                      color: Colors.black.withOpacity(0.55),
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      isPlaying ? Icons.pause_rounded : Icons.play_arrow_rounded,
                      color: Colors.white,
                      size: 20,
                    ),
                  ),
                ),
              ],
            ),
            title: Text(
              track.displayTitle(isArabic),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w600,
                fontSize: 14,
              ),
            ),
            subtitle: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SizedBox(height: 2),
                Text(
                  track.artistName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: FMColors.textMuted, fontSize: 12),
                ),
                const SizedBox(height: 4),
                Row(
                  children: [
                    Text(
                      track.formattedDuration,
                      style: const TextStyle(color: FMColors.cyan, fontSize: 11, fontWeight: FontWeight.w500),
                    ),
                    if (track.genres.isNotEmpty) ...[
                      const SizedBox(width: 8),
                      Text(
                        '•  ${track.genres.first.displayName(isArabic)}',
                        style: const TextStyle(color: FMColors.textMuted, fontSize: 11),
                      ),
                    ],
                    const Spacer(),
                    _buildOfferBadge(track, isArabic),
                  ],
                ),
              ],
            ),
            trailing: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                IconButton(
                  icon: const Icon(
                    Icons.playlist_add_rounded,
                    color: Color(0xFF2DE2FF),
                    size: 22,
                  ),
                  tooltip: isArabic ? 'إضافة إلى قائمة تشغيل' : 'Add to Playlist',
                  onPressed: () => AddToPlaylistSheet.show(context, track),
                ),
                IconButton(
                  icon: Icon(
                    track.isFavorite ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                    color: track.isFavorite ? FMColors.magenta : Colors.white54,
                    size: 20,
                  ),
                  tooltip: isArabic ? 'المفضلة' : 'Favorite',
                  onPressed: () {
                    musicService.toggleFavorite(track);
                  },
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildOfferBadge(CatalogTrackModel track, bool isArabic) {
    if (track.hasFreeOffer) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
        decoration: BoxDecoration(
          color: FMColors.success.withOpacity(0.15),
          borderRadius: BorderRadius.circular(4),
        ),
        child: Text(
          isArabic ? 'مجاني' : 'Free',
          style: const TextStyle(color: FMColors.success, fontSize: 10, fontWeight: FontWeight.bold),
        ),
      );
    }
    if (track.hasRentOffer && track.rentOffer != null) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
        decoration: BoxDecoration(
          color: FMColors.orange.withOpacity(0.15),
          borderRadius: BorderRadius.circular(4),
        ),
        child: Text(
          track.rentOffer!.formattedPrice,
          style: const TextStyle(color: FMColors.orange, fontSize: 10, fontWeight: FontWeight.bold),
        ),
      );
    }
    if (track.hasSaleOffer && track.saleOffer != null) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
        decoration: BoxDecoration(
          color: FMColors.magenta.withOpacity(0.15),
          borderRadius: BorderRadius.circular(4),
        ),
        child: Text(
          track.saleOffer!.formattedPrice,
          style: const TextStyle(color: FMColors.magenta, fontSize: 10, fontWeight: FontWeight.bold),
        ),
      );
    }
    return const SizedBox.shrink();
  }

  Widget _buildMarketplaceDisabledState(bool isArabic) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: FMColors.surfaceElevated,
                shape: BoxShape.circle,
                border: Border.all(color: FMColors.border),
              ),
              child: const Icon(
                Icons.music_off_outlined,
                color: FMColors.warning,
                size: 56,
              ),
            ),
            const SizedBox(height: 24),
            Text(
              isArabic ? 'سوق الموسيقى غير متاح حالياً' : 'Music Marketplace Currently Offline',
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 10),
            Text(
              isArabic
                  ? 'تم إيقاف سوق الموسيقى مؤقتاً للصيانة من قبل إدارة المنصة. يرجى المحاولة لاحقاً.'
                  : 'The music marketplace is temporarily disabled for administrative maintenance. Please check back soon.',
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: FMColors.textMuted,
                fontSize: 14,
                height: 1.4,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
