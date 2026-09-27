import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/live_chat/chat_list_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/utils/responsive.dart';

class ChatSearch extends StatelessWidget {
  const ChatSearch({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final searchController = TextEditingController();
    return Consumer<ChatListService>(
      builder: (context, provider, child) => Container(
          decoration: BoxDecoration(
              color: FMColors.surfaceElevated,
              border: Border.all(color: FMColors.border),
              borderRadius: BorderRadius.circular(10)),
          child: TextFormField(
            controller: searchController,
            onFieldSubmitted: (value) {
              if (value.isNotEmpty) {}
            },
            onChanged: (value) {
              if (value.isNotEmpty) {
                provider.searchUser(value);
              } else {
                provider.setLoadedChatList();
              }
            },
            style: const TextStyle(fontSize: 14, color: Colors.white),
            decoration: InputDecoration(
                border: InputBorder.none,
                prefixIcon: const Icon(Icons.search, color: FMColors.textMuted),
                hintText: lnProvider.getString('Search'),
                hintStyle: const TextStyle(color: FMColors.textMuted),
                contentPadding:
                    const EdgeInsets.symmetric(horizontal: 8, vertical: 15)),
          )),
    );
  }
}
