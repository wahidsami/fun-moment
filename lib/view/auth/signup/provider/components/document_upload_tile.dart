import 'dart:io';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:funmoments/model/provider_registration_model.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class DocumentUploadTile extends StatelessWidget {
  final String title;
  final String? subtitle;
  final bool isRequired;
  final String? filePath;
  final String? errorMessage;
  final ValueChanged<String?> onFilePicked;

  const DocumentUploadTile({
    Key? key,
    required this.title,
    this.subtitle,
    this.isRequired = false,
    this.filePath,
    this.errorMessage,
    required this.onFilePicked,
  }) : super(key: key);

  Future<void> _pickFile(BuildContext context) async {
    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png'],
      );

      if (result != null && result.files.isNotEmpty && result.files.first.path != null) {
        final path = result.files.first.path!;
        if (!ProviderRegistrationModel.isValidDocumentExtension(path)) {
          OthersHelper().showToast('Only PDF, JPG, JPEG, and PNG files are supported', Colors.black);
          return;
        }

        final file = File(path);
        if (await file.exists()) {
          final sizeInBytes = await file.length();
          if (sizeInBytes > 10 * 1024 * 1024) {
            OthersHelper().showToast('File size must be under 10MB', Colors.black);
            return;
          }
        }

        onFilePicked(path);
      }
    } catch (e) {
      debugPrint('Error picking file: $e');
      OthersHelper().showToast('Failed to select file', Colors.black);
    }
  }

  @override
  Widget build(BuildContext context) {
    final hasFile = filePath != null && filePath!.isNotEmpty;
    final fileName = hasFile ? filePath!.split(Platform.pathSeparator).last : '';
    final isPdf = fileName.toLowerCase().endsWith('.pdf');

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Text(
              title,
              style: const TextStyle(
                color: FMColors.textPrimary,
                fontSize: 13,
                fontWeight: FontWeight.w700,
              ),
            ),
            if (isRequired) ...[
              const SizedBox(width: 4),
              const Text(
                '*',
                style: TextStyle(color: FMColors.magenta, fontWeight: FontWeight.bold),
              ),
            ] else ...[
              const SizedBox(width: 6),
              const Text(
                '(Optional)',
                style: TextStyle(color: FMColors.textMuted, fontSize: 11),
              ),
            ],
          ],
        ),
        if (subtitle != null) ...[
          const SizedBox(height: 3),
          Text(
            subtitle!,
            style: const TextStyle(color: FMColors.textMuted, fontSize: 11),
          ),
        ],
        const SizedBox(height: 8),
        InkWell(
          onTap: () => _pickFile(context),
          borderRadius: BorderRadius.circular(14),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            decoration: BoxDecoration(
              color: FMColors.surface,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: errorMessage != null
                    ? FMColors.magenta
                    : (hasFile ? FMColors.cyan : FMColors.border),
                width: hasFile ? 1.5 : 1,
              ),
            ),
            child: hasFile
                ? Row(
                    children: [
                      Icon(
                        isPdf ? Icons.picture_as_pdf_rounded : Icons.image_rounded,
                        color: isPdf ? Colors.redAccent : FMColors.cyan,
                        size: 24,
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          fileName,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: FMColors.textPrimary,
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.refresh_rounded, color: FMColors.textMuted, size: 20),
                        tooltip: 'Replace',
                        onPressed: () => _pickFile(context),
                      ),
                      IconButton(
                        icon: const Icon(Icons.close_rounded, color: Colors.redAccent, size: 20),
                        tooltip: 'Remove',
                        onPressed: () => onFilePicked(null),
                      ),
                    ],
                  )
                : Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.upload_file_rounded, color: FMColors.textMuted, size: 22),
                      const SizedBox(width: 8),
                      Text(
                        'Upload document (PDF / JPG / PNG)',
                        style: TextStyle(
                          color: errorMessage != null ? FMColors.magenta : FMColors.textMuted,
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
          ),
        ),
        if (errorMessage != null) ...[
          const SizedBox(height: 4),
          Text(
            errorMessage!,
            style: const TextStyle(color: FMColors.magenta, fontSize: 11),
          ),
        ],
      ],
    );
  }
}
