import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/model/buyer_music_model.dart';
import 'package:funmoments/service/buyer_music_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/music/components/music_artwork_widget.dart';

class AddToPlaylistSheet extends StatefulWidget {
  final CatalogTrackModel track;

  const AddToPlaylistSheet({
    Key? key,
    required this.track,
  }) : super(key: key);

  static Future<void> show(BuildContext context, CatalogTrackModel track) {
    return showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => AddToPlaylistSheet(track: track),
    );
  }

  @override
  State<AddToPlaylistSheet> createState() => _AddToPlaylistSheetState();
}

class _AddToPlaylistSheetState extends State<AddToPlaylistSheet> {
  bool _isLoading = true;
  List<MusicPlaylistModel> _playlists = [];

  @override
  void initState() {
    super.initState();
    _loadPlaylists();
  }

  Future<void> _loadPlaylists() async {
    setState(() {
      _isLoading = true;
    });

    final buyerService = Provider.of<BuyerMusicService>(context, listen: false);
    List<MusicPlaylistModel> results = await buyerService.fetchPlaylistsForTrack(widget.track.id);
    if (results.isEmpty && buyerService.playlists.isNotEmpty) {
      results = buyerService.playlists;
    }

    if (mounted) {
      setState(() {
        _playlists = results;
        _isLoading = false;
      });
    }
  }

  Future<void> _showCreatePlaylistDialog(BuildContext context, bool isArabic) async {
    final nameController = TextEditingController();
    final descController = TextEditingController();

    final created = await showDialog<bool>(
      context: context,
      builder: (dialogCtx) {
        return AlertDialog(
          backgroundColor: FMColors.surfaceElevated,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: Text(
            isArabic ? 'إنشاء قائمة تشغيل جديدة' : 'New Playlist',
            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: nameController,
                autofocus: true,
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  hintText: isArabic ? 'اسم قائمة التشغيل' : 'Playlist name',
                  hintStyle: const TextStyle(color: Colors.white54),
                  filled: true,
                  fillColor: FMColors.surface,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: FMColors.border),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: descController,
                style: const TextStyle(color: Colors.white),
                maxLines: 2,
                decoration: InputDecoration(
                  hintText: isArabic ? 'الوصف (اختياري)' : 'Description (optional)',
                  hintStyle: const TextStyle(color: Colors.white54),
                  filled: true,
                  fillColor: FMColors.surface,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: FMColors.border),
                  ),
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogCtx, false),
              child: Text(isArabic ? 'إلغاء' : 'Cancel', style: const TextStyle(color: Colors.white70)),
            ),
            ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: FMColors.cyan,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              onPressed: () {
                if (nameController.text.trim().isNotEmpty) {
                  Navigator.pop(dialogCtx, true);
                }
              },
              child: Text(isArabic ? 'إنشاء' : 'Create', style: const TextStyle(color: Colors.white)),
            ),
          ],
        );
      },
    );

    if (created == true && nameController.text.trim().isNotEmpty) {
      final buyerService = Provider.of<BuyerMusicService>(context, listen: false);
      final newPlaylist = await buyerService.createPlaylist(
        nameController.text.trim(),
        description: descController.text.trim(),
      );
      if (newPlaylist != null) {
        // Automatically add track to the freshly created playlist
        await buyerService.addTrackToPlaylist(newPlaylist.id, widget.track.id);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                isArabic
                    ? 'تم إنشاء "${newPlaylist.name}" وإضافة المسار إليها'
                    : 'Created "${newPlaylist.name}" and added track',
              ),
              backgroundColor: const Color(0xFF10B981),
            ),
          );
          Navigator.pop(context);
        }
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              isArabic ? 'تعذر إنشاء قائمة التشغيل' : 'Failed to create playlist',
            ),
            backgroundColor: Colors.redAccent,
          ),
        );
      }
    }
  }

  Future<void> _toggleTrackInPlaylist(MusicPlaylistModel playlist, bool isArabic) async {
    final buyerService = Provider.of<BuyerMusicService>(context, listen: false);

    if (playlist.containsTrack) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            isArabic
                ? 'المسار موجود بالفعل في "${playlist.name}"'
                : 'Track is already in "${playlist.name}"',
          ),
          backgroundColor: FMColors.surfaceElevated,
        ),
      );
      return;
    }

    final res = await buyerService.addTrackToPlaylist(playlist.id, widget.track.id);

    if (mounted) {
      if (res['success'] == true) {
        setState(() {
          final idx = _playlists.indexWhere((p) => p.id == playlist.id);
          if (idx != -1) {
            final p = _playlists[idx];
            _playlists[idx] = MusicPlaylistModel(
              id: p.id,
              name: p.name,
              description: p.description,
              coverImageUrl: p.coverImageUrl,
              isPublic: p.isPublic,
              tracksCount: p.tracksCount + 1,
              totalDurationSeconds: p.totalDurationSeconds,
              createdAt: p.createdAt,
              updatedAt: p.updatedAt,
              containsTrack: true,
            );
          }
        });

        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              isArabic
                  ? 'تمت إضافة المسار إلى "${playlist.name}"'
                  : 'Added to "${playlist.name}"',
            ),
            backgroundColor: const Color(0xFF10B981),
          ),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(res['message']?.toString() ?? 'Error adding track'),
            backgroundColor: Colors.redAccent,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final rtlService = Provider.of<RtlService>(context, listen: false);
    final isArabic = rtlService.langSlug == 'ar';

    return Container(
      constraints: BoxConstraints(
        maxHeight: MediaQuery.of(context).size.height * 0.75,
      ),
      decoration: BoxDecoration(
        color: FMColors.background,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
        border: Border.all(color: FMColors.border),
      ),
      child: SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Handle bar
            Center(
              child: Container(
                margin: const EdgeInsets.only(top: 12, bottom: 8),
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: Colors.white24,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),

            // Sheet Header
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          isArabic ? 'إضافة إلى قائمة تشغيل' : 'Add to Playlist',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          widget.track.displayTitle(isArabic),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: Colors.white60,
                            fontSize: 13,
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close_rounded, color: Colors.white70),
                    onPressed: () => Navigator.pop(context),
                  ),
                ],
              ),
            ),

            const Divider(color: Colors.white12, height: 1),

            // Action: Create New Playlist
            ListTile(
              contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4),
              leading: Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [Color(0xFF2DE2FF), Color(0xFF8A2BE2)],
                  ),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(Icons.add_rounded, color: Colors.white, size: 24),
              ),
              title: Text(
                isArabic ? 'قائمة تشغيل جديدة' : 'New Playlist',
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w600,
                  fontSize: 15,
                ),
              ),
              subtitle: Text(
                isArabic ? 'إنشاء وإضافة هذا المسار' : 'Create and add this track',
                style: const TextStyle(color: Colors.white54, fontSize: 12),
              ),
              onTap: () => _showCreatePlaylistDialog(context, isArabic),
            ),

            const Divider(color: Colors.white10, height: 1),

            // Playlists list / Loading / Empty
            Expanded(
              child: _isLoading
                  ? const Center(
                      child: CircularProgressIndicator(color: Color(0xFF2DE2FF)),
                    )
                  : _playlists.isEmpty
                      ? Center(
                          child: Padding(
                            padding: const EdgeInsets.all(24),
                            child: Text(
                              isArabic
                                  ? 'ليس لديك قوائم تشغيل حتى الآن. أنشئ قائمة للبدء!'
                                  : 'You don\'t have any playlists yet. Create one to get started!',
                              textAlign: TextAlign.center,
                              style: const TextStyle(color: Colors.white54, fontSize: 14),
                            ),
                          ),
                        )
                      : ListView.separated(
                          padding: const EdgeInsets.symmetric(vertical: 8),
                          itemCount: _playlists.length,
                          separatorBuilder: (_, __) => const Divider(color: Colors.white10, height: 1),
                          itemBuilder: (context, index) {
                            final playlist = _playlists[index];
                            final contains = playlist.containsTrack;

                            return ListTile(
                              contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 2),
                              leading: MusicArtworkWidget(
                                imageUrl: playlist.coverImageUrl,
                                width: 44,
                                height: 44,
                                borderRadius: 8,
                                placeholderIcon: Icons.queue_music_rounded,
                              ),
                              title: Text(
                                playlist.name,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  color: Colors.white,
                                  fontWeight: contains ? FontWeight.bold : FontWeight.normal,
                                  fontSize: 15,
                                ),
                              ),
                              subtitle: Text(
                                isArabic
                                    ? '${playlist.tracksCount} مسار'
                                    : '${playlist.tracksCount} ${playlist.tracksCount == 1 ? 'track' : 'tracks'}',
                                style: const TextStyle(color: Colors.white54, fontSize: 12),
                              ),
                              trailing: contains
                                  ? Container(
                                      padding: const EdgeInsets.all(4),
                                      decoration: const BoxDecoration(
                                        color: Color(0x3310B981),
                                        shape: BoxShape.circle,
                                      ),
                                      child: const Icon(
                                        Icons.check_rounded,
                                        color: Color(0xFF10B981),
                                        size: 20,
                                      ),
                                    )
                                  : const Icon(
                                      Icons.add_circle_outline_rounded,
                                      color: Colors.white38,
                                      size: 22,
                                    ),
                              onTap: () => _toggleTrackInPlaylist(playlist, isArabic),
                            );
                          },
                        ),
            ),
          ],
        ),
      ),
    );
  }
}
