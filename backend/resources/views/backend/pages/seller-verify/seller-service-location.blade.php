@extends('backend.admin-master')
@section('site-title')
    {{__('Seller Service Location')}}
@endsection
@section('style')
    <style>
        .close{ border: none;  }
        .dashboard-switch-single{
            font-size: 20px;
        }
        .swal_delete_button{
            color: #da0000 !important;
        }
        /* Default styles for the input box */
        #pac-input {
            height: 3em;
            width:75%;
            margin-left: 140px;
            border: 1px solid;
            top: 4px;
            font-size: 16px;
        }

        /* Media query for screens smaller than 768px */
        @media (max-width: 1499px) {
            #pac-input {
                width: 100%;
                margin-left: 0;
            }
        }

        .notice-board{
            border-left: 5px solid #a9a9a9;
        }

    </style>
@endsection
@section('content')
    <div class="col-lg-12 col-ml-12 padding-bottom-30">
        <div class="row">
            <div class="col-lg-12">
                <div class="margin-top-40"></div>
                <x-msg.success/>
                <x-msg.error/>
            </div>
            <div class="col-lg-12">
                <!-- map section start-->
                <div class="dashboard_table__wrapper dashboard_border  padding-20 radius-10 bg-white">
                    <div class="dashboard_table__title__flex">
                        <h6 class="dashboard_table__title"> {{__('Seller Service Zone Settings')}} </h6>
                    </div>

                        <p class="text-info mx-3">{{__('Search seller service location, pick a location, and submit.')}}
                         <a href="https://drive.google.com/file/d/1BwDAjSLAeb4LaxzOkrdsgGO_Io2jM6S6/view?usp=sharing" target="_blank">
                        <strong class="text-warning">{{__('Video link')}}</strong></a></p>

                    <div class="row">
                        <!-- google map show -->
                        <div class="col-lg-8 mt-4">
                            <div class="card">
                                <div class="card-body">
                                    <!-- Start Map -->
                                    <div class="map-warper dark-support rounded overflow-hidden">
                                        <input id="pac-input" class="controls rounded"
                                               type="text" placeholder="{{ __('Search your Zone')}}"/>
                                        <div id="map_canvas" style="height: 480px"></div>
                                    </div>
                                    <!-- End Map -->
                                </div>
                            </div>
                        </div>

                        <!-- lat lon section start -->
                        <div class="col-lg-4">
                            <form action="{{route('admin.frontend.seller.service.location.add')}}" enctype="multipart/form-data" method="POST">
                                @csrf

                                <input type="hidden" name="seller_id" id="seller_id" value="{{$seller->id}}">

                                <div class="mb-30">
                                    <div class="form-group mt-3">
                                        <label for="seller_address" class="label_title"> {{ __('Seller Service Location') }} <span class="text-danger">*</span> </label>
                                        <input type="text" name="seller_address" id="seller_address" class="form-control" value="{{$seller->seller_address }}" placeholder="{{ __('Seller Service Location') }}" readonly >
                                    </div>
                                </div>

                                <div class="mb-30">
                                    <div class="form-group mt-3">
                                        <label for="latitude" class="label_title"> {{ __('Latitude') }} <span class="text-danger">*</span> </label>
                                        <input type="text" name="latitude" id="latitude" class="form-control" value="{{ $seller->latitude }}" placeholder="{{ __('Latitude') }}" readonly >
                                    </div>
                                </div>
                                <div class="mb-30">
                                    <div class="form-group mt-3">
                                        <label for="longitude" class="label_title"> {{ __('Longitude') }} <span class="text-danger">*</span> </label>
                                        <input type="text" name="longitude" id="longitude" class="form-control" value="{{ $seller->longitude }}" placeholder="{{ __('Longitude') }}" readonly >
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end mt-5">
                                    <button href="#" class="btn btn-success check_valid_address" type="submit" style="border: none">{{ __('submit')}}</button>
                                    <button href="#" class="btn btn-danger mx-3 clear_all_value" type="reset">{{ __('Clear')}}</button>
                                </div>

                            </form>
                        </div>
                        <!-- lat lon section end -->
                    </div>


                </div>
            </div>
        </div>
    </div>
@endsection
@section('script')
<!-- google api key  -->
<script src="https://maps.googleapis.com/maps/api/js?key={{get_static_option('service_google_map_api_key')}}&libraries=places&v=3.46.0"></script>
<script>
    function readURL(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function (e) {
                $('#viewer').attr('src', e.target.result);
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    $("#customFileEg1").change(function () {
        readURL(this);
    });

    $(document).ready(function () {
        function initAutocomplete() {
            var myLatLng = {
                lat: <?= $seller->latitude ?? 0 ?>,
                lng: <?= $seller->longitude ?? 0 ?>
            };

            const map = new google.maps.Map(document.getElementById("map_canvas"), {
                center: myLatLng,
                zoom: 13,
                mapTypeId: "roadmap",
            });

            var marker = new google.maps.Marker({
                position: myLatLng,
                map: map,
            });

            marker.setMap(map);
            var geocoder = new google.maps.Geocoder();

            // new start
            google.maps.event.addListener(map, 'click', function (mapsMouseEvent) {
                var coordinates = JSON.stringify(mapsMouseEvent.latLng.toJSON(), null, 2);
                var coordinates = JSON.parse(coordinates);
                var latlng = new google.maps.LatLng(coordinates['lat'], coordinates['lng']);
                marker.setPosition(latlng);
                map.panTo(latlng);
                document.getElementById('latitude').value = coordinates['lat'];
                document.getElementById('longitude').value = coordinates['lng'];

                // Perform reverse geocoding to get the address details
                geocoder.geocode({ 'location': latlng }, function (results, status) {
                    if (status === google.maps.GeocoderStatus.OK) {
                        if (results[0]) {
                            var countryName = '';
                            var cityName = '';

                            for (var i = 0; i < results[0].address_components.length; i++) {
                                var addressComponent = results[0].address_components[i];

                                if (addressComponent.types.includes('country')) {
                                    countryName = addressComponent.long_name;
                                }
                                if (addressComponent.types.includes('locality') || addressComponent.types.includes('postal_town')) {
                                    cityName = addressComponent.long_name;
                                }
                            }
                            // Update #seller_address element with the complete address
                            var final_address = cityName + ', ' + countryName;
                            $('#seller_address').val(final_address);
                        } else {
                            console.log('No results found');
                        }
                    } else {
                        console.log('Geocoder failed due to: ' + status);
                    }
                });

            });
            //// new end

            // Search box create
            const input = document.getElementById("pac-input");
            const searchBox = new google.maps.places.SearchBox(input);

            // // Set the Restrictions specified countries
            const restractCountrys = @json($country_code);
            // Restrict to cities
            const cityTypes = ['(cities)'];
            const options = {
                componentRestrictions: { country: restractCountrys },
                types: cityTypes
            };

            map.controls[google.maps.ControlPosition.TOP_CENTER].push(input, options);
            // Google map Search current view
            map.addListener("bounds_changed", () => {
                searchBox.setBounds(map.getBounds());
            });


            let markers = [];
            // info place
            searchBox.addListener("places_changed", () => {
                const places = searchBox.getPlaces();
                if (places.length == 0) { return; }
                // select old marker remove
                markers.forEach((marker) => {
                    marker.setMap(null);
                });
                markers = [];
                // icon, name, location each
                const bounds = new google.maps.LatLngBounds();
                places.forEach((place) => {
                    if (!place.geometry || !place.geometry.location) {
                        console.log("Returned place contains no geometry");
                        return;
                    }
                    var mrkr = new google.maps.Marker({
                        map,
                        title: place.name,
                        position: place.geometry.location,
                    });
                    google.maps.event.addListener(mrkr, "click", function (event) {

                        // for full address title start
                        var coordinates = JSON.stringify(event.latLng.toJSON(), null, 2);
                        var coordinates = JSON.parse(coordinates);
                        var latlng = new google.maps.LatLng(coordinates['lat'], coordinates['lng']);
                        marker.setPosition(latlng);
                        map.panTo(latlng);
                        // for full address title end

                        document.getElementById('latitude').value = this.position.lat();
                        document.getElementById('longitude').value = this.position.lng();

                        // for full address title start
                        // Perform reverse geocoding to get the address details
                        geocoder.geocode({ 'location': latlng }, function (results, status) {
                            if (status === google.maps.GeocoderStatus.OK) {
                                if (results[0]) {
                                    var countryName = '';
                                    var cityName = '';
                                    for (var i = 0; i < results[0].address_components.length; i++) {
                                        var addressComponent = results[0].address_components[i];

                                        if (addressComponent.types.includes('country')) {
                                            countryName = addressComponent.long_name;
                                        }
                                        if (addressComponent.types.includes('locality') || addressComponent.types.includes('postal_town')) {
                                            cityName = addressComponent.long_name;
                                        }
                                    }

                                    // Construct the final address based on availability of city and country
                                    var final_address = cityName && countryName ? cityName + ', ' + countryName : countryName;

                                    $('#seller_address').val(final_address);

                                } else {
                                    console.log('No results found');
                                }
                            } else {
                                console.log('Geocoder failed due to: ' + status);
                            }
                        });
                        // for full address title end

                    });
                    markers.push(mrkr);
                    if (place.geometry.viewport) { bounds.union(place.geometry.viewport); } else { bounds.extend(place.geometry.location); }
                });
                map.fitBounds(bounds);
            });
        }
        initAutocomplete();
    });

    // clear all value
    $('.clear_all_value').click(function () {
        $('#name').val(null);
        $('#pac-input').val(null);
    });
</script>
@endsection

