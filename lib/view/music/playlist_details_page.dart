import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/model/buyer_music_model.dart';
import 'package:funmoments/service/buyer_music_service.dart';
import 'package:funmoments/service/music_player_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/music/components/artwork_ambient_lighting_widget.dart';
import 'package:funmoments/view/music/components/mini_player_bar.dart';
import 'package:funmoments/view/music/components/music_artwork_widget.dart';
import 'package:funmoments/view/music/music_track_details_page.dart';

class PlaylistDetailsPage extends StatefulWidget {
  final int playlistId;

  const PlaylistDetailsPage({
    Key? key,
    required this.playlistId,
  }) : super(key: key);

  @override
  State<PlaylistDetailsPage> createState() => _PlaylistDetailsPageState();
}

class _PlaylistDetailsPageState extends State<PlaylistDetailsPage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final svc = Provider.of<BuyerMusicService>(context, listen: false);
      if (svc.currentPlaylist?.id != widget.playlistId) {
        svc.fetchPlaylistDetails(widget.playlistId);
      }
    });
  }

  void _playAll(BuildContext context, List<CatalogTrackModel> tracks) {
    if (tracks.isEmpty) return;

    // Filter to tracks that are available or previewable
    final playerService = Provider.of<MusicPlayerService>(context, listen: false);
    playerService.loadQueue(tracks, startIndex: 0);
  }

  void _onTrackTap(BuildContext context, CatalogTrackModel track, int index, List<CatalogTrackModel> tracks, bool isArabic) {
    final playerService = Provider.of<MusicPlayerService>(context, listen: false);
    final status = track.accessStatus;

    if (status != null && status.isUnavailable) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            isArabic ? 'هذا المسار غير متاح حالياً.' : 'This track is currently unavailable.',
          ),
          backgroundColor: Colors.redAccent,
        ),
      );
      return;
    }

    if (status != null && (status.isPurchaseRequired || status.isRentalExpired)) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            isArabic
                ? 'تشغيل معاينة المسار (الشراء أو الاستئجار مطلوب للاستماع الكامل).'
                : 'Playing preview (purchase or rental required for full track).',
          ),
          backgroundColor: const Color(0xFF2DE2FF).withOpacity(0.9),
          duration: const Duration(seconds: 3),
          action: SnackBarAction(
            label: isArabic ? 'التفاصيل' : 'Details',
            textColor: Colors.black,
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => MusicTrackDetailsPage(track: track)),
              );
            },
          ),
        ),
      );
    }

    // Play track within playlist queue context
    playerService.loadQueue(tracks, startIndex: index);
  }

  Future<void> _removeTrack(BuildContext context, CatalogTrackModel track, bool isArabic) async {
    final buyerService = Provider.of<BuyerMusicService>(context, listen: false);
    final success = await buyerService.removeTrackFromPlaylist(widget.playlistId, track.id);

    if (success && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            isArabic
                ? 'تمت إزالة "${track.displayTitle(isArabic)}" من قائمة التشغيل'
                : 'Removed "${track.displayTitle(isArabic)}" from playlist',
          ),
          backgroundColor: FMColors.surfaceElevated,
        ),
      );
    }
  }

  Future<void> _onReorder(int oldIndex, int newIndex, List<CatalogTrackModel> tracks) async {
    if (oldIndex < newIndex) {
      newIndex -= 1;
    }
    final item = tracks.removeAt(oldIndex);
    tracks.insert(newIndex, item);

    final orderedIds = tracks.map((t) => t.id).toList();
    final buyerService = Provider.of<BuyerMusicService>(context, listen: false);
    await buyerService.reorderPlaylist(widget.playlistId, orderedIds);
  }

  Widget _buildStatusBadge(TrackAccessStatusModel? accessStatus, bool isArabic) {
    if (accessStatus == null) {
      return const SizedBox.shrink();
    }

    Color bgColor;
    Color textColor;

    switch (accessStatus.status) {
      case 'free':
        bgColor = const Color(0xFF10B981).withOpacity(0.18);
        textColor = const Color(0xFF10B981);
        break;
      case 'purchased':
        bgColor = const Color(0xFF2DE2FF).withOpacity(0.18);
        textColor = const Color(0xFF2DE2FF);
        break;
      case 'rented':
        bgColor = const Color(0xFF8A2BE2).withOpacity(0.22);
        textColor = const Color(0xFFBF5AF2);
        break;
      case 'rental_expired':
        bgColor = const Color(0xFFFFB703).withOpacity(0.20);
        textColor = const Color(0xFFFFB703);
        break;
      case 'purchase_required':
        bgColor = const Color(0xFF3B82F6).withOpacity(0.18);
        textColor = const Color(0xFF60A5FA);
        break;
      case 'unavailable':
      default:
        bgColor = Colors.redAccent.withOpacity(0.18);
        textColor = Colors.redAccent;
        break;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: textColor.withOpacity(0.4), width: 0.8),
      ),
      child: Text(
        accessStatus.displayLabel(isArabic),
        style: TextStyle(
          color: textColor,
          fontSize: 11,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final rtlService = Provider.of<RtlService>(context, listen: false);
    final isArabic = rtlService.langSlug == 'ar';

    return Scaffold(
      backgroundColor: FMColors.background,
      appBar: AppBar(
        backgroundColor: FMColors.surface,
        elevation: 0,
        leading: IconButton(
          icon: Icon(
            isArabic ? Icons.arrow_forward_ios_rounded : Icons.arrow_back_ios_rounded,
            color: Colors.white,
            size: 20,
          ),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text(
          isArabic ? 'تفاصيل قائمة التشغيل' : 'Playlist Details',
          style: const TextStyle(
            color: Colors.white,
            fontSize: 18,
            fontWeight: FontWeight.bold,
          ),
        ),
        centerTitle: true,
      ),
      body: Consumer<BuyerMusicService>(
        builder: (context, buyerService, child) {
          if (buyerService.isCurrentPlaylistLoading && buyerService.currentPlaylist == null) {
            return const Center(
              child: CircularProgressIndicator(color: Color(0xFF2DE2FF)),
            );
          }

          final playlist = buyerService.currentPlaylist;
          if (playlist == null) {
            return Center(
              child: Text(
                isArabic ? 'لم يتم العثور على قائمة التشغيل' : 'Playlist not found',
                style: const TextStyle(color: Colors.white54, fontSize: 16),
              ),
            );
          }

          final tracks = List<CatalogTrackModel>.from(playlist.tracks);

          return CustomScrollView(
            slivers: [
              // Header Section
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 20),
                  child: Column(
                    children: [
                      // Centerpiece Artwork
                      ArtworkAmbientLightingWidget(
                        imageUrl: playlist.coverImageUrl,
                        size: 160,
                        borderRadius: 18,
                        isPlaying: true,
                      ),
                      const SizedBox(height: 18),

                      // Playlist Name
                      Text(
                        playlist.name,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 22,
                          fontWeight: FontWeight.bold,
                          letterSpacing: 0.2,
                        ),
                      ),

                      if (playlist.description != null && playlist.description!.isNotEmpty) ...[
                        const SizedBox(height: 6),
                        Text(
                          playlist.description!,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: Colors.white60,
                            fontSize: 13,
                          ),
                        ),
                      ],

                      const SizedBox(height: 8),

                      // Tracks count and total duration
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Text(
                            isArabic
                                ? '${tracks.length} مسار'
                                : '${tracks.length} ${tracks.length == 1 ? 'track' : 'tracks'}',
                            style: const TextStyle(
                              color: Color(0xFF2DE2FF),
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          if (playlist.totalDurationSeconds > 0) ...[
                            const Text(' • ', style: TextStyle(color: Colors.white38)),
                            Text(
                              playlist.formattedTotalDuration,
                              style: const TextStyle(color: Colors.white54, fontSize: 13),
                            ),
                          ],
                        ],
                      ),

                      const SizedBox(height: 20),

                      // Play All Button
                      if (tracks.isNotEmpty)
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton.icon(
                            style: ElevatedButton.styleFrom(
                              backgroundColor: Colors.transparent,
                              shadowColor: Colors.transparent,
                              padding: const EdgeInsets.symmetric(vertical: 14),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                              elevation: 0,
                            ),
                            icon: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 26),
                            label: Text(
                              isArabic ? 'تشغيل الكل' : 'Play All',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                                letterSpacing: 0.5,
                              ),
                            ),
                            onPressed: () => _playAll(context, tracks),
                          ),
                        ).decorateWithGradient(
                          gradient: const LinearGradient(
                            colors: [Color(0xFF2DE2FF), Color(0xFF8A2BE2), Color(0xFFFF4FB3)],
                          ),
                          borderRadius: BorderRadius.circular(14),
                        ),
                    ],
                  ),
                ),
              ),

              const SliverToBoxAdapter(
                child: Divider(color: Colors.white12, height: 1),
              ),

              // Reorderable Track List or Empty State
              if (tracks.isEmpty)
                SliverFillRemaining(
                  hasScrollBody: false,
                  child: Center(
                    child: Padding(
                      padding: const EdgeInsets.all(24.0),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.music_off_rounded, color: Colors.white24, size: 48),
                          const SizedBox(height: 12),
                          Text(
                            isArabic ? 'قائمة التشغيل هذه فارغة' : 'This playlist is empty',
                            style: const TextStyle(color: Colors.white70, fontSize: 16, fontWeight: FontWeight.bold),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            isArabic
                                ? 'استكشف مسارات متجر الموسيقى وأضفها إلى هذه القائمة.'
                                : 'Explore the music marketplace and add tracks to this playlist.',
                            textAlign: TextAlign.center,
                            style: const TextStyle(color: Colors.white38, fontSize: 13),
                          ),
                        ],
                      ),
                    ),
                  ),
                )
              else
                SliverPadding(
                  padding: const EdgeInsets.only(top: 8, bottom: 24),
                  sliver: SliverList(
                    delegate: SliverChildBuilderDelegate(
                      (context, index) {
                        final track = tracks[index];

                        return Dismissible(
                          key: ValueKey(track.id),
                          direction: DismissDirection.endToStart,
                          background: Container(
                            alignment: Alignment.centerRight,
                            padding: const EdgeInsets.symmetric(horizontal: 20),
                            color: Colors.redAccent.withOpacity(0.85),
                            child: const Icon(Icons.delete_outline_rounded, color: Colors.white),
                          ),
                          onDismissed: (_) => _removeTrack(context, track, isArabic),
                          child: InkWell(
                            onTap: () => _onTrackTap(context, track, index, tracks, isArabic),
                            child: Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                              child: Row(
                                children: [
                                  // Drag indicator / Position
                                  SizedBox(
                                    width: 28,
                                    child: Text(
                                      '${index + 1}',
                                      textAlign: TextAlign.center,
                                      style: const TextStyle(
                                        color: Colors.white38,
                                        fontSize: 13,
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 8),

                                  // Artwork Thumbnail
                                  MusicArtworkWidget(
                                    imageUrl: track.coverImageUrl,
                                    width: 50,
                                    height: 50,
                                    borderRadius: 10,
                                  ),
                                  const SizedBox(width: 14),

                                  // Track Info & Status Badge
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          track.displayTitle(isArabic),
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                          style: const TextStyle(
                                            color: Colors.white,
                                            fontSize: 15,
                                            fontWeight: FontWeight.bold,
                                          ),
                                        ),
                                        const SizedBox(height: 2),
                                        Text(
                                          track.artistName,
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                          style: const TextStyle(
                                            color: Colors.white60,
                                            fontSize: 12,
                                          ),
                                        ),
                                        const SizedBox(height: 4),
                                        Wrap(
                                          crossAxisAlignment: WrapCrossAlignment.center,
                                          spacing: 8,
                                          runSpacing: 4,
                                          children: [
                                            _buildStatusBadge(track.accessStatus, isArabic),
                                            Text(
                                              track.formattedDuration,
                                              style: const TextStyle(
                                                color: Colors.white38,
                                                fontSize: 11,
                                              ),
                                            ),
                                          ],
                                        ),
                                      ],
                                    ),
                                  ),

                                  // Remove action button
                                  IconButton(
                                    icon: const Icon(
                                      Icons.remove_circle_outline_rounded,
                                      color: Colors.white38,
                                      size: 20,
                                    ),
                                    tooltip: isArabic ? 'إزالة' : 'Remove',
                                    onPressed: () => _removeTrack(context, track, isArabic),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        );
                      },
                      childCount: tracks.length,
                    ),
                  ),
                ),
            ],
          );
        },
      ),
      bottomNavigationBar: const MiniPlayerBar(),
    );
  }
}

extension WidgetGradientDecorator on Widget {
  Widget decorateWithGradient({
    required Gradient gradient,
    required BorderRadius borderRadius,
  }) {
    return Container(
      decoration: BoxDecoration(
        gradient: gradient,
        borderRadius: borderRadius,
      ),
      child: this,
    );
  }
}
