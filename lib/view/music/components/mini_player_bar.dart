import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/model/buyer_music_model.dart';
import 'package:funmoments/service/music_player_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/music/components/music_artwork_widget.dart';
import 'package:funmoments/view/music/music_track_details_page.dart';

class MiniPlayerBar extends StatelessWidget {
  const MiniPlayerBar({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Consumer2<MusicPlayerService, RtlService>(
      builder: (context, player, rtl, child) {
        final track = player.currentTrack;
        if (track == null) {
          return const SizedBox.shrink();
        }

        final isRtl = rtl.langSlug.startsWith('ar');
        final currentPos = player.position;
        final totalDur = player.duration > Duration.zero
            ? player.duration
            : Duration(seconds: track.durationSeconds > 0 ? track.durationSeconds : 30);
        final progress = totalDur.inMilliseconds > 0
            ? (currentPos.inMilliseconds / totalDur.inMilliseconds).clamp(0.0, 1.0)
            : 0.0;

        return Dismissible(
          key: ValueKey('mini-player-${track.id}'),
          direction: DismissDirection.down,
          onDismissed: (_) => player.closePlayer(),
          child: GestureDetector(
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => MusicTrackDetailsPage(track: track),
                ),
              );
            },
            child: Container(
              margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              decoration: BoxDecoration(
                color: FMColors.surfaceElevated.withOpacity(0.96),
                borderRadius: BorderRadius.circular(FMRadii.md),
                border: Border.all(color: FMColors.border.withOpacity(0.8)),
                boxShadow: const [
                  BoxShadow(
                    color: Colors.black54,
                    blurRadius: 14,
                    offset: Offset(0, 4),
                  ),
                ],
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  // Progress line
                  ClipRRect(
                    borderRadius: const BorderRadius.only(
                      topLeft: Radius.circular(FMRadii.md),
                      topRight: Radius.circular(FMRadii.md),
                    ),
                    child: LinearProgressIndicator(
                      value: progress,
                      backgroundColor: Colors.white12,
                      valueColor: const AlwaysStoppedAnimation<Color>(FMColors.cyan),
                      minHeight: 2.5,
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    child: Row(
                      children: [
                        // Cover artwork
                        MusicArtworkWidget(
                          imageUrl: track.coverArtUrl,
                          width: 44,
                          height: 44,
                          borderRadius: FMRadii.sm,
                          placeholderIconSize: 22,
                          placeholderBackgroundColor: FMColors.surface,
                        ),
                        const SizedBox(width: 12),
                        // Track title & artist
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      track.displayTitle(isRtl),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: const TextStyle(
                                        color: FMColors.textPrimary,
                                        fontWeight: FontWeight.w600,
                                        fontSize: 14,
                                      ),
                                    ),
                                  ),
                                  if (player.playbackMode == 'preview')
                                    Container(
                                      margin: const EdgeInsets.only(left: 6),
                                      padding: const EdgeInsets.symmetric(
                                          horizontal: 6, vertical: 2),
                                      decoration: BoxDecoration(
                                        color: FMColors.orange.withOpacity(0.2),
                                        borderRadius: BorderRadius.circular(4),
                                        border: Border.all(
                                            color: FMColors.orange.withOpacity(0.6), width: 0.5),
                                      ),
                                      child: const Text(
                                        'PREVIEW',
                                        style: TextStyle(
                                          color: FMColors.orange,
                                          fontSize: 9,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                                    ),
                                ],
                              ),
                              const SizedBox(height: 2),
                              Text(
                                track.artistName,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: FMColors.textMuted,
                                  fontSize: 12,
                                ),
                              ),
                            ],
                          ),
                        ),
                        // Play/Pause / Buffering indicator
                        if (player.isLoadingTicket || player.isBuffering)
                          const SizedBox(
                            width: 36,
                            height: 36,
                            child: Padding(
                              padding: EdgeInsets.all(8.0),
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                valueColor: AlwaysStoppedAnimation<Color>(FMColors.cyan),
                              ),
                            ),
                          )
                        else
                          IconButton(
                            icon: Icon(
                              player.isPlaying
                                  ? Icons.pause_circle_filled_rounded
                                  : Icons.play_circle_filled_rounded,
                              color: FMColors.cyan,
                              size: 34,
                            ),
                            padding: EdgeInsets.zero,
                            constraints: const BoxConstraints(),
                            onPressed: () => player.togglePlayPause(),
                          ),
                        const SizedBox(width: 4),
                        // Close button
                        IconButton(
                          icon: const Icon(Icons.close_rounded, color: FMColors.textMuted, size: 20),
                          padding: EdgeInsets.zero,
                          constraints: const BoxConstraints(),
                          onPressed: () => player.closePlayer(),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}
