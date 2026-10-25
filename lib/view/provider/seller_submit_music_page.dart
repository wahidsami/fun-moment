import 'dart:io';
import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/model/seller_music_model.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/seller_music_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class SellerSubmitMusicPage extends StatefulWidget {
  const SellerSubmitMusicPage({Key? key}) : super(key: key);

  @override
  State<SellerSubmitMusicPage> createState() => _SellerSubmitMusicPageState();
}

class _SellerSubmitMusicPageState extends State<SellerSubmitMusicPage> {
  final _formKey = GlobalKey<FormState>();
  late SellerMusicService _musicService;

  // Controllers
  final TextEditingController _titleController = TextEditingController();
  final TextEditingController _artistController = TextEditingController();
  final TextEditingController _descriptionController = TextEditingController();
  final TextEditingController _rightsNotesController = TextEditingController();

  // Offers
  bool _enableFree = false;
  bool _enableRent = true;
  final TextEditingController _rentPriceController = TextEditingController(text: '15');
  final TextEditingController _rentDaysController = TextEditingController(text: '7');

  bool _enableSale = true;
  final TextEditingController _salePriceController = TextEditingController(text: '45');

  // Files
  File? _pickedAudioFile;
  String? _pickedAudioFileName;
  int? _pickedAudioFileSizeBytes;

  File? _pickedCoverFile;

  // Metadata
  int? _selectedGenreId;
  final List<String> _selectedSecondaryGenres = [];
  String _selectedRightsDeclaration = 'exclusive';
  bool _agreeRights = false;

  bool _isLoadingConfig = true;
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    _musicService = SellerMusicService();
    _initData();
  }

  Future<void> _initData() async {
    setState(() => _isLoadingConfig = true);
    await Future.wait([
      _musicService.fetchConfig(),
      _musicService.fetchGenres(),
    ]);

    if (mounted) {
      if (_musicService.genres.isNotEmpty) {
        _selectedGenreId = _musicService.genres.first.id;
      }
      if (_musicService.config != null) {
        _rentDaysController.text = _musicService.config!.defaultRentalDurationDays.toString();
        _enableFree = _musicService.config!.offerFreeEnabled;
        _enableRent = _musicService.config!.offerRentEnabled;
        _enableSale = _musicService.config!.offerSaleEnabled;
      }
      setState(() => _isLoadingConfig = false);
    }
  }

  @override
  void dispose() {
    _titleController.dispose();
    _artistController.dispose();
    _descriptionController.dispose();
    _rightsNotesController.dispose();
    _rentPriceController.dispose();
    _rentDaysController.dispose();
    _salePriceController.dispose();
    super.dispose();
  }

  Future<void> _pickAudioFile(AppStringService ln) async {
    final allowedFormats = _musicService.config?.allowedAudioFormats ?? ['mp3', 'wav', 'flac', 'aac', 'm4a'];
    final maxMb = _musicService.config?.effectiveMaxUploadMb ?? 30;

    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: allowedFormats,
      );

      if (result != null && result.files.isNotEmpty && result.files.first.path != null) {
        final file = File(result.files.first.path!);
        final sizeBytes = await file.length();

        if (sizeBytes > maxMb * 1024 * 1024) {
          OthersHelper().showToast(
            '${ln.getString('Audio file exceeds the limit of')} $maxMb MB',
            Colors.black,
          );
          return;
        }

        setState(() {
          _pickedAudioFile = file;
          _pickedAudioFileName = result.files.first.name;
          _pickedAudioFileSizeBytes = sizeBytes;
        });
      }
    } catch (e) {
      OthersHelper().showToast(ln.getString('Failed to pick audio file'), Colors.black);
    }
  }

  Future<void> _pickCoverImage(AppStringService ln) async {
    try {
      final picker = ImagePicker();
      final picked = await picker.pickImage(
        source: ImageSource.gallery,
        maxWidth: 1600,
        maxHeight: 1600,
        imageQuality: 90,
      );

      if (picked != null) {
        final file = File(picked.path);
        final size = await file.length();
        if (size > 5 * 1024 * 1024) {
          OthersHelper().showToast(ln.getString('Cover artwork must be under 5MB'), Colors.black);
          return;
        }
        setState(() {
          _pickedCoverFile = file;
        });
      }
    } catch (e) {
      OthersHelper().showToast(ln.getString('Failed to pick cover image'), Colors.black);
    }
  }

  Future<void> _submitForm(AppStringService ln) async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    if (_pickedAudioFile == null) {
      OthersHelper().showToast(ln.getString('Please select an audio master file'), Colors.black);
      return;
    }

    if (_selectedGenreId == null) {
      OthersHelper().showToast(ln.getString('Please select a primary genre'), Colors.black);
      return;
    }

    if (!_agreeRights) {
      OthersHelper().showToast(ln.getString('You must accept the rights declaration'), Colors.black);
      return;
    }

    // Build offers list
    List<SellerMusicOffer> offers = [];
    if (_enableFree) {
      offers.add(SellerMusicOffer(offerType: 'free', price: 0));
    }
    if (_enableRent) {
      final price = double.tryParse(_rentPriceController.text.trim()) ?? 0;
      final days = int.tryParse(_rentDaysController.text.trim()) ?? 7;
      if (price <= 0) {
        OthersHelper().showToast(ln.getString('Rental price must be greater than zero'), Colors.black);
        return;
      }
      offers.add(SellerMusicOffer(offerType: 'rent', price: price, rentalDurationDays: days));
    }
    if (_enableSale) {
      final price = double.tryParse(_salePriceController.text.trim()) ?? 0;
      if (price <= 0) {
        OthersHelper().showToast(ln.getString('Sale price must be greater than zero'), Colors.black);
        return;
      }
      offers.add(SellerMusicOffer(offerType: 'sale', price: price));
    }

    if (offers.isEmpty) {
      OthersHelper().showToast(ln.getString('Please configure at least one commercial offer'), Colors.black);
      return;
    }

    setState(() => _isSubmitting = true);

    final res = await _musicService.submitTrack(
      title: _titleController.text.trim(),
      artistName: _artistController.text.trim(),
      description: _descriptionController.text.trim(),
      genreId: _selectedGenreId!,
      secondaryGenres: _selectedSecondaryGenres,
      audioFile: _pickedAudioFile!,
      coverImage: _pickedCoverFile,
      rightsDeclaration: _selectedRightsDeclaration,
      rightsNotes: _rightsNotesController.text.trim(),
      agreeRightsDeclaration: _agreeRights,
      offers: offers,
    );

    setState(() => _isSubmitting = false);

    if (res['success'] == true) {
      OthersHelper().showToast(
        ln.getString('Track submitted successfully! Audio is now processing in the background.'),
        Colors.black,
      );
      if (mounted) {
        Navigator.pop(context, true);
      }
    } else {
      OthersHelper().showToast(res['message'] ?? ln.getString('Submission failed'), Colors.black);
    }
  }

  @override
  Widget build(BuildContext context) {
    final ln = Provider.of<AppStringService>(context);

    if (_isLoadingConfig) {
      return Scaffold(
        backgroundColor: FMColors.background,
        appBar: AppBar(
          title: Text(ln.getString('Submit Music Track')),
          backgroundColor: FMColors.surfaceDark,
        ),
        body: const Center(child: CircularProgressIndicator(color: FMColors.magenta)),
      );
    }

    final config = _musicService.config;
    final isSubmissionsDisabled = config != null && (!config.marketplaceEnabled || !config.sellerSubmissionsEnabled);

    return Scaffold(
      backgroundColor: FMColors.background,
      appBar: AppBar(
        title: Text(
          ln.getString('Submit Music Track'),
          style: const TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.bold),
        ),
        backgroundColor: FMColors.surfaceDark,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: Colors.white, size: 18),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Notice banner if disabled by admin
              if (isSubmissionsDisabled) ...[
                Container(
                  padding: const EdgeInsets.all(14),
                  margin: const EdgeInsets.only(bottom: 16),
                  decoration: BoxDecoration(
                    color: FMColors.warning.withOpacity(0.15),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: FMColors.warning),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.warning_amber_rounded, color: FMColors.warning, size: 24),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          ln.getString('Music seller submissions are currently temporarily disabled by platform administration.'),
                          style: const TextStyle(color: Colors.white, fontSize: 12),
                        ),
                      ),
                    ],
                  ),
                ),
              ],

              // 1. Audio Master Upload Card
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: FMColors.surfaceElevated,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: FMColors.cyan.withOpacity(0.4)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Icon(Icons.audiotrack_rounded, color: FMColors.cyan, size: 20),
                        const SizedBox(width: 8),
                        Text(
                          ln.getString('Master Audio Recording *'),
                          style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.bold),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(
                      '${ln.getString('Allowed formats:')} ${(config?.allowedAudioFormats ?? ['mp3', 'wav', 'flac', 'aac', 'm4a']).join(', ').toUpperCase()} • ${ln.getString('Max:')} ${config?.effectiveMaxUploadMb ?? 30} MB',
                      style: const TextStyle(color: FMColors.textMuted, fontSize: 11),
                    ),
                    const SizedBox(height: 12),

                    InkWell(
                      onTap: () => _pickAudioFile(ln),
                      borderRadius: BorderRadius.circular(12),
                      child: Container(
                        width: double.infinity,
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                        decoration: BoxDecoration(
                          color: FMColors.inputSurface,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(
                            color: _pickedAudioFile != null ? FMColors.cyan : FMColors.border,
                            width: 1.5,
                          ),
                        ),
                        child: Row(
                          children: [
                            Icon(
                              _pickedAudioFile != null ? Icons.check_circle_rounded : Icons.upload_file_rounded,
                              color: _pickedAudioFile != null ? FMColors.cyan : FMColors.textMuted,
                              size: 26,
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    _pickedAudioFileName ?? ln.getString('Tap to select audio file'),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: TextStyle(
                                      color: _pickedAudioFile != null ? Colors.white : FMColors.textMuted,
                                      fontSize: 13,
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                  if (_pickedAudioFileSizeBytes != null) ...[
                                    const SizedBox(height: 2),
                                    Text(
                                      '${(_pickedAudioFileSizeBytes! / (1024 * 1024)).toStringAsFixed(1)} MB',
                                      style: const TextStyle(color: FMColors.textMuted, fontSize: 11),
                                    ),
                                  ],
                                ],
                              ),
                            ),
                            Text(
                              _pickedAudioFile != null ? ln.getString('Change') : ln.getString('Browse'),
                              style: const TextStyle(color: FMColors.cyan, fontSize: 12, fontWeight: FontWeight.bold),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),

              // 2. Cover Artwork Card
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: FMColors.surfaceElevated,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: FMColors.border),
                ),
                child: Row(
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(12),
                      child: Container(
                        width: 64,
                        height: 64,
                        color: FMColors.inputSurface,
                        child: _pickedCoverFile != null
                            ? Image.file(_pickedCoverFile!, fit: BoxFit.cover)
                            : const Icon(Icons.image_outlined, color: FMColors.textMuted, size: 30),
                      ),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            ln.getString('Cover Artwork (Optional)'),
                            style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            ln.getString('Square JPG, PNG, or WebP up to 5MB'),
                            style: const TextStyle(color: FMColors.textMuted, fontSize: 11),
                          ),
                          const SizedBox(height: 6),
                          InkWell(
                            onTap: () => _pickCoverImage(ln),
                            child: Text(
                              _pickedCoverFile != null ? ln.getString('Change Cover Image') : ln.getString('Select Cover Image'),
                              style: const TextStyle(color: FMColors.magenta, fontSize: 12, fontWeight: FontWeight.bold),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),

              // 3. Track Metadata
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: FMColors.surfaceElevated,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: FMColors.border),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      ln.getString('Track Information'),
                      style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 14),

                    // Title
                    TextFormField(
                      controller: _titleController,
                      style: const TextStyle(color: Colors.white, fontSize: 13),
                      decoration: InputDecoration(
                        labelText: '${ln.getString('Track Title')} *',
                        labelStyle: const TextStyle(color: FMColors.textMuted),
                        filled: true,
                        fillColor: FMColors.inputSurface,
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: FMColors.border)),
                        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: FMColors.border)),
                      ),
                      validator: (val) {
                        if (val == null || val.trim().isEmpty) {
                          return ln.getString('Please enter a track title');
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 12),

                    // Artist display name
                    TextFormField(
                      controller: _artistController,
                      style: const TextStyle(color: Colors.white, fontSize: 13),
                      decoration: InputDecoration(
                        labelText: ln.getString('Artist / DJ / Display Name'),
                        labelStyle: const TextStyle(color: FMColors.textMuted),
                        filled: true,
                        fillColor: FMColors.inputSurface,
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: FMColors.border)),
                        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: FMColors.border)),
                      ),
                    ),
                    const SizedBox(height: 12),

                    // Primary Genre
                    DropdownButtonFormField<int>(
                      value: _selectedGenreId,
                      dropdownColor: FMColors.surfaceDark,
                      style: const TextStyle(color: Colors.white, fontSize: 13),
                      decoration: InputDecoration(
                        labelText: '${ln.getString('Primary Genre')} *',
                        labelStyle: const TextStyle(color: FMColors.textMuted),
                        filled: true,
                        fillColor: FMColors.inputSurface,
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: FMColors.border)),
                        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: FMColors.border)),
                      ),
                      items: _musicService.genres.map((g) {
                        return DropdownMenuItem<int>(
                          value: g.id,
                          child: Text(g.name),
                        );
                      }).toList(),
                      onChanged: (val) {
                        setState(() {
                          _selectedGenreId = val;
                        });
                      },
                    ),
                    const SizedBox(height: 12),

                    // Description
                    TextFormField(
                      controller: _descriptionController,
                      maxLines: 3,
                      style: const TextStyle(color: Colors.white, fontSize: 13),
                      decoration: InputDecoration(
                        labelText: ln.getString('Description & Liner Notes'),
                        labelStyle: const TextStyle(color: FMColors.textMuted),
                        filled: true,
                        fillColor: FMColors.inputSurface,
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: FMColors.border)),
                        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: FMColors.border)),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),

              // 4. Commercial Offers Configuration
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: FMColors.surfaceElevated,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: FMColors.border),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      ln.getString('Commercial Offers & Licensing'),
                      style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      ln.getString('Configure the streaming terms available to listeners.'),
                      style: const TextStyle(color: FMColors.textMuted, fontSize: 11),
                    ),
                    const SizedBox(height: 14),

                    // Free offer
                    if (config?.offerFreeEnabled == true) ...[
                      SwitchListTile(
                        contentPadding: EdgeInsets.zero,
                        activeColor: FMColors.cyan,
                        title: Text(ln.getString('Free In-App Streaming'), style: const TextStyle(color: Colors.white, fontSize: 13)),
                        subtitle: Text(ln.getString('Allow all app users to stream the track freely'), style: const TextStyle(color: FMColors.textMuted, fontSize: 11)),
                        value: _enableFree,
                        onChanged: (v) => setState(() => _enableFree = v),
                      ),
                      const Divider(color: FMColors.border),
                    ],

                    // Rental offer
                    if (config?.offerRentEnabled == true) ...[
                      SwitchListTile(
                        contentPadding: EdgeInsets.zero,
                        activeColor: FMColors.orange,
                        title: Text(ln.getString('Time-Bound Rental'), style: const TextStyle(color: Colors.white, fontSize: 13)),
                        subtitle: Text(ln.getString('Allow users to rent streaming access for specific days'), style: const TextStyle(color: FMColors.textMuted, fontSize: 11)),
                        value: _enableRent,
                        onChanged: (v) => setState(() => _enableRent = v),
                      ),
                      if (_enableRent) ...[
                        Row(
                          children: [
                            Expanded(
                              child: TextFormField(
                                controller: _rentPriceController,
                                keyboardType: TextInputType.number,
                                style: const TextStyle(color: Colors.white, fontSize: 13),
                                decoration: InputDecoration(
                                  labelText: '${ln.getString('Rental Price')} (SAR)',
                                  labelStyle: const TextStyle(color: FMColors.textMuted),
                                  filled: true,
                                  fillColor: FMColors.inputSurface,
                                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                                ),
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: TextFormField(
                                controller: _rentDaysController,
                                keyboardType: TextInputType.number,
                                style: const TextStyle(color: Colors.white, fontSize: 13),
                                decoration: InputDecoration(
                                  labelText: ln.getString('Duration (Days)'),
                                  labelStyle: const TextStyle(color: FMColors.textMuted),
                                  filled: true,
                                  fillColor: FMColors.inputSurface,
                                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                      const Divider(color: FMColors.border),
                    ],

                    // Sale offer
                    if (config?.offerSaleEnabled == true) ...[
                      SwitchListTile(
                        contentPadding: EdgeInsets.zero,
                        activeColor: FMColors.magenta,
                        title: Text(ln.getString('Perpetual In-App Streaming (Sale)'), style: const TextStyle(color: Colors.white, fontSize: 13)),
                        subtitle: Text(
                          ln.getString('Grants perpetual in-app streaming only. No audio master downloads or commercial remix rights.'),
                          style: const TextStyle(color: FMColors.textMuted, fontSize: 11),
                        ),
                        value: _enableSale,
                        onChanged: (v) => setState(() => _enableSale = v),
                      ),
                      if (_enableSale) ...[
                        TextFormField(
                          controller: _salePriceController,
                          keyboardType: TextInputType.number,
                          style: const TextStyle(color: Colors.white, fontSize: 13),
                          decoration: InputDecoration(
                            labelText: '${ln.getString('Purchase Price')} (SAR)',
                            labelStyle: const TextStyle(color: FMColors.textMuted),
                            filled: true,
                            fillColor: FMColors.inputSurface,
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                          ),
                        ),
                      ],
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 16),

              // 5. Rights Declaration & Acceptance
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: FMColors.surfaceElevated,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: FMColors.border),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      ln.getString('Rights Declaration & Legal Warranty'),
                      style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 10),

                    DropdownButtonFormField<String>(
                      value: _selectedRightsDeclaration,
                      dropdownColor: FMColors.surfaceDark,
                      style: const TextStyle(color: Colors.white, fontSize: 13),
                      decoration: InputDecoration(
                        labelText: ln.getString('Declaration Type'),
                        labelStyle: const TextStyle(color: FMColors.textMuted),
                        filled: true,
                        fillColor: FMColors.inputSurface,
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      items: const [
                        DropdownMenuItem(value: 'exclusive', child: Text('Original / Exclusive Owner')),
                        DropdownMenuItem(value: 'non_exclusive', child: Text('Non-Exclusive Licensee')),
                        DropdownMenuItem(value: 'public_domain', child: Text('Public Domain / Creative Commons')),
                      ],
                      onChanged: (v) {
                        if (v != null) setState(() => _selectedRightsDeclaration = v);
                      },
                    ),
                    const SizedBox(height: 12),

                    // Checkbox
                    CheckboxListTile(
                      contentPadding: EdgeInsets.zero,
                      activeColor: FMColors.magenta,
                      value: _agreeRights,
                      onChanged: (v) => setState(() => _agreeRights = v ?? false),
                      title: Text(
                        ln.getString('I solemnly declare that I own or hold full authorization and rights to distribute this master recording and underlying composition on the FUN MOMENT platform.'),
                        style: const TextStyle(color: Colors.white70, fontSize: 12),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),

              // Submit Button
              SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton(
                  onPressed: _isSubmitting || isSubmissionsDisabled ? null : () => _submitForm(ln),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: FMColors.magenta,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    elevation: 2,
                  ),
                  child: _isSubmitting
                      ? const SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white),
                        )
                      : Text(
                          ln.getString('Submit Track for Review'),
                          style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.bold),
                        ),
                ),
              ),
              const SizedBox(height: 32),
            ],
          ),
        ),
      ),
    );
  }
}
