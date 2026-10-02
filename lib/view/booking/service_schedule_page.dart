import 'package:date_picker_timeline/date_picker_timeline.dart';
import 'package:flutter/material.dart';
import 'package:flutterzilla_fixed_grid/flutterzilla_fixed_grid.dart';
import 'package:intl/intl.dart';

import 'package:page_transition/page_transition.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/helper/extension/context_extension.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/book_steps_service.dart';
import 'package:funmoments/service/booking_services/book_service.dart';
import 'package:funmoments/service/booking_services/coupon_service.dart';
import 'package:funmoments/service/booking_services/shedule_service.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/view/auth/login/login.dart';
import 'package:funmoments/view/booking/booking_location_page.dart';

import 'package:funmoments/view/utils/common_helper.dart';
import 'package:funmoments/view/utils/constant_colors.dart';
import 'package:funmoments/view/utils/constant_styles.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:funmoments/view/utils/responsive.dart';
import '../../service/profile_service.dart';
import 'components/steps.dart';

class ServiceSchedulePage extends StatefulWidget {
  const ServiceSchedulePage({Key? key}) : super(key: key);

  @override
  _ServiceSchedulePageState createState() => _ServiceSchedulePageState();
}

class _ServiceSchedulePageState extends State<ServiceSchedulePage> {
  int selectedShedule = 0;
  String? _selectedTime;
  late DateTime _currentWeekStartDate;
  late DateTime _selectedDate;
  late String _selectedWeekday;
  late String _monthAndDate;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    _currentWeekStartDate = today;
    _selectedDate = now;
    // CRITICAL: Always use English (null) for the API weekday identifier so it returns canonical 'Sun', 'Mon', etc.
    _selectedWeekday = firstThreeLetter(now.toLocal(), null);
    _monthAndDate = getMonthAndDate(now.toLocal(), null);

    WidgetsBinding.instance.addPostFrameCallback((_) {
      final bookService = Provider.of<BookService>(context, listen: false);
      Provider.of<SheduleService>(context, listen: false).fetchShedule(
          bookService.sellerId,
          _selectedWeekday,
          serviceId: bookService.serviceId,
          date: _selectedDate);
    });
  }

  void _onPreviousWeek() {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    if (!_currentWeekStartDate.isAfter(today)) return;

    DateTime newWeekStart = _currentWeekStartDate.subtract(const Duration(days: 7));
    if (newWeekStart.isBefore(today)) {
      newWeekStart = today;
    }

    setState(() {
      _currentWeekStartDate = newWeekStart;
      final weekEnd = _currentWeekStartDate.add(const Duration(days: 6));
      if (_selectedDate.isBefore(_currentWeekStartDate) || _selectedDate.isAfter(weekEnd)) {
        _selectedDate = _currentWeekStartDate;
        _selectedWeekday = firstThreeLetter(_selectedDate, null);
        _monthAndDate = getMonthAndDate(_selectedDate, null);
        selectedShedule = 0;
        _selectedTime = null;
      }
    });

    final bookService = Provider.of<BookService>(context, listen: false);
    Provider.of<SheduleService>(context, listen: false).fetchShedule(
        bookService.sellerId,
        _selectedWeekday,
        serviceId: bookService.serviceId,
        date: _selectedDate);
  }

  void _onNextWeek() {
    setState(() {
      _currentWeekStartDate = _currentWeekStartDate.add(const Duration(days: 7));
      final weekEnd = _currentWeekStartDate.add(const Duration(days: 6));
      if (_selectedDate.isBefore(_currentWeekStartDate) || _selectedDate.isAfter(weekEnd)) {
        _selectedDate = _currentWeekStartDate;
        _selectedWeekday = firstThreeLetter(_selectedDate, null);
        _monthAndDate = getMonthAndDate(_selectedDate, null);
        selectedShedule = 0;
        _selectedTime = null;
      }
    });

    final bookService = Provider.of<BookService>(context, listen: false);
    Provider.of<SheduleService>(context, listen: false).fetchShedule(
        bookService.sellerId,
        _selectedWeekday,
        serviceId: bookService.serviceId,
        date: _selectedDate);
  }

  Future<void> _openCalendarPicker() async {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final initial = _selectedDate.isBefore(today) ? today : _selectedDate;
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: today,
      lastDate: today.add(const Duration(days: 365)),
    );

    if (picked != null) {
      final pickedDateOnly = DateTime(picked.year, picked.month, picked.day);
      setState(() {
        _selectedDate = picked;
        final weekEnd = _currentWeekStartDate.add(const Duration(days: 6));
        if (pickedDateOnly.isBefore(_currentWeekStartDate) || pickedDateOnly.isAfter(weekEnd)) {
          _currentWeekStartDate = pickedDateOnly;
        }
        _selectedWeekday = firstThreeLetter(picked, null);
        _monthAndDate = getMonthAndDate(picked, null);
        selectedShedule = 0;
        _selectedTime = null;
      });

      final bookService = Provider.of<BookService>(context, listen: false);
      Provider.of<SheduleService>(context, listen: false).fetchShedule(
          bookService.sellerId,
          _selectedWeekday,
          serviceId: bookService.serviceId,
          date: picked);
    }
  }

  @override
  Widget build(BuildContext context) {
    ConstantColors cc = ConstantColors();
    final rtlPorvider = Provider.of<RtlService>(context, listen: false);
    return WillPopScope(
      onWillPop: () {
        BookStepsService().decreaseStep(context);
        //set coupon value to default again
        Provider.of<CouponService>(context, listen: false).setCouponDefault();
        return Future.value(true);
      },
      child: Scaffold(
        backgroundColor: cc.bgColor,
        appBar: CommonHelper().appbarForBookingPages('Schedule', context,
            extraFunction: () {
          //set coupon value to default again
          Provider.of<CouponService>(context, listen: false).setCouponDefault();
        }),
        body: Consumer<AppStringService>(
          builder: (context, asProvider, child) => Consumer<SheduleService>(
            builder: (context, provider, child) {
              //if user didnt select anything or current selection is invalid, preselect the first available slot
              if (provider.isloading == false &&
                  provider.schedules != 'nothing' &&
                  provider.schedules.schedules != null &&
                  provider.schedules.schedules.isNotEmpty) {
                if (_selectedTime == null ||
                    selectedShedule >= provider.schedules.schedules.length) {
                  selectedShedule = 0;
                  _selectedTime = provider.schedules.schedules[0].schedule;
                }
              }
              return Column(
                children: [
                  Expanded(
                    child: SingleChildScrollView(
                      physics: physicsCommon,
                      child: Container(
                          padding: EdgeInsets.symmetric(
                            horizontal: screenPadding,
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              //Circular Progress bar
                              Steps(cc: cc),
                              // CommonHelper().borderButtonOrange(
                              //     _selectedDate == null
                              //         ? asProvider.getString('Select Date')
                              //         : "${firstThreeLetter(_selectedDate, rtlProvider.langSlug.substring(0, 2)) ?? ''}",
                              //     () {
                              //   final now = DateTime.now();
                              //   showDatePicker(
                              //           context: context,
                              //           initialDate: now,
                              //           firstDate: now,
                              //           lastDate:
                              //               now.add(const Duration(days: 365)))
                              //       .then((value) {
                              //     if (value == null) {
                              //       return;
                              //     }
                              //     setState(() {
                              //       _selectedWeekday =
                              //           firstThreeLetter(value, null);
                              //       _monthAndDate =
                              //           getMonthAndDate(value, null);
                              //       _selectedDate = value;
                              //     });
                              //     print(_selectedWeekday);

                              //     //fetch shedule
                              //     provider.fetchShedule(
                              //         Provider.of<BookService>(context,
                              //                 listen: false)
                              //             .sellerId,
                              //         _selectedWeekday);
                              //   });
                              // }),

                              // Week navigation header
                              Builder(builder: (context) {
                                final now = DateTime.now();
                                final today = DateTime(now.year, now.month, now.day);
                                final canGoPrevious = _currentWeekStartDate.isAfter(today);

                                return Padding(
                                  padding: const EdgeInsets.only(top: 14, bottom: 8),
                                  child: Row(
                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                    children: [
                                      Row(
                                        children: [
                                          Icon(Icons.calendar_month_rounded, color: cc.primaryColor, size: 20),
                                          const SizedBox(width: 8),
                                          Text(
                                            DateFormat.yMMMM(rtlPorvider.langSlug).format(_currentWeekStartDate),
                                            style: TextStyle(
                                              color: cc.greyPrimary,
                                              fontSize: 16,
                                              fontWeight: FontWeight.bold,
                                            ),
                                          ),
                                        ],
                                      ),
                                      Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          IconButton(
                                            icon: Icon(
                                              Icons.chevron_left_rounded,
                                              color: canGoPrevious ? cc.primaryColor : Colors.grey.withOpacity(0.4),
                                              size: 26,
                                            ),
                                            splashRadius: 20,
                                            tooltip: asProvider.getString('Previous Week'),
                                            onPressed: canGoPrevious ? _onPreviousWeek : null,
                                          ),
                                          IconButton(
                                            icon: Icon(
                                              Icons.date_range_rounded,
                                              color: cc.primaryColor,
                                              size: 22,
                                            ),
                                            splashRadius: 20,
                                            tooltip: asProvider.getString('Select Date'),
                                            onPressed: _openCalendarPicker,
                                          ),
                                          IconButton(
                                            icon: Icon(
                                              Icons.chevron_right_rounded,
                                              color: cc.primaryColor,
                                              size: 26,
                                            ),
                                            splashRadius: 20,
                                            tooltip: asProvider.getString('Next Week'),
                                            onPressed: _onNextWeek,
                                          ),
                                        ],
                                      ),
                                    ],
                                  ),
                                );
                              }),

                              DatePicker(
                                _currentWeekStartDate,
                                key: ValueKey('dp_${_currentWeekStartDate.millisecondsSinceEpoch}_${_selectedDate.millisecondsSinceEpoch}'),
                                height: 88,
                                locale: rtlPorvider.langSlug,
                                initialSelectedDate: _selectedDate,
                                daysCount: 7,
                                selectionColor: cc.primaryColor,
                                selectedTextColor: Colors.white,
                                dateTextStyle: TextStyle(
                                    color: cc.greyPrimary,
                                    fontSize: 18,
                                    fontWeight: FontWeight.bold),
                                dayTextStyle: TextStyle(
                                    color: cc.greyParagraph, fontSize: 11),
                                monthTextStyle: TextStyle(
                                    color: cc.greyParagraph, fontSize: 11),
                                onDateChange: (value) {
                                  // New date selected
                                  setState(() {
                                    _selectedWeekday =
                                        firstThreeLetter(value, null);
                                    _monthAndDate =
                                        getMonthAndDate(value, null);
                                    _selectedDate = value;
                                    selectedShedule = 0;
                                    _selectedTime = null;
                                  });

                                  //fetch shedule with selected date
                                  final bookService = Provider.of<BookService>(
                                      context,
                                      listen: false);
                                  provider.fetchShedule(
                                      bookService.sellerId,
                                      _selectedWeekday,
                                      serviceId: bookService.serviceId,
                                      date: value);
                                },
                              ),

                              // Time =============================>
                              const SizedBox(
                                height: 30,
                              ),
                              CommonHelper().titleCommon(
                                  '${asProvider.getString('Available time')}:'),

                              const SizedBox(
                                height: 17,
                              ),
                              provider.isloading == false
                                  ? provider.schedules != 'nothing'
                                      ? GridView.builder(
                                          clipBehavior: Clip.none,
                                          gridDelegate:
                                              FlutterzillaFixedGridView(
                                                  crossAxisCount: 2,
                                                  mainAxisSpacing: 19,
                                                  crossAxisSpacing: 19,
                                                  height: screenWidth <
                                                          fourinchScreenWidth
                                                      ? 75
                                                      : 60),
                                          padding:
                                              const EdgeInsets.only(top: 12),
                                          itemCount: provider
                                              .schedules.schedules.length,
                                          shrinkWrap: true,
                                          physics:
                                              const NeverScrollableScrollPhysics(),
                                          itemBuilder: (context, index) {
                                            return InkWell(
                                              splashColor: Colors.transparent,
                                              highlightColor:
                                                  Colors.transparent,
                                              onTap: () {
                                                setState(() {
                                                  selectedShedule = index;
                                                  _selectedTime = provider
                                                      .schedules
                                                      .schedules[index]
                                                      .schedule;
                                                });
                                              },
                                              child: Stack(
                                                clipBehavior: Clip.none,
                                                children: [
                                                  Container(
                                                    alignment: Alignment.center,
                                                    decoration: BoxDecoration(
                                                        color: cc.black9,
                                                        border: Border.all(
                                                            color: selectedShedule ==
                                                                    index
                                                                ? cc
                                                                    .primaryColor
                                                                : cc
                                                                    .borderColor),
                                                        borderRadius:
                                                            BorderRadius
                                                                .circular(5)),
                                                    padding: const EdgeInsets
                                                        .symmetric(
                                                        horizontal: 13,
                                                        vertical: 15),
                                                    child: Text(
                                                      provider
                                                          .schedules
                                                          .schedules[index]
                                                          .schedule,
                                                      style: TextStyle(
                                                        color: selectedShedule == index ? cc.primaryColor : cc.greyPrimary,
                                                        fontSize: 14,
                                                        fontWeight:
                                                            FontWeight.w400,
                                                      ),
                                                    ),
                                                  ),
                                                  selectedShedule == index
                                                      ? Positioned(
                                                          right: -7,
                                                          top: -7,
                                                          child: CommonHelper()
                                                              .checkCircle())
                                                      : Container()
                                                ],
                                              ),
                                            );
                                          },
                                        )
                                      : Text(
                                          asProvider.getString(
                                              'No shedule available on this date'),
                                          style:
                                              TextStyle(color: cc.primaryColor),
                                        )
                                  : OthersHelper().showLoading(cc.primaryColor),
                              const SizedBox(
                                height: 30,
                              ),
                            ],
                          )),
                    ),
                  ),

                  //  bottom container
                  Container(
                    padding: EdgeInsets.only(
                        left: screenPadding, top: 20, right: screenPadding),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: const BorderRadius.only(
                        topRight: Radius.circular(20),
                        topLeft: Radius.circular(20),
                      ),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.grey.withOpacity(0.1),
                          spreadRadius: 8,
                          blurRadius: 17,
                          offset:
                              const Offset(0, 0), // changes position of shadow
                        ),
                      ],
                    ),
                    child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // CommonHelper().titleCommon('Scheduling for:'),
                          // const SizedBox(
                          //   height: 15,
                          // ),
                          // BookingHelper().rowLeftRight(
                          //     'assets/svg/calendar.svg',
                          //     'Date',
                          //     'Friday, 18 March 2022'),
                          // const SizedBox(
                          //   height: 14,
                          // ),
                          // BookingHelper().rowLeftRight(
                          //     'assets/svg/clock.svg',
                          //     'Time',
                          //     '02:00 PM -03:00 PM'),
                          // const SizedBox(
                          //   height: 23,
                          // ),
                          Consumer<ProfileService>(
                              builder: (context, ps, child) {
                            return CommonHelper().buttonOrange(
                                ps.profileDetails == null ||
                                        ps.profileDetails is String
                                    ? "Sing In"
                                    : asProvider.getString('Next'), () {
                              if (ps.profileDetails == null ||
                                  ps.profileDetails is String) {
                                context.toPage(const LoginPage(
                                  hasBackButton: true,
                                  returnToPrevious: true,
                                ));

                                return;
                              }
                              if (_selectedTime != null) {
                                //increase page steps by one
                                BookStepsService().onNext(context);
                                //set selected shedule so that we can use it later
                                Provider.of<BookService>(context, listen: false)
                                    .setDateTime(_monthAndDate, _selectedTime,
                                        _selectedWeekday,
                                        date: _selectedDate);
                                print(_selectedDate);
                                Navigator.push(
                                    context,
                                    PageTransition(
                                        type: PageTransitionType.rightToLeft,
                                        child: const BookingLocationPage()));
                              }
                            });
                          }),
                          const SizedBox(
                            height: 30,
                          ),
                        ]),
                  )
                ],
              );
            },
          ),
        ),
      ),
    );
  }
}
