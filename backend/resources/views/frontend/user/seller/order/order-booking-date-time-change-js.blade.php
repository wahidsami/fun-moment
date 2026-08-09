    <script>
        const UserSelectedLangSlug = "{{current(explode('_',\App\Helpers\LanguageHelper::user_lang_slug()))}}";
    </script>
    <script src="{{asset('assets/common/js/flatpickr.js')}}"></script>
    <script src="//npmcdn.com/flatpickr/dist/l10n/{{current(explode('_',\App\Helpers\LanguageHelper::user_lang_slug()))}}.js"></script>
    <script>
        (function($) {
            "use strict";

            $(document).ready(async function() {

                //Date and time
                $(document).on('click', '.order_booking_date_change_modal', function (e) {
                    e.preventDefault();

                    // Get service_id from data attribute
                    let service_id = $(this).data('service_id');
                    // Update text in the element with class .seller-id-for-schedule
                    let order_id = $(this).data('id');
                    let seller_id = $(this).data('seller_id');
                    $('.seller-id-for-schedule').text(seller_id);
                    $('#order_booking_change_date_time_id').val(order_id);

                    $.ajax({
                        url: "{{ route('seller.order.booking.date.time.all.days') }}",
                        method: 'GET',
                        data: { id: service_id },
                        success: function(response) {
                            var days_count = response.days_count;
                            // Initialize Flatpickr
                            $("#service_available_dates").flatpickr({
                                minDate: "today",
                                maxDate: new Date().fp_incr(days_count),
                                inline: true,
                                altInput: true,
                                altFormat: "F j, Y",
                                dateFormat: "Y-m-d",
                                locale: UserSelectedLangSlug
                            });
                        },
                        error: function(xhr, status, error) {
                        }
                    });
                });


                //find schedule for a day
                $(".schedule_loader").hide();
                var date_string_format='';
                $(document).on('change','#service_available_dates',function(){
                    let date_string = $(this).val();
                    let day_date = new Date($(this).val());
                    date_string_format = day_date.toDateString();
                    let day = date_string_format.split(' ')[0];
                    let seller_id = $('.seller-id-for-schedule').text();

                    //set value in confirmation fieldset
                    $('.confirm-overview-left .available_date').text(date_string);
                    $('#service_available_dates').val(date_string);

                    $.ajax({
                        url:"{{ route('service.schedule.by.day') }}",
                        method:'post',
                        data:{
                            day:day,
                            date_string:date_string,
                            seller_id:seller_id
                        },
                        beforeSend: function() {
                            $(".schedule_loader").show();
                        },
                        success:function(res){
                            if(res.status=='success'){
                                let all_lists = '';
                                let all_schedules = res.schedules;
                                $.each(all_schedules, function(index, value) {
                                 all_lists += '<div class="custom_radio__single mt-2 get-schedule"><input class="custom_radio__single__input" type="radio" name="time" id="radio3"> <label for="radio3">'+value.schedule+'</label></div>';
                                });
                                $(".show-schedule").html(all_lists);
                                $(".schedule_loader").hide();
                            }if(res.status=='no schedule'){
                                $(".show-schedule").html('<div class="alert alert-warning mt-3"><li class="list">{{ __("Schedule not available") }}</li></div>');
                                $(".schedule_loader").hide();
                            }
                        }
                    })
                });

                //get available schedule
                var available_schedule ='';
                $(document).on('click','.get-schedule',function(){
                    available_schedule = $(this).text();
                    //set value in confirmation fieldset
                    $('.confirm-overview-left .available_schedule').text(available_schedule);
                    $('#service_available_schedule').val(available_schedule);
                });


                // order booking date and time change
                $(document).on('click', '.order_booking_date_change_modal', function(e) {
                    e.preventDefault();
                    let modalContainer = $('#orderBookingDateTimeChange');
                    let order_id = $(this).data('id');
                    modalContainer.find('input[name="order_id"]').val(order_id);
                });

                // Order booking date and time change
                $(document).on('click', '.booking-date-change-decline-info', function() {
                    let rejection_reason = $(this).data('rejection_reason');
                    let booking_date_change_info = $('#bookingDateChangeDeclineInfoModal');
                    booking_date_change_info.find('.booking_change_date_decline_reason_show').text(rejection_reason);
                });

            });
        })(jQuery);
    </script>