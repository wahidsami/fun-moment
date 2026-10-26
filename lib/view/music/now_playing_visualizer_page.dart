import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/model/buyer_music_model.dart';
import 'package:funmoments/service/buyer_music_service.dart';
import 'package:funmoments/service/music_player_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/music/components/dj_visualizer_widget.dart';
import 'package:funmoments/view/music/components/music_artwork_widget.dart';
import 'package:funmoments/view/music/components/player_queue_sheet.dart';

class NowPlayingVisualizerPage extends StatefulWidget {
  final CatalogTrackModel track;

  const NowPlayingVisualizerPage({Key? key, required this.track}) : super(key: key);

  @override
  State<NowPlayingVisualizerPage> createState() => _NowPlayingVisualizerPageState();
}

class _NowPlayingVisualizerPageState extends State<NowPlayingVisualizerPage> {
  late CatalogTrackModel _track;
  DJVisualizerMode _visualizerMode = DJVisualizerMode.neonSpectrum;
  bool _isSeeking = false;
  double _dragPosition = 0.0;

  @override
  void initState() {
    super.initState();
    _track = widget.track;
  }

  String _formatSeconds(int seconds) {
    if (seconds < 0) seconds = 0;
    final int m = seconds ~/ 60;
    final int s = seconds % 60;
    return '${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
  }

  void _cycleVisualizerMode() {
    setState(() {
      switch (_visualizerMode) {
        case DJVisualizerMode.neonSpectrum:
          _visualizerMode = DJVisualizerMode.beatRing;
          break;
        case DJVisualizerMode.beatRing:
          _visualizerMode = DJVisualizerMode.clubLights;
          break;
        case DJVisualizerMode.clubLights:
          _visualizerMode = DJVisualizerMode.off;
          break;
        case DJVisualizerMode.off:
          _visualizerMode = DJVisualizerMode.neonSpectrum;
          break;
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final rtl = Provider.of<RtlService>(context);
    final isArabic = rtl.langSlug.startsWith('ar');
    final musicService = Provider.of<BuyerMusicService>(context);
    final player = Provider.of<MusicPlayerService>(context);

    // Follow active player track if playing
    if (player.hasActiveTrack && player.currentTrack != null && player.currentTrack!.id != _track.id) {
      _track = player.currentTrack!;
    }

    final bool isThisTrackActive = player.hasActiveTrack && player.currentTrack?.id == _track.id;
    final Duration currentPos = isThisTrackActive ? player.position : Duration.zero;
    final Duration totalDur = isThisTrackActive && player.duration > Duration.zero
        ? player.duration
        : Duration(seconds: _track.durationSeconds > 0 ? _track.durationSeconds : 30);

    final double sliderMax = totalDur.inSeconds.toDouble() > 0 ? totalDur.inSeconds.toDouble() : 30.0;
    final double sliderValue = _isSeeking
        ? _dragPosition.clamp(0.0, sliderMax)
        : currentPos.inSeconds.toDouble().clamp(0.0, sliderMax);

    final int effectiveBpm = _track.bpm != null && _track.bpm! > 0 ? _track.bpm! : 124;

    return Scaffold(
      backgroundColor: const Color(0xFF070810),
      body: Stack(
        children: [
          // 1. Procedural DJ Visualizer Canvas (Background)
          Positioned.fill(
            child: DJVisualizerWidget(
              mode: _visualizerMode,
              isPlaying: isThisTrackActive && player.isPlaying,
              bpm: effectiveBpm,
              position: currentPos,
              duration: totalDur,
            ),
          ),

          // 2. Main Full-Screen Player Foreground
          SafeArea(
            child: LayoutBuilder(
              builder: (context, constraints) {
                final double screenH = constraints.maxHeight;
                final double screenW = constraints.maxWidth;
                final double artworkSize = (screenH * 0.30).clamp(160.0, screenW * 0.70);

                return SingleChildScrollView(
                  physics: const BouncingScrollPhysics(),
                  child: ConstrainedBox(
                    constraints: BoxConstraints(minHeight: constraints.maxHeight),
                    child: IntrinsicHeight(
                      child: Column(
                        children: [
                          // Top App Bar
                          Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                // Minimize / Back button
                                IconButton(
                                  icon: const Icon(Icons.keyboard_arrow_down_rounded, color: Colors.white, size: 32),
                                  tooltip: isArabic ? 'إغلاق الشاشة الكاملة' : 'Minimize Player',
                                  onPressed: () => Navigator.pop(context),
                                ),

                                // Center: Visualizer Mode Selector Chip
                                GestureDetector(
                                  onTap: _cycleVisualizerMode,
                                  child: Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                                    decoration: BoxDecoration(
                                      color: FMColors.surfaceElevated.withOpacity(0.85),
                                      borderRadius: BorderRadius.circular(FMRadii.pill),
                                      border: Border.all(
                                        color: _visualizerMode != DJVisualizerMode.off
                                            ? FMColors.cyan.withOpacity(0.7)
                                            : Colors.white24,
                                      ),
                                      boxShadow: [
                                        if (_visualizerMode != DJVisualizerMode.off)
                                          BoxShadow(
                                            color: FMColors.cyan.withOpacity(0.3),
                                            blurRadius: 10,
                                            spreadRadius: 1,
                                          ),
                                      ],
                                    ),
                                    child: Row(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        Icon(
                                          _visualizerMode.icon,
                                          size: 16,
                                          color: _visualizerMode != DJVisualizerMode.off
                                              ? FMColors.cyan
                                              : Colors.white60,
                                        ),
                                        const SizedBox(width: 6),
                                        Text(
                                          isArabic ? _visualizerMode.nameAr : _visualizerMode.nameEn,
                                          style: TextStyle(
                                            color: _visualizerMode != DJVisualizerMode.off
                                                ? Colors.white
                                                : Colors.white70,
                                            fontSize: 12,
                                            fontWeight: FontWeight.w700,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),

                                // Right: Queue Sheet Launcher
                                Stack(
                                  alignment: Alignment.center,
                                  children: [
                                    IconButton(
                                      icon: const Icon(Icons.queue_music_rounded, color: Colors.white, size: 26),
                                      tooltip: isArabic ? 'قائمة التشغيل' : 'Play Queue',
                                      onPressed: () {
                                        showModalBottomSheet(
                                          context: context,
                                          isScrollControlled: true,
                                          backgroundColor: Colors.transparent,
                                          builder: (_) => const PlayerQueueSheet(),
                                        );
                                      },
                                    ),
                                    if (player.queue.isNotEmpty)
                                      Positioned(
                                        top: 6,
                                        right: isArabic ? null : 6,
                                        left: isArabic ? 6 : null,
                                        child: Container(
                                          padding: const EdgeInsets.all(3),
                                          decoration: const BoxDecoration(
                                            color: FMColors.magenta,
                                            shape: BoxShape.circle,
                                          ),
                                          constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                                          child: Text(
                                            '${player.queue.length}',
                                            style: const TextStyle(
                                              color: Colors.white,
                                              fontSize: 9,
                                              fontWeight: FontWeight.bold,
                                            ),
                                            textAlign: TextAlign.center,
                                          ),
                                        ),
                                      ),
                                  ],
                                ),
                              ],
                            ),
                          ),

                          const SizedBox(height: 12),

                          // Center Stage: Album / Track Artwork with Neon Backlight
                          Center(
                            child: Hero(
                              tag: 'track-art-${_track.id}',
                              child: Container(
                                decoration: BoxDecoration(
                                  borderRadius: BorderRadius.circular(FMRadii.xl),
                                  boxShadow: [
                                    BoxShadow(
                                      color: isThisTrackActive && player.isPlaying
                                          ? FMColors.cyan.withOpacity(0.35)
                                          : Colors.black87,
                                      blurRadius: 36,
                                      spreadRadius: 2,
                                      offset: const Offset(0, 8),
                                    ),
                                    BoxShadow(
                                      color: isThisTrackActive && player.isPlaying
                                          ? FMColors.magenta.withOpacity(0.25)
                                          : Colors.black45,
                                      blurRadius: 24,
                                      spreadRadius: 1,
                                    ),
                                  ],
                                ),
                                child: MusicArtworkWidget(
                                  imageUrl: _track.coverArtUrl,
                                  width: artworkSize,
                                  height: artworkSize,
                                  borderRadius: FMRadii.xl,
                                  placeholderIconSize: 64,
                                  showLoadingIndicator: true,
                                  border: Border.all(
                                    color: isThisTrackActive && player.isPlaying
                                        ? FMColors.cyan.withOpacity(0.6)
                                        : FMColors.border,
                                    width: 1.5,
                                  ),
                                ),
                              ),
                            ),
                          ),

                          const SizedBox(height: 16),

                // Track Details & Metadata Row
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 28),
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  _track.displayTitle(isArabic),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 22,
                                    fontWeight: FontWeight.w800,
                                    letterSpacing: -0.3,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  _track.artistName,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    color: FMColors.textMuted,
                                    fontSize: 15,
                                    fontWeight: FontWeight.w500,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          // Favorite heart toggle
                          IconButton(
                            iconSize: 26,
                            icon: Icon(
                              _track.isFavorite ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                              color: _track.isFavorite ? FMColors.magenta : Colors.white60,
                            ),
                            onPressed: () async {
                              final newStatus = await musicService.toggleFavorite(_track);
                              setState(() {
                                _track.isFavorite = newStatus;
                              });
                            },
                          ),
                        ],
                      ),

                      const SizedBox(height: 10),

                      // Badges: DJ Tempo + Stream Type + Attribution
                      Wrap(
                        spacing: 8,
                        runSpacing: 6,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        children: [
                          // Tempo badge
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                            decoration: BoxDecoration(
                              color: FMColors.cyan.withOpacity(0.15),
                              borderRadius: BorderRadius.circular(FMRadii.sm),
                              border: Border.all(color: FMColors.cyan.withOpacity(0.4)),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const Icon(Icons.speed_rounded, size: 13, color: FMColors.cyan),
                                const SizedBox(width: 4),
                                Text(
                                  '$effectiveBpm BPM',
                                  style: const TextStyle(
                                    color: FMColors.cyan,
                                    fontSize: 11,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ],
                            ),
                          ),

                          // Stream status
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                            decoration: BoxDecoration(
                              color: (isThisTrackActive && player.playbackMode == 'full')
                                  ? FMColors.success.withOpacity(0.15)
                                  : FMColors.orange.withOpacity(0.15),
                              borderRadius: BorderRadius.circular(FMRadii.sm),
                              border: Border.all(
                                color: (isThisTrackActive && player.playbackMode == 'full')
                                    ? FMColors.success.withOpacity(0.4)
                                    : FMColors.orange.withOpacity(0.4),
                              ),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(
                                  (isThisTrackActive && player.playbackMode == 'full')
                                      ? Icons.verified_rounded
                                      : Icons.headphones_rounded,
                                  size: 13,
                                  color: (isThisTrackActive && player.playbackMode == 'full')
                                      ? FMColors.success
                                      : FMColors.orange,
                                ),
                                const SizedBox(width: 4),
                                Text(
                                  (isThisTrackActive && player.playbackMode == 'full')
                                      ? (isArabic ? 'بث كامل معتمد' : 'Full In-App Stream')
                                      : (isArabic ? 'معاينة تجريبية' : 'Secure Preview'),
                                  style: TextStyle(
                                    color: (isThisTrackActive && player.playbackMode == 'full')
                                        ? FMColors.success
                                        : FMColors.orange,
                                    fontSize: 11,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ],
                            ),
                          ),

                          if (_track.isFirstParty)
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                              decoration: BoxDecoration(
                                color: FMColors.warning.withOpacity(0.15),
                                borderRadius: BorderRadius.circular(FMRadii.sm),
                                border: Border.all(color: FMColors.warning.withOpacity(0.4)),
                              ),
                              child: Text(
                                _track.displayBadge(isArabic),
                                style: const TextStyle(
                                  color: FMColors.warning,
                                  fontSize: 10,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 18),

                // Seek Bar Slider & Timestamps
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: Column(
                    children: [
                      SliderTheme(
                        data: SliderTheme.of(context).copyWith(
                          activeTrackColor: FMColors.cyan,
                          inactiveTrackColor: Colors.white12,
                          thumbColor: Colors.white,
                          thumbShape: const RoundSliderThumbShape(enabledThumbRadius: 6),
                          overlayColor: FMColors.cyan.withOpacity(0.2),
                          trackHeight: 3.5,
                        ),
                        child: Slider(
                          value: sliderValue,
                          min: 0.0,
                          max: sliderMax,
                          onChanged: (val) {
                            setState(() {
                              _isSeeking = true;
                              _dragPosition = val;
                            });
                          },
                          onChangeEnd: (val) {
                            _isSeeking = false;
                            if (isThisTrackActive) {
                              player.seek(Duration(seconds: val.toInt()));
                            }
                          },
                        ),
                      ),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              _formatSeconds(sliderValue.toInt()),
                              style: const TextStyle(color: FMColors.textMuted, fontSize: 12),
                            ),
                            Text(
                              _formatSeconds(sliderMax.toInt()),
                              style: const TextStyle(color: FMColors.textMuted, fontSize: 12),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 12),

                // Playback Controls Row: Shuffle · Previous · Play/Pause · Next · Repeat
                // Note: In Arabic RTL, Row lays out children from right to left.
                // To ensure Previous is always on the visual left of Play/Pause and Next is on the visual right:
                // - In LTR (English): [Shuffle, Previous, Play/Pause, Next, Repeat]
                // - In RTL (Arabic): [Shuffle, Next, Play/Pause, Previous, Repeat]
                Builder(
                  builder: (context) {
                    final Widget prevButton = IconButton(
                      iconSize: 42,
                      padding: const EdgeInsets.all(4),
                      tooltip: isArabic ? 'السابق' : 'Previous',
                      icon: Icon(
                        Icons.skip_previous_rounded,
                        color: player.hasPrevious ? Colors.white : Colors.white24,
                        size: 42,
                      ),
                      onPressed: player.hasPrevious
                          ? () {
                              player.playPrevious();
                            }
                          : null,
                    );

                    final Widget nextButton = IconButton(
                      iconSize: 42,
                      padding: const EdgeInsets.all(4),
                      tooltip: isArabic ? 'التالي' : 'Next',
                      icon: Icon(
                        Icons.skip_next_rounded,
                        color: player.hasNext ? Colors.white : Colors.white24,
                        size: 42,
                      ),
                      onPressed: player.hasNext
                          ? () {
                              player.playNext();
                            }
                          : null,
                    );

                    return Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          // 1. Shuffle
                          IconButton(
                            iconSize: 26,
                            tooltip: isArabic ? 'تشغيل عشوائي' : 'Shuffle',
                            icon: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(
                                  Icons.shuffle_rounded,
                                  color: player.isShuffle ? FMColors.cyan : Colors.white38,
                                  size: 24,
                                ),
                                const SizedBox(height: 2),
                                Container(
                                  width: 4,
                                  height: 4,
                                  decoration: BoxDecoration(
                                    color: player.isShuffle ? FMColors.cyan : Colors.transparent,
                                    shape: BoxShape.circle,
                                  ),
                                ),
                              ],
                            ),
                            onPressed: () => player.toggleShuffle(),
                          ),

                          // 2. Left side button (Previous in English, Next in Arabic RTL)
                          isArabic ? nextButton : prevButton,

                          // 3. Central Play / Pause Button with Neon Aura
                          GestureDetector(
                            onTap: () {
                              if (isThisTrackActive) {
                                player.togglePlayPause();
                              } else {
                                player.playTrack(_track);
                              }
                            },
                            child: Container(
                              width: 72,
                              height: 72,
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                gradient: const LinearGradient(
                                  colors: [FMColors.cyan, FMColors.magenta],
                                  begin: Alignment.topLeft,
                                  end: Alignment.bottomRight,
                                ),
                                boxShadow: [
                                  BoxShadow(
                                    color: (isThisTrackActive && player.isPlaying)
                                        ? FMColors.cyan.withOpacity(0.55)
                                        : Colors.black54,
                                    blurRadius: 18,
                                    spreadRadius: 2,
                                  ),
                                ],
                              ),
                              child: Center(
                                child: (isThisTrackActive && (player.isBuffering || player.isLoadingTicket))
                                    ? const SizedBox(
                                        width: 28,
                                        height: 28,
                                        child: CircularProgressIndicator(
                                          strokeWidth: 3,
                                          valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                                        ),
                                      )
                                    : Icon(
                                        (isThisTrackActive && player.isPlaying)
                                            ? Icons.pause_rounded
                                            : Icons.play_arrow_rounded,
                                        color: Colors.white,
                                        size: 40,
                                      ),
                              ),
                            ),
                          ),

                          // 4. Right side button (Next in English, Previous in Arabic RTL)
                          isArabic ? prevButton : nextButton,

                          // 5. Repeat
                          IconButton(
                            iconSize: 26,
                            tooltip: isArabic ? 'تكرار' : 'Repeat',
                            icon: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(
                                  player.repeatMode == RepeatMode.one
                                      ? Icons.repeat_one_rounded
                                      : Icons.repeat_rounded,
                                  color: player.repeatMode != RepeatMode.off
                                      ? FMColors.cyan
                                      : Colors.white38,
                                  size: 24,
                                ),
                                const SizedBox(height: 2),
                                Container(
                                  width: 4,
                                  height: 4,
                                  decoration: BoxDecoration(
                                    color: player.repeatMode != RepeatMode.off
                                        ? FMColors.cyan
                                        : Colors.transparent,
                                    shape: BoxShape.circle,
                                  ),
                                ),
                              ],
                            ),
                            onPressed: () => player.toggleRepeatMode(),
                          ),
                        ],
                      ),
                    );
                  },
                ),

                const SizedBox(height: 20),

                // Visualizer Mode Quick Selector Pills
                Container(
                  margin: const EdgeInsets.symmetric(horizontal: 24),
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
                  decoration: BoxDecoration(
                    color: FMColors.surface.withOpacity(0.7),
                    borderRadius: BorderRadius.circular(FMRadii.pill),
                    border: Border.all(color: FMColors.border.withOpacity(0.6)),
                  ),
                  child: Row(
                    children: [
                      _buildModePill(
                        mode: DJVisualizerMode.neonSpectrum,
                        title: isArabic ? 'طيف النيون' : 'Spectrum',
                        icon: Icons.equalizer_rounded,
                      ),
                      _buildModePill(
                        mode: DJVisualizerMode.beatRing,
                        title: isArabic ? 'حلقات الإيقاع' : 'Beat Ring',
                        icon: Icons.radar_rounded,
                      ),
                      _buildModePill(
                        mode: DJVisualizerMode.clubLights,
                        title: isArabic ? 'أضواء الحفلة' : 'Club Lights',
                        icon: Icons.flare_rounded,
                      ),
                    ],
                  ),
                ),

                          const SizedBox(height: 16),
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildModePill({
    required DJVisualizerMode mode,
    required String title,
    required IconData icon,
  }) {
    final bool isSelected = _visualizerMode == mode;
    return Expanded(
      child: GestureDetector(
        onTap: () {
          setState(() {
            _visualizerMode = mode;
          });
        },
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 220),
          padding: const EdgeInsets.symmetric(vertical: 8),
          decoration: BoxDecoration(
            color: isSelected
                ? FMColors.cyan.withOpacity(0.2)
                : Colors.transparent,
            borderRadius: BorderRadius.circular(FMRadii.pill),
            border: isSelected
                ? Border.all(color: FMColors.cyan.withOpacity(0.8), width: 1.2)
                : null,
          ),
          child: FittedBox(
            fit: BoxFit.scaleDown,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  icon,
                  size: 14,
                  color: isSelected ? FMColors.cyan : Colors.white54,
                ),
                const SizedBox(width: 4),
                Text(
                  title,
                  style: TextStyle(
                    color: isSelected ? Colors.white : Colors.white60,
                    fontSize: 11,
                    fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
