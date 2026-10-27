import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/model/buyer_music_model.dart';
import 'package:funmoments/service/buyer_music_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/music/components/mini_player_bar.dart';
import 'package:funmoments/view/music/components/music_artwork_widget.dart';
import 'package:funmoments/view/music/playlist_details_page.dart';

class MyPlaylistsPage extends StatefulWidget {
  const MyPlaylistsPage({Key? key}) : super(key: key);

  @override
  State<MyPlaylistsPage> createState() => _MyPlaylistsPageState();
}

class _MyPlaylistsPageState extends State<MyPlaylistsPage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<BuyerMusicService>(context, listen: false).fetchPlaylists();
    });
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
                    borderSide: BorderSide(color: FMColors.border),
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
                    borderSide: BorderSide(color: FMColors.border),
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
      if (newPlaylist != null && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              isArabic ? 'تم إنشاء قائمة التشغيل بنجاح' : 'Playlist created successfully',
            ),
            backgroundColor: const Color(0xFF10B981),
          ),
        );
      }
    }
  }

  Future<void> _showRenameDialog(BuildContext context, MusicPlaylistModel playlist, bool isArabic) async {
    final nameController = TextEditingController(text: playlist.name);
    final descController = TextEditingController(text: playlist.description ?? '');

    final updated = await showDialog<bool>(
      context: context,
      builder: (dialogCtx) {
        return AlertDialog(
          backgroundColor: FMColors.surfaceElevated,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: Text(
            isArabic ? 'تعديل قائمة التشغيل' : 'Edit Playlist',
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
                    borderSide: BorderSide(color: FMColors.border),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: descController,
                style: const TextStyle(color: Colors.white),
                maxLines: 2,
                decoration: InputDecoration(
                  hintText: isArabic ? 'الوصف' : 'Description',
                  hintStyle: const TextStyle(color: Colors.white54),
                  filled: true,
                  fillColor: FMColors.surface,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: BorderSide(color: FMColors.border),
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
              child: Text(isArabic ? 'حفظ' : 'Save', style: const TextStyle(color: Colors.white)),
            ),
          ],
        );
      },
    );

    if (updated == true && nameController.text.trim().isNotEmpty) {
      final buyerService = Provider.of<BuyerMusicService>(context, listen: false);
      final success = await buyerService.updatePlaylist(
        playlist.id,
        name: nameController.text.trim(),
        description: descController.text.trim(),
      );
      if (success && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              isArabic ? 'تم تحديث قائمة التشغيل' : 'Playlist updated',
            ),
            backgroundColor: const Color(0xFF10B981),
          ),
        );
      }
    }
  }

  Future<void> _confirmDeletePlaylist(BuildContext context, MusicPlaylistModel playlist, bool isArabic) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogCtx) {
        return AlertDialog(
          backgroundColor: FMColors.surfaceElevated,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: Text(
            isArabic ? 'حذف قائمة التشغيل؟' : 'Delete Playlist?',
            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
          ),
          content: Text(
            isArabic
                ? 'هل أنت متأكد من حذف "${playlist.name}"؟ لن يتم حذف المسارات الصوتية من المنصة.'
                : 'Are you sure you want to delete "${playlist.name}"? The audio tracks will not be deleted.',
            style: const TextStyle(color: Colors.white70, fontSize: 14),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogCtx, false),
              child: Text(isArabic ? 'إلغاء' : 'Cancel', style: const TextStyle(color: Colors.white70)),
            ),
            ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.redAccent,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              onPressed: () => Navigator.pop(dialogCtx, true),
              child: Text(isArabic ? 'حذف' : 'Delete', style: const TextStyle(color: Colors.white)),
            ),
          ],
        );
      },
    );

    if (confirmed == true) {
      final buyerService = Provider.of<BuyerMusicService>(context, listen: false);
      final success = await buyerService.deletePlaylist(playlist.id);
      if (success && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              isArabic ? 'تم حذف قائمة التشغيل' : 'Playlist deleted',
            ),
            backgroundColor: FMColors.surfaceElevated,
          ),
        );
      }
    }
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
          isArabic ? 'قوائم التشغيل' : 'My Playlists',
          style: const TextStyle(
            color: Colors.white,
            fontSize: 18,
            fontWeight: FontWeight.bold,
          ),
        ),
        centerTitle: true,
        actions: [
          IconButton(
            icon: const Icon(Icons.add_rounded, color: Color(0xFF2DE2FF), size: 26),
            tooltip: isArabic ? 'إنشاء قائمة تشغيل' : 'Create Playlist',
            onPressed: () => _showCreatePlaylistDialog(context, isArabic),
          ),
        ],
      ),
      body: Consumer<BuyerMusicService>(
        builder: (context, buyerService, child) {
          if (buyerService.isPlaylistsLoading) {
            return const Center(
              child: CircularProgressIndicator(color: Color(0xFF2DE2FF)),
            );
          }

          if (buyerService.playlistsError == 'UNAUTHENTICATED') {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(28.0),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.lock_outline_rounded, color: Colors.white38, size: 56),
                    const SizedBox(height: 16),
                    Text(
                      isArabic ? 'يرجى تسجيل الدخول' : 'Please Sign In',
                      style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      isArabic
                          ? 'سجل دخولك لإنشاء وإدارة قوائم التشغيل الموسيقية الخاصة بك.'
                          : 'Sign in to create and manage your private music playlists.',
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Colors.white54, fontSize: 14),
                    ),
                  ],
                ),
              ),
            );
          }

          if (buyerService.playlists.isEmpty) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(32.0),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 80,
                      height: 80,
                      decoration: BoxDecoration(
                        color: FMColors.surfaceElevated,
                        shape: BoxShape.circle,
                        border: Border.all(color: const Color(0xFF2DE2FF).withOpacity(0.3)),
                      ),
                      child: const Icon(
                        Icons.queue_music_rounded,
                        color: Color(0xFF2DE2FF),
                        size: 40,
                      ),
                    ),
                    const SizedBox(height: 20),
                    Text(
                      isArabic ? 'لا توجد قوائم تشغيل حتى الآن' : 'No Playlists Yet',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      isArabic
                          ? 'أنشئ قوائم تشغيل مخصصة لمساراتك المفضلة من FUN MOMENT واكتشف تجربة استماع فريدة.'
                          : 'Create custom playlists for your favorite FUN MOMENT tracks and enjoy a personalized listening journey.',
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Colors.white54, fontSize: 13, height: 1.4),
                    ),
                    const SizedBox(height: 24),
                    ElevatedButton.icon(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: FMColors.cyan,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      icon: const Icon(Icons.add_rounded, size: 20),
                      label: Text(
                        isArabic ? 'إنشاء قائمة تشغيل' : 'Create Playlist',
                        style: const TextStyle(fontWeight: FontWeight.bold),
                      ),
                      onPressed: () => _showCreatePlaylistDialog(context, isArabic),
                    ),
                  ],
                ),
              ),
            );
          }

          return RefreshIndicator(
            color: const Color(0xFF2DE2FF),
            backgroundColor: FMColors.surfaceElevated,
            onRefresh: () => buyerService.fetchPlaylists(),
            child: ListView.separated(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
              itemCount: buyerService.playlists.length,
              separatorBuilder: (_, __) => const SizedBox(height: 12),
              itemBuilder: (context, index) {
                final playlist = buyerService.playlists[index];

                return InkWell(
                  borderRadius: BorderRadius.circular(14),
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => PlaylistDetailsPage(playlistId: playlist.id),
                      ),
                    );
                  },
                  child: Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: FMColors.surface,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: FMColors.border),
                    ),
                    child: Row(
                      children: [
                        // Cover artwork
                        MusicArtworkWidget(
                          imageUrl: playlist.coverImageUrl,
                          width: 60,
                          height: 60,
                          borderRadius: 10,
                          placeholderIcon: Icons.queue_music_rounded,
                        ),
                        const SizedBox(width: 14),

                        // Title, details
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                playlist.name,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 16,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                              if (playlist.description != null && playlist.description!.isNotEmpty) ...[
                                const SizedBox(height: 2),
                                Text(
                                  playlist.description!,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(color: Colors.white60, fontSize: 12),
                                ),
                              ],
                              const SizedBox(height: 4),
                              Row(
                                children: [
                                  Text(
                                    isArabic
                                        ? '${playlist.tracksCount} مسار'
                                        : '${playlist.tracksCount} ${playlist.tracksCount == 1 ? 'track' : 'tracks'}',
                                    style: const TextStyle(color: Color(0xFF2DE2FF), fontSize: 12, fontWeight: FontWeight.w600),
                                  ),
                                  if (playlist.totalDurationSeconds > 0) ...[
                                    const Text(' • ', style: TextStyle(color: Colors.white38)),
                                    Text(
                                      playlist.formattedTotalDuration,
                                      style: const TextStyle(color: Colors.white54, fontSize: 12),
                                    ),
                                  ],
                                ],
                              ),
                            ],
                          ),
                        ),

                        // Actions Popup
                        PopupMenuButton<String>(
                          icon: const Icon(Icons.more_vert_rounded, color: Colors.white54),
                          color: FMColors.surfaceElevated,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          onSelected: (action) {
                            if (action == 'rename') {
                              _showRenameDialog(context, playlist, isArabic);
                            } else if (action == 'delete') {
                              _confirmDeletePlaylist(context, playlist, isArabic);
                            }
                          },
                          itemBuilder: (popCtx) => [
                            PopupMenuItem(
                              value: 'rename',
                              child: Row(
                                children: [
                                  const Icon(Icons.edit_rounded, color: Colors.white70, size: 18),
                                  const SizedBox(width: 10),
                                  Text(
                                    isArabic ? 'تعديل' : 'Edit',
                                    style: const TextStyle(color: Colors.white),
                                  ),
                                ],
                              ),
                            ),
                            PopupMenuItem(
                              value: 'delete',
                              child: Row(
                                children: [
                                  const Icon(Icons.delete_outline_rounded, color: Colors.redAccent, size: 18),
                                  const SizedBox(width: 10),
                                  Text(
                                    isArabic ? 'حذف' : 'Delete',
                                    style: const TextStyle(color: Colors.redAccent),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          );
        },
      ),
      bottomNavigationBar: const MiniPlayerBar(),
    );
  }
}
