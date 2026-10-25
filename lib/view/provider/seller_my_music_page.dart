import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/model/seller_music_model.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/seller_music_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/provider/seller_submit_music_page.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class SellerMyMusicPage extends StatefulWidget {
  const SellerMyMusicPage({Key? key}) : super(key: key);

  @override
  State<SellerMyMusicPage> createState() => _SellerMyMusicPageState();
}

class _SellerMyMusicPageState extends State<SellerMyMusicPage> {
  late SellerMusicService _musicService;
  String _selectedFilter = 'all'; // all, pending, approved, rejected, failed
  final TextEditingController _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _musicService = SellerMusicService();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadTracks();
    });
  }

  Future<void> _loadTracks({bool refresh = false}) async {
    String? status;
    String? mediaStatus;

    if (_selectedFilter == 'pending') {
      status = 'pending';
    } else if (_selectedFilter == 'approved') {
      status = 'approved';
    } else if (_selectedFilter == 'rejected') {
      status = 'rejected';
    } else if (_selectedFilter == 'failed') {
      mediaStatus = 'failed';
    }

    await _musicService.fetchTracks(
      status: status,
      mediaStatus: mediaStatus,
      search: _searchController.text.trim(),
      refresh: refresh,
    );
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Widget _buildFilterChip(String key, String label) {
    final isSelected = _selectedFilter == key;
    return GestureDetector(
      onTap: () {
        if (_selectedFilter != key) {
          setState(() {
            _selectedFilter = key;
          });
          _loadTracks(refresh: true);
        }
      },
      child: Container(
        margin: const EdgeInsets.only(right: 8),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: isSelected ? FMColors.magenta : FMColors.surfaceElevated,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: isSelected ? FMColors.magenta : FMColors.border,
            width: 1,
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            color: isSelected ? Colors.white : FMColors.textSecondary,
            fontSize: 12,
            fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
          ),
        ),
      ),
    );
  }

  Widget _buildStatusBadge(SellerMusicTrack track, AppStringService ln) {
    if (track.isMediaFailed) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        decoration: BoxDecoration(
          color: FMColors.error.withOpacity(0.15),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: FMColors.error.withOpacity(0.5)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline_rounded, color: FMColors.error, size: 12),
            const SizedBox(width: 4),
            Text(
              ln.getString('Processing Failed'),
              style: const TextStyle(color: FMColors.error, fontSize: 11, fontWeight: FontWeight.bold),
            ),
          ],
        ),
      );
    }

    if (track.isMediaProcessing) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        decoration: BoxDecoration(
          color: FMColors.cyan.withOpacity(0.15),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: FMColors.cyan.withOpacity(0.5)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const SizedBox(
              width: 10,
              height: 10,
              child: CircularProgressIndicator(strokeWidth: 2, color: FMColors.cyan),
            ),
            const SizedBox(width: 6),
            Text(
              ln.getString('Processing'),
              style: const TextStyle(color: FMColors.cyan, fontSize: 11, fontWeight: FontWeight.bold),
            ),
          ],
        ),
      );
    }

    if (track.isApproved) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        decoration: BoxDecoration(
          color: FMColors.success.withOpacity(0.15),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: FMColors.success.withOpacity(0.5)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.check_circle_outline_rounded, color: FMColors.success, size: 12),
            const SizedBox(width: 4),
            Text(
              ln.getString('Published'),
              style: const TextStyle(color: FMColors.success, fontSize: 11, fontWeight: FontWeight.bold),
            ),
          ],
        ),
      );
    }

    if (track.isRejected) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        decoration: BoxDecoration(
          color: FMColors.error.withOpacity(0.15),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: FMColors.error.withOpacity(0.5)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cancel_outlined, color: FMColors.error, size: 12),
            const SizedBox(width: 4),
            Text(
              ln.getString('Rejected'),
              style: const TextStyle(color: FMColors.error, fontSize: 11, fontWeight: FontWeight.bold),
            ),
          ],
        ),
      );
    }

    if (track.isSuspended) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        decoration: BoxDecoration(
          color: FMColors.warning.withOpacity(0.15),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: FMColors.warning.withOpacity(0.5)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.pause_circle_outline_rounded, color: FMColors.warning, size: 12),
            const SizedBox(width: 4),
            Text(
              ln.getString('Suspended'),
              style: const TextStyle(color: FMColors.warning, fontSize: 11, fontWeight: FontWeight.bold),
            ),
          ],
        ),
      );
    }

    // Default: Pending review
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: FMColors.info.withOpacity(0.15),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: FMColors.info.withOpacity(0.5)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.hourglass_empty_rounded, color: FMColors.info, size: 12),
          const SizedBox(width: 4),
          Text(
            ln.getString('Pending Review'),
            style: const TextStyle(color: FMColors.info, fontSize: 11, fontWeight: FontWeight.bold),
          ),
        ],
      ),
    );
  }

  Widget _buildTrackCard(SellerMusicTrack track, AppStringService ln) {
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: FMColors.surfaceElevated,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: track.isRejected
              ? FMColors.error.withOpacity(0.4)
              : track.isMediaFailed
                  ? FMColors.error.withOpacity(0.4)
                  : FMColors.border,
          width: 1,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Cover Artwork
              ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: Container(
                  width: 58,
                  height: 58,
                  color: FMColors.inputSurface,
                  child: track.coverImageUrl != null && track.coverImageUrl!.isNotEmpty
                      ? Image.network(
                          track.coverImageUrl!,
                          fit: BoxFit.cover,
                          errorBuilder: (_, __, ___) => const Icon(
                            Icons.music_note_rounded,
                            color: FMColors.textMuted,
                            size: 28,
                          ),
                        )
                      : const Icon(
                          Icons.music_note_rounded,
                          color: FMColors.textMuted,
                          size: 28,
                        ),
                ),
              ),
              const SizedBox(width: 12),

              // Title, Artist, Genre, Duration
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      track.title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 15,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      track.artistName ?? ln.getString('Unknown Artist'),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: FMColors.textMuted,
                        fontSize: 12,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: FMColors.card,
                            borderRadius: BorderRadius.circular(6),
                            border: Border.all(color: FMColors.border.withOpacity(0.5)),
                          ),
                          child: Text(
                            track.genreName ?? ln.getString('General'),
                            style: const TextStyle(color: FMColors.textSecondary, fontSize: 10),
                          ),
                        ),
                        if (track.durationSeconds != null && track.durationSeconds! > 0) ...[
                          const SizedBox(width: 6),
                          Text(
                            '• ${track.formattedDuration}',
                            style: const TextStyle(color: FMColors.textMuted, fontSize: 11),
                          ),
                        ],
                      ],
                    ),
                  ],
                ),
              ),

              // Status badge
              _buildStatusBadge(track, ln),
            ],
          ),

          // Commercial offers tag row
          if (track.offers.isNotEmpty) ...[
            const SizedBox(height: 10),
            Wrap(
              spacing: 6,
              runSpacing: 4,
              children: track.offers.map((offer) {
                Color chipColor = FMColors.magenta;
                if (offer.offerType == 'free') chipColor = FMColors.cyan;
                if (offer.offerType == 'rent') chipColor = FMColors.orange;

                return Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: chipColor.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: chipColor.withOpacity(0.4)),
                  ),
                  child: Text(
                    offer.offerType == 'free'
                        ? ln.getString('Free Streaming')
                        : offer.offerType == 'rent'
                            ? '${ln.getString('Rent')}: ${offer.price} ${offer.currency} (${offer.rentalDurationDays ?? 7}d)'
                            : '${ln.getString('Buy')}: ${offer.price} ${offer.currency}',
                    style: TextStyle(
                      color: chipColor,
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                );
              }).toList(),
            ),
          ],

          // Rejection reason banner
          if (track.isRejected && track.rejectionReason != null && track.rejectionReason!.isNotEmpty) ...[
            const SizedBox(height: 10),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: FMColors.error.withOpacity(0.1),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: FMColors.error.withOpacity(0.3)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Icon(Icons.info_outline_rounded, color: FMColors.error, size: 14),
                      const SizedBox(width: 4),
                      Text(
                        ln.getString('Rejection Reason:'),
                        style: const TextStyle(
                          color: FMColors.error,
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    track.rejectionReason!,
                    style: const TextStyle(color: Colors.white70, fontSize: 11),
                  ),
                ],
              ),
            ),
          ],

          // Retry processing action for failed tracks
          if (track.isMediaFailed) ...[
            const SizedBox(height: 10),
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                OutlinedButton.icon(
                  onPressed: () async {
                    final success = await _musicService.retryProcessing(track.id);
                    if (success) {
                      OthersHelper().showToast(ln.getString('Processing retry queued successfully'), Colors.black);
                      _loadTracks();
                    } else {
                      OthersHelper().showToast(ln.getString('Failed to retry processing'), Colors.black);
                    }
                  },
                  icon: const Icon(Icons.refresh_rounded, size: 14, color: FMColors.cyan),
                  label: Text(
                    ln.getString('Retry Processing'),
                    style: const TextStyle(color: FMColors.cyan, fontSize: 11),
                  ),
                  style: OutlinedButton.styleFrom(
                    side: const BorderSide(color: FMColors.cyan),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final ln = Provider.of<AppStringService>(context);

    return Scaffold(
      backgroundColor: FMColors.background,
      appBar: AppBar(
        title: Text(
          ln.getString('My Music'),
          style: const TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.bold),
        ),
        backgroundColor: FMColors.surfaceDark,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: Colors.white, size: 18),
          onPressed: () => Navigator.pop(context),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded, color: Colors.white),
            onPressed: () => _loadTracks(refresh: true),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: FMColors.magenta,
        onPressed: () async {
          final res = await Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const SellerSubmitMusicPage()),
          );
          if (res == true) {
            _loadTracks(refresh: true);
          }
        },
        icon: const Icon(Icons.add_rounded, color: Colors.white),
        label: Text(
          ln.getString('Submit Track'),
          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
        ),
      ),
      body: ChangeNotifierProvider<SellerMusicService>.value(
        value: _musicService,
        child: Consumer<SellerMusicService>(
          builder: (context, svc, child) {
            return Column(
              children: [
                // Filter Tabs Bar
                Container(
                  color: FMColors.surfaceDark,
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  child: SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: [
                        _buildFilterChip('all', ln.getString('All')),
                        _buildFilterChip('pending', ln.getString('Pending Review')),
                        _buildFilterChip('approved', ln.getString('Approved')),
                        _buildFilterChip('rejected', ln.getString('Rejected')),
                        _buildFilterChip('failed', ln.getString('Failed Processing')),
                      ],
                    ),
                  ),
                ),

                // Track Count Header
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        '${ln.getString('Total Tracks')}: ${svc.totalTracks}',
                        style: const TextStyle(color: FMColors.textMuted, fontSize: 12, fontWeight: FontWeight.w600),
                      ),
                      if (svc.isLoading)
                        const SizedBox(
                          width: 14,
                          height: 14,
                          child: CircularProgressIndicator(strokeWidth: 2, color: FMColors.magenta),
                        ),
                    ],
                  ),
                ),

                // Tracks List / Empty / Error State
                Expanded(
                  child: RefreshIndicator(
                    color: FMColors.magenta,
                    onRefresh: () => _loadTracks(refresh: true),
                    child: svc.isLoading && svc.tracks.isEmpty
                        ? const Center(
                            child: CircularProgressIndicator(color: FMColors.magenta),
                          )
                        : svc.errorMessage != null && svc.tracks.isEmpty
                            ? Center(
                                child: Padding(
                                  padding: const EdgeInsets.all(24),
                                  child: Column(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      const Icon(Icons.error_outline_rounded, color: FMColors.error, size: 42),
                                      const SizedBox(height: 12),
                                      Text(
                                        svc.errorMessage!,
                                        textAlign: TextAlign.center,
                                        style: const TextStyle(color: Colors.white70, fontSize: 13),
                                      ),
                                      const SizedBox(height: 16),
                                      ElevatedButton(
                                        onPressed: () => _loadTracks(refresh: true),
                                        style: ElevatedButton.styleFrom(
                                          backgroundColor: FMColors.magenta,
                                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                        ),
                                        child: Text(ln.getString('Try Again')),
                                      ),
                                    ],
                                  ),
                                ),
                              )
                            : svc.tracks.isEmpty
                                ? Center(
                                    child: Padding(
                                      padding: const EdgeInsets.all(32),
                                      child: Column(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          Container(
                                            padding: const EdgeInsets.all(20),
                                            decoration: const BoxDecoration(
                                              color: FMColors.surfaceElevated,
                                              shape: BoxShape.circle,
                                            ),
                                            child: const Icon(
                                              Icons.library_music_rounded,
                                              size: 48,
                                              color: FMColors.textMuted,
                                            ),
                                          ),
                                          const SizedBox(height: 16),
                                          Text(
                                            ln.getString('No tracks uploaded yet'),
                                            style: const TextStyle(
                                              color: Colors.white,
                                              fontSize: 16,
                                              fontWeight: FontWeight.bold,
                                            ),
                                          ),
                                          const SizedBox(height: 6),
                                          Text(
                                            ln.getString('Submit your master recordings, configure rental or purchase offers, and reach your listeners.'),
                                            textAlign: TextAlign.center,
                                            style: const TextStyle(
                                              color: FMColors.textMuted,
                                              fontSize: 12,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  )
                                : ListView.builder(
                                    physics: const AlwaysScrollableScrollPhysics(),
                                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 80),
                                    itemCount: svc.tracks.length,
                                    itemBuilder: (context, index) {
                                      return _buildTrackCard(svc.tracks[index], ln);
                                    },
                                  ),
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}
