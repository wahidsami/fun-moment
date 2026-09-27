import 'package:flutter/material.dart';
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
  final _descController = TextEditingController();
  int? _selectedCategoryId;
  bool _isSubmitting = false;

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
    _descController.dispose();
    super.dispose();
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
      final providerService = Provider.of<ProviderServiceManagementService>(context, listen: false);
      final success = await providerService.createService(
        context: context,
        title: _titleController.text.trim(),
        description: _descController.text.trim(),
        categoryId: _selectedCategoryId!,
        price: price,
      );

      if (!mounted) return;

      if (success) {
        // Show clear success confirmation dialog styled with FUN MOMENT dark/neon UI
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

        // Navigate automatically to Provider Services / My Services
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

              // Price
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
                    return 'Please enter a valid price';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 20),

              // Description
              Text(
                lnProvider.getString('Description (min 150 characters)'),
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _descController,
                maxLines: 6,
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  hintText: lnProvider.getString('Describe your service in detail (required by platform standards)...'),
                  hintStyle: const TextStyle(color: FMColors.textMuted),
                  filled: true,
                  fillColor: FMColors.surfaceDark,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                ),
                validator: (val) {
                  if (val == null || val.trim().isEmpty) {
                    return 'Description is required';
                  }
                  if (val.trim().length < 150) {
                    return 'Description must be at least 150 characters (${val.trim().length}/150)';
                  }
                  return null;
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
