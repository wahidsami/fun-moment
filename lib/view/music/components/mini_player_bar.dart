import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/model/buyer_music_model.dart';
import 'package:funmoments/service/music_player_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/music/components/music_artwork_widget.dart';
import 'package:funmoments/view/music/music_track_details_page.dart';
import 'package:funmoments/view/music/now_playing_visualizer_page.dart';

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
                        // Cover artwork with ambient glow
                        Container(
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(FMRadii.sm),
                            boxShadow: [
                              if (player.isPlaying) ...[
                                BoxShadow(
                                  color: const Color(0xFF2DE2FF).withOpacity(0.36),
                                  blurRadius: 10,
                                  spreadRadius: 1,
                                ),
                                BoxShadow(
                                  color: const Color(0xFFFF4FB3).withOpacity(0.20),
                                  blurRadius: 6,
                                ),
                              ],
                            ],
                          ),
                          child: MusicArtworkWidget(
                            imageUrl: track.coverArtUrl,
                            width: 44,
                            height: 44,
                            borderRadius: FMRadii.sm,
                            placeholderIconSize: 22,
                            placeholderBackgroundColor: FMColors.surface,
                          ),
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
                                  // Live mini DJ equalizer indicator when playing
                                  if (player.isPlaying) ...[
                                    const SizedBox(width: 6),
                                    _MiniEqualizerBars(isPlaying: player.isPlaying),
                                  ],
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
                        // Next track button
                        IconButton(
                          icon: Icon(
                            Icons.skip_next_rounded,
                            color: player.hasNext ? Colors.white : Colors.white24,
                            size: 26,
                          ),
                          padding: EdgeInsets.zero,
                          constraints: const BoxConstraints(),
                          tooltip: isRtl ? 'التالي' : 'Next',
                          onPressed: player.hasNext ? () => player.playNext() : null,
                        ),
                        const SizedBox(width: 8),
                        // DJ Visualizer Full-Screen Launcher
                        IconButton(
                          icon: const Icon(Icons.equalizer_rounded, color: FMColors.cyan, size: 22),
                          padding: EdgeInsets.zero,
                          constraints: const BoxConstraints(),
                          tooltip: isRtl ? 'وضع الدي جي البصري' : 'DJ Visualizer',
                          onPressed: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => NowPlayingVisualizerPage(track: track),
                              ),
                            );
                          },
                        ),
                        const SizedBox(width: 8),
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

/// Lightweight, playback-reactive animated 4-bar mini equalizer indicator.
class _MiniEqualizerBars extends StatefulWidget {
  final bool isPlaying;

  const _MiniEqualizerBars({Key? key, required this.isPlaying}) : super(key: key);

  @override
  State<_MiniEqualizerBars> createState() => _MiniEqualizerBarsState();
}

class _MiniEqualizerBarsState extends State<_MiniEqualizerBars>
    with SingleTickerProviderStateMixin, WidgetsBindingObserver {
  late AnimationController _controller;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    )..repeat();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.inactive ||
        state == AppLifecycleState.hidden) {
      _controller.stop();
    } else if (state == AppLifecycleState.resumed) {
      if (mounted) _controller.repeat();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, child) {
        final double t = _controller.value * 2 * math.pi;
        final double h1 = widget.isPlaying ? 4.0 + 8.0 * (0.5 + 0.5 * math.sin(t)) : 3.0;
        final double h2 = widget.isPlaying ? 4.0 + 10.0 * (0.5 + 0.5 * math.cos(t * 1.3)) : 4.0;
        final double h3 = widget.isPlaying ? 4.0 + 9.0 * (0.5 + 0.5 * math.sin(t * 1.7 + 1)) : 3.0;
        final double h4 = widget.isPlaying ? 4.0 + 7.0 * (0.5 + 0.5 * math.cos(t * 0.8 + 2)) : 2.5;

        return SizedBox(
          height: 14,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              _buildBar(h1, const Color(0xFF2DE2FF)),
              const SizedBox(width: 2),
              _buildBar(h2, const Color(0xFF8A2BE2)),
              const SizedBox(width: 2),
              _buildBar(h3, const Color(0xFFFF4FB3)),
              const SizedBox(width: 2),
              _buildBar(h4, const Color(0xFFFFB703)),
            ],
          ),
        );
      },
    );
  }

  Widget _buildBar(double height, Color color) {
    return Container(
      width: 2.2,
      height: height.clamp(2.5, 14.0),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(1.2),
        boxShadow: widget.isPlaying
            ? [BoxShadow(color: color.withOpacity(0.4), blurRadius: 2)]
            : null,
      ),
    );
  }
}

