import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/home_services/category_service.dart';
import 'package:funmoments/service/provider_service_management_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class ProviderAddServicePage extends StatefulWidget {
  const ProviderAddServicePage({Key? key}) : super(key: key);

  @override
  State<ProviderAddServicePage> createState() => _ProviderAddServicePageState();
}

class _ProviderAddServicePageState extends State<ProviderAddServicePage> {
  final _formKey = GlobalKey<FormState>();
  final _titleController = TextEditingController();
  final _priceController = TextEditingController();
  final _descController = TextEditingController();
  int? _selectedCategoryId;

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
    if (!_formKey.currentState!.validate()) return;

    if (_selectedCategoryId == null) {
      OthersHelper().showToast('Please select a category', Colors.black);
      return;
    }

    final price = double.tryParse(_priceController.text.trim()) ?? 0.0;
    final success = await Provider.of<ProviderServiceManagementService>(context, listen: false).createService(
      context: context,
      title: _titleController.text.trim(),
      description: _descController.text.trim(),
      categoryId: _selectedCategoryId!,
      price: price,
    );

    if (success && mounted) {
      Navigator.pop(context);
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
                  onPressed: providerService.isCreating ? null : _submit,
                  child: providerService.isCreating
                      ? const CircularProgressIndicator(color: Colors.white)
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
