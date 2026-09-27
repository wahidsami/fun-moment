import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/home_services/category_service.dart';
import 'package:funmoments/service/provider_service_management_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/provider/provider_services_page.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class ProviderAddServicePage extends StatefulWidget {
  final bool fromMyServices;
  const ProviderAddServicePage({Key? key, this.fromMyServices = false}) : super(key: key);

  @override
  State<ProviderAddServicePage> createState() => _ProviderAddServicePageState();
}

class _ProviderAddServicePageState extends State<ProviderAddServicePage> {
  final _formKey = GlobalKey<FormState>();
  final _titleController = TextEditingController();
  final _priceController = TextEditingController();
  final _durationController = TextEditingController(text: '1');
  final _descController = TextEditingController();

  int? _selectedCategoryId;
  File? _selectedImage;
  final ImagePicker _picker = ImagePicker();
  bool _isSubmitting = false;

  // Dynamic includes list
  final List<Map<String, dynamic>> _includes = [];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<CategoryService>(context, listen: false).fetchCategory();
    });
  }

  @override
  void dispose() {
    _titleController.dispose();
    _priceController.dispose();
    _durationController.dispose();
    _descController.dispose();
    super.dispose();
  }

  Future<void> _pickImage() async {
    try {
      final XFile? picked = await _picker.pickImage(
        source: ImageSource.gallery,
        maxWidth: 1600,
        maxHeight: 1600,
        imageQuality: 88,
      );
      if (picked != null) {
        setState(() {
          _selectedImage = File(picked.path);
        });
      }
    } catch (e) {
      debugPrint('Error picking image: $e');
    }
  }

  void _removeImage() {
    setState(() {
      _selectedImage = null;
    });
  }

  void _addIncludeDialog(BuildContext context) {
    final lnProvider = Provider.of<AppStringService>(context, listen: false);
    final titleCtrl = TextEditingController();
    final priceCtrl = TextEditingController(text: '0');

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: FMColors.surfaceDark,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: BorderSide(color: FMColors.magenta.withOpacity(0.3)),
        ),
        title: Text(
          lnProvider.getString("Add What's Included"),
          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: titleCtrl,
              style: const TextStyle(color: Colors.white),
              decoration: InputDecoration(
                hintText: lnProvider.getString('Item title (e.g. Standard Setup)'),
                hintStyle: const TextStyle(color: FMColors.textMuted),
                filled: true,
                fillColor: FMColors.background,
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: priceCtrl,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              style: const TextStyle(color: Colors.white),
              decoration: InputDecoration(
                hintText: lnProvider.getString('Price (0 if included in base)'),
                hintStyle: const TextStyle(color: FMColors.textMuted),
                filled: true,
                fillColor: FMColors.background,
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: Text(lnProvider.getString('Cancel'), style: const TextStyle(color: FMColors.textMuted)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: FMColors.magenta,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            ),
            onPressed: () {
              final t = titleCtrl.text.trim();
              if (t.isNotEmpty) {
                final p = double.tryParse(priceCtrl.text.trim()) ?? 0.0;
                setState(() {
                  _includes.add({
                    'title': t,
                    'price': p,
                    'quantity': 1,
                  });
                });
                Navigator.pop(ctx);
              }
            },
            child: Text(lnProvider.getString('Add'), style: const TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  Future<void> _submit() async {
    if (_isSubmitting) return;

    final lnProvider = Provider.of<AppStringService>(context, listen: false);

    if (!_formKey.currentState!.validate()) return;

    if (_selectedCategoryId == null) {
      OthersHelper().showToast(lnProvider.getString('Please select a category'), Colors.black);
      return;
    }

    setState(() {
      _isSubmitting = true;
    });

    try {
      final price = double.tryParse(_priceController.text.trim()) ?? 0.0;
      final deliveryDays = int.tryParse(_durationController.text.trim()) ?? 1;
      final providerService = Provider.of<ProviderServiceManagementService>(context, listen: false);

      final success = await providerService.createService(
        context: context,
        title: _titleController.text.trim(),
        description: _descController.text.trim(),
        categoryId: _selectedCategoryId!,
        price: price,
        deliveryDays: deliveryDays,
        imageFile: _selectedImage,
        includes: _includes.isNotEmpty ? _includes : null,
      );

      if (!mounted) return;

      if (success) {
        await showDialog(
          context: context,
          barrierDismissible: false,
          builder: (dialogCtx) => AlertDialog(
            backgroundColor: FMColors.surfaceDark,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(20),
              side: BorderSide(color: FMColors.magenta.withOpacity(0.4), width: 1.5),
            ),
            contentPadding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 64,
                  height: 64,
                  decoration: BoxDecoration(
                    color: FMColors.magenta.withOpacity(0.15),
                    shape: BoxShape.circle,
                    border: Border.all(color: FMColors.magenta, width: 2),
                  ),
                  child: const Icon(
                    Icons.check_circle_outline_rounded,
                    color: FMColors.magenta,
                    size: 38,
                  ),
                ),
                const SizedBox(height: 18),
                Text(
                  lnProvider.getString('Service Submitted Successfully'),
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                    fontSize: 18,
                  ),
                ),
                const SizedBox(height: 10),
                Text(
                  lnProvider.getString('Your service has been submitted and is pending admin approval.'),
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
                  height: 48,
                  child: ElevatedButton(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: FMColors.magenta,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    onPressed: () {
                      Navigator.pop(dialogCtx);
                    },
                    child: Text(
                      lnProvider.getString('View My Services'),
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                        fontSize: 15,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        );

        if (!mounted) return;

        if (widget.fromMyServices) {
          Navigator.pop(context);
        } else {
          Navigator.pushReplacement(
            context,
            MaterialPageRoute(builder: (_) => const ProviderServicesPage()),
          );
        }
      }
    } finally {
      if (mounted) {
        setState(() {
          _isSubmitting = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final lnProvider = Provider.of<AppStringService>(context);
    final rtl = Provider.of<RtlService>(context);
    final catService = Provider.of<CategoryService>(context);
    final providerService = Provider.of<ProviderServiceManagementService>(context);

    return Scaffold(
      backgroundColor: FMColors.background,
      appBar: AppBar(
        title: Text(
          lnProvider.getString('Add Service'),
          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
        ),
        backgroundColor: FMColors.surfaceDark,
        elevation: 0,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Notice banner
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: FMColors.magenta.withOpacity(0.12),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: FMColors.magenta.withOpacity(0.3)),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.info_outline, color: FMColors.magenta, size: 22),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        lnProvider.getString('New services are submitted for admin approval before being visible to customers.'),
                        style: const TextStyle(color: Colors.white, fontSize: 13),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),

              // Service Image / Thumbnail
              Text(
                lnProvider.getString('Service Thumbnail'),
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
              ),
              const SizedBox(height: 8),
              GestureDetector(
                onTap: _pickImage,
                child: Container(
                  height: 170,
                  width: double.infinity,
                  decoration: BoxDecoration(
                    color: FMColors.surfaceDark,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(
                      color: _selectedImage != null ? FMColors.magenta : FMColors.border.withOpacity(0.6),
                      width: _selectedImage != null ? 1.5 : 1,
                    ),
                  ),
                  child: _selectedImage != null
                      ? Stack(
                          fit: StackFit.expand,
                          children: [
                            ClipRRect(
                              borderRadius: BorderRadius.circular(13),
                              child: Image.file(_selectedImage!, fit: BoxFit.cover),
                            ),
                            Positioned(
                              top: 8,
                              right: 8,
                              child: GestureDetector(
                                onTap: _removeImage,
                                child: Container(
                                  padding: const EdgeInsets.all(6),
                                  decoration: const BoxDecoration(
                                    color: Colors.black87,
                                    shape: BoxShape.circle,
                                  ),
                                  child: const Icon(Icons.close, color: Colors.white, size: 18),
                                ),
                              ),
                            ),
                            Positioned(
                              bottom: 8,
                              right: 8,
                              child: Container(
                                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                decoration: BoxDecoration(
                                  color: Colors.black.withOpacity(0.7),
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    const Icon(Icons.edit, size: 14, color: Colors.white),
                                    const SizedBox(width: 4),
                                    Text(
                                      lnProvider.getString('Change'),
                                      style: const TextStyle(color: Colors.white, fontSize: 12),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ],
                        )
                      : Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.add_photo_alternate_outlined, size: 48, color: FMColors.magenta.withOpacity(0.8)),
                            const SizedBox(height: 8),
                            Text(
                              lnProvider.getString('Upload Service Thumbnail'),
                              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 14),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              lnProvider.getString('Tap to select from gallery'),
                              style: const TextStyle(color: FMColors.textMuted, fontSize: 12),
                            ),
                          ],
                        ),
                ),
              ),
              const SizedBox(height: 20),

              // Title
              Text(
                lnProvider.getString('Service Title'),
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _titleController,
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  hintText: lnProvider.getString('e.g. Professional Deep Cleaning'),
                  hintStyle: const TextStyle(color: FMColors.textMuted),
                  filled: true,
                  fillColor: FMColors.surfaceDark,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                ),
                validator: (val) {
                  if (val == null || val.trim().isEmpty) {
                    return 'Title is required';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 20),

              // Category
              Text(
                lnProvider.getString('Category'),
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
              ),
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 14),
                decoration: BoxDecoration(
                  color: FMColors.surfaceDark,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: DropdownButtonHideUnderline(
                  child: DropdownButton<int>(
                    isExpanded: true,
                    dropdownColor: FMColors.surfaceDark,
                    hint: Text(
                      lnProvider.getString('Select Category'),
                      style: const TextStyle(color: FMColors.textMuted),
                    ),
                    value: _selectedCategoryId,
                    items: catService.categoriesDropdownList.map<DropdownMenuItem<int>>((item) {
                      return DropdownMenuItem<int>(
                        value: item.id,
                        child: Text(
                          item.name ?? '',
                          style: const TextStyle(color: Colors.white),
                        ),
                      );
                    }).toList(),
                    onChanged: (val) {
                      setState(() {
                        _selectedCategoryId = val;
                      });
                    },
                  ),
                ),
              ),
              const SizedBox(height: 20),

              // Price & Duration Row
              Row(
                children: [
                  // Price
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${lnProvider.getString('Price')} (${rtl.currency})',
                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                        ),
                        const SizedBox(height: 8),
                        TextFormField(
                          controller: _priceController,
                          keyboardType: const TextInputType.numberWithOptions(decimal: true),
                          style: const TextStyle(color: Colors.white),
                          decoration: InputDecoration(
                            hintText: '0.00',
                            hintStyle: const TextStyle(color: FMColors.textMuted),
                            filled: true,
                            fillColor: FMColors.surfaceDark,
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                          ),
                          validator: (val) {
                            if (val == null || val.trim().isEmpty) {
                              return 'Price is required';
                            }
                            if (double.tryParse(val.trim()) == null) {
                              return 'Invalid price';
                            }
                            return null;
                          },
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 14),
                  // Duration / Delivery Days
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          lnProvider.getString('Delivery Days'),
                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                        ),
                        const SizedBox(height: 8),
                        TextFormField(
                          controller: _durationController,
                          keyboardType: TextInputType.number,
                          style: const TextStyle(color: Colors.white),
                          decoration: InputDecoration(
                            hintText: '1',
                            hintStyle: const TextStyle(color: FMColors.textMuted),
                            filled: true,
                            fillColor: FMColors.surfaceDark,
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                          ),
                          validator: (val) {
                            if (val != null && val.isNotEmpty && int.tryParse(val) == null) {
                              return 'Must be number';
                            }
                            return null;
                          },
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),

              // Description
              Text(
                lnProvider.getString('Description (min 10 characters)'),
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _descController,
                maxLines: 5,
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  hintText: lnProvider.getString('Describe your service in detail...'),
                  hintStyle: const TextStyle(color: FMColors.textMuted),
                  filled: true,
                  fillColor: FMColors.surfaceDark,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                ),
                validator: (val) {
                  if (val == null || val.trim().isEmpty) {
                    return 'Description is required';
                  }
                  if (val.trim().length < 10) {
                    return 'Description must be at least 10 characters (${val.trim().length}/10)';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 24),

              // What's Included Section
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    lnProvider.getString("What's Included"),
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                  ),
                  TextButton.icon(
                    onPressed: () => _addIncludeDialog(context),
                    icon: const Icon(Icons.add, color: FMColors.magenta, size: 18),
                    label: Text(
                      lnProvider.getString('Add Item'),
                      style: const TextStyle(color: FMColors.magenta, fontWeight: FontWeight.bold),
                    ),
                  ),
                ],
              ),
              if (_includes.isEmpty)
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: FMColors.surfaceDark.withOpacity(0.5),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: FMColors.border.withOpacity(0.3)),
                  ),
                  child: Text(
                    lnProvider.getString('No included items added yet. Click "+ Add Item" to specify deliverables.'),
                    style: const TextStyle(color: FMColors.textMuted, fontSize: 12),
                  ),
                )
              else
                ListView.builder(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  itemCount: _includes.length,
                  itemBuilder: (ctx, idx) {
                    final item = _includes[idx];
                    return Container(
                      margin: const EdgeInsets.only(bottom: 8),
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                      decoration: BoxDecoration(
                        color: FMColors.surfaceDark,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: FMColors.border.withOpacity(0.4)),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.check_circle, size: 16, color: FMColors.magenta),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              item['title'] ?? '',
                              style: const TextStyle(color: Colors.white, fontSize: 13),
                            ),
                          ),
                          if ((item['price'] ?? 0) > 0)
                            Text(
                              '+${rtl.currency}${item['price']}',
                              style: const TextStyle(color: FMColors.magenta, fontSize: 12, fontWeight: FontWeight.bold),
                            ),
                          IconButton(
                            icon: const Icon(Icons.close, size: 16, color: FMColors.textMuted),
                            onPressed: () {
                              setState(() {
                                _includes.removeAt(idx);
                              });
                            },
                          ),
                        ],
                      ),
                    );
                  },
                ),
              const SizedBox(height: 32),

              // Submit Button
              SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: FMColors.magenta,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                  onPressed: (_isSubmitting || providerService.isCreating) ? null : _submit,
                  child: (_isSubmitting || providerService.isCreating)
                      ? const SizedBox(
                          width: 24,
                          height: 24,
                          child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5),
                        )
                      : Text(
                          lnProvider.getString('Submit for Approval'),
                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16),
                        ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
