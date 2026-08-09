@section('style')
    <style>
            /* Filter container styles */
        .filter-container {
            margin: 0px 0;
            padding: 5px;
            background-color: #ffffff;
            border: 1px solid #ffffff;
            border-radius: 5px;
        }

        .name_wise_filter {
            height: 62px;
            padding: 10px;
        }

        /* Filter label styles */
        .filter-container label {
            font-weight: bold;
            margin-right: 10px;
        }

        /* Filter input styles */
        .filter-container input,
        .filter-container select {
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-right: 10px;
        }
        .filter-container input, .filter-container select {
            border: 1px solid #fff;
        }
            .seller_not_found {
                align-items: center;
                text-align: center;
                display: block;
                font-size: 18px;
                color: red;
                font-weight: 600;
            }
    </style>
@endsection

<div class="banner-inner-area section-bg-2" data-padding-top="{{$padding_top}}" data-padding-bottom="{{$padding_bottom}}" style="background-color:{{$section_bg}}">
    <div class="container container-two">
        <div class="row">
            <div class="col-lg-12">
                <div class="section_start mb-5 text-center">
                    <h3 class="title">{{$section_title}}</h3>
                    <span class="title">{{$description}}</span>
                </div>
            </div>
        </div>

        <div class="row">
            <form method="get" action="{{url()->current()}}" id="search_seller_list_form" class="d-flex gap-4 text-center justify-content-center">
                <div class="col-lg-3">
                    <div class="filter-container text-center name_wise_filter">
                        <input name="q" value="{{request()->get('q')}}" id="search_by_seller_query" type="text" class="w-100" placeholder="{{ __('Filter by name') }}">
                    </div>
                </div>
                <div class="col-lg-2">
                    <div class="filter-container text-center">
                    <select id="search_by_country_seller" name="country_id">
                        <option value="">{{ __('Filter by Location:') }}</option>
                        @foreach ($countries as $country) {
                          @php
                           $selected = !empty(request()->get('country_id')) && request()->get('country_id') ==  $country->id ? 'selected' : '';
                          @endphp
                        <option {{$selected}} value="{{$country->id}}">{{$country->country}}</option>
                        @endforeach
                    </select>
                    </div>
                </div>

            </form>
        </div>

        <div class="row align-items-center mt-3">
            @foreach ($seller_lists as $seller)
                @php
                   $seller_since = \App\User::select('created_at')->where('id', $seller->id)->where('user_status', 1)->first();
                   $completed_order = \App\Order::where('seller_id', $seller->id)->where('status', 2)->count() ?? ' ';
                   $seller_rating = \App\Review::where('seller_id', $seller->id)->where('type', 1)->avg('rating');
                   $seller_rating_percentage_value = ceil($seller_rating * 20) ?? ' ';
                   $service_rating = \App\Review::where('seller_id', $seller->id)->where('type', 1)->avg('rating');
                   $service_reviews = \App\Review::where('seller_id', $seller->id)->where('type', 1)->get();
                @endphp

                <div class="col-lg-2 col-md-6 col-sm-6 col-xl-2 col-xxl-2">
                <div class="single_seller_profile">
                    <div class="thumb" {!!  render_background_image_markup_by_attachment_id($seller->image) !!}></div>

                    <div class="content_area_wrap">
                        <h4 class="title">
                            <a href="{{route('about.seller.profile',$seller->username)}}">{{ $seller->name }}</a>
                            @if(optional($seller->sellerVerify)->status==1)
                            <div data-toggle="tooltip">
                                <span class="seller-verified"> <i class="las la-check"></i> </span>
                            </div>
                            @endif
                        </h4>
                        <div class="seller_location">
                            <strong> <i class="las la-map-marker fs-5 font"></i> <span>{{ sellerProfileLocation($seller) }}</span> </strong>
                        </div>

                        @if($service_rating >=1)
                            <div class="profiles-review">
                               <span class="reviews">
                                   <b>{!! ratting_star(round($service_rating, 1)) !!}</b>
                                   {!! $service_reviews->count() !!}
                               </span>
                            </div>
                        @endif
                        <span class="order_completation">{{$completed_order}} {{__('Order Completed')}}</span>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        @if($seller_lists->count() > 0)
            <div class="col-lg-12">
                <div class="blog-pagination margin-top-55">
                    <div class="custom-pagination mt-4 mt-lg-5">
                        {!! $seller_lists->links() !!}
                    </div>
                </div>
            </div>
        @else
            <div class="row align-items-center mt-3">
                <div class="col-lg-12">
                        <span class="seller_not_found">{{__('Seller not found')}}</span>
                </div>
            </div>
        @endif

    </div>
</div>
@section('scripts')
    <script>
        (function($){
            "use strict";

            $(document).ready(function(){

                $(document).on('change','#search_by_country_seller',function(e){
                    e.preventDefault();
                    $('#search_seller_list_form').trigger('submit');
                });

                // Job search by text
                var oldSearchQ = '';
                $(document).on('keyup','#search_by_seller_query',function(e){
                    e.preventDefault();
                    let qVal = $(this).val().trim();

                    if(oldSearchQ !== qVal){
                        setTimeout(function (){
                            oldSearchQ = qVal.trim();
                            if(qVal.length > 2){
                                $('#search_seller_list_form').trigger('submit');
                            }
                        },2000);
                    }
                });

            });
        })(jQuery);
    </script>
@endsection