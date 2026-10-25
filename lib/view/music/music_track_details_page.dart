import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/model/buyer_music_model.dart';
import 'package:funmoments/service/buyer_music_service.dart';
import 'package:funmoments/service/music_player_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';

class MusicTrackDetailsPage extends StatefulWidget {
  final CatalogTrackModel track;

  const MusicTrackDetailsPage({Key? key, required this.track}) : super(key: key);

  @override
  State<MusicTrackDetailsPage> createState() => _MusicTrackDetailsPageState();
}

class _MusicTrackDetailsPageState extends State<MusicTrackDetailsPage> {
  late CatalogTrackModel _track;
  bool _isSeeking = false;
  double _dragPosition = 0.0;

  @override
  void initState() {
    super.initState();
    _track = widget.track;
  }

  void _showPhase5Modal(BuildContext context, String offerType, String price) {
    final isArabic = Provider.of<RtlService>(context, listen: false).langSlug.startsWith('ar');
    showModalBottomSheet(
      context: context,
      backgroundColor: FMColors.surfaceElevated,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(FMRadii.lg)),
      ),
      builder: (ctx) {
        return Padding(
          padding: const EdgeInsets.fromLTRB(24, 20, 24, 32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 48,
                height: 4,
                decoration: BoxDecoration(
                  color: Colors.white24,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(height: 20),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: FMColors.cyan.withOpacity(0.12),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.lock_clock_rounded,
                  color: FMColors.cyan,
                  size: 38,
                ),
              ),
              const SizedBox(height: 16),
              Text(
                isArabic ? 'الدفع والشراء متاح قريباً في المرحلة الخامسة' : 'Checkout Available in Phase 5',
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: FMColors.textPrimary,
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 10),
              Text(
                isArabic
                    ? 'عرض ($offerType) بسعر $price معروض كبيانات تجارية معتمدة. سيتم تفعيل بوابات الدفع وشراء التراخيص بالكامل في المرحلة القادمة.'
                    : 'The $offerType offer at $price is configured on the catalog. Real payment settlement and order processing will launch in Phase 5.',
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: FMColors.textMuted,
                  fontSize: 14,
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: FMColors.cyan,
                    foregroundColor: Colors.black,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(FMRadii.md),
                    ),
                    padding: const EdgeInsets.symmetric(vertical: 14),
                  ),
                  onPressed: () => Navigator.pop(ctx),
                  child: Text(
                    isArabic ? 'حسناً، فهمت' : 'Got it',
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final rtl = Provider.of<RtlService>(context);
    final isArabic = rtl.langSlug.startsWith('ar');
    final musicService = Provider.of<BuyerMusicService>(context);
    final player = Provider.of<MusicPlayerService>(context);

    final bool isThisTrackActive = player.currentTrack?.id == _track.id;
    final Duration currentPos = isThisTrackActive ? player.position : Duration.zero;
    final Duration totalDur = isThisTrackActive && player.duration > Duration.zero
        ? player.duration
        : Duration(seconds: _track.durationSeconds > 0 ? _track.durationSeconds : 30);

    final double sliderMax = totalDur.inSeconds.toDouble() > 0 ? totalDur.inSeconds.toDouble() : 30.0;
    final double sliderValue = _isSeeking
        ? _dragPosition.clamp(0.0, sliderMax)
        : currentPos.inSeconds.toDouble().clamp(0.0, sliderMax);

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
          isArabic ? 'تفاصيل المقطع الموسيقي' : 'Track Details',
          style: const TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w700,
            fontSize: 17,
          ),
        ),
        centerTitle: true,
        actions: [
          IconButton(
            icon: Icon(
              _track.isFavorite ? Icons.favorite_rounded : Icons.favorite_border_rounded,
              color: _track.isFavorite ? FMColors.magenta : Colors.white70,
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
      body: SingleChildScrollView(
        physics: const BouncingScrollPhysics(),
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            // Artwork
            Center(
              child: Hero(
                tag: 'track-art-${_track.id}',
                child: Container(
                  width: 260,
                  height: 260,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(FMRadii.lg),
                    boxShadow: const [
                      BoxShadow(
                        color: Colors.black87,
                        blurRadius: 24,
                        offset: Offset(0, 10),
                      ),
                    ],
                    border: Border.all(color: FMColors.border),
                  ),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(FMRadii.lg),
                    child: _track.coverArtUrl != null && _track.coverArtUrl!.isNotEmpty
                        ? Image.network(
                            _track.coverArtUrl!,
                            fit: BoxFit.cover,
                            errorBuilder: (_, __, ___) => _artFallback(),
                          )
                        : _artFallback(),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 24),

            // Track title & artist
            Text(
              _track.displayTitle(isArabic),
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 22,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              _track.artistName,
              style: const TextStyle(
                color: FMColors.textMuted,
                fontSize: 16,
                fontWeight: FontWeight.w500,
              ),
            ),

            // Mode Badge
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 5),
              decoration: BoxDecoration(
                color: (isThisTrackActive && player.playbackMode == 'full')
                    ? FMColors.success.withOpacity(0.15)
                    : FMColors.cyan.withOpacity(0.15),
                borderRadius: BorderRadius.circular(FMRadii.pill),
                border: Border.all(
                  color: (isThisTrackActive && player.playbackMode == 'full')
                      ? FMColors.success.withOpacity(0.5)
                      : FMColors.cyan.withOpacity(0.5),
                ),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(
                    (isThisTrackActive && player.playbackMode == 'full')
                        ? Icons.verified_rounded
                        : Icons.headphones_rounded,
                    size: 15,
                    color: (isThisTrackActive && player.playbackMode == 'full')
                        ? FMColors.success
                        : FMColors.cyan,
                  ),
                  const SizedBox(width: 6),
                  Text(
                    (isThisTrackActive && player.playbackMode == 'full')
                        ? (isArabic ? 'بث صوتي كامل معتمد' : 'Full In-App Stream')
                        : (isArabic ? 'معاينة تجريبية آمنة' : 'Secure Stream Preview'),
                    style: TextStyle(
                      color: (isThisTrackActive && player.playbackMode == 'full')
                          ? FMColors.success
                          : FMColors.cyan,
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 20),

            // Player seek slider & times
            Column(
              children: [
                SliderTheme(
                  data: SliderTheme.of(context).copyWith(
                    activeTrackColor: FMColors.cyan,
                    inactiveTrackColor: Colors.white12,
                    thumbColor: FMColors.cyan,
                    thumbShape: const RoundSliderThumbShape(enabledThumbRadius: 6),
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

            const SizedBox(height: 16),

            // Controls: Big Play Button
            Center(
              child: GestureDetector(
                onTap: () {
                  if (isThisTrackActive) {
                    player.togglePlayPause();
                  } else {
                    player.playTrack(_track);
                  }
                },
                child: Container(
                  width: 68,
                  height: 68,
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: FMGradients.funGradient,
                    boxShadow: [
                      BoxShadow(
                        color: Color(0x66FF4FB3),
                        blurRadius: 18,
                        offset: Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Center(
                    child: (isThisTrackActive && (player.isLoadingTicket || player.isBuffering))
                        ? const CircularProgressIndicator(
                            strokeWidth: 3,
                            valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                          )
                        : Icon(
                            (isThisTrackActive && player.isPlaying)
                                ? Icons.pause_rounded
                                : Icons.play_arrow_rounded,
                            color: Colors.white,
                            size: 38,
                          ),
                  ),
                ),
              ),
            ),

            const SizedBox(height: 28),

            // Metadata card (Genres, BPM, Key, Rights)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: FMColors.surface,
                borderRadius: BorderRadius.circular(FMRadii.md),
                border: Border.all(color: FMColors.border),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    isArabic ? 'معلومات العمل الموسيقي' : 'Track Information',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 12),
                  if (_track.genres.isNotEmpty) ...[
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: _track.genres.map((g) {
                        return Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(
                            color: FMColors.surfaceElevated,
                            borderRadius: BorderRadius.circular(FMRadii.sm),
                            border: Border.all(color: FMColors.border),
                          ),
                          child: Text(
                            g.name,
                            style: const TextStyle(
                              color: FMColors.textSecondary,
                              fontSize: 12,
                            ),
                          ),
                        );
                      }).toList(),
                    ),
                    const SizedBox(height: 12),
                  ],
                  _metaRow(isArabic ? 'المدة الإجمالية' : 'Duration', _track.formattedDuration),
                  if (_track.bpm != null && _track.bpm! > 0)
                    _metaRow('BPM', '${_track.bpm}'),
                  if (_track.musicalKey != null && _track.musicalKey!.isNotEmpty)
                    _metaRow(isArabic ? 'المقام / المفتاح' : 'Key', _track.musicalKey!),
                  if (_track.rightsDeclaration != null && _track.rightsDeclaration!.isNotEmpty)
                    _metaRow(
                      isArabic ? 'إعلان الحقوق' : 'Rights License',
                      _formatRights(_track.rightsDeclaration!, isArabic),
                    ),
                  if (_track.seller != null)
                    _metaRow(
                      isArabic ? 'مقدم المحتوى' : 'Uploaded by',
                      _track.seller!.name,
                    ),
                ],
              ),
            ),

            const SizedBox(height: 24),

            // Commercial Offers Section (Phase 5 Boundary)
            Align(
              alignment: isArabic ? Alignment.centerRight : Alignment.centerLeft,
              child: Text(
                isArabic ? 'خيارات الترخيص والعروض' : 'Available Offers',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            const SizedBox(height: 12),

            if (_track.offers.isEmpty)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: FMColors.surface,
                  borderRadius: BorderRadius.circular(FMRadii.md),
                  border: Border.all(color: FMColors.border),
                ),
                child: Text(
                  isArabic
                      ? 'لا توجد عروض أسعار تجارية معلنة حالياً.'
                      : 'No commercial offers configured for this track.',
                  style: const TextStyle(color: FMColors.textMuted),
                ),
              )
            else
              Column(
                children: _track.offers.map((offer) {
                  return _offerCard(context, offer, isArabic);
                }).toList(),
              ),

            const SizedBox(height: 40),
          ],
        ),
      ),
    );
  }

  Widget _artFallback() {
    return Container(
      color: FMColors.surfaceElevated,
      child: const Center(
        child: Icon(Icons.music_note_rounded, color: FMColors.cyan, size: 64),
      ),
    );
  }

  Widget _metaRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: FMColors.textMuted, fontSize: 13)),
          Text(value, style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }

  String _formatRights(String rights, bool isArabic) {
    switch (rights) {
      case 'exclusive':
        return isArabic ? 'حقوق حصرية' : 'Exclusive';
      case 'non_exclusive':
        return isArabic ? 'ترخيص غير حصري' : 'Non-Exclusive';
      case 'public_domain':
        return isArabic ? 'ملكية عامة' : 'Public Domain';
      default:
        return rights;
    }
  }

  Widget _offerCard(BuildContext context, CatalogOfferModel offer, bool isArabic) {
    String typeLabel = '';
    IconData typeIcon = Icons.music_note_rounded;
    Color typeColor = FMColors.cyan;

    if (offer.offerType == 'free') {
      typeLabel = isArabic ? 'استماع مجاني' : 'Free Streaming';
      typeIcon = Icons.all_inclusive_rounded;
      typeColor = FMColors.success;
    } else if (offer.offerType == 'rent') {
      typeLabel = isArabic
          ? 'استئجار مؤقت (${offer.rentalDurationDays ?? 7} أيام)'
          : 'Rental (${offer.rentalDurationDays ?? 7} days)';
      typeIcon = Icons.timer_outlined;
      typeColor = FMColors.orange;
    } else if (offer.offerType == 'sale') {
      typeLabel = isArabic ? 'شراء ترخيص دائم' : 'Perpetual License';
      typeIcon = Icons.shopping_bag_outlined;
      typeColor = FMColors.magenta;
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: FMColors.surface,
        borderRadius: BorderRadius.circular(FMRadii.md),
        border: Border.all(color: typeColor.withOpacity(0.35)),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: typeColor.withOpacity(0.12),
              borderRadius: BorderRadius.circular(FMRadii.sm),
            ),
            child: Icon(typeIcon, color: typeColor, size: 24),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  typeLabel,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w700,
                    fontSize: 14,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  offer.formattedPrice,
                  style: TextStyle(
                    color: typeColor,
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                  ),
                ),
              ],
            ),
          ),
          if (offer.offerType == 'free')
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              decoration: BoxDecoration(
                color: FMColors.success.withOpacity(0.15),
                borderRadius: BorderRadius.circular(FMRadii.sm),
              ),
              child: Text(
                isArabic ? 'متاح للبث' : 'Stream Ready',
                style: const TextStyle(
                  color: FMColors.success,
                  fontSize: 11,
                  fontWeight: FontWeight.bold,
                ),
              ),
            )
          else
            ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: FMColors.surfaceElevated,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(FMRadii.sm),
                  side: BorderSide(color: FMColors.border.withOpacity(0.8)),
                ),
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              ),
              onPressed: () {
                _showPhase5Modal(context, typeLabel, offer.formattedPrice);
              },
              child: Text(
                isArabic ? 'المرحلة 5' : 'Phase 5',
                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
              ),
            ),
        ],
      ),
    );
  }

  String _formatSeconds(int totalSec) {
    final m = totalSec ~/ 60;
    final s = totalSec % 60;
    return '${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
  }
}
