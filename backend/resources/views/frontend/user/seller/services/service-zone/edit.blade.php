@extends('frontend.user.buyer.buyer-master')
@section('site-title')
{{__('Zone Edit')}}
@endsection
@section('style')
<link rel="stylesheet" href="{{asset('assets/backend/css/bootstrap-tagsinput.css')}}">
<link rel="stylesheet" href="{{asset('assets/common/css/flatpickr.min.css')}}">
<style>
    .close{ border: none;  }
    .dashboard-switch-single{
        font-size: 20px;
    }
    .swal_delete_button{
        color: #da0000 !important;
    }
</style>
@endsection
@section('content')
<x-frontend.seller-buyer-preloader/>
@include('frontend.user.seller.partials.sidebar-two')
<div class="dashboard__right">
    @include('frontend.user.buyer.header.buyer-header')
    <div class="dashboard__body">
        <div class="dashboard__inner">

            <!-- map section start-->
            <div class="dashboard_table__wrapper dashboard_border  padding-20 radius-10 bg-white">
                <div class="dashboard_table__title__flex">
                    <h6 class="dashboard_table__title"> {{__('Zone Update')}} </h6>
                    <div class="btn-wrapper" data-bs-toggle="modal" data-bs-target="#openTicket">  </div>
                </div>

                <!-- Instructions -->
              <div class="dashboard_table__main custom--table mt-4">
                  <x-msg.error/>
                  <x-msg.success/>

                 <div class="row">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <strong>{{ __('Instructions') }}</strong>
                                <div class="text-dark font-weight-bold">
                                    <strong>{{ __('Create zone by click on map and connect the dots together') }}</strong> <br>
                                    <strong><i class="las la-hand-pointer" style="font-size: 22px"></i> {{ __('Use this to drag map to find proper area') }}</strong> <br>
                                    <strong> <i class="las la-pen-fancy" style="font-size: 22px"></i> {{ __('Click this icon to start pin points in the map and connect them to draw a zone . Minimum 3 points required') }}</strong>
                                </div>
                            </div>
                            <div class="card-body">
                                <img class="dark-support" src="{{asset('assets/backend/images/map/instructions.gif')}}" alt=""/>
                            </div>
                        </div>
                    </div>

                     <div class="col-lg-6">
                        <div class="card">
                            <form action="{{route('seller.zone.update')}}" enctype="multipart/form-data" method="POST">
                                @csrf

                                <input type="hidden" name="id" value="{{$zone->id }}">
                            <div class="card-header">
                                <div class="">
                                    <label for="title" class="label_title">{{ __('Zone Name')}} <span class="text-danger">*</span> </label>
                                    <input type="text" class="form-control" id="floatingInput" name="name" placeholder="{{ __('Zone Name')}}" value="{{$zone->name}}" >
                                </div>
                            </div>

                            <div class="card-body">

                                <div class="form-group mb-3" style="display: none">
                                    <label class="input-label"  for="exampleFormControlInput1">{{ __('coordinates')}}
                                        <span class="input-label-secondary">{{ __('draw_your_zone_on_the_map')}}</span>
                                    </label>
                                    <textarea type="text" rows="8" name="coordinates" id="coordinates"  class="form-control" readonly></textarea>
                                </div>

                                <!-- Start Map -->
                                <div class="map-warper dark-support rounded overflow-hidden">
                                    <input id="pac-input" class="controls rounded"
                                           style="height: 3em;width:fit-content;"
                                           title="{{ __('search_your_location_here')}}" type="text"
                                           placeholder="{{ __('search_here')}}"/>
                                    <div id="map-canvas" style="height: 310px"></div>
                                </div>
                                <!-- End Map -->
                            </div>

                            <div class="d-flex justify-content-end gap-20 mt-30">
                                <button href="#" class="dashboard_table__title__btn btn-bg-2 radius-5 mx-3" type="reset" id="reset_btn">{{ __('reset')}}</button>
                                <button href="#" class="dashboard_table__title__btn btn-bg-1 radius-5" type="submit">{{ __('submit')}}</button>
                            </div>

                           </form>

                        </div>
                    </div>


                 </div>
              </div>
                <!-- End Instructions -->
            </div>
        </div>
    </div>
</div>
@endsection
@section('scripts')
 <!-- google api key  -->
<script  src="https://maps.googleapis.com/maps/api/js?key={{get_static_option('service_google_map_api_key')}}&libraries=drawing,places&v=3.45.8"></script>
<script>
    auto_grow();
    function auto_grow() {
        let element = document.getElementById("coordinates");
        element.style.height = "5px";
        element.style.height = (element.scrollHeight) + "px";
    }
</script>

 <script>
     var map; // Global declaration of the map
     var lat_longs = [];
     var drawingManager;
     var lastpolygon = null;
     var bounds = new google.maps.LatLngBounds();
     var polygons = [];

     function resetMap(controlDiv) {
         // Set CSS for the control border.
         const controlUI = document.createElement("div");
         controlUI.style.backgroundColor = "#fff";
         controlUI.style.border = "2px solid #fff";
         controlUI.style.borderRadius = "3px";
         controlUI.style.boxShadow = "0 2px 6px rgba(0,0,0,.3)";
         controlUI.style.cursor = "pointer";
         controlUI.style.marginTop = "8px";
         controlUI.style.marginBottom = "22px";
         controlUI.style.textAlign = "center";
         controlUI.title = "Reset map";
         controlDiv.appendChild(controlUI);
         // Set CSS for the control interior.
         const controlText = document.createElement("div");
         controlText.style.color = "rgb(25,25,25)";
         controlText.style.fontFamily = "Roboto,Arial,sans-serif";
         controlText.style.fontSize = "10px";
         controlText.style.lineHeight = "16px";
         controlText.style.paddingLeft = "2px";
         controlText.style.paddingRight = "2px";
         controlText.innerHTML = "X";
         controlUI.appendChild(controlText);
         // Setup the click event listeners: simply set the map to Chicago.
         controlUI.addEventListener("click", () => {
             lastpolygon.setMap(null);
             $('#coordinates').val('');

         });
     }



     function initialize() {
         var myLatlng = new google.maps.LatLng('{{$center_lat}}', '{{$center_lng}}');
         var myOptions = {
             zoom: 13,
             center: myLatlng,
             mapTypeId: google.maps.MapTypeId.ROADMAP
         };
         map = new google.maps.Map(document.getElementById("map-canvas"), myOptions);
         const polygonCoords = [
           @php $coordinates = json_decode($zone->coordinates->toJson())->coordinates; @endphp
                 @foreach(current($coordinates) as $coords){
                     lat: {{$coords[0]}}, lng: {{$coords[1]}}
                   },
             @endforeach
         ];


         var zonePolygon = new google.maps.Polygon({
             paths: polygonCoords,
             strokeColor: "#050df2",
             strokeOpacity: 0.8,
             strokeWeight: 2,
             fillOpacity: 0,
         });


         zonePolygon.setMap(map);

         zonePolygon.getPaths().forEach(function (path) {
             path.forEach(function (latlng) {
                 bounds.extend(latlng);
                 map.fitBounds(bounds);
             });
         });


         drawingManager = new google.maps.drawing.DrawingManager({
             drawingMode: google.maps.drawing.OverlayType.POLYGON,
             drawingControl: true,
             drawingControlOptions: {
                 position: google.maps.ControlPosition.TOP_CENTER,
                 drawingModes: [google.maps.drawing.OverlayType.POLYGON]
             },
             polygonOptions: {
                 editable: true
             }
         });
         drawingManager.setMap(map);

         google.maps.event.addListener(drawingManager, "overlaycomplete", function (event) {
             var newShape = event.overlay;
             newShape.type = event.type;
         });

         google.maps.event.addListener(drawingManager, "overlaycomplete", function (event) {
             if (lastpolygon) {
                 lastpolygon.setMap(null);
             }
             $('#coordinates').val(event.overlay.getPath().getArray());
             lastpolygon = event.overlay;
             auto_grow();
         });
         const resetDiv = document.createElement("div");
         resetMap(resetDiv, lastpolygon);
         map.controls[google.maps.ControlPosition.TOP_CENTER].push(resetDiv);

         // Create the search box and link it to the UI element.
         const input = document.getElementById("pac-input");
         const searchBox = new google.maps.places.SearchBox(input);
         map.controls[google.maps.ControlPosition.TOP_CENTER].push(input);
         // Bias the SearchBox results towards current map's viewport.
         map.addListener("bounds_changed", () => {
             searchBox.setBounds(map.getBounds());
         });
         let markers = [];
         // Listen for the event fired when the user selects a prediction and retrieve
         // more details for that place.
         searchBox.addListener("places_changed", () => {
             const places = searchBox.getPlaces();

             if (places.length == 0) {
                 return;
             }
             // Clear out the old markers.
             markers.forEach((marker) => {
                 marker.setMap(null);
             });
             markers = [];
             // For each place, get the icon, name and location.
             const bounds = new google.maps.LatLngBounds();
             places.forEach((place) => {
                 if (!place.geometry || !place.geometry.location) {
                     console.log("Returned place contains no geometry");
                     return;
                 }
                 const icon = {
                     url: place.icon,
                     size: new google.maps.Size(71, 71),
                     origin: new google.maps.Point(0, 0),
                     anchor: new google.maps.Point(17, 34),
                     scaledSize: new google.maps.Size(25, 25),
                 };
                 // Create a marker for each place.
                 markers.push(
                     new google.maps.Marker({
                         map,
                         icon,
                         title: place.name,
                         position: place.geometry.location,
                     })
                 );

                 if (place.geometry.viewport) {
                     // Only geocodes have viewport.
                     bounds.union(place.geometry.viewport);
                 } else {
                     bounds.extend(place.geometry.location);
                 }
             });
             map.fitBounds(bounds);
         });
     }


     google.maps.event.addDomListener(window, 'load', initialize);

     function set_all_zones() {
         $.get({
             url: '{{route('seller.get.active.zone',[$zone->id])}}',
             dataType: 'json',
             success: function (data) {

                 console.log(data);
                 for (var i = 0; i < data.length; i++) {
                     polygons.push(new google.maps.Polygon({
                         paths: data[i],
                         strokeColor: "#FF0000",
                         strokeOpacity: 0.8,
                         strokeWeight: 2,
                         fillColor: "#FF0000",
                         fillOpacity: 0.1,
                     }));
                     polygons[i].setMap(map);
                 }

             },
         });
     }

     $(document).on('ready', function () {
         set_all_zones();
     });

     $('#reset_btn').click(function () {
         $('#name').val(null);

         lastpolygon.setMap(null);
         $('#coordinates').val(null);
     })
 </script>
@endsection