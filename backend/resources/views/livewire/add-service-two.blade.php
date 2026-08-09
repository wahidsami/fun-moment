 <form enctype="multipart/form-data">
     <div class="mt-4">
         @if (session()->has('message'))
             <div class="alert alert-danger">
                 {{ session('message') }}
             </div>
         @endif
     </div>

     <div class="mt-3">
         <x-error-message></x-error-message>
     </div>

     <input type="hidden" name="current_tab" wire:model="current_tab">
        <input type="hidden" name="is_service_all_cities"  id="is_service_all_cities_id">
        <div class="add-service-wrapper mt-4">
            <!--Nav Bar Tabs markup start -->
            <div wire:ignore class="nav nav-pills" id="add-service-tab"
                 role="tablist" aria-orientation="vertical">
                <a class="nav-link @if($current_tab === "service-info-tab") active @endif  stepIndicator" id="service-info-tab"
                   data-bs-toggle="pill" href="#service-info" role="tab"
                   aria-controls="service-info"
                   aria-selected="true"><span class="nav-link-number">{{ __('1') }}</span>{{__('Service Info')}}</a>
                <a class="nav-link @if($current_tab === "service-category-tab") active @endif  stepIndicator" id="service-category-tab"
                   data-bs-toggle="pill" href="#service-category" role="tab"
                   aria-controls="service-category"
                   aria-selected="true"><span class="nav-link-number">{{ __('2') }}</span>{{__('Service Category')}}</a>
                <a class="nav-link @if($current_tab === "service-attribute-tab") active @endif stepIndicator" id="service-attribute-tab" data-bs-toggle="pill"
                   href="#service-attribute" role="tab"
                   aria-controls="service-attribute"
                   aria-selected="false"><span class="nav-link-number">{{ __('3') }}</span>{{__('Service Attributes')}}</a>
                <a class="nav-link  @if($current_tab === "services-meta-tab") active @endif  stepIndicator" id="services-meta-tab" data-bs-toggle="pill"
                   href="#services-meta" role="tab"
                   aria-controls="services-meta"
                   aria-selected="false"><span class="nav-link-number">{{ __('4') }}</span>{{__('Meta Section')}}</a>
                <a class="nav-link @if($current_tab === "service-media-uploads-tab") active @endif  stepIndicator" id="service-media-uploads-tab" data-bs-toggle="pill"
                   href="#service-media-uploads" role="tab"
                   aria-controls="service-media-uploads"
                   aria-selected="false"><span class="nav-link-number">{{ __('5') }}</span>{{__('Media Uploads')}}</a>
                <a class="nav-link @if($current_tab === "service-set-availability-tab") active @endif stepIndicator" id="service-set-availability-tab" data-bs-toggle="pill"
                   href="#service-set-availability" role="tab"
                   aria-controls="service-set-availability"
                   aria-selected="false"><span class="nav-link-number">{{ __('6') }}</span>{{__('Set Availability')}}</a>
            </div>
            <!--Nav Bar Tabs markup end -->
           
            <div  class="add-service-content-wrapper mt-4">
                <div class="tab-content add-service-content" id="add-service-tabContent">

                    <!-- service Info start-->
                    <div  wire:ignore class="tab-pane fade @if($current_tab === "service-info-tab") show active @endif step" id="service-info" role="tabpanel" aria-labelledby="service-info-tab">
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="single-dashboard-input">
                                    <div class="row g-4">
                                        <div class="col-sm-6">
                                            <div class="single-info-input">
                                                <label for="title" class="info-title"> {{__('Service Title')}} <span class="text-danger">*</span> </label>
                                                <input class="form--control" name="title" id="title" type="text" placeholder="{{ __('Add title')}}" wire:model.defer="services.title">
                                            </div>
                                            <div class="single-dashboard-input mt-3">
                                                <div class="single-info-input permalink_label">
                                                    <label for="title" class="info-title text-dark"> {{__('Permalink')}} <span class="text-danger">*</span> </label>
                                                    <span id="slug_show" class="display-inline"></span>
                                                    <span id="slug_edit" class="display-inline"> </span>
                                                    <button class="btn btn-warning btn-sm slug_edit_button">  <i class="las la-edit"></i> </button>
                                                    <input class="form--control service_slug" name="slug" id="slug" style="display: none" type="text" wire:model.defer="services.slug">
                                                    <button class="btn btn-info btn-sm slug_update_button mt-2" style="display: none">{{  __('Update')}}</button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="single-info-input">
                                                <label for="video" class="info-title"> {{__('Service Video Url')}} </label>
                                                <input class="form--control" name="video" id="video" type="text" placeholder="{{__('youtube embed code')}}" wire:model.defer="services.video">
                                                <small class="text-danger">{{__('must be embed code from youtube.')}} <span class="text-dark">{{ __('Ex. <iframe width="560" height="315" src="https://www.youtube.com"></iframe>')  }}</span> </small>
                                            </div>
                                        </div>
                                        <div class="col-sm-12">
                                            <div class="single-info-input" wire:ignore>
                                                <label for="description" class="info-title"> {{__('Service Description')}} <span class="text-danger">*</span> <small class="text-info">{{__('minimum 150 characters')}} </small>  </label>
                                                <textarea wire:model.defer="services.description" id="summernote" class="form--control textarea--form textarea-input" cols="20" rows="2" placeholder="{{__('Type Description')}}"></textarea>

                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- service Info end-->

                    <!-- service Category start-->
                    <div wire:ignore  class="tab-pane fade @if($current_tab === "service-category-tab") show active @endif step" id="service-category" role="tabpanel" aria-labelledby="service-category-tab">
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="single-dashboard-input">
                                    <div class="row g-4">
                                        <div class="col-lg-4 col-sm-6">
                                            <div class="single-info-input">
                                                <label for="category" class="info-title"> {{__('Select Main Category')}} <span class="text-danger">*</span> </label>
                                                <select class="select2" name="category" id="category" wire:model.defer="category">
                                                    <option value="">{{__('Select Category')}}</option>
                                                    @foreach($categories as $cat)
                                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-sm-6">
                                            <div class="single-info-input sub_category_wrapper">
                                                <label for="subcategory" class="info-title"> {{__('Select Sub Category')}} </label>
                                                <select  name="subcategory" id="subcategory" class="subcategory" wire:model.defer="subcategory"></select>
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-sm-6">
                                            <div class="single-info-input child_category_wrapper">
                                                <label for="child_category" class="info-title"> {{__('Select Child Category')}} </label>
                                                <select  name="child_category" id="child_category" wire:model.defer="child_category"></select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- service Category end-->

                    <!-- service attribute start-->
                   <div wire:ignore.self class="tab-pane fade service_tab_hide_show
                     @if($current_tab === "service-attribute-tab") show active @endif step" id="service-attribute"   role="tabpanel" aria-labelledby="service-attribute-tab">
                        <div class="row g-4">
                            <div wire:ignore class="col-xxl-2 col-md-3 col-sm-4" >
                                <div class="service-attribute-wrapper">                                
                                    <!-- include service tabs markup start -->
                                    <div class="nav nav-pills flex-column" id="add-service-attribute-tab"
                                         role="tablist" aria-orientation="vertical">
                                        <a class="nav-link active" id="included-service-tab"
                                           data-bs-toggle="pill" href="#included-service" role="tab"
                                           aria-controls="included-service"
                                           aria-selected="true">
                                            <span class="nav-link-number">{{__('1')}}</span>
                                            {{__('Included Service')}}
                                        </a>
                                        <a class="nav-link" id="additional-service-tab" data-bs-toggle="pill"
                                           href="#additional-service" role="tab"
                                           aria-controls="additional-service"
                                           aria-selected="false">
                                            <span class="nav-link-number">{{__('2')}}</span>
                                            {{__('Additional Service')}}
                                        </a>
                                        <a class="nav-link" id="benefit-service-tab" data-bs-toggle="pill"
                                           href="#benefit-service" role="tab"
                                           aria-controls="benefit-service"
                                           aria-selected="false">
                                            <span class="nav-link-number">{{__('3')}}</span>
                                            {{__('Benefit')}}
                                        </a>
                                        <a class="nav-link faq_show_hide" id="faq-service-tab" data-bs-toggle="pill"
                                           href="#faq-service" role="tab"
                                           aria-controls="faq-service"
                                           aria-selected="false">
                                            <span class="nav-link-number">{{__('4')}}</span>
                                            {{__('Faq')}}
                                        </a>
                                    </div>
                                     <!-- include service tabs markup end -->

                                     <!--service price show start -->
                                    <div class="edit-service-wrappers mt-4">
                                        <div class="single-dashboard-input service-price-show-hide">
                                            <div class="single-info-input">
                                                <label class="info-title"> {{__('Service Price')}}</label>
                                                <input class="form--control" type="text" name="price" id="service_total_price" disabled>
                                            </div>
                                        </div>
                                    </div>
                                     <!--service price show end -->
                                </div>
                            </div>
                            <div class="col-xxl-10 col-md-9 col-sm-8">
                                <div class="row g-4">
                                    <div class="col-xl-12">
                                        <div class="tab-content add-service-attribute-content" id="add-service-attribute-tabContent">

                                            <div wire:ignore.self class="tab-pane fade active show" id="included-service" role="tabpanel" aria-labelledby="included-service-tab">
                                                <!-- Include Service start -->
                                                <div class="single-settings">
                                                    <div  wire:ignore class="dashboard_table__title__flex">
                                                        <div class="dashboard__headerContents__left">
                                                            <h4 class="input-title">  {{__('Whats Included This Package')}} </h4>
                                                            <div class="online_service mt-3">
                                                                <div class="dashboard-switch-single d-flex gap-1">
                                                                    <span class="text-info">{{__('Is Service Online')}}</span>
                                                                    <input  wire:click="openDiv" class="custom-switch is_service_online" id="is_service_online" type="checkbox" wire:model.defer="is_service_online" />
                                                                    <label class="switch-label mt-0" for="is_service_online"></label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                             
                                                    <!-- include markup start -->
                                                    <div>
                                                        <div class="add-input append-additional-includes mt-4">
                                                            <div wire:ignore class="single-dashboard-input what-include-element">
                                                                <div class="row g-4">
                                                                    <div class="col-lg-4 col-sm-6">
                                                                        <div class="single-info-input">
                                                                            <label class="label_title">{{ __('Title') }} <span class="text-danger">*</span> </label>
                                                                            <input class="form--control" wire:model.defer="include_service_title.0" type="text" placeholder="{{__('Service Title')}}">
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-lg-4 col-sm-6">
                                                                        <div class="single-info-input is_service_online_hide">
                                                                            <label class="label_title">{{ __('Unit Price') }} <span class="text-danger">*</span> </label>
                                                                            <input class="form--control include-price" wire:model.defer="include_service_price.0" type="number"  placeholder="{{__('Add Price')}}">
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-lg-4 col-sm-6">
                                                                        <div class="single-info-input is_service_online_hide">
                                                                            <label class="label_title">{{ __('Quantity') }}</label>
                                                                            <input class="form--control numeric-value" type="text" step="0.01" id="include_service_quantity.0" value="1" placeholder="{{__('Add Quantity')}}" readonly>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            @foreach($includes_services as $key => $value)
                                                                   @php
                                                                       $key = $key + 1;
                                                                   @endphp
                                                                <div wire:ignore.self>
                                                                    <div class="single-dashboard-input what-include-element mt-4">
                                                                        <div class="row align-items-center g-4">
                                                                            <div class="col-lg-4 col-sm-6">
                                                                                <div class="single-info-input">
                                                                                    <label class="label_title">{{ __('Title') }} <span class="text-danger">*</span> </label>
                                                                                    <input class="form--control" type="text" wire:model.defer="include_service_title.{{ $key }}" placeholder="{{__('Service title')}}">
                                                                                </div>
                                                                            </div>
                                                                            <div class="@if($online_offline_show_hide) d-none @endif col-lg-3 col-sm-6 is_service_online_hide">
                                                                                <div class="single-info-input">
                                                                                    <label class="label_title">{{ __('Unit Price') }} <span class="text-danger">*</span> </label>
                                                                                    <input class="form--control include-price" type="text" wire:model.defer="include_service_price.{{ $key }}" placeholder="{{__('Add Price')}}">
                                                                                </div>
                                                                            </div>
                                                                            <div class="@if($online_offline_show_hide) d-none @endif col-lg-3 col-sm-6 is_service_online_hide">
                                                                                <div class="single-info-input">
                                                                                    <label class="label_title">{{ __('Quantity') }}</label>
                                                                                    <input class="form--control numeric-value" value="1" type="text" placeholder="{{__('Add Quantity')}}" readonly>
                                                                                </div>

                                                                            </div>

                                                                            <div class=" col-lg-2 col-sm-6 is_service_online_hide">
                                                                                <button class="btn btn-danger remove-include mt-3" wire:click.prevent="remove({{$key}})"><i class="las la-times"></i></button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>

                                                        <div  wire:ignore.self class="btn-wrapper mt-3">
                                                            <a href="javascript:void(0)" class="btn-see-more style-02 color-3 hide_service_and_show" wire:click="addIncludeServices({{ $i_include }})"> {{__('Add More')}} </a>
                                                        </div>
                                                    </div>
                                                    <!-- include markup start -->
                                                </div>
                                                <!-- Include Service end -->

                                                <!-- Online Service start -->
                                                <div  wire:ignore class="single-settings day_review_show_hide">
                                                    <div class="single-dashboard-input mt-4">
                                                        <div class="row g-4">
                                                            <div class="col-lg-4 col-sm-6">
                                                                <div class="single-info-input">
                                                                    <label class="label_title">{{ __('Delivery Days') }} <span class="text-danger">*</span> </label>
                                                                    <input class="form--control" type="number"  wire:model.defer="online_service.delivery_days" name="delivery_days" placeholder="{{__('Delivery Days')}}">
                                                                </div>
                                                            </div>
                                                            <div class="col-lg-4 col-sm-6">
                                                                <div class="single-info-input">
                                                                    <label class="label_title">{{ __('Revisions') }}</label>
                                                                    <input class="form--control" type="number"  wire:model.defer="online_service.revision" name="revision" placeholder="{{__('Revision Times')}}">
                                                                </div>
                                                            </div>
                                                            <div class="col-lg-4 col-sm-6">
                                                                <div class="single-settings online_service_price_show_hide">
                                                                    <div class="single-dashboard-input">
                                                                        <div class="single-info-input">
                                                                            <label class="label_title">{{ __('Service Price') }} <span class="text-danger">*</span> </label>
                                                                            <input class="form--control" type="number" step="0.01" wire:model.defer="online_service.online_service_price" name="online_service_price" placeholder="{{__('Service price')}}">
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- Online Service end -->
                                            </div>

                                            <!-- Additional Service Start -->
                                            <div wire:ignore.self class="tab-pane fade" id="additional-service" role="tabpanel" aria-labelledby="additional-service-tab">
                                                <div class="single-settings mt-4">
                                                    <h4 class="input-title"> {{__('Add Additional Services')}} </h4>

                                                    <div class="append-additional-services mt-4">
                                                        <div class="single-dashboard-input additional-services">

                                                            <!--default additional service markup start -->
                                                            <div class="row g-4 mt-2" wire:ignore>
                                                                <div  class="col-xl-3 col-sm-6">
                                                                    <div class="single-info-input">
                                                                        <label class="label_title">{{ __('Title') }} <span class="text-danger">*</span> </label>
                                                                        <input class="form--control" type="text"  wire:model.defer="additional_service_title.0"  placeholder="{{__('Service title')}}">
                                                                    </div>
                                                                </div>
                                                                <div class="col-xl-2 col-sm-6">
                                                                    <div class="single-info-input">
                                                                        <label class="label_title">{{ __('Unit Price') }} <span class="text-danger">*</span> </label>
                                                                        <input class="form--control numeric-value" type="number"  wire:model.defer="additional_service_price.0"  placeholder="{{__('Add Price')}}">
                                                                    </div>
                                                                </div>
                                                                <div class="col-xl-2 col-sm-6">
                                                                    <div class="single-info-input">
                                                                        <label class="label_title">{{ __('Quantity') }}</label>
                                                                        <input class="form--control numeric-value" type="text" value="1" placeholder="{{__('Add Quantity')}}" readonly>
                                                                    </div>
                                                                </div>
                                                                <div class="col-xl-3 col-sm-6">
                                                                    <div class="single-info-input">
                                                                        <div class="form-group ">
                                                                            <div class="media-upload-btn-wrapper">
                                                                                <div class="img-wrap mb-0"></div>
                                                                                <input type="hidden" id="additional_service_image">
                                                                                <button data-value="0" type="button" class="btn btn-info media_upload_form_btn"
                                                                                        data-btntitle="{{__('Select Image')}}"
                                                                                        data-modaltitle="{{__('Upload Image')}}" data-bs-toggle="modal"
                                                                                        data-bs-target="#media_upload_modal">
                                                                                    {{__('Upload Image')}}
                                                                                </button>
                                                                                <small style="font-size: 10px">{{ __('image format: jpg,jpeg,png')}} <span> ({{ __('recommended size 78x78') }}) </span> </small>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!--default additional service markup end -->

                                                                @foreach($additional_service_inputs as $key => $value)
                                                                    @php
                                                                        $key = $key + 1;
                                                                    @endphp
                                                                    <div wire:ignore.self >
                                                                        <div class="row g-4 mt-2">
                                                                        <div  class="col-xl-3 col-sm-6">
                                                                            <div class="single-info-input">
                                                                                <label class="label_title">{{ __('Title') }} <span class="text-danger">*</span> </label>
                                                                                <input class="form--control" type="text"  wire:model.defer="additional_service_title.{{ $key }}"  placeholder="{{__('Service title')}}">
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-xl-2 col-sm-6">
                                                                            <div class="single-info-input">
                                                                                <label class="label_title">{{ __('Unit Price') }} <span class="text-danger">*</span> </label>
                                                                                <input class="form--control numeric-value" type="number"  wire:model.defer="additional_service_price.{{ $key }}"  placeholder="{{__('Add Price')}}">
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-xl-2 col-sm-6">
                                                                            <div class="single-info-input">
                                                                                <label class="label_title">{{ __('Quantity') }}</label>
                                                                                <input class="form--control numeric-value" type="text" value="1" placeholder="{{__('Add Quantity')}}" readonly>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-xl-3 col-sm-6">
                                                                            <div class="single-info-input">
                                                                                <div class="form-group ">
                                                                                    <div class="media-upload-btn-wrapper" wire:ignore>
                                                                                        <div class="img-wrap mb-0"></div>

                                                                                        <input type="hidden" id="additional_service_image">
                                                                                        <button data-value="{{ $key }}" type="button" class="btn btn-info media_upload_form_btn"
                                                                                                data-btntitle="{{__('Select Image')}}"
                                                                                                data-modaltitle="{{__('Upload Image')}}" data-bs-toggle="modal"
                                                                                                data-bs-target="#media_upload_modal">
                                                                                            {{__('Upload Image')}}
                                                                                        </button>
                                                                                        <small style="font-size: 10px">{{ __('image format: jpg,jpeg,png')}} <span> ({{ __('recommended size 78x78') }}) </span> </small>

                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-xl-2 col-sm-2" wire:ignore>
                                                                            <span class="btn btn-danger"  wire:click.prevent="removeAdditionalService({{$key}})"><i class="las la-times"></i></span>
                                                                        </div>
                                                                    </div>
                                                                  </div>
                                                                @endforeach
                                                        </div>
                                                    </div>

                                                    <div wire:ignore.self class="btn-wrapper mt-3">
                                                        <a href="javascript:void(0)" class="btn-see-more style-02 color-3 add-additional-services" wire:click.prevent="addAdditionalService({{$i_additional}})"> {{__('Add More')}} </a>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Additional Service end -->

                                            <!-- Service Benefit Start -->
                                            <div  wire:ignore.self class="tab-pane fade" id="benefit-service" role="tabpanel" aria-labelledby="benefit-service-tab">
                                                <div class="single-settings margin-top-40">
                                                    <h4 class="input-title"> {{__('Benefit Of This Package')}} </h4>
                                                    <div class="append-benifits">

                                                        <div class="single-dashboard-input benifits" wire:ignore>
                                                            <div class="single-info-input mt-3">
                                                                <input class="form--control" type="text" wire:model.defer="benifits.0"  placeholder="{{__('Type Here')}}">
                                                            </div>
                                                        </div>

                                                        @foreach($service_benefit_inputs as $key => $value)
                                                            @php
                                                                $key = $key + 1;
                                                            @endphp
                                                            <div class="single-dashboard-input benifits faq_show_hide" wire:ignore.self>
                                                                <div class="row align-items-center g-4">
                                                                    <div class="col-xl-10 col-sm-9">
                                                                        <div class="single-info-input mt-3">
                                                                            <input class="form--control" type="text" wire:model.defer="benifits.{{ $key }}" placeholder="{{__('Type Here')}}">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-xl-2 col-sm-3">
                                                                        <span class="btn btn-danger" wire:click.prevent="removeBenefit({{$key}})"><i class="las la-times"></i></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <div class="btn-wrapper mt-3">
                                                        <a href="javascript:void(0)" class="btn-see-more style-02 color-3" wire:click.prevent="addBenefit({{ $i_benefit }})"> {{__('Add More')}} </a>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Service Benefit end -->

                                            <!-- Service Faqs Start -->
                                            <div  wire:ignore.self class="tab-pane fade" id="faq-service" role="tabpanel" aria-labelledby="faq-service-tab">
                                                <div class="single-settings margin-top-40 faq_show_hide">
                                                    <h4 class="input-title"> {{__('Faqs')}} </h4>
                                                    <div class="append-faqs">

                                                        <div class="single-dashboard-input faqs">
                                                            <div class="single-info-input mt-3">
                                                                <label class="label_title">{{__('Title') }}</label>
                                                                <input class="form--control" type="text" wire:model.defer="faqs_title.0" placeholder="{{__('Faq Title')}}">
                                                            </div>
                                                            <div class="single-info-input mt-3">
                                                                <label class="label_title">{{__('Description') }}</label>
                                                                <textarea class="form--control textarea-input" wire:model.defer="faqs_description.0"  cols="20" rows="2" placeholder="{{__('Faq Description')}}"></textarea>
                                                            </div>
                                                        </div>

                                                        @foreach($faq_inputs as $key => $value)
                                                            @php
                                                                $key = $key + 1;
                                                            @endphp
                                                           <div class="row" wire:ignore.self>
                                                               <div class="col-xl-10">
                                                                   <div class="single-dashboard-input faqs">
                                                                       <div class="single-info-input mt-3">
                                                                           <label class="label_title">{{__('Title') }}</label>
                                                                           <input class="form--control" type="text" wire:model.defer="faqs_title.{{ $key }}" placeholder="{{__('Faq Title')}}">
                                                                       </div>
                                                                       <div class="single-info-input mt-3">
                                                                           <label class="label_title">{{__('Description') }}</label>
                                                                           <textarea class="form--control textarea-input" wire:model.defer="faqs_description.{{ $key }}" cols="20" rows="2" placeholder="{{__('Faq Description')}}"></textarea>
                                                                       </div>
                                                                   </div>
                                                               </div>
                                                               <div class="col-xl-2">
                                                                   <span class="btn btn-danger remove-faqs mt-3" wire:click.prevent="removeFaq({{$key}})"><i class="las la-times"></i></span>
                                                               </div>
                                                           </div>
                                                        @endforeach

                                                    </div>
                                                    <div class="btn-wrapper mt-3">
                                                        <a href="javascript:void(0)" class="btn-see-more style-02 color-3 add-faqs" wire:click.prevent="addFaq({{ $i_faq }})"> {{__('Add More')}} </a>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Service Faqs end -->
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- service attribute end-->

                    <!-- service Meta Section start-->
                    <div  wire:ignore  class="tab-pane fade @if($current_tab === "services-meta-tab") show active @endif step" id="services-meta" role="tabpanel" aria-labelledby="services-meta-tab">
                        <div class="card">
                            <div class="card-body meta">
                                <h5 class="header-title">{{__('Meta Section')}}</h5>
                                <div class="row g-4 mt-1">
                                    <div class="col-xxl-2 col-xl-3 col-sm-4">
                                        <div class="nav nav-pills flex-column" id="v-pills-tab"
                                             role="tablist" aria-orientation="vertical">
                                            <a class="nav-link active" id="v-pills-home-tab"
                                               data-bs-toggle="pill" href="#v-pills-home" role="tab"
                                               aria-controls="v-pills-home"
                                               aria-selected="true">
                                                <span class="nav-link-number">{{__('1')}}</span>
                                                {{__('Service Meta')}}</a>
                                            <a class="nav-link" id="v-pills-profile-tab" data-bs-toggle="pill"
                                               href="#v-pills-profile" role="tab"
                                               aria-controls="v-pills-profile"
                                               aria-selected="false">
                                                <span class="nav-link-number">{{__('2')}}</span>
                                                {{__('Facebook Meta')}}</a>
                                            <a class="nav-link" id="v-pills-messages-tab" data-bs-toggle="pill"
                                               href="#v-pills-messages" role="tab"
                                               aria-controls="v-pills-messages"
                                               aria-selected="false">
                                                <span class="nav-link-number">{{__('3')}}</span>
                                                {{__('Twitter Meta')}}</a>
                                        </div>
                                    </div>
                                    <div class="col-xxl-10 col-xl-9 col-sm-8">
                                        <div class="tab-content meta-content" id="v-pills-tabContent">

                                            <div class="tab-pane fade show active" id="v-pills-home"
                                                 role="tabpanel" aria-labelledby="v-pills-home-tab">
                                                <div class="row g-4">
                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label class="label_title" for="title">{{__('Meta Title')}}</label>
                                                            <input type="text" class="form-control" name="meta_title" id="meta_title" placeholder="{{__('Title')}}" wire:model.defer="meta.meta_title">
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-12">
                                                        <div class="form-group" wire:ignore>
                                                            <label class="label_title" for="slug">{{__('Meta Tags')}}</label>
                                                            <input type="text" class="form-control" name="meta_tags" placeholder="Slug" data-role="tagsinput" id="meta_tags" wire:model.defer="meta.meta_tags">
                                                        </div>

                                                    </div>
                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label class="label_title" for="title">{{__('Meta Description')}}</label>
                                                            <textarea name="meta_description"  class="form-control textarea-input" cols="20" rows="2" wire:model.defer="meta.meta_description"
                                                            placeholder="{{ __('Description') }}"></textarea>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>

                                            <div class="tab-pane fade" id="v-pills-profile" role="tabpanel"
                                                 aria-labelledby="v-pills-profile-tab">
                                                <div class="row g-4">
                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label class="label_title" for="title">{{__('Facebook Meta Title')}}</label>
                                                            <input type="text" class="form-control" placeholder="{{__('Title')}}"  wire:model.defer="meta.facebook_meta_tags" name="facebook_meta_tags">
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label class="label_title" for="title">{{__('Facebook Meta Description')}}</label>
                                                            <textarea name="facebook_meta_description" class="form-control textarea-input" cols="20"
                                                                      rows="2" wire:model.defer="meta.facebook_meta_description" placeholder="{{ __('Description') }}"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label class="label_title" for="image">{{__('Facebook Meta Image')}}</label>
                                                            <div class="media-upload-btn-wrapper">
                                                                <div class="img-wrap"></div>
                                                                <input type="hidden"  name="facebook_meta_image" wire:model.defer="meta.facebook_meta_image" id="facebook_meta_image">
                                                                <button type="button"
                                                                        class="btn btn-info media_upload_form_btn"
                                                                        data-btntitle="{{__('Select Image')}}"
                                                                        data-modaltitle="{{__('Upload Image')}}"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#media_upload_modal">
                                                                    {{__('Upload Image')}}
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="tab-pane fade" id="v-pills-messages" role="tabpanel"
                                                 aria-labelledby="v-pills-messages-tab">
                                                <div class="row g-4">
                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label class="label_title" for="title">{{__('Twitter Meta Title')}}</label>
                                                            <input type="text" class="form-control" placeholder="{{__('Title')}}"
                                                                   name="twitter_meta_tags" wire:model.defer="meta.twitter_meta_tags" >
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label class="label_title" for="title">{{__('Twitter Meta Description')}}</label>
                                                            <textarea name="twitter_meta_description" class="form-control textarea-input" cols="20"
                                                                      rows="2" wire:model.defer="meta.twitter_meta_description" placeholder="{{ __('Description') }}"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label class="label_title" for="image">{{__('Twitter Meta Image')}}</label>
                                                            <div class="media-upload-btn-wrapper">
                                                                <div class="img-wrap"></div>
                                                                <input type="hidden" name="twitter_meta_image" wire:model.defer="meta.twitter_meta_image" id="twitter_meta_image">
                                                                <button type="button"
                                                                        class="btn btn-info media_upload_form_btn"
                                                                        data-btntitle="{{__('Select Image')}}"
                                                                        data-modaltitle="{{__('Upload Image')}}"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#media_upload_modal">
                                                                    {{__('Upload Image')}}
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    <!-- service Meta Section end-->

                    <!-- service Media Uploads start-->
                    <div   wire:ignore class="tab-pane fade @if($current_tab === "service-media-uploads-tab") show active @endif step" id="service-media-uploads" role="tabpanel" aria-labelledby="service-media-uploads-tab">
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="single-dashboard-input">
                                    <div class="single-dashboard-input">
                                        <div class="single-info-input">
                                            <div class="form-group ">
                                                <div class="media-upload-btn-wrapper">
                                                    <div class="img-wrap"></div>
                                                    <input type="hidden" name="image" wire:model.defer="services.image" id="service_image">
                                                    <button type="button" class="btn btn-info media_upload_form_btn"
                                                            data-btntitle="{{__('Select Image')}}"
                                                            data-modaltitle="{{__('Upload Image')}}" data-bs-toggle="modal"
                                                            data-bs-target="#media_upload_modal">
                                                        {{__('Upload Main Image')}}
                                                    </button>
                                                    <small>{{ __('image format: jpg,jpeg,png')}}</small> <br>
                                                    <small>{{ __('recommended size 1920x1280') }}</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group ">
                                        <div class="media-upload-btn-wrapper">
                                            <div class="img-wrap"></div>
                                            <input type="hidden" wire:model.defer="image_gallery" id="image_gallery">
                                            <button type="button" class="btn btn-info media_upload_form_btn"
                                                    data-btntitle="{{__('Select Image')}}"
                                                    data-modaltitle="{{__('Upload Image')}}"
                                                    data-bs-toggle="modal"
                                                    data-mulitple="true"
                                                    data-bs-target="#media_upload_modal">
                                                {{__('Upload Gallery Image')}}
                                            </button>
                                            <small>{{ __('image format: jpg,jpeg,png')}}</small> <br>
                                            <small>{{ __('recommended size 1920x1280') }}</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- service Media Uploads end-->

                    <!-- service Set Availability start-->
                    <div   wire:ignore class="tab-pane fade @if($current_tab === "service-set-availability-tab") show active @endif step" id="service-set-availability" role="tabpanel" aria-labelledby="service-set-availability-tab">
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="row g-4">
                                    <div class="col-lg-12">
                                        <div class="available-all-city-area">
                                            <span class="text-info">{{__('Is Available All Cities and Area')}}</span>
                                            <div class="dashboard-switch-single d-flex">
                                                <input class="hide_show_check_box_new custom-switch is_service_all_cities" id="is_service_all_cities" type="checkbox" wire:model.defer="services.is_service_all_cities"/>
                                                <label class="switch-label mt-2" for="is_service_all_cities"></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- service Set Availability end-->
                </div>
            </div>
        </div>


        <!-- start previous / next buttons -->
        <div  wire:ignore class="col-lg-12">
            <div class="single-settings">
                <div class="btn-wrapper d-flex gap-2 mt-4">
                    <button class="dashboard_table__title__btn btn-outline-1 radius-5" id="prevBtn" type="button">{{__('Previous')}}</button>
                    <button class="dashboard_table__title__btn btn-bg-1 radius-5" style="border: none" id="nextBtn" type="button">{{__('Next')}}</button>

                    <button class="dashboard_table__title__btn btn-bg-1 radius-5"
                            style="border: none; @if($hideSubmitButton) display: none @endif"
                            id="submitBtn" type="submit"
                            wire:loading.attr="disabled"
                            wire:loading.class="btn-disabled"
                            wire:click.prevent="serviceStore">{{__('Submit')}}
                    </button>
                </div>
            </div>
        </div>
    </form>

<!-- for select -->
 @push('scripts')
     <script>
         $(document).ready(function() {
             // online service hide and show
             $(".add-what-includes").on('click',function(){
                 if ($("#is_service_online").is(':checked')){
                     $('.is_service_online_hide').hide();
                 }
             });

             $(document).on('click',"#add-service-tab .nav-link",function(e){
                 let el = $(this);               
                $('input[name="current_tab"]').val(el.attr('id'));
             });

             $(document).on('click', "#service-attribute-tab" ,function(e){
                 $(".service_tab_hide_show").removeClass('d-none');
             });

             $('#category').on('change', function (e) {
                 @this.set('category', $(this).val());
             });

             $('#subcategory').on('change', function (e) {
                 @this.set('subcategory', $(this).val());
             });

             $('#child_category').on('change', function (e) {
                 @this.set('child_category', $(this).val());
             });

             // service slug
             $('#title').on('keyup input', function (e) {
                 @this.set('services.slug', $(this).val());
             });

             // livewire custom summernote design
             $('#summernote').summernote({
                 placeholder: '',
                 tabsize: 2,
                 focus: true,
                 toolbar: [
                     ['font', ['bold', 'italic', 'underline', 'clear']],
                     ['color', ['color']],
                     ['para', ['ul', 'ol', 'paragraph']],
                     ['height', ['height']],
                     ['table', ['table']],
                     ['insert', ['link', 'picture', 'hr']],
                     ['view', ['fullscreen', 'codeview']],
                     ['help', ['help']]
                 ],
                 callbacks: {
                     onKeyup: function(e) {
                         @this.set('services.description', $(this).val());
                     },
                 }
             });

             // meta tags
             $('#meta_tags').on('change', function (e) {
                 @this.set('meta.meta_tags', $(this).val());
             });

              // facebook mata image
                 $(document).on("click",".media_upload_modal_submit_btn", function() {
                     @this.set('meta.facebook_meta_image', $('#facebook_meta_image').val());
                  });

                // twitter mata image
                 $(document).on("click",".media_upload_modal_submit_btn", function() {
                     @this.set('meta.twitter_meta_image', $('#twitter_meta_image').val());
                  });

                 // service image
                 $(document).on("click",".media_upload_modal_submit_btn", function() {
                     @this.set('services.image', $('#service_image').val());
                  });

                 // service image gallery
                 $(document).on("click",".media_upload_modal_submit_btn", function() {
                     @this.set('services.image_gallery', $('#image_gallery').val());
                  });

            //  service additional image add
             $(document).on("click",".media_upload_modal_submit_btn", function() {
                 let additionalServicesButton = $(".additional-services button.media_upload_form_btn");
                 additionalServicesButton.each(function (){
                     let name = 'additional_service_image.' + $(this).attr("data-value");
                     let value = $(this).attr("data-image-ids");
                     @this.set(name , value);
                 });
             });

             // is service online
             $('.day_review_show_hide').hide()
             $('.faq_show_hide').hide()
             $('.online_service_price_show_hide').hide()

             $("#is_service_online").on('change', function() {
                 if ($("#is_service_online").is(':checked')){
                     $('.faq_show_hide').show()
                     $('.service-price-show-hide').hide()
                 }else {
                     $('.faq_show_hide').hide()
                     $('.service-price-show-hide').show()
                 }
             });


         });
     </script>
 @endpush
