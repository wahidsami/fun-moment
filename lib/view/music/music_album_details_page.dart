import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/model/buyer_music_model.dart';
import 'package:funmoments/service/buyer_music_service.dart';
import 'package:funmoments/service/music_player_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/music/components/mini_player_bar.dart';
import 'package:funmoments/view/music/components/music_artwork_widget.dart';
import 'package:funmoments/view/music/music_track_details_page.dart';

/// Dedicated Album Details page showcasing album artwork, metadata,
/// full tracklist with track numbering, and full-album playback controls
/// (Play All, Shuffle Play, individual track selection).
class MusicAlbumDetailsPage extends StatefulWidget {
  final int albumId;
  final CatalogAlbumModel? initialAlbum;

  const MusicAlbumDetailsPage({
    Key? key,
    required this.albumId,
    this.initialAlbum,
  }) : super(key: key);

  @override
  State<MusicAlbumDetailsPage> createState() => _MusicAlbumDetailsPageState();
}

class _MusicAlbumDetailsPageState extends State<MusicAlbumDetailsPage> {
  CatalogAlbumModel? _album;
  bool _isLoading = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _album = widget.initialAlbum;
    _fetchDetails();
  }

  Future<void> _fetchDetails() async {
    if (_album == null || _album!.tracks.isEmpty) {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });
    }

    try {
      final svc = Provider.of<BuyerMusicService>(context, listen: false);
      final detailed = await svc.getAlbumDetails(widget.albumId);
      if (mounted) {
        if (detailed != null) {
          setState(() {
            _album = detailed;
            _isLoading = false;
            _errorMessage = null;
          });
        } else if (_album == null) {
          setState(() {
            _isLoading = false;
            _errorMessage = 'Failed to load album details.';
          });
        } else {
          setState(() {
            _isLoading = false;
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
          if (_album == null) {
            _errorMessage = e.toString();
          }
        });
      }
    }
  }

  void _playAll(MusicPlayerService player) {
    final tracks = _album?.tracks ?? [];
    if (tracks.isEmpty) return;

    if (player.isShuffle) {
      player.toggleShuffle();
    }
    player.loadQueue(tracks, startIndex: 0, autoPlay: true);
  }

  void _playShuffle(MusicPlayerService player) {
    final tracks = _album?.tracks ?? [];
    if (tracks.isEmpty) return;

    if (!player.isShuffle) {
      player.toggleShuffle();
    }
    player.loadQueue(tracks, startIndex: 0, autoPlay: true);
  }

  void _playTrackAt(MusicPlayerService player, int index) {
    final tracks = _album?.tracks ?? [];
    if (tracks.isEmpty || index < 0 || index >= tracks.length) return;

    player.loadQueue(tracks, startIndex: index, autoPlay: true);
  }

  @override
  Widget build(BuildContext context) {
    final rtl = Provider.of<RtlService>(context);
    final isArabic = rtl.langSlug.startsWith('ar');
    final player = Provider.of<MusicPlayerService>(context);

    final album = _album;
    final tracks = album?.tracks ?? [];
    final bool hasTracks = tracks.isNotEmpty;

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
          isArabic ? 'تفاصيل الألبوم' : 'Album Details',
          style: const TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w800,
            fontSize: 18,
          ),
        ),
        centerTitle: true,
      ),
      body: Stack(
        children: [
          RefreshIndicator(
            color: FMColors.cyan,
            backgroundColor: FMColors.surfaceElevated,
            onRefresh: _fetchDetails,
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
              padding: EdgeInsets.fromLTRB(
                16,
                8,
                16,
                player.hasActiveTrack ? 108 : 32,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // Loading indicator if no data yet
                  if (_isLoading && album == null)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 80),
                      child: Center(
                        child: CircularProgressIndicator(color: FMColors.cyan),
                      ),
                    )
                  else if (_errorMessage != null && album == null)
                    _buildErrorState(isArabic)
                  else if (album != null) ...[
                    // Album Artwork Hero
                    Center(
                      child: Hero(
                        tag: 'album-cover-${album.id}',
                        child: MusicArtworkWidget(
                          imageUrl: album.coverImageUrl,
                          width: 210,
                          height: 210,
                          borderRadius: FMRadii.lg,
                          placeholderIcon: Icons.album_rounded,
                          placeholderIconSize: 68,
                          showLoadingIndicator: true,
                          border: Border.all(color: FMColors.border),
                          boxShadow: const [
                            BoxShadow(
                              color: Colors.black87,
                              blurRadius: 24,
                              offset: Offset(0, 10),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 20),

                    // Album Title
                    Text(
                      album.displayTitle(isArabic),
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 22,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 6),

                    // Artist Name
                    Text(
                      album.displayArtist,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: FMColors.cyan,
                        fontSize: 15,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 12),

                    // Metadata chips (Genre, Release Date, Tracks Count, Total Duration)
                    Wrap(
                      alignment: WrapAlignment.center,
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        if (album.genre != null)
                          _metaBadge(album.genre!.displayName(isArabic), Icons.category_rounded),
                        if (album.releasedAt != null && album.releasedAt!.isNotEmpty)
                          _metaBadge(
                            album.releasedAt!.split('T').first.split(' ').first,
                            Icons.calendar_today_rounded,
                          ),
                        _metaBadge(
                          isArabic
                              ? '${album.tracksCount} ${album.tracksCount == 1 ? "مقطع" : "مقاطع"}'
                              : '${album.tracksCount} ${album.tracksCount == 1 ? "track" : "tracks"}',
                          Icons.music_note_rounded,
                        ),
                        if (album.formattedTotalDuration.isNotEmpty)
                          _metaBadge(album.formattedTotalDuration, Icons.schedule_rounded),
                      ],
                    ),

                    // Album description if available
                    if (album.displayDescription(isArabic).isNotEmpty) ...[
                      const SizedBox(height: 14),
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: FMColors.surface,
                          borderRadius: BorderRadius.circular(FMRadii.md),
                          border: Border.all(color: FMColors.border),
                        ),
                        child: Text(
                          album.displayDescription(isArabic),
                          style: const TextStyle(
                            color: FMColors.textMuted,
                            fontSize: 13,
                            height: 1.4,
                          ),
                          textAlign: TextAlign.center,
                        ),
                      ),
                    ],

                    const SizedBox(height: 22),

                    // Action buttons (Play All & Shuffle Play)
                    Row(
                      children: [
                        // Play All Button
                        Expanded(
                          child: Container(
                            height: 48,
                            decoration: BoxDecoration(
                              gradient: hasTracks ? FMGradients.funGradient : null,
                              color: hasTracks ? null : FMColors.surfaceElevated,
                              borderRadius: BorderRadius.circular(FMRadii.pill),
                              boxShadow: hasTracks
                                  ? const [
                                      BoxShadow(
                                        color: Color(0x55FF4FB3),
                                        blurRadius: 14,
                                        offset: Offset(0, 4),
                                      ),
                                    ]
                                  : null,
                            ),
                            child: Material(
                              color: Colors.transparent,
                              child: InkWell(
                                borderRadius: BorderRadius.circular(FMRadii.pill),
                                onTap: hasTracks ? () => _playAll(player) : null,
                                child: Row(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    Icon(
                                      Icons.play_arrow_rounded,
                                      color: hasTracks ? Colors.white : Colors.white38,
                                      size: 26,
                                    ),
                                    const SizedBox(width: 6),
                                    Text(
                                      isArabic ? 'تشغيل الكل' : 'Play All',
                                      style: TextStyle(
                                        color: hasTracks ? Colors.white : Colors.white38,
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
                        const SizedBox(width: 12),

                        // Shuffle Play Button
                        Expanded(
                          child: Container(
                            height: 48,
                            decoration: BoxDecoration(
                              color: hasTracks ? FMColors.surfaceElevated : FMColors.surface,
                              borderRadius: BorderRadius.circular(FMRadii.pill),
                              border: Border.all(
                                color: hasTracks ? FMColors.cyan.withOpacity(0.6) : FMColors.border,
                              ),
                            ),
                            child: Material(
                              color: Colors.transparent,
                              child: InkWell(
                                borderRadius: BorderRadius.circular(FMRadii.pill),
                                onTap: hasTracks ? () => _playShuffle(player) : null,
                                child: Row(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    Icon(
                                      Icons.shuffle_rounded,
                                      color: hasTracks ? FMColors.cyan : Colors.white38,
                                      size: 22,
                                    ),
                                    const SizedBox(width: 6),
                                    Text(
                                      isArabic ? 'تشغيل عشوائي' : 'Shuffle',
                                      style: TextStyle(
                                        color: hasTracks ? Colors.white : Colors.white38,
                                        fontSize: 14,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 28),

                    // Tracklist Section Header
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          isArabic ? 'مسارات الألبوم' : 'Album Tracks',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 17,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        if (hasTracks)
                          Text(
                            isArabic ? '${tracks.length} مقطع' : '${tracks.length} tracks',
                            style: const TextStyle(
                              color: FMColors.textMuted,
                              fontSize: 13,
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 12),

                    // Empty or Loaded Tracklist
                    if (!hasTracks && !_isLoading)
                      _buildEmptyTracklistState(isArabic)
                    else if (hasTracks)
                      ListView.separated(
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        itemCount: tracks.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 8),
                        itemBuilder: (context, index) {
                          final track = tracks[index];
                          final isCurrentlyPlayingThis =
                              player.currentTrack?.id == track.id && player.isPlaying;
                          final isCurrentTrackInPlayer = player.currentTrack?.id == track.id;

                          return _buildTrackTile(
                            track: track,
                            index: index,
                            isPlaying: isCurrentlyPlayingThis,
                            isActive: isCurrentTrackInPlayer,
                            isArabic: isArabic,
                            onTap: () => _playTrackAt(player, index),
                            onDetailsTap: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => MusicTrackDetailsPage(track: track),
                                ),
                              );
                            },
                          );
                        },
                      ),
                  ],
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

  Widget _metaBadge(String text, IconData icon) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: FMColors.surfaceElevated,
        borderRadius: BorderRadius.circular(FMRadii.sm),
        border: Border.all(color: FMColors.border),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: FMColors.cyan),
          const SizedBox(width: 5),
          Text(
            text,
            style: const TextStyle(
              color: FMColors.textSecondary,
              fontSize: 12,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTrackTile({
    required CatalogTrackModel track,
    required int index,
    required bool isPlaying,
    required bool isActive,
    required bool isArabic,
    required VoidCallback onTap,
    required VoidCallback onDetailsTap,
  }) {
    final trackNum = track.trackNumber ?? (index + 1);

    return Container(
      decoration: BoxDecoration(
        color: isActive ? FMColors.cyan.withOpacity(0.08) : FMColors.surface,
        borderRadius: BorderRadius.circular(FMRadii.md),
        border: Border.all(
          color: isActive ? FMColors.cyan.withOpacity(0.4) : FMColors.border,
        ),
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(FMRadii.md),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            child: Row(
              children: [
                // Track Number or Active Wave Icon
                SizedBox(
                  width: 28,
                  child: Center(
                    child: isPlaying
                        ? const Icon(Icons.graphic_eq_rounded, color: FMColors.cyan, size: 20)
                        : Text(
                            '$trackNum',
                            style: TextStyle(
                              color: isActive ? FMColors.cyan : FMColors.textMuted,
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                  ),
                ),
                const SizedBox(width: 8),

                // Track Artwork Thumbnail
                MusicArtworkWidget(
                  imageUrl: track.coverArtUrl,
                  width: 44,
                  height: 44,
                  borderRadius: FMRadii.sm,
                  placeholderIconSize: 20,
                ),
                const SizedBox(width: 12),

                // Track Title, Artist, Badges
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        track.displayTitle(isArabic),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: isActive ? FMColors.cyan : Colors.white,
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              track.artistName,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: FMColors.textMuted,
                                fontSize: 12,
                              ),
                            ),
                          ),
                          if (track.isFirstParty) ...[
                            const SizedBox(width: 6),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                              decoration: BoxDecoration(
                                color: FMColors.warning.withOpacity(0.15),
                                borderRadius: BorderRadius.circular(3),
                              ),
                              child: Text(
                                track.displayBadge(isArabic),
                                style: const TextStyle(
                                  color: FMColors.warning,
                                  fontSize: 9,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),
                          ],
                          if (track.isRemix) ...[
                            const SizedBox(width: 6),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                              decoration: BoxDecoration(
                                color: Colors.purple.withOpacity(0.15),
                                borderRadius: BorderRadius.circular(3),
                              ),
                              child: Text(
                                isArabic ? 'ريمكس' : 'REMIX',
                                style: const TextStyle(
                                  color: Colors.purpleAccent,
                                  fontSize: 9,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),

                // Duration
                Text(
                  track.formattedDuration,
                  style: const TextStyle(
                    color: FMColors.textMuted,
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                  ),
                ),

                // Info / Track Details Button
                IconButton(
                  icon: const Icon(Icons.info_outline_rounded, color: FMColors.textMuted, size: 20),
                  tooltip: isArabic ? 'تفاصيل المقطع' : 'Track Details',
                  onPressed: onDetailsTap,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildEmptyTracklistState(bool isArabic) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 36, horizontal: 20),
      decoration: BoxDecoration(
        color: FMColors.surface,
        borderRadius: BorderRadius.circular(FMRadii.md),
        border: Border.all(color: FMColors.border),
      ),
      child: Column(
        children: [
          const Icon(Icons.music_off_rounded, color: FMColors.textMuted, size: 40),
          const SizedBox(height: 12),
          Text(
            isArabic
                ? 'لا توجد مقاطع مضافة لهذا الألبوم حالياً'
                : 'No tracks added to this album yet',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 14,
              fontWeight: FontWeight.w600,
            ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 4),
          Text(
            isArabic
                ? 'سيتم إضافة المقاطع فور اعتمادها في المكتبة.'
                : 'Tracks will appear here once released into the catalog.',
            style: const TextStyle(color: FMColors.textMuted, fontSize: 12),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  Widget _buildErrorState(bool isArabic) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 40, horizontal: 20),
      decoration: BoxDecoration(
        color: FMColors.surface,
        borderRadius: BorderRadius.circular(FMRadii.md),
        border: Border.all(color: FMColors.border),
      ),
      child: Column(
        children: [
          const Icon(Icons.error_outline_rounded, color: FMColors.magenta, size: 44),
          const SizedBox(height: 12),
          Text(
            isArabic ? 'تعذر تحميل بيانات الألبوم' : 'Failed to load album',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 16,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            _errorMessage ?? '',
            style: const TextStyle(color: FMColors.textMuted, fontSize: 12),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 16),
          ElevatedButton.icon(
            style: ElevatedButton.styleFrom(
              backgroundColor: FMColors.cyan,
              foregroundColor: Colors.black,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(FMRadii.pill)),
            ),
            icon: const Icon(Icons.refresh_rounded, size: 18),
            label: Text(isArabic ? 'إعادة المحاولة' : 'Retry'),
            onPressed: _fetchDetails,
          ),
        ],
      ),
    );
  }
}
