import 'package:flutter/material.dart';
import 'package:flutter_widget_from_html/flutter_widget_from_html.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';

class DescInHtml extends StatelessWidget {
  const DescInHtml({Key? key, this.desc}) : super(key: key);
  final desc;

  @override
  Widget build(BuildContext context) {
    return HtmlWidget(
      '''
     $desc
  ''',
      onErrorBuilder: (context, element, error) =>
          Text('$element error: $error', style: const TextStyle(color: FMColors.error)),
      onLoadingBuilder: (context, element, loadingProgress) =>
          const CircularProgressIndicator(color: FMColors.magenta),
      renderMode: RenderMode.column,
      textStyle: const TextStyle(fontSize: 15, color: FMColors.textSecondary, height: 1.5),
    );
  }
}
