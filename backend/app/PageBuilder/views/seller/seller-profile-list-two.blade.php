<!-- Tasker's area end -->
<section class="new_tasker_area padding-top-50 padding-bottom-100"
         data-padding-top="{{$padding_top}}" data-padding-bottom="{{$padding_bottom}}" style="background-color:{{$section_bg}}">
    <div class="container">
        <div class="new_sectionTitle">
            <h2 class="title">{{ $section_title }}</h2>
            <p class="section-para">{{ $description }}</p>
        </div>
        <div class="row g-4 mt-4">
            @foreach ($seller_lists as $seller)
             <div class="col-lg-3 col-md-6">
                <div class="new_tasker__single radius-10">
                    <div class="new_tasker__single__flex">
                        <div class="new_tasker__single__thumb">
                          @php  $img_url = get_attachment_image_by_id($seller->image); @endphp
                            @if(isset($img_url))
                                {!! render_image_markup_by_attachment_id($seller->image) !!}
                            @else
                               <img src="{{ asset('assets/uploads/no-image.png') }}" alt="noImage">
                            @endif
                        </div>
                        <div class="new_tasker__single__contents">
                            <h4 class="new_tasker__single__title verified"><a @if(!empty($seller->username)) href="{{ route('about.seller.profile',$seller->username) }}" @endif >{{  $seller->name }}</a></h4>
                            <div class="author_tag__review radius-5 mt-2">
                                  @php
                                       $service_rating = \App\Review::where('seller_id', $seller->id)->where('type', 1)->avg('rating');
                                       $service_reviews = \App\Review::where('seller_id', $seller->id)->where('type', 1)->get();

                                       if ($review_or_order_wise_seller_show == 'seller_review'){
                                          $completed_order = \App\Order::where('seller_id', $seller->id)->where('status', 2)->count();
                                       }else{
                                          $completed_order = \App\Order::where('seller_id', $seller->id)->where('status', 2)->where('created_at', \Carbon\Carbon::now()->month)->count();
                                       }


                                 @endphp
                                @if($service_rating >=1)
                                    <a href="javascript:void(0)" class="author_tag__review__star"> {!! ratting_star(round($service_rating, 1)) !!} </a>
                                    <a href="javascript:void(0)" class="author_tag__review__para">  ({{ $service_reviews->count() }}) </a>
                                @endif
                            </div>
                            <a href="javascript:void(0)" class="new_tasker__single__order radius-5 mt-2"> {{ __('Order Completed:') }} {{ $completed_order }}  </a>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>