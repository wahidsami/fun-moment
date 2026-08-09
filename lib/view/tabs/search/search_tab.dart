import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/helper/extension/context_extension.dart';
import 'package:funmoments/helper/extension/string_extension.dart';
import 'package:funmoments/helper/extension/widget_extension.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/view/home_map_view/home_map_view.dart';
import 'package:funmoments/view/search/components/search_bar.dart' as sb;
import 'package:funmoments/view/utils/common_helper.dart';
import 'package:funmoments/view/utils/constant_colors.dart';
import 'package:funmoments/view/utils/constant_styles.dart';

class SearchTab extends StatefulWidget {
  const SearchTab({Key? key}) : super(key: key);

  @override
  _SearchTabState createState() => _SearchTabState();
}

class _SearchTabState extends State<SearchTab> {
  ValueNotifier<bool> viewMap = ValueNotifier(false);
  @override
  void initState() {
    super.initState();
  }

  @override
  Widget build(BuildContext context) {
    ConstantColors cc = ConstantColors();

    return Listener(
      onPointerDown: (_) {
        FocusScopeNode currentFocus = FocusScope.of(context);
        if (!currentFocus.hasPrimaryFocus) {
          currentFocus.focusedChild?.unfocus();
        }
      },
      child: Scaffold(
          backgroundColor: Colors.white,
          body: SafeArea(
            child: Container(
              clipBehavior: Clip.none,
              child: Consumer<AppStringService>(
                builder: (context, asProvider, child) => Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            CommonHelper().titleCommon(
                                asProvider.getString('Search services')),
                            ValueListenableBuilder<bool>(
                                valueListenable: viewMap,
                                builder: (context, view, child) => IconButton(
                                    onPressed: () {
                                      context.toPage(HomeMapView());
                                      // debugPrint(view.toString());
                                      // viewMap.value = !view;
                                    },
                                    icon:
                                        "map".toSVGSized(24, color: cc.black4)))
                          ]).hp20,
                      sizedBox20(),
                      Expanded(
                          child: ValueListenableBuilder<bool>(
                              valueListenable: viewMap,
                              builder: (context, map, _) =>
                                  map ? HomeMapView() : const sb.SearchBar())),
                    ]),
              ),
            ),
          )),
    );
  }
}
