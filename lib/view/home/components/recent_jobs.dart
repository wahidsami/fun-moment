import 'package:auto_size_text/auto_size_text.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/jobs_service/my_jobs_service.dart';
import 'package:funmoments/service/jobs_service/recent_jobs_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/home/components/section_title.dart';
import 'package:funmoments/view/jobs/job_details_page.dart';
import 'package:funmoments/view/utils/constant_styles.dart';
import 'package:funmoments/view/utils/responsive.dart';

class RecentJobs extends StatelessWidget {
  const RecentJobs({
    Key? key,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Consumer<RecentJobsService>(
      builder: (context, provider, child) => Consumer<AppStringService>(
        builder: (context, asProvider, child) {
          if (provider.recentJobs == null || provider.recentJobs.isEmpty) {
            return const SizedBox.shrink();
          }

          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SizedBox(height: 26),
              SectionTitle(
                cc: null,
                title: asProvider.getString('Recent jobs'),
                hasSeeAllBtn: false,
                pressed: () {},
              ),
              const SizedBox(height: 16),
              SizedBox(
                height: 168,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  shrinkWrap: true,
                  clipBehavior: Clip.none,
                  itemCount: provider.recentJobs.length,
                  separatorBuilder: (_, __) => const SizedBox(width: 16),
                  itemBuilder: (context, i) => InkWell(
                    splashColor: Colors.transparent,
                    highlightColor: Colors.transparent,
                    onTap: () {
                      Provider.of<MyJobsService>(context, listen: false)
                          .setOrderDetailsLoadingStatus(true);
                      Navigator.push(
                        context,
                        MaterialPageRoute<void>(
                          builder: (BuildContext context) => JobDetailsPage(
                            imageLink:
                                provider.recentJobsImages.length > i
                                    ? provider.recentJobsImages[i].imgUrl
                                    : '',
                            jobId: provider.recentJobs[i].id,
                          ),
                        ),
                      );
                    },
                    child: FMSurfaceCard(
                      padding: EdgeInsets.zero,
                      child: SizedBox(
                        width: 320,
                        child: Row(
                          children: [
                            SizedBox(
                              width: 120,
                              child: FMNetworkImageFrame(
                                imageUrl: provider.recentJobsImages.length > i
                                    ? provider.recentJobsImages[i].imgUrl
                                    : '',
                                overlay: true,
                                borderRadius: const BorderRadius.horizontal(
                                  left: Radius.circular(FMRadii.lg),
                                ),
                              ),
                            ),
                            Expanded(
                              child: Padding(
                                padding: const EdgeInsets.all(16),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  mainAxisAlignment:
                                      MainAxisAlignment.spaceBetween,
                                  children: [
                                    Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          provider.recentJobs[i].title,
                                          maxLines: 2,
                                          overflow: TextOverflow.ellipsis,
                                          style: Theme.of(context)
                                              .textTheme
                                              .headlineSmall
                                              ?.copyWith(fontSize: 17),
                                        ),
                                        const SizedBox(height: 6),
                                        AutoSizeText(
                                          '${provider.recentJobs[i].view} views',
                                          maxLines: 1,
                                          style: Theme.of(context)
                                              .textTheme
                                              .bodySmall,
                                        ),
                                      ],
                                    ),
                                    Row(
                                      children: [
                                        AutoSizeText(
                                          '${asProvider.getString('Starts from')}:',
                                          maxLines: 1,
                                          style: Theme.of(context)
                                              .textTheme
                                              .bodySmall,
                                        ),
                                        const SizedBox(width: 6),
                                        Consumer<RtlService>(
                                          builder: (context, rtlP, child) =>
                                              AutoSizeText(
                                            rtlP.currencyDirection == 'left'
                                                ? '${rtlP.currency} ${provider.recentJobs[i].price}'
                                                : '${provider.recentJobs[i].price}${rtlP.currency}',
                                            maxLines: 1,
                                            style: Theme.of(context)
                                                .textTheme
                                                .headlineSmall
                                                ?.copyWith(
                                                  color: FMColors.magentaLight,
                                                  fontSize: 20,
                                                ),
                                          ),
                                        ),
                                      ],
                                    )
                                  ],
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

