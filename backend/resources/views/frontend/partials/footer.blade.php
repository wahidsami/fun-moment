@php
$footer_variant = !is_null(get_footer_style()) ? get_footer_style() : '02';
@endphp
@include('frontend.partials.pages-portion.footers.footer-'.$footer_variant)

<!-- back to top area start -->
<div class="back-to-top {{$page_post->back_to_top ?? ''}}">
    <span class="back-top"><i class="las la-angle-up"></i></span>
</div>
<!-- back to top area end -->

@if(moduleExists("LiveChat"))
<x-livechat.widget-markup />
@endif

    <!--new js load -->
    <script src="{{ asset('assets/frontend/theme-two/js/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('assets/frontend/theme-two/js/jquery-migrate.min.js') }}"></script>
    <script src="{{ asset('assets/frontend/theme-two/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/frontend/theme-two/js/wow.min.js') }}"></script>
    <script src="{{ asset('assets/frontend/theme-two/js/slick.js') }}"></script>
    <script src="{{ asset('assets/frontend/theme-two/js/jquery.nicescroll.min.js') }}"></script>
    <script src="{{ asset('assets/frontend/theme-two/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/frontend/theme-two/js/flatpickr.min.js') }}"></script>
    <script src="{{ asset('assets/frontend/theme-two/js/main.js') }}"></script>

    <script src="{{asset('assets/frontend/js/jquery.magnific-popup.js')}}"></script>
    <script src="{{asset('assets/frontend/js/jquery.nice-select.js')}}"></script>
    <script src="{{asset('assets/frontend/js/main.js')}}"></script>
    <script src="{{asset('assets/frontend/js/dynamic-script.js')}}"></script>




<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/{{current(explode('_',\App\Helpers\LanguageHelper::user_lang_slug()))}}.js"></script>
<script>
    $('.nav-new-menu-style li').addClass('list');
    /*-----------------
     Select2
    ------------------*/
    $('select').select2({
         language: "{{current(explode('_',\App\Helpers\LanguageHelper::user_lang_slug()))}}"
    });
</script>

<script>
    $.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': '{{csrf_token()}}'
    }
});
</script>

@include('frontend.pages.services.partials.service-search')
@include('frontend.partials.home-search')
@include('frontend.partials.inline-scripts')
@include('frontend.partials.gdpr-cookie')

@if(moduleExists("JobPost"))
    @include('jobpost::frontend.jobs.job-search')
@endif

@if(!empty( get_static_option('tawk_api_key')))
 {!! get_static_option('tawk_api_key') !!}
 @endif

@if(!empty(get_static_option('site_google_captcha_v3_site_key'))  )
    <script src="https://www.google.com/recaptcha/api.js?render={{get_static_option('site_google_captcha_v3_site_key')}}"></script>
    <script>
        (function() {
            "use strict";
            grecaptcha.ready(function () {
                grecaptcha.execute("{{get_static_option('site_google_captcha_v3_site_key')}}", {action: 'homepage'}).then(function (token) {
                    if(document.getElementById('gcaptcha_token') != null){
                        document.getElementById('gcaptcha_token').value = token;
                    }
                });
            });

        })(jQuery);
    </script>
@endif

<script src="{{asset('assets/common/js/toastr.min.js')}}"></script>
{!! Toastr::message() !!}
{{--<script>--}}
{{--    $('[data-toggle="tooltip"]').tooltip()--}}
{{--</script>--}}
@if(request()->routeIs('frontend.order.payment.success'))
<script>
    var myCalendar = createCalendar({
        options: {
            class: 'my-class',
            // You can pass an ID. If you don't, one will be generated for you
            id: 'my-id'
        },
        data: {
            // Event title
            title: "{{ optional($order_details->service)->title }}",

            // Event start date
            start: new Date('{{ Carbon\Carbon::parse(strtotime($order_details->date))->format('F d, Y H:i') }}'),

            // Event duration (IN MINUTES)
            Minutes: 120,

            // You can also choose to set an end time
            // If an end time is set, this will take precedence over duration
            end: new Date('{{ Carbon\Carbon::parse(strtotime($order_details->date))->format('F d, Y H:i') }}'),

            // Event Address
            address: "{{ optional($order_details->buyer)->address }}",

            // Event Description
            description: 'Order Successfully Created'
        }
    });

    document.querySelector('.new-cal').appendChild(myCalendar);
</script>
@endif
@if(moduleExists("LiveChat"))
    <x-livechat.widget-js />
@endif
@if(moduleExists("Subscription"))
   @include('frontend.partials.subscription-js')
@endif
@yield('scripts')

@if(!empty(get_static_option('google_map_settings')))
@php
    $root_url = url('/');
    $service_page_url = url('/service-list');
    $request_url = URL::current();

    // get page info
      $home_page_one_with_root = url('/', 'home-page-one');
      $home_page_two_with_root = url('/', 'home-page-two');
      $home_page_three_with_root = url('/', 'home-page-three');
      $home_page_four_with_root = url('/', 'home-page-four');
      $check_google_map_for_page = $root_url == $request_url || $home_page_one_with_root == $request_url || $home_page_two_with_root == $request_url
         || $home_page_three_with_root == $request_url || $home_page_four_with_root == $request_url
@endphp
    @if($check_google_map_for_page)
        <!-- google map for live location -->
        <script src="https://maps.googleapis.com/maps/api/js?key={{get_static_option('service_google_map_api_key')}}&libraries=places"> </script>
    @endif

    @if($service_page_url == $request_url)
        <script>
            // Function to get a cookie
            function getCookie(name) {
            var nameEQ = name + "=";
            var ca = document.cookie.split(';');
            for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
            }
        </script>
    @endif

    @if($check_google_map_for_page)
        <script>
            // Cookie add set of time
            function setCookie(name, value, days) {
                var expires = "";
                if (days) {
                    var date = new Date();
                    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                    expires = "; expires=" + date.toUTCString();
                }
                document.cookie = name + "=" + (value || "") + expires + "; path=/";
            }

            // if  Cookie id null
            function getCookie(name) {
                var nameEQ = name + "=";
                var ca = document.cookie.split(';');
                for (var i = 0; i < ca.length; i++) {
                    var c = ca[i];
                    while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                    if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
                }
                return null;
            }

            // Function to get the visitor's country and coordinates
            function getVisitorLocation() {
                // Check if the Geolocation API is supported
                if (navigator.geolocation) {
                    // Get the visitor's current position
                    navigator.geolocation.getCurrentPosition(function(position) {
                        var latitude = position.coords.latitude;
                        var longitude = position.coords.longitude;

                        // Store the latitude and longitude in localStorage
                        localStorage.setItem('latitude', latitude);
                        localStorage.setItem('longitude', longitude);

                        // Create a new Geocoder object
                        var geocoder = new google.maps.Geocoder();
                        // Prepare the latitude and longitude values as a LatLng object
                        var latLng = new google.maps.LatLng(latitude, longitude);

                        // Reverse geocode the coordinates to get the address
                        geocoder.geocode({ 'location': latLng }, function(results, status) {
                            if (status === 'OK') {
                                if (results[0]) {
                                    // Get the country component from the address
                                    var address = results[0].formatted_address;
                                    var placeId = results[0].place_id;
                                    var country = '';
                                    for (var i = 0; i < results[0].address_components.length; i++) {
                                        var component = results[0].address_components[i];
                                        if (component.types.includes('country')) {
                                            country = component.long_name;
                                            break;
                                        }
                                    }


                                    $('#myLocationGetAddress').on('click', function() {

                                        if ('permissions' in navigator) {
                                            // Request location permission using the Permissions API
                                            navigator.permissions.query({ name: 'geolocation' }).then(function(permissionStatus) {
                                                if (permissionStatus.state === 'granted') {
                                                    // Location permission granted
                                                    $("#autocomplete").val(address);
                                                } else if (permissionStatus.state === 'prompt') {
                                                    // Location permission prompt
                                                    permissionStatus.onchange = function() {
                                                        if (permissionStatus.state === 'granted') {
                                                            // Location permission granted
                                                            $("#autocomplete").val(address);
                                                        } else {
                                                            // Location permission denied
                                                            console.log('Please allow the permission');
                                                            // Show a custom alert or fallback message to the user here
                                                        }
                                                    };
                                                    permissionStatus.prompt();
                                                } else {
                                                    // Location permission denied
                                                    console.log('Please allow the permission');
                                                    // Show a custom alert or fallback message to the user here
                                                }
                                            });
                                        } else if ('geolocation' in navigator) {
                                            // Request location permission using the Geolocation API
                                            navigator.geolocation.getCurrentPosition(function(position) {
                                                // Location permission granted
                                                $("#autocomplete").val(address);
                                            }, function(error) {
                                                // Location permission denied or error occurred
                                                console.log('Please allow the permission');
                                                // Show a custom alert or fallback message to the user here
                                            });
                                        } else {
                                            // Geolocation API not supported
                                            console.log('Geolocation API not supported');
                                            // Show a custom alert or fallback message to the user here
                                        }
                                    });



                                    // visitor full address get
                                    // $("#autocomplete").val(address);
                                    // Store the place ID in a cookie
                                    // setCookie('placeId', placeId, 7);

                                    // Display the stored place ID
                                    var storedPlaceId = getCookie('placeId');
                                    if (storedPlaceId) {
                                        console.log('Stored Place ID: ' + storedPlaceId);
                                    } else {
                                        console.log('No stored Place ID found');
                                    }


                                } else {
                                    console.log('No results found');
                                }
                            } else {
                                console.log('Geocoder failed due to: ' + status);
                            }
                         });
                    }, function() {
                        console.log('Error: The Geolocation service failed.');
                    });
                } else {
                    console.log('Error: Geolocation is not supported by this browser.');
                }
            }
            // Call the getVisitorLocation() function
           getVisitorLocation();
        </script>


        <!-- autocomplete address js start -->
        <script>
            $(document).ready(function () {
                $("#latitudeArea").addClass("d-none");
                $("#longtitudeArea").addClass("d-none");
            });
        </script>

      <!-- search user address wise-->
        <script>
            $(document).ready(function () {
                $('#autocomplete').on('keyup change click', function () {
                        var input = document.getElementById("pac-input");
                        var autocomplete = new google.maps.places.Autocomplete(input);
                        autocomplete.setFields(["address_components", "geometry"]);
                        autocomplete.addListener("place_changed", function () {
                            var place = autocomplete.getPlace();
                            if (!place.geometry || !place.geometry.location) {
                                console.log("No location data found for the selected place.");
                                return;
                            }
                            // Get the latitude and longitude of the selected place
                           let selectedLatitude = place.geometry.location.lat();
                            console.log(selectedLatitude);
                            console.log(place);
                        });
                });
            });
        </script>

        <script>
            google.maps.event.addDomListener(window, 'load', initialize);

            // Function to set a cookie
            function setCookie(name, value, days) {
                var expires = "";
                if (days) {
                    var date = new Date();
                    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                    expires = "; expires=" + date.toUTCString();
                }
                document.cookie = name + "=" + (value || "") + expires + "; path=/";
            }

            // Function to get a cookie
            function getCookie(name) {
                var nameEQ = name + "=";
                var ca = document.cookie.split(';');
                for (var i = 0; i < ca.length; i++) {
                    var c = ca[i];
                    while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                    if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
                }
                return null;
            }

            function initialize() {
                var input = document.getElementById('autocomplete');

                var autocomplete = new google.maps.places.Autocomplete(input);

                autocomplete.addListener('place_changed', function () {
                    var place = autocomplete.getPlace();
                    $('#latitude').val(place.geometry.location.lat());
                    $('#longitude').val(place.geometry.location.lng());
                    $("#latitudeArea").removeClass("d-none");
                    $("#longitudeArea").removeClass("d-none");

                    $("#change_address_new").val(1);

                    // Check if stored placeId exists
                    var storedPlaceId = getCookie('placeId');
                    var placeId = place.place_id;

                    if (storedPlaceId) {
                        var currentPlaceId = placeId;

                        if (currentPlaceId !== storedPlaceId) {
                            // Remove old cookies
                            document.cookie = "placeId=;expires=" + new Date(0).toUTCString();
                            document.cookie = "address=;expires=" + new Date(0).toUTCString();
                        }
                    }

                    setCookie('placeId', placeId, 7);
                    setCookie('address', place.formatted_address, 7);

                    // Log the new cookies
                    console.log('New Place ID: ' + placeId);
                    console.log('New Address: ' + place.formatted_address);
                });


                $('.setLocation_btn').on('click', function () {
                    var changeAddress = $('#change_address_new').val();
                    if (changeAddress === '') {
                        // User didn't change the address, use current location-wise service
                        var place = autocomplete.getPlace();
                        var placeId = place.place_id;
                        var address = place.formatted_address;

                        var id_add = setCookie('placeId', placeId, 7);
                        var add_add = setCookie('address', address, 7);

                        console.log(id_add);
                        console.log(add_add);
                    }
                });
            }
        </script>

        <!-- location address validation -->
        <script>
            $(document).ready(function () {
                // empty check
                  $(".setLocation_btn").click(function() {
                    var getAutocompleteInputValue = $('#autocomplete').val();
                      var errorMessage = '{{ __('Set location') }}';
                      console.log(errorMessage)

                    if (getAutocompleteInputValue === null || getAutocompleteInputValue === "") {
                        toastr.warning(errorMessage);
                        $(this).prop("disabled", true);
                    }
                });

                // remove  disabled
                var autocompleteInput = $('#autocomplete');
                autocompleteInput.on('keyup click change', function() {
                    var getAutocompleteInputValue = $('#autocomplete').val();
                    if (getAutocompleteInputValue !== null) {
                        $('.setLocation_btn').removeAttr('disabled');
                    }
                });

            });
        </script>
        <!-- autocomplete address js end -->
    @endif
@endif
</body>
</html>