import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/model/buyer_music_model.dart';
import 'package:funmoments/service/buyer_music_service.dart';
import 'package:funmoments/service/music_player_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/music/components/music_artwork_widget.dart';

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

  void _showCheckoutModal(BuildContext context, CatalogOfferModel offer) {
    final isArabic = Provider.of<RtlService>(context, listen: false).langSlug.startsWith('ar');
    final buyerService = Provider.of<BuyerMusicService>(context, listen: false);
    final playerService = Provider.of<MusicPlayerService>(context, listen: false);

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: FMColors.surfaceElevated,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(FMRadii.lg)),
      ),
      builder: (ctx) {
        String selectedGateway = 'wallet';
        bool isSubmitting = false;
        String? orderError;

        return StatefulBuilder(
          builder: (modalCtx, setModalState) {
            return FutureBuilder<Map<String, dynamic>?>(
              future: buyerService.getQuote(offer.id),
              builder: (quoteCtx, snapshot) {
                final quoteLoading = snapshot.connectionState == ConnectionState.waiting;
                final quoteData = snapshot.data;
                final quote = quoteData?['quote'] as Map<String, dynamic>?;
                final bool checkoutEnabled = quoteData?['checkout_enabled'] ?? false;

                final subtotal = quote != null ? (quote['subtotal'] as num).toDouble() : offer.price;
                final vatAmount = quote != null ? (quote['vat_amount'] as num).toDouble() : 0.0;
                final totalAmount = quote != null ? (quote['total_amount'] as num).toDouble() : offer.price;
                final currency = quote?['currency'] ?? 'SAR';

                return Padding(
                  padding: EdgeInsets.fromLTRB(
                    24,
                    20,
                    24,
                    MediaQuery.of(ctx).viewInsets.bottom + 32,
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      // Sheet drag handle
                      Center(
                        child: Container(
                          width: 48,
                          height: 4,
                          decoration: BoxDecoration(
                            color: Colors.white24,
                            borderRadius: BorderRadius.circular(2),
                          ),
                        ),
                      ),
                      const SizedBox(height: 20),

                      // Header
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(10),
                            decoration: BoxDecoration(
                              color: offer.isRent
                                  ? FMColors.orange.withOpacity(0.15)
                                  : FMColors.magenta.withOpacity(0.15),
                              borderRadius: BorderRadius.circular(FMRadii.sm),
                            ),
                            child: Icon(
                              offer.isRent ? Icons.timer_outlined : Icons.shopping_bag_outlined,
                              color: offer.isRent ? FMColors.orange : FMColors.magenta,
                              size: 24,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  offer.isRent
                                      ? (isArabic
                                          ? 'استئجار (${offer.rentalDurationDays ?? 7} أيام)'
                                          : 'Rental (${offer.rentalDurationDays ?? 7} Days)')
                                      : (isArabic ? 'شراء استماع دائم' : 'Permanent Streaming Purchase'),
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 16,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                Text(
                                  _track.title,
                                  style: const TextStyle(
                                    color: FMColors.textMuted,
                                    fontSize: 13,
                                  ),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 20),

                      // Loading quote or breakdown
                      if (quoteLoading)
                        const Padding(
                          padding: EdgeInsets.all(32),
                          child: Center(
                            child: CircularProgressIndicator(color: FMColors.cyan),
                          ),
                        )
                      else ...[
                        // Gate: Checkout Paused Notice
                        if (!checkoutEnabled)
                          Container(
                            padding: const EdgeInsets.all(14),
                            margin: const EdgeInsets.only(bottom: 16),
                            decoration: BoxDecoration(
                              color: FMColors.orange.withOpacity(0.12),
                              borderRadius: BorderRadius.circular(FMRadii.md),
                              border: Border.all(color: FMColors.orange.withOpacity(0.4)),
                            ),
                            child: Row(
                              children: [
                                const Icon(Icons.info_outline, color: FMColors.orange, size: 22),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Text(
                                    isArabic
                                        ? 'الشراء التجاري معلق حالياً بانتظار مراجعة السياسات الضريبية (هيئة الزكاة والضريبة والجمارك 2026).'
                                        : 'Commercial checkout is temporarily paused pending tax policy review.',
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontSize: 12,
                                      height: 1.3,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),

                        // Financial Breakdown Card
                        Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: FMColors.surface,
                            borderRadius: BorderRadius.circular(FMRadii.md),
                            border: Border.all(color: FMColors.border),
                          ),
                          child: Column(
                            children: [
                              _quoteRow(
                                isArabic ? 'السعر الأساسي' : 'Subtotal',
                                '${subtotal.toStringAsFixed(2)} $currency',
                              ),
                              if (vatAmount > 0) ...[
                                const SizedBox(height: 8),
                                _quoteRow(
                                  isArabic ? 'ضريبة القيمة المضافة (15%)' : 'VAT (15%)',
                                  '${vatAmount.toStringAsFixed(2)} $currency',
                                ),
                              ],
                              const Divider(color: FMColors.border, height: 20),
                              _quoteRow(
                                isArabic ? 'الإجمالي المستحق' : 'Total Payable',
                                '${totalAmount.toStringAsFixed(2)} $currency',
                                isTotal: true,
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 18),

                        // Gateway Selector (Wallet vs PayTabs)
                        if (checkoutEnabled) ...[
                          Text(
                            isArabic ? 'طريقة الدفع' : 'Payment Method',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 13,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          const SizedBox(height: 10),
                          Row(
                            children: [
                              Expanded(
                                child: _gatewayOption(
                                  title: isArabic ? 'المحفظة' : 'Wallet',
                                  icon: Icons.account_balance_wallet_outlined,
                                  isSelected: selectedGateway == 'wallet',
                                  onTap: () => setModalState(() => selectedGateway = 'wallet'),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: _gatewayOption(
                                  title: isArabic ? 'بطاقة بنكية' : 'Card / PayTabs',
                                  icon: Icons.credit_card_outlined,
                                  isSelected: selectedGateway == 'paytabs',
                                  onTap: () => setModalState(() => selectedGateway = 'paytabs'),
                                ),
                              ),
                            ],
                          ),
                        ],
                        const SizedBox(height: 14),

                        // Licensing disclaimer
                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: Colors.black26,
                            borderRadius: BorderRadius.circular(FMRadii.sm),
                          ),
                          child: Text(
                            isArabic
                                ? 'ترخيص استماع وبث صوتي فقط داخل التطبيق. لا يشمل تنزيل الملفات الأصلية أو الاستخدام التجاري أو إعادة التوزيع.'
                                : 'In-App Streaming Only. Master downloads, commercial-use, and redistribution rights are not included.',
                            style: const TextStyle(
                              color: FMColors.textMuted,
                              fontSize: 11,
                              height: 1.3,
                            ),
                          ),
                        ),
                        const SizedBox(height: 16),

                        if (orderError != null) ...[
                          Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: Text(
                              orderError!,
                              style: const TextStyle(color: FMColors.error, fontSize: 12),
                              textAlign: TextAlign.center,
                            ),
                          ),
                        ],

                        // Confirm Checkout Button
                        SizedBox(
                          height: 50,
                          child: ElevatedButton(
                            style: ElevatedButton.styleFrom(
                              backgroundColor: checkoutEnabled ? FMColors.cyan : Colors.white12,
                              foregroundColor: checkoutEnabled ? Colors.black : Colors.white38,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(FMRadii.md),
                              ),
                            ),
                            onPressed: (!checkoutEnabled || isSubmitting)
                                ? null
                                : () async {
                                    setModalState(() {
                                      isSubmitting = true;
                                      orderError = null;
                                    });

                                    final res = await buyerService.createOrder(
                                      offerId: offer.id,
                                      paymentGateway: selectedGateway,
                                    );

                                    setModalState(() => isSubmitting = false);

                                    if (res['success'] == true) {
                                      Navigator.pop(ctx);
                                      if (selectedGateway == 'wallet') {
                                        // Auto-unlock playback
                                        playerService.playTrack(_track);
                                        ScaffoldMessenger.of(context).showSnackBar(
                                          SnackBar(
                                            backgroundColor: FMColors.success,
                                            content: Text(
                                              isArabic
                                                  ? 'تم الشراء بنجاح! بدأ الاستماع الآن.'
                                                  : 'Order confirmed! Streaming unlocked.',
                                            ),
                                          ),
                                        );
                                      } else {
                                        ScaffoldMessenger.of(context).showSnackBar(
                                          SnackBar(
                                            backgroundColor: FMColors.cyan,
                                            content: Text(
                                              isArabic
                                                  ? 'تم تسجيل الطلب في انتظار استكمال الدفع.'
                                                  : 'Order created, awaiting payment completion.',
                                            ),
                                          ),
                                        );
                                      }
                                    } else {
                                      setModalState(() {
                                        orderError = isArabic
                                            ? (res['message_ar'] ?? res['message_en'] ?? 'فشل إتمام الطلب.')
                                            : (res['message_en'] ?? 'Failed to complete order.');
                                      });
                                    }
                                  },
                            child: isSubmitting
                                ? const SizedBox(
                                    width: 20,
                                    height: 20,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      valueColor: AlwaysStoppedAnimation<Color>(Colors.black),
                                    ),
                                  )
                                : Text(
                                    checkoutEnabled
                                        ? (isArabic ? 'تأكيد الدفع والاستماع' : 'Confirm & Play')
                                        : (isArabic ? 'الشراء معلق حالياً' : 'Checkout Disabled'),
                                    style: const TextStyle(
                                      fontWeight: FontWeight.bold,
                                      fontSize: 15,
                                    ),
                                  ),
                          ),
                        ),
                      ],
                    ],
                  ),
                );
              },
            );
          },
        );
      },
    );
  }

  Widget _quoteRow(String label, String value, {bool isTotal = false}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: TextStyle(
            color: isTotal ? Colors.white : FMColors.textMuted,
            fontSize: isTotal ? 14 : 13,
            fontWeight: isTotal ? FontWeight.bold : FontWeight.normal,
          ),
        ),
        Text(
          value,
          style: TextStyle(
            color: isTotal ? FMColors.cyan : Colors.white,
            fontSize: isTotal ? 16 : 13,
            fontWeight: isTotal ? FontWeight.bold : FontWeight.w600,
          ),
        ),
      ],
    );
  }

  Widget _gatewayOption({
    required String title,
    required IconData icon,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 10),
        decoration: BoxDecoration(
          color: isSelected ? FMColors.cyan.withOpacity(0.12) : FMColors.surface,
          borderRadius: BorderRadius.circular(FMRadii.md),
          border: Border.all(
            color: isSelected ? FMColors.cyan : FMColors.border,
            width: isSelected ? 1.5 : 1,
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              icon,
              size: 18,
              color: isSelected ? FMColors.cyan : FMColors.textSecondary,
            ),
            const SizedBox(width: 8),
            Text(
              title,
              style: TextStyle(
                color: isSelected ? Colors.white : FMColors.textSecondary,
                fontSize: 12,
                fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
              ),
            ),
          ],
        ),
      ),
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
                child: MusicArtworkWidget(
                  imageUrl: _track.coverArtUrl,
                  width: 260,
                  height: 260,
                  borderRadius: FMRadii.lg,
                  placeholderIconSize: 64,
                  showLoadingIndicator: true,
                  border: Border.all(color: FMColors.border),
                  boxShadow: const [
                    BoxShadow(
                      color: Colors.black87,
                      blurRadius: 24,
                      offset: Offset(0, 10),
                    ),
                  ],
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
            if (_track.isFirstParty) ...[
              const SizedBox(height: 6),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                decoration: BoxDecoration(
                  color: FMColors.warning.withOpacity(0.15),
                  borderRadius: BorderRadius.circular(FMRadii.sm),
                  border: Border.all(color: FMColors.warning.withOpacity(0.4)),
                ),
                child: Text(
                  _track.displayBadge(isArabic),
                  style: const TextStyle(
                    color: FMColors.warning,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
            if (_track.isRemix) ...[
              const SizedBox(height: 6),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                decoration: BoxDecoration(
                  color: Colors.purple.withOpacity(0.15),
                  borderRadius: BorderRadius.circular(FMRadii.sm),
                  border: Border.all(color: Colors.purple.withOpacity(0.4)),
                ),
                child: Text(
                  isArabic
                      ? 'ريمكس من: ${_track.originalTrack?.title ?? "العمل الأصلي"}'
                      : 'Remix of: ${_track.originalTrack?.title ?? "Original Track"}',
                  style: const TextStyle(
                    color: Colors.purpleAccent,
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],

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
                  if (_track.album != null)
                    _metaRow(isArabic ? 'الألبوم' : 'Album', _track.album!.title),
                  if (_track.trackNumber != null)
                    _metaRow(isArabic ? 'رقم المسار' : 'Track #', '#${_track.trackNumber}'),
                  if (_track.originalTrack != null)
                    _metaRow(isArabic ? 'العمل الأصلي' : 'Original Track', _track.originalTrack!.title),
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
                  if (_track.isFirstParty)
                    _metaRow(
                      isArabic ? 'ملكية العمل' : 'Ownership',
                      isArabic ? 'أصلي من FUN MOMENT (المكتبة الرسمية)' : 'FUN MOMENT Official Original',
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
                _showCheckoutModal(context, offer);
              },
              child: Text(
                offer.isRent
                    ? (isArabic ? 'استئجار' : 'Rent')
                    : (isArabic ? 'شراء' : 'Buy'),
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
