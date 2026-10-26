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

class MyMusicFavoritesPage extends StatefulWidget {
  const MyMusicFavoritesPage({Key? key}) : super(key: key);

  @override
  State<MyMusicFavoritesPage> createState() => _MyMusicFavoritesPageState();
}

class _MyMusicFavoritesPageState extends State<MyMusicFavoritesPage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<BuyerMusicService>(context, listen: false).fetchFavorites();
    });
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
          isArabic ? 'المقاطع الصوتية المفضلة' : 'Favorite Tracks',
          style: const TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w700,
            fontSize: 17,
          ),
        ),
        centerTitle: true,
      ),
      body: Stack(
        children: [
          RefreshIndicator(
            color: FMColors.magenta,
            backgroundColor: FMColors.surfaceElevated,
            onRefresh: () async {
              await musicService.fetchFavorites();
            },
            child: musicService.isFavoritesLoading && musicService.favoriteTracks.isEmpty
                ? const Center(
                    child: CircularProgressIndicator(
                      valueColor: AlwaysStoppedAnimation<Color>(FMColors.magenta),
                    ),
                  )
                : musicService.favoriteTracks.isEmpty
                    ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
                        children: [
                          SizedBox(height: MediaQuery.of(context).size.height * 0.25),
                          Center(
                            child: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Container(
                                  padding: const EdgeInsets.all(20),
                                  decoration: BoxDecoration(
                                    color: FMColors.surfaceElevated,
                                    shape: BoxShape.circle,
                                    border: Border.all(color: FMColors.border),
                                  ),
                                  child: const Icon(
                                    Icons.favorite_border_rounded,
                                    color: FMColors.magenta,
                                    size: 48,
                                  ),
                                ),
                                const SizedBox(height: 16),
                                Text(
                                  isArabic ? 'لا توجد مقاطع مفضلة بعد' : 'No Favorite Tracks Yet',
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 18,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Padding(
                                  padding: const EdgeInsets.symmetric(horizontal: 40),
                                  child: Text(
                                    isArabic
                                        ? 'استكشف سوق الموسيقى وأضف أغانيك ومقاطعك المفضلة للوصول إليها بسرعة.'
                                        : 'Explore the Music Marketplace and save tracks you love for quick access.',
                                    textAlign: TextAlign.center,
                                    style: const TextStyle(
                                      color: FMColors.textMuted,
                                      fontSize: 14,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      )
                    : ListView.builder(
                        physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
                        padding: EdgeInsets.fromLTRB(
                          16,
                          12,
                          16,
                          player.hasActiveTrack ? 90 : 24,
                        ),
                        itemCount: musicService.favoriteTracks.length,
                        itemBuilder: (context, index) {
                          final track = musicService.favoriteTracks[index];
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
                              leading: MusicArtworkWidget(
                                imageUrl: track.coverArtUrl,
                                width: 50,
                                height: 50,
                                borderRadius: FMRadii.sm,
                                placeholderIconSize: 24,
                                placeholderBackgroundColor: FMColors.surfaceElevated,
                              ),
                              title: Text(
                                track.displayTitle(isArabic),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w600,
                                  fontSize: 15,
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
                                    style: const TextStyle(
                                      color: FMColors.textMuted,
                                      fontSize: 13,
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  Row(
                                    children: [
                                      Text(
                                        track.formattedDuration,
                                        style: const TextStyle(
                                          color: FMColors.cyan,
                                          fontSize: 11,
                                          fontWeight: FontWeight.w500,
                                        ),
                                      ),
                                      if (track.genres.isNotEmpty) ...[
                                        const SizedBox(width: 8),
                                        Container(
                                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                                          decoration: BoxDecoration(
                                            color: FMColors.surfaceElevated,
                                            borderRadius: BorderRadius.circular(4),
                                          ),
                                          child: Text(
                                            track.genres.first.displayName(isArabic),
                                            style: const TextStyle(
                                              color: FMColors.textSecondary,
                                              fontSize: 10,
                                            ),
                                          ),
                                        ),
                                      ],
                                    ],
                                  ),
                                ],
                              ),
                              trailing: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  IconButton(
                                    icon: Icon(
                                      isPlaying
                                          ? Icons.pause_circle_filled_rounded
                                          : Icons.play_circle_filled_rounded,
                                      color: FMColors.cyan,
                                      size: 32,
                                    ),
                                    onPressed: () {
                                      if (player.currentTrack?.id == track.id) {
                                        player.togglePlayPause();
                                      } else {
                                        player.playTrack(track);
                                      }
                                    },
                                  ),
                                  IconButton(
                                    icon: const Icon(
                                      Icons.favorite_rounded,
                                      color: FMColors.magenta,
                                      size: 22,
                                    ),
                                    onPressed: () {
                                      musicService.toggleFavorite(track);
                                    },
                                  ),
                                ],
                              ),
                              onTap: () {
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => MusicTrackDetailsPage(track: track),
                                  ),
                                );
                              },
                            ),
                          );
                        },
                      ),
          ),
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
}
