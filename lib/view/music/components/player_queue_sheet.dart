import 'package:flutter/material.dart' hide RepeatMode;
import 'package:provider/provider.dart';
import 'package:funmoments/model/buyer_music_model.dart';
import 'package:funmoments/service/music_player_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/music/components/music_artwork_widget.dart';

class PlayerQueueSheet extends StatelessWidget {
  const PlayerQueueSheet({Key? key}) : super(key: key);

  void _confirmClearQueue(BuildContext context, MusicPlayerService player, bool isArabic) {
    showDialog(
      context: context,
      builder: (dialogCtx) => AlertDialog(
        backgroundColor: FMColors.surfaceElevated,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(FMRadii.md),
          side: const BorderSide(color: FMColors.border),
        ),
        title: Text(
          isArabic ? 'مسح قائمة التشغيل' : 'Clear Play Queue',
          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 18),
        ),
        content: Text(
          isArabic
              ? 'هل أنت متأكد من مسح جميع المقاطع من قائمة التشغيل وإيقاف الصوت؟'
              : 'Are you sure you want to remove all tracks from the queue and stop playback?',
          style: const TextStyle(color: FMColors.textSecondary, fontSize: 14),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogCtx),
            child: Text(
              isArabic ? 'إلغاء' : 'Cancel',
              style: const TextStyle(color: FMColors.textMuted),
            ),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: FMColors.error,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(FMRadii.sm),
              ),
            ),
            onPressed: () async {
              Navigator.pop(dialogCtx);
              await player.clearQueue();
              if (context.mounted) {
                Navigator.pop(context);
              }
            },
            child: Text(
              isArabic ? 'مسح الكل' : 'Clear All',
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final rtl = Provider.of<RtlService>(context);
    final isArabic = rtl.langSlug.startsWith('ar');
    final screenHeight = MediaQuery.of(context).size.height;

    return Consumer<MusicPlayerService>(
      builder: (context, player, child) {
        final currentTrack = player.currentTrack;
        final currentIndex = player.currentIndex;
        final totalTracks = player.queue.length;

        // Upcoming tracks are those after currentIndex in play order
        final upcomingTracks = (currentIndex >= 0 && currentIndex < totalTracks - 1)
            ? player.queue.sublist(currentIndex + 1)
            : <CatalogTrackModel>[];

        return Container(
          height: screenHeight * 0.82,
          decoration: const BoxDecoration(
            color: FMColors.surfaceElevated,
            borderRadius: BorderRadius.vertical(top: Radius.circular(FMRadii.lg)),
            border: Border(
              top: BorderSide(color: FMColors.border, width: 1.5),
              left: BorderSide(color: FMColors.border, width: 1),
              right: BorderSide(color: FMColors.border, width: 1),
            ),
          ),
          child: SafeArea(
            top: false,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 10),
                // Drag handle
                Center(
                  child: Container(
                    width: 44,
                    height: 4,
                    decoration: BoxDecoration(
                      color: Colors.white24,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                const SizedBox(height: 12),

                // Top Header: Title, Count, Clear Queue, Close
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: Row(
                    children: [
                      const Icon(Icons.queue_music_rounded, color: FMColors.cyan, size: 24),
                      const SizedBox(width: 10),
                      Text(
                        isArabic ? 'قائمة التشغيل' : 'Play Queue',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 18,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(width: 8),
                      if (totalTracks > 0)
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(
                            color: FMColors.cyan.withOpacity(0.15),
                            borderRadius: BorderRadius.circular(FMRadii.pill),
                            border: Border.all(color: FMColors.cyan.withOpacity(0.4)),
                          ),
                          child: Text(
                            '$totalTracks',
                            style: const TextStyle(
                              color: FMColors.cyan,
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      const Spacer(),
                      if (totalTracks > 0)
                        TextButton.icon(
                          onPressed: () => _confirmClearQueue(context, player, isArabic),
                          icon: const Icon(Icons.delete_sweep_outlined, color: FMColors.error, size: 18),
                          label: Text(
                            isArabic ? 'مسح' : 'Clear',
                            style: const TextStyle(
                              color: FMColors.error,
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          style: TextButton.styleFrom(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                            minimumSize: Size.zero,
                            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                          ),
                        ),
                      const SizedBox(width: 6),
                      IconButton(
                        icon: const Icon(Icons.close_rounded, color: FMColors.textMuted, size: 22),
                        padding: EdgeInsets.zero,
                        constraints: const BoxConstraints(),
                        onPressed: () => Navigator.pop(context),
                      ),
                    ],
                  ),
                ),

                // Quick Playback Mode Bar: Shuffle & Repeat state
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                  child: Row(
                    children: [
                      // Shuffle toggle chip
                      InkWell(
                        onTap: () => player.toggleShuffle(),
                        borderRadius: BorderRadius.circular(FMRadii.sm),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                          decoration: BoxDecoration(
                            color: player.isShuffle ? FMColors.cyan.withOpacity(0.15) : FMColors.surface,
                            borderRadius: BorderRadius.circular(FMRadii.sm),
                            border: Border.all(
                              color: player.isShuffle ? FMColors.cyan : FMColors.border,
                            ),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                Icons.shuffle_rounded,
                                size: 16,
                                color: player.isShuffle ? FMColors.cyan : FMColors.textMuted,
                              ),
                              const SizedBox(width: 6),
                              Text(
                                isArabic ? 'عشوائي' : 'Shuffle',
                                style: TextStyle(
                                  color: player.isShuffle ? FMColors.cyan : FMColors.textMuted,
                                  fontSize: 12,
                                  fontWeight: player.isShuffle ? FontWeight.bold : FontWeight.normal,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),

                      // Repeat mode chip
                      InkWell(
                        onTap: () => player.toggleRepeatMode(),
                        borderRadius: BorderRadius.circular(FMRadii.sm),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                          decoration: BoxDecoration(
                            color: player.repeatMode == RepeatMode.off
                                ? FMColors.surface
                                : (player.repeatMode == RepeatMode.one
                                    ? FMColors.magenta.withOpacity(0.15)
                                    : FMColors.cyan.withOpacity(0.15)),
                            borderRadius: BorderRadius.circular(FMRadii.sm),
                            border: Border.all(
                              color: player.repeatMode == RepeatMode.off
                                  ? FMColors.border
                                  : (player.repeatMode == RepeatMode.one ? FMColors.magenta : FMColors.cyan),
                            ),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                player.repeatMode == RepeatMode.one
                                    ? Icons.repeat_one_rounded
                                    : Icons.repeat_rounded,
                                size: 16,
                                color: player.repeatMode == RepeatMode.off
                                    ? FMColors.textMuted
                                    : (player.repeatMode == RepeatMode.one ? FMColors.magenta : FMColors.cyan),
                              ),
                              const SizedBox(width: 6),
                              Text(
                                player.repeatMode == RepeatMode.off
                                    ? (isArabic ? 'تكرار: إيقاف' : 'Repeat: Off')
                                    : (player.repeatMode == RepeatMode.all
                                        ? (isArabic ? 'تكرار: الكل' : 'Repeat: All')
                                        : (isArabic ? 'تكرار: مسار واحد' : 'Repeat: One')),
                                style: TextStyle(
                                  color: player.repeatMode == RepeatMode.off
                                      ? FMColors.textMuted
                                      : (player.repeatMode == RepeatMode.one ? FMColors.magenta : FMColors.cyan),
                                  fontSize: 12,
                                  fontWeight: player.repeatMode != RepeatMode.off ? FontWeight.bold : FontWeight.normal,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),

                const Divider(color: FMColors.border, height: 1),

                // Main Queue Content
                Expanded(
                  child: totalTracks == 0
                      ? _buildEmptyState(isArabic)
                      : CustomScrollView(
                          physics: const BouncingScrollPhysics(),
                          slivers: [
                            // Section: Now Playing
                            if (currentTrack != null) ...[
                              SliverToBoxAdapter(
                                child: Padding(
                                  padding: const EdgeInsets.fromLTRB(16, 14, 16, 8),
                                  child: Row(
                                    children: [
                                      const Icon(Icons.graphic_eq_rounded, color: FMColors.cyan, size: 18),
                                      const SizedBox(width: 8),
                                      Text(
                                        isArabic ? 'يعمل الآن' : 'Now Playing',
                                        style: const TextStyle(
                                          color: FMColors.cyan,
                                          fontSize: 13,
                                          fontWeight: FontWeight.bold,
                                          letterSpacing: 0.5,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                              SliverToBoxAdapter(
                                child: Padding(
                                  padding: const EdgeInsets.symmetric(horizontal: 16),
                                  child: _buildNowPlayingCard(context, currentTrack, player, isArabic),
                                ),
                              ),
                            ],

                            // Section: Next In Queue Header
                            SliverToBoxAdapter(
                              child: Padding(
                                padding: const EdgeInsets.fromLTRB(16, 20, 16, 8),
                                child: Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    Text(
                                      isArabic
                                          ? 'التالي في القائمة (${upcomingTracks.length})'
                                          : 'Next In Queue (${upcomingTracks.length})',
                                      style: const TextStyle(
                                        color: Colors.white,
                                        fontSize: 14,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                    if (upcomingTracks.isNotEmpty)
                                      Text(
                                        isArabic ? 'اسحب لإعادة الترتيب' : 'Drag to reorder',
                                        style: const TextStyle(
                                          color: FMColors.textMuted,
                                          fontSize: 11,
                                        ),
                                      ),
                                  ],
                                ),
                              ),
                            ),

                            // Upcoming Tracks Reorderable List
                            if (upcomingTracks.isEmpty)
                              SliverToBoxAdapter(
                                child: Padding(
                                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 24),
                                  child: Center(
                                    child: Column(
                                      children: [
                                        const Icon(Icons.playlist_play_rounded, color: Colors.white24, size: 48),
                                        const SizedBox(height: 8),
                                        Text(
                                          isArabic
                                              ? 'لا توجد مقاطع لاحقة في القائمة'
                                              : 'No upcoming tracks in queue',
                                          style: const TextStyle(color: FMColors.textMuted, fontSize: 13),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                              )
                            else
                              SliverToBoxAdapter(
                                child: Padding(
                                  padding: const EdgeInsets.symmetric(horizontal: 16),
                                  child: Theme(
                                    data: Theme.of(context).copyWith(
                                      canvasColor: Colors.transparent,
                                      shadowColor: Colors.transparent,
                                    ),
                                    child: ReorderableListView.builder(
                                      shrinkWrap: true,
                                      physics: const NeverScrollableScrollPhysics(),
                                      itemCount: upcomingTracks.length,
                                      onReorder: (oldIndex, newIndex) {
                                        final fullOldIdx = currentIndex + 1 + oldIndex;
                                        final fullNewIdx = currentIndex + 1 + newIndex;
                                        player.reorderQueue(fullOldIdx, fullNewIdx);
                                      },
                                      itemBuilder: (ctx, index) {
                                        final track = upcomingTracks[index];
                                        final actualIndex = currentIndex + 1 + index;
                                        return _buildUpcomingTrackTile(
                                          key: ValueKey('queue_item_${track.id}_$actualIndex'),
                                          track: track,
                                          actualIndex: actualIndex,
                                          player: player,
                                          isArabic: isArabic,
                                        );
                                      },
                                    ),
                                  ),
                                ),
                              ),

                            // Section: Previously Played (if any)
                            if (currentIndex > 0) ...[
                              SliverToBoxAdapter(
                                child: Padding(
                                  padding: const EdgeInsets.fromLTRB(16, 24, 16, 8),
                                  child: Text(
                                    isArabic
                                        ? 'تم تشغيلها سابقاً ($currentIndex)'
                                        : 'Previously Played ($currentIndex)',
                                    style: const TextStyle(
                                      color: FMColors.textMuted,
                                      fontSize: 13,
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                ),
                              ),
                              SliverList(
                                delegate: SliverChildBuilderDelegate(
                                  (ctx, index) {
                                    final track = player.queue[index];
                                    return Padding(
                                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                                      child: _buildPreviousTrackTile(
                                        track: track,
                                        index: index,
                                        player: player,
                                        isArabic: isArabic,
                                      ),
                                    );
                                  },
                                  childCount: currentIndex,
                                ),
                              ),
                            ],

                            const SliverToBoxAdapter(
                              child: SizedBox(height: 32),
                            ),
                          ],
                        ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildEmptyState(bool isArabic) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: const BoxDecoration(
              color: FMColors.surface,
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.music_off_rounded,
              color: FMColors.textMuted,
              size: 48,
            ),
          ),
          const SizedBox(height: 16),
          Text(
            isArabic ? 'قائمة التشغيل فارغة' : 'Your queue is empty',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 16,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            isArabic
                ? 'اختر أي عمل موسيقي لبدء الاستماع'
                : 'Select any track to start listening',
            style: const TextStyle(
              color: FMColors.textMuted,
              fontSize: 13,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildNowPlayingCard(
    BuildContext context,
    CatalogTrackModel track,
    MusicPlayerService player,
    bool isArabic,
  ) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: FMColors.surface,
        borderRadius: BorderRadius.circular(FMRadii.md),
        border: Border.all(color: FMColors.cyan.withOpacity(0.7), width: 1.5),
        boxShadow: [
          BoxShadow(
            color: FMColors.cyan.withOpacity(0.15),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Row(
        children: [
          MusicArtworkWidget(
            imageUrl: track.coverArtUrl,
            width: 48,
            height: 48,
            borderRadius: FMRadii.sm,
            placeholderIconSize: 24,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  track.displayTitle(isArabic),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  track.artistName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FMColors.textSecondary,
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
          Text(
            track.formattedDuration,
            style: const TextStyle(color: FMColors.textMuted, fontSize: 11),
          ),
          const SizedBox(width: 8),
          IconButton(
            icon: Icon(
              player.isPlaying ? Icons.pause_circle_filled_rounded : Icons.play_circle_filled_rounded,
              color: FMColors.cyan,
              size: 32,
            ),
            padding: EdgeInsets.zero,
            constraints: const BoxConstraints(),
            onPressed: () => player.togglePlayPause(),
          ),
        ],
      ),
    );
  }

  Widget _buildUpcomingTrackTile({
    required Key key,
    required CatalogTrackModel track,
    required int actualIndex,
    required MusicPlayerService player,
    required bool isArabic,
  }) {
    return Container(
      key: key,
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: FMColors.surface,
        borderRadius: BorderRadius.circular(FMRadii.md),
        border: Border.all(color: FMColors.border),
      ),
      child: Row(
        children: [
          // Drag handle
          const Icon(Icons.drag_indicator_rounded, color: Colors.white30, size: 22),
          const SizedBox(width: 6),
          // Artwork
          MusicArtworkWidget(
            imageUrl: track.coverArtUrl,
            width: 40,
            height: 40,
            borderRadius: FMRadii.sm,
            placeholderIconSize: 20,
          ),
          const SizedBox(width: 10),
          // Title & Artist (clickable to jump)
          Expanded(
            child: InkWell(
              onTap: () => player.playAtIndex(actualIndex),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    track.displayTitle(isArabic),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    track.artistName,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: FMColors.textMuted,
                      fontSize: 11,
                    ),
                  ),
                ],
              ),
            ),
          ),
          Text(
            track.formattedDuration,
            style: const TextStyle(color: FMColors.textMuted, fontSize: 11),
          ),
          const SizedBox(width: 4),
          // Remove from queue action
          IconButton(
            icon: const Icon(Icons.close_rounded, color: FMColors.textMuted, size: 18),
            tooltip: isArabic ? 'إزالة من القائمة' : 'Remove from queue',
            padding: const EdgeInsets.all(4),
            constraints: const BoxConstraints(),
            onPressed: () => player.removeAt(actualIndex),
          ),
        ],
      ),
    );
  }

  Widget _buildPreviousTrackTile({
    required CatalogTrackModel track,
    required int index,
    required MusicPlayerService player,
    required bool isArabic,
  }) {
    return Opacity(
      opacity: 0.55,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
          color: FMColors.surface,
          borderRadius: BorderRadius.circular(FMRadii.sm),
          border: Border.all(color: FMColors.border.withOpacity(0.5)),
        ),
        child: Row(
          children: [
            MusicArtworkWidget(
              imageUrl: track.coverArtUrl,
              width: 34,
              height: 34,
              borderRadius: FMRadii.sm,
              placeholderIconSize: 16,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    track.displayTitle(isArabic),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  Text(
                    track.artistName,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: FMColors.textMuted,
                      fontSize: 10,
                    ),
                  ),
                ],
              ),
            ),
            IconButton(
              icon: const Icon(Icons.replay_rounded, color: Colors.white54, size: 18),
              tooltip: isArabic ? 'إعادة التشغيل' : 'Replay',
              padding: EdgeInsets.zero,
              constraints: const BoxConstraints(),
              onPressed: () => player.playAtIndex(index),
            ),
          ],
        ),
      ),
    );
  }
}
