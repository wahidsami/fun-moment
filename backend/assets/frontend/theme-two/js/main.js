(function($) {
    "use strict";

    $(document).ready(function() {


        /*-------------------------------------------
            google map css
         ------------------------------------------*/
            $(document).on('click','.pac-target-input', function (){
                $(this).parent().addClass('service_search_with_google_map');
                $(this).addClass('position-relative');
            });

       /*-------------------------------------------
           service list page online offline service
         ------------------------------------------*/
        $("#online-service-btn").click(function() {

        });


        /*-------------------------------------------
        	On Click Open Navbar right contents 
        ------------------------------------------*/
        let [clickShowIcon, navRightContent] = [
            document.querySelector('.click_show_icon'),
            document.querySelector('.nav_rightContent'),
        ];
        if(clickShowIcon != null) {
            clickShowIcon.addEventListener('click', () => 
                navRightContent.classList.toggle('active')
            );
        }
        /* 
        ----------------------------------------
            SearchBar
        ----------------------------------------
        */
        let searchClose = document.querySelector('.search__wrapper__close');
        let searchOpen = document.querySelector('.search__click');
        let searchBar = document.querySelector('.search__wrapper');
        if(searchClose != null) {
            searchClose.addEventListener('click', () => {
                searchBar.classList.remove('active');
            })
        }
        if(searchOpen != null) {
            searchOpen.addEventListener('click', () => {
                searchBar.classList.toggle('active');
            })
        }
        
        /* 
        ========================================
            Click add bookmark js
        ========================================
        */
        let addBookmark = document.querySelector('.add_bookmark')
        if(addBookmark != null) {
            addBookmark.addEventListener('click', () => addBookmark.classList.toggle('marked'))
        }
        
        /* 
        ========================================
            Click Popup Location
        ========================================
        */
        // $(document).on('click', '.popup_overlay, .popup__close, .popup__save', function() {
        //     $('.popup__main, .popup_overlay').removeClass('popup_show');
        // });
        // $(document).on('click', '.click_location', function() {
        //     $('.popup__main, .popup_overlay').toggleClass('popup_show');
        // });

        const clickLocation = document.querySelector('.click_location');
        const popupMain = document.querySelector('.popup__main');
        const popupOverlay = document.querySelector('.popup_overlay');
        const popupClose = document.querySelector('.popup__close');
        const popupRemove = document.querySelector('.popup__remove');
        const popupSave = document.querySelector('.popup__save');

        const togglePopup = () => {
            if (popupMain) {
                popupMain.classList.toggle('popup_show')
            }
            popupOverlay.classList.toggle('popup_show')
        }

        if (clickLocation) {
            clickLocation.addEventListener('click', togglePopup);
        }
        if (popupOverlay) {
            popupOverlay.addEventListener('click', togglePopup);
        }
        if (popupSave) {
            popupSave.addEventListener('click', togglePopup);
        }
        if (popupClose) {
            popupClose.addEventListener('click', togglePopup);
        }
        if (popupRemove) {
            popupRemove.addEventListener('click', togglePopup);
        }
        

        /*--------------------
            wow js init
        ---------------------*/
        new WOW().init();
        /* 
        ----------------------------------------
            Location click
        ----------------------------------------
        */
        // $(document).on('click', '.single-location, .date-time-list .list', function() {
        //     $(this).siblings().removeClass('active');
        //     $(this).addClass('active');
        // });
        /* 
        ========================================
            Dashboard Dropdown Side Menu
        ========================================
        */
        $(document).on('click', '.dashboard-list .has-children a', function(e) {
            var db = $(this).parent('.has-children');
            if (db.hasClass('open')) {
                db.removeClass('open');
                db.find('.submenu').children('.has-children').removeClass("open"); //2nd children remove 
                db.find('.submenu').removeClass('open');
                db.find('.submenu').slideUp(300, "swing");
            } else {
                db.addClass('open');
                db.children('.submenu').slideDown(300, "swing");
                db.siblings('.has-children').children('.submenu').slideUp(300, "swing");
                db.siblings('.has-children').removeClass('open');
            }
        });

        /* 
        ========================================
            Toggle Recent Order js
        ========================================
        */
        $(document).on('click', '.toggle_recentOrder', function(e) {
            let rc = $(this).closest('.recentOrder_parent');
            if (rc.hasClass('open')) {
                rc.removeClass('open');
                rc.find('.recentOrder_children').removeClass('open');
                rc.find('.recentOrder_children').slideUp(300);
            } else {
                rc.addClass('open');
                rc.children('.recentOrder_children').slideDown(300);
                rc.siblings('.recentOrder_parent').children('.recentOrder_children').slideUp(300);
                rc.siblings('.recentOrder_parent').removeClass('open');
            }
        });
        /* 
        ========================================
            Dashboard Responsive Sidebar
        ========================================
        */

        $(document).on('click', '.sidebar-icon, .responsive_class_for_bars', function() {
            $('.dashboard-close, .dashboard-left-content, .body-overlay').toggleClass('active');
        });


        /* 
        ========================================
            Chat Responsive Sidebar Css
        ========================================
        */
        $(document).on('click', '.close_chat, .body-overlay', function() {
            $('.chat_wrapper__contact__close, .body-overlay').removeClass('active');
        });
        $(document).on('click', '.chat_sidebar', function() {
            $('.chat_wrapper__contact__close, .body-overlay').addClass('active');
        });
        /* 
        ========================================
            Chat Click and Active Class
        ========================================
        */
        $(document).on('click', '.chat_item', function() {
            $(this).addClass('active').siblings().removeClass('active');
            $('.chat_wrapper__contact__close, .body-overlay').removeClass('active');
        });
        /*
        ========================================
            Attach File js
        ========================================
        */
        let uploadImage = document.querySelector("#uploadImage");
        let inputTag = document.querySelector("#inputTag");

        if(inputTag != null) {
            inputTag.addEventListener('change', function () {

                let inputTagFile = document.querySelector("#inputTag").files[0];

                uploadImage.innerText = inputTagFile.name;

                console.log(inputTag)

            });

        };

        /* 
        ==================================================
            Dashboard Status hover Dropdown
        ==================================================
        */
        // $(document).on('click', '.dropdown__status__list__option', function(e) {
        //     e.preventDefault();
        //     let listOption = $(this);
        //     listOption.addClass('selected').siblings().removeClass('selected');
        //     let listValue = listOption.text();
        //     let listParent = listOption.closest('.dropdown__status');
        //     let listMain = listParent.find('.dropdown__status__main');
        //     if (listMain.hasClass('Open')) {
        //         listMain.removeClass('Open');
        //     }
        //     if (listMain.hasClass('Completed')) {
        //         listMain.removeClass('Completed');
        //     }
        //     if (listMain.hasClass('Close')) {
        //         listMain.removeClass('Close');
        //     }
        //     let oldAttr = listParent.find('.dropdown__status__main').attr('value');
        //     listParent.find('.dropdown__status__main').text(listValue).removeClass(oldAttr).addClass(('value', listValue));
        //     listParent.find('.dropdown__status__main').attr('value', listValue);
        // });

        $(document).on('click', '.dropdown__status__list__option', function(e) {
            e.preventDefault();
            let listOption = $(this);
            listOption.addClass('selected').siblings().removeClass('selected');
            let listValue = listOption.text();
            let listParent = listOption.closest('.dropdown__status');
            let listMain = listParent.find('.dropdown__status__main');
            listMain.removeClass('Open Completed Close');
            let oldAttr = listMain.attr('value');
            listMain.text(listValue).removeClass(oldAttr).addClass(listValue);
            listMain.attr('value', listValue);
        });

        /*
        ========================================
           Faq accordion
        ========================================
        */
        $(document).on('click','.new_faq_contents .new_faq_title', function(e) {
            let faq = $(this).closest('.new_faq_item');
            if (faq.hasClass('open')) {
                faq.removeClass('open');
                faq.find('.new_faq_panel').removeClass('open');
                faq.find('.new_faq_panel').slideUp(300);
            } else {
                faq.addClass('open');
                faq.children('.new_faq_panel').slideDown(300);
                faq.siblings('.new_faq_item').closest('.new_faq_panel').slideUp(300);
                faq.siblings('.new_faq_item').removeClass('open');
                faq.siblings('.new_faq_item').find('.new_faq_title').removeClass('open');
                faq.siblings('.new_faq_item').find('.new_faq_panel').slideUp(300);
            }
        });

        /* 
        ========================================
            Password Show Hide On Click
        ========================================
        */
        $(document).on("click", ".toggle_password", function(e) {
            e.preventDefault();
            let inputPass = $(this).parent().find("input");
            if (inputPass.attr("type") === "password") {
                inputPass.attr("type", "text");
                $(this).children().addClass('fa-eye').removeClass('fa-eye-slash');
            } else {
                inputPass.attr("type", "password");
                $(this).children().addClass('fa-eye-slash').removeClass('fa-eye');
            }
        });
        /*-----------------
         Select2 Js
        ------------------*/
        $('.select2_activation').select2();
        /* 
        ========================================
            Product Quantity js
        ========================================
        */
        $(document).on('click', '.plus', function() {
            var selectedInput = $(this).parent().find('.quantity-input');
            // if (selectedInput.val() < 50) {
            selectedInput[0].stepUp(1);
            // }
        });
        $(document).on('click', '.substract', function() {
            var selectedInput = $(this).parent().find('.quantity-input');
            if (selectedInput.val() > 1) {
                selectedInput[0].stepDown(1);
            }
        });

        /* 
        ========================================
            Flatpickr Js
        ========================================
        */
        $('.flatpickr_calendar').flatpickr({
            altFormat: "invisible",
            // altInput: false,
            inline: true,
        });

        /* 
        ========================================
            Tab
        ========================================
        */
        $(document).on('click', 'ul.tabs li', function() {
            var tab_id = $(this).attr('data-tab');

            $('ul.tabs li').removeClass('active');
            $('.tab_content_item').removeClass('active');

            $(this).addClass('active');
            $("#" + tab_id).addClass('active');
        });

        // document.addEventListener('click', (event) => {
        //     const target = event.target.closest('ul.tabs li');
        //     if (!target) return;
          
        //     const tabId = target.dataset.tab;
        //     document.querySelectorAll('ul.tabs li, .tab_content_item').forEach(el => el.classList.remove('active'));
        //     target.classList.add('active');
        //     document.getElementById(tabId).classList.add('active');
        // });

        // $(document).on('click', 'ul.tabs li', function() {
        //     const tabId = $(this).data('tab');
        //     $('ul.tabs li, .tab_content_item').removeClass('active');
        //     $(this).addClass('active');
        //     $(`#${tabId}`).addClass('active');
        // });

        

        /* 
        ========================================
            Pagination 
        ========================================
        */
        $(document).on('click', '.pagination-list li', function() {
            $(this).siblings().removeClass("active");
            $(this).addClass("active");
        });

        /* 
        ========================================
            Radio box active Class Js
        ========================================
        */
        $(document).on('click', '.custom_radio__single', (event) => {
            let customRadio = event.currentTarget;
            $(customRadio).addClass('active').siblings().removeClass('active');

            if ($(customRadio).hasClass('active')) {
                $(customRadio).find('input').prop('checked', true);
            } else {
                $(customRadio).find('input').prop('checked', false);
            }
        });

        /* 
        ========================================
           Job Post Custom Switch Js
        ========================================
        */
        let switchInput = $('.custom_switch .switch_input');
        let switchParent = $('.switchParent');
        let replaceText = switchParent.find('.replaceText');
        
        $(document).on('click', '.custom_switch', function() {
            let currentSwitch = $(this);
            let currentSwitchInput = currentSwitch.find(switchInput);
            let currentReplaceText = currentSwitch.closest(switchParent).find(replaceText);
          
            if (!currentSwitchInput.prop('checked')) {
              currentReplaceText.text('Close');
            } else {
              currentReplaceText.text('Active');
            }
          });

        /* 
        ========================================
            StepForm active class Js
        ========================================
        */
        // $(document).on('click', '.cleaning_select, .shifting_select, .painting_select', function(e) {
        //     $(this).addClass('active').siblings().removeClass('active');
        // });

        $(document).on('click', '.cleaning_select, .shifting_select, .painting_select', (e) => {
            const addClassItem = e.currentTarget;
            $(addClassItem).addClass('active').siblings().removeClass('active');

        });
        /*
        ----------------------------
            Multi step Form
        ------------------------------
        */
       $(function() {
            let current_fs, next_fs, previous_fs, opacity, prevStepBtn;
            let stepsList = $(".new_stepForm_list__item");

            $(document).on('click', ".next", function(e) {
                e.preventDefault();

                current_fs = $(this).closest('fieldset');
                next_fs = current_fs.next();

                stepsList.eq($("fieldset").index(current_fs)).addClass("completed").removeClass("active");
                stepsList.eq($("fieldset").index(next_fs)).addClass("active");


                next_fs.show();

                current_fs.animate({ opacity: 0 }, {
                    step: function(now) {
                        opacity = 1 - now;

                        current_fs.css({ display: 'none', position: 'relative' });

                        next_fs.css({ 'opacity': opacity });
                    },
                    duration: 500
                });

            });

            // $(document).on('click', ".stepPrevious", function(e) {
            //     e.preventDefault();
            //     current_fs = $(this).closest('fieldset');
            //     previous_fs = current_fs.prev();
            //
            //     stepsList.eq($("fieldset").index(previous_fs)).removeClass("completed").addClass("active");
            //     stepsList.eq($("fieldset").index(current_fs)).removeClass("active");
            //
            //     previous_fs.show();
            //     current_fs.animate({ opacity: 0 }, {
            //         step: function(now) {
            //             opacity = 1 - now;
            //
            //             current_fs.css({ display: 'none', position: 'relative' });
            //
            //             previous_fs.css({ 'opacity': opacity });
            //         },
            //         duration: 500
            //     });
            // });


            $(document).on('click', ".stepPrevious", function(e) {
                e.preventDefault();
                current_fs = $(this).closest('fieldset');
                previous_fs = current_fs.prev();
                stepsList.eq($("fieldset").index(previous_fs)).removeClass("completed").addClass("active");
                stepsList.eq($("fieldset").index(current_fs)).removeClass("active");

                previous_fs.show();

                // previous button new js add for last next button show
                prevStepBtn = $('fieldset .btn-wrapper');
                prevStepBtn.css({ display: 'flex', opacity: 1});

                current_fs.animate({ opacity: 0 }, {
                    step: function(now) {
                        opacity = 1 - now;
                        current_fs.css({ display: 'none', position: 'relative'});
                        previous_fs.css({ 'opacity': opacity });
                    },
                    duration: 500
                });
            });


            function  editServiceBookInfo(){
                // if location active
                if ($('.edit_location').hasClass('active')) {
                    $('.edit_location').removeClass('active');
                    $('.padding-top-50.confirm-location').css({ display: 'none', position: 'relative' });
                }

                // if service info active
                if ($('.edit_service_info').hasClass('active')) {
                    $('.edit_service_info').removeClass('active');
                    $('.padding-top-50.edit_style_service_info').css({ display: 'none', position: 'relative' });
                }

                // if booking info active
                if ($('.edit_booking_info').hasClass('active')) {
                    $('.edit_booking_info').removeClass('active');
                    $('.padding-top-50.edit_style_booking_info').css({ display: 'none', position: 'relative' });
                }

                // if booking info active
                if ($('.edit_booking_info').hasClass('active')) {
                    $('.edit_booking_info').removeClass('active');
                    $('.padding-top-50.edit_style_booking_info').css({ display: 'none', position: 'relative' });
                }

                // if date & time active
                if ($('.edit_date_time_info').hasClass('active')) {
                    $('.edit_date_time_info').removeClass('active');
                    $('.padding-top-50.edit_style_schedule').css({ display: 'none', position: 'relative' });

                }

                // if payment option active
                if ($('.edit_payment_option').hasClass('active')) {
                    $('.edit_payment_option').removeClass('active');
                    $('.padding-top-50.edit_style_payment_option').css({ display: 'none', position: 'relative' });
                }

                // if payment option Completed
                if ($('.edit_payment_option').hasClass('completed')) {
                    if ($('#order_from_user_wallet').val() !== null && $('#check3').is(":checked")){
                        $('.edit_payment_option').removeClass('active');
                    }
                }


                // for all global previous button new js add for last next button show
                prevStepBtn = $('fieldset .btn-wrapper');
                prevStepBtn.css({ display: 'flex', opacity: 1});
            }


           // service info disable true or false
           $('.click_edit_service_info').on('click', function() {
               if ($('.edit_service_info').hasClass('completed')) {
                   $('.click_edit_service_info').prop('disabled', false);
               } else {
                   $('.click_edit_service_info').prop('disabled', true);
               }
           });
            // booking info disable true or false
            $('.click_edit_booking_info').on('click', function() {
               if ($('.edit_service_info').hasClass('completed')) {
                   $('.click_edit_booking_info').prop('disabled', false);
               } else {
                   $('.click_edit_booking_info').prop('disabled', true);
               }
           });

            // date time disable true or false
            $('.click_edit_date_time').on('click', function() {
               if ($('.edit_service_info').hasClass('completed')) {
                   $('.click_edit_date_time').prop('disabled', false);
               } else {
                   $('.click_edit_date_time').prop('disabled', true);
               }
           });

            // payment disable true or false
            $('.edit_payment_option').on('click', function() {
               if ($('.edit_payment_option').hasClass('completed')) {
                   $('.edit_payment_option').prop('disabled', false);
               } else {
                   $('.edit_payment_option').prop('disabled', true);
               }
           });


            // location
           $(document).on('click', ".click_edit_address", function(e) {
             e.preventDefault();
               // Remove the display property from the fieldset element
               $('.confirm-location').css({ display: 'block', opacity: 1 });
               $('.edit_style_booking_info').css({ display: 'none', opacity: 0 });
               $('.edit_style_schedule').css({ display: 'none', opacity: 0 });
               $('.edit_style_payment_option').css({ display: 'none', opacity: 0 });
               $('.edit_style_service_info').css({ display: 'none', opacity: 0 });

               editServiceBookInfo();

               // edit open after show
               $('.edit_location').addClass('active');
               $('.confirm-location').css({ display: 'block', opacity: 1 });
           });

            // service info
           $(document).on('click', ".click_edit_service_info", function(e) {
             e.preventDefault();
               console.log(444)
               // Remove the display property from the fieldset element
               $('.edit_style_service_info').css({ display: 'block', opacity: 1 });
               $('.edit_style_booking_info').css({ display: 'none', opacity: 0 });
               $('.edit_style_schedule').css({ display: 'none', opacity: 0 });
               $('.edit_style_payment_option').css({ display: 'none', opacity: 0 });

               editServiceBookInfo();

               // edit open after show
               $('.edit_service_info').addClass('active');
               $('.edit_service_info').css({ display: 'block', opacity: 1 });
           });

            // booking info
           $(document).on('click', ".click_edit_booking_info", function(e) {
               e.preventDefault();
               // Remove the display property from the fieldset element
               $('.edit_style_booking_info').css({ display: 'block', opacity: 1 });
               $('.confirm-location').css({ display: 'none', opacity: 0 });
               $('.edit_style_service_info').css({ display: 'none', opacity: 0 });
               $('.edit_style_schedule').css({ display: 'none', opacity: 0 });
               $('.edit_style_payment_option').css({ display: 'none', opacity: 0 });

               editServiceBookInfo();

               // edit open after show
               $('.edit_booking_info').addClass('active');
               $('.edit_style_booking_info').css({ display: 'block', opacity: 1 });
           });


           // date time
           $(document).on('click', ".click_edit_date_time", function(e) {
             e.preventDefault();
               // Remove the display property from the fieldset element
               $('.edit_style_schedule').css({ display: 'block', opacity: 1 });
               $('.confirm-location').css({ display: 'none', opacity: 0 });
               $('.edit_style_service_info').css({ display: 'none', opacity: 0 });
               $('.edit_style_booking_info').css({ display: 'none', opacity: 0 });
               $('.edit_style_payment_option').css({ display: 'none', opacity: 0 });

               editServiceBookInfo();

               // edit open after show
               $('.edit_date_time_info').addClass('active');
               $('.edit_style_schedule').css({ display: 'block', opacity: 1 });
           });

           // payment option
           $(document).on('click', ".edit_payment_option", function(e) {
             e.preventDefault();
               // Remove the display property from the fieldset element
               $('.edit_style_payment_option').css({ display: 'block', opacity: 1 });
               $('.confirm-location').css({ display: 'none', opacity: 0 });
               $('.edit_style_service_info').css({ display: 'none', opacity: 0 });
               $('.edit_style_booking_info').css({ display: 'none', opacity: 0 });
               $('.edit_style_schedule').css({ display: 'none', opacity: 0 });

               editServiceBookInfo();

               // edit open after show
               $('.edit_payment_option').addClass('active');
               $('.edit_style_payment_option').css({ display: 'block', opacity: 1 });
           });


        });


        /*
        ========================================
            Click and page reload js
        ========================================
        */
        let pageReloadBtn = document.querySelector('.pageReload');
        if(pageReloadBtn != undefined) {
            let pageReload = () => {
                location.reload();
            }
            pageReloadBtn.addEventListener('click', pageReload);
        }
        /*-------------------------------
            Navbar Toggler Icon
        ------------------------------
        */
        $(document).on('click', '.navbar_toggler', function() {
            $(this).toggleClass("active")
        });
        // $(document).on('click', '.navbar_toggler', () => {
        //     $(this).toggleClass("active");
        // });
        /*
        ========================================
            Global Slider Init
        ========================================
        */
        var globalSlickInit = $('.global-slick-init');
        if (globalSlickInit.length > 0) {
            //todo have to check slider item 
            $.each(globalSlickInit, function(index, value) {
                if ($(this).children('div').length > 1) {
                    //todo configure slider settings object
                    var sliderSettings = {};
                    var allData = $(this).data();
                    var infinite = typeof allData.infinite == 'undefined' ? false : allData.infinite;
                    var arrows = typeof allData.arrows == 'undefined' ? false : allData.arrows;
                    var autoplay = typeof allData.autoplay == 'undefined' ? false : allData.autoplay;
                    var focusOnSelect = typeof allData.focusonselect == 'undefined' ? false : allData.focusonselect;
                    var swipeToSlide = typeof allData.swipetoslide == 'undefined' ? false : allData.swipetoslide;
                    var slidesToShow = typeof allData.slidestoshow == 'undefined' ? 1 : allData.slidestoshow;
                    var slidesToScroll = typeof allData.slidestoscroll == 'undefined' ? 1 : allData.slidestoscroll;
                    var speed = typeof allData.speed == 'undefined' ? '500' : allData.speed;
                    var dots = typeof allData.dots == 'undefined' ? false : allData.dots;
                    var cssEase = typeof allData.cssease == 'undefined' ? 'linear' : allData.cssease;
                    var prevArrow = typeof allData.prevarrow == 'undefined' ? '' : allData.prevarrow;
                    var nextArrow = typeof allData.nextarrow == 'undefined' ? '' : allData.nextarrow;
                    var centerMode = typeof allData.centermode == 'undefined' ? false : allData.centermode;
                    var centerPadding = typeof allData.centerpadding == 'undefined' ? false : allData.centerpadding;
                    var rows = typeof allData.rows == 'undefined' ? 1 : parseInt(allData.rows);
                    var autoplay = typeof allData.autoplay == 'undefined' ? false : allData.autoplay;
                    var autoplaySpeed = typeof allData.autoplayspeed == 'undefined' ? 2000 : parseInt(allData.autoplayspeed);
                    var lazyLoad = typeof allData.lazyload == 'undefined' ? false : allData.lazyload; // have to remove it from settings object if it undefined
                    var appendDots = typeof allData.appenddots == 'undefined' ? false : allData.appenddots;
                    var appendArrows = typeof allData.appendarrows == 'undefined' ? false : allData.appendarrows;
                    var asNavFor = typeof allData.asnavfor == 'undefined' ? false : allData.asnavfor;
                    var verticalSwiping = typeof allData.verticalswiping == 'undefined' ? false : allData.verticalswiping;
                    var vertical = typeof allData.vertical == 'undefined' ? false : allData.vertical;
                    var fade = typeof allData.fade == 'undefined' ? false : allData.fade;
                    var rtl = typeof allData.rtl == 'undefined' ? false : allData.rtl;
                    var responsive = typeof $(this).data('responsive') == 'undefined' ? false : $(this).data('responsive');
                    //slider settings object setup
                    sliderSettings.infinite = infinite;
                    sliderSettings.arrows = arrows;
                    sliderSettings.autoplay = autoplay;
                    sliderSettings.focusOnSelect = focusOnSelect;
                    sliderSettings.swipeToSlide = swipeToSlide;
                    sliderSettings.slidesToShow = slidesToShow;
                    sliderSettings.slidesToScroll = slidesToScroll;
                    sliderSettings.speed = speed;
                    sliderSettings.dots = dots;
                    sliderSettings.cssEase = cssEase;
                    sliderSettings.prevArrow = prevArrow;
                    sliderSettings.nextArrow = nextArrow;
                    sliderSettings.rows = rows;
                    sliderSettings.autoplaySpeed = autoplaySpeed;
                    sliderSettings.autoplay = autoplay;
                    sliderSettings.verticalSwiping = verticalSwiping;
                    sliderSettings.vertical = vertical;
                    sliderSettings.rtl = rtl;
                    if (centerMode != false) {
                        sliderSettings.centerMode = centerMode;
                    }
                    if (centerPadding != false) {
                        sliderSettings.centerPadding = centerPadding;
                    }
                    if (lazyLoad != false) {
                        sliderSettings.lazyLoad = lazyLoad;
                    }
                    if (appendDots != false) {
                        sliderSettings.appendDots = appendDots;
                    }
                    if (appendArrows != false) {
                        sliderSettings.appendArrows = appendArrows;
                    }
                    if (asNavFor != false) {
                        sliderSettings.asNavFor = asNavFor;
                    }
                    if (fade != false) {
                        sliderSettings.fade = fade;
                    }
                    if (responsive != false) {
                        sliderSettings.responsive = responsive;
                    }
                    $(this).slick(sliderSettings);
                }
            });
        }
        /*------------------
            back to top
        ------------------*/
        $(document).on('click', '.back-to-top', function() {
            $("html,body").animate({
                scrollTop: 0
            }, 1500);
        });

    });
    /*-------------------------------
        Back To Top
    ------------------------------
    */
    $(window).on('scroll', function() {
        //back to top show/hide
        var ScrollTop = $('.back-to-top');
        if ($(window).scrollTop() > 300) {
            ScrollTop.fadeIn(300);
        } else {
            ScrollTop.fadeOut(300);
        }
    });
    /*-----------------
    preloader
    ------------------*/
    $(window).on('load', function() {
        $('#preloader').delay(300).fadeOut('fast');
        $('body').delay(300).css({
            'overflow': 'visible'
        });
    });

})(jQuery);