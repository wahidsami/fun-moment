<div class="inner-wrap-contents">
    <p class="wrap-para">{{ __('Hello, Order Created By:') }} {{ optional($order_details->buyer)->name }} <br>
        {{ __('Order has been created successfully at:') .optional($order_details->created_at)->toFormattedDateString().','. ucwords(str_replace("_", " ", $order_details->payment_gateway)) }}
    </p>
    <h4 class="earning-order-title">{{ __('Your Order ID') }} #{{ $order_details->id }}<br>
        {{ __('Total Amount') }} {{ float_amount_with_currency_symbol($order_details->total) }}<br>
        {{ __('Tax Amount') }} {{ float_amount_with_currency_symbol($order_details->tax) }} <br> <br>
        @if($order_details->transaction_id !='')
            {{ __('Your Transaction Id') }} {{ $order_details->transaction_id }} <br>
        @endif
    </h4>


</div>


@php $package_fee =0;
    $order_includes = App\OrderInclude::where('order_id',$order_details->id)->get()
@endphp

@if($order_includes->count()>=1)
    <h3 class="earning-title">{{ __('Order Include Details') }}</h3>
    <table class="table table-bordered table-responsive" style="margin: 0 auto; border: 1px solid #ddd; border-collapse: collapse; width: 100%; margin-bottom: 30px;overflow-x: auto;">
        <thead>
        <tr class="table-row">
            <th style="background-color: #111d5c; color: #fff; padding: 10px; text-align: left;">{{ __('Title') }}</th>
            @if($order_details->is_order_online !=1)
                <th style="background-color: #111d5c; color: #fff; padding: 10px; text-align: left;">{{ __('Unit Price') }}</th>
                <th style="background-color: #111d5c; color: #fff; padding: 10px; text-align: left;">{{ __('Quantity') }}</th>
                <th style="background-color: #111d5c; color: #fff; padding: 10px; text-align: left;" class="table-heading">{{ __('Total') }}</th>
            @endif
        </tr>
        </thead>
        <tbody>
        @php $package_fee =0;
            $order_includes = App\OrderInclude::where('order_id',$order_details->id)->get()
        @endphp
        @foreach($order_includes as $include)
            <tr class="table-row">
                <td style="border: 1px solid #ddd; padding: 8px; text-align:left;">{{ $include->title }}</td>
                @if($order_details->is_order_online !=1)
                    <td style="border: 1px solid #ddd; padding: 8px;text-align:left;">{{ float_amount_with_currency_symbol($include->price) }}</td>
                    <td style="border: 1px solid #ddd; padding: 8px;text-align:left;">{{ $include->quantity }}</td>
                    <td style="border: 1px solid #ddd; padding: 8px;text-align:left;">{{ float_amount_with_currency_symbol($include->price * $include->quantity) }}</td>
                    @php $package_fee += $include->price * $include->quantity @endphp
                @endif
            </tr>
        @endforeach
        <tr class="table-row">
            @if($order_details->is_order_online !=1)
                <td colspan="3" style="padding: 10px"><strong>{{ get_static_option('service_package_fee_title') ??  __('Package Fee') }}</strong></td>
                <td style="padding: 10px"><strong>{{ float_amount_with_currency_symbol($package_fee) }}</strong></td>
            @else
                <td style="padding: 10px; text-align:left;"><strong>{{ __('Package Fee') . float_amount_with_currency_symbol($order_details->package_fee) }}</strong></td>
            @endif
        </tr>
        </tbody>
    </table>
@endif


@php $extra_service =0;
    $order_additionals = App\OrderAdditional::where('order_id',$order_details->id)->get()
@endphp

@if($order_additionals->count()>=1)
    <h3 class="earning-title">{{ get_static_option('service_extra_title') ?? __('Order Additional Details') }}</h3>
    <table class="table table-bordered" style="margin: 0 auto; border: 1px solid #ddd; border-collapse: collapse; width: 100%; margin-bottom: 30px;">
        <thead>
        <tr>
            <th style="background-color: #111d5c; color: #fff; padding: 10px; text-align: left;">{{ __('Title') }}</th>
            <th style="background-color: #111d5c; color: #fff; padding: 10px; text-align: left;">{{ __('Unit Price') }}</th>
            <th style="background-color: #111d5c; color: #fff; padding: 10px; text-align: left;">{{ __('Quantity') }}</th>
            <th style="background-color: #111d5c; color: #fff; padding: 10px; text-align: left;">{{ __('Total') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($order_additionals as $additional)
            <tr>
                <td style="border: 1px solid #ddd; padding: 8px;">{{ $additional->title }}</td>
                <td style="border: 1px solid #ddd; padding: 8px;">{{ float_amount_with_currency_symbol($additional->price) }}</td>
                <td style="border: 1px solid #ddd; padding: 8px;">{{ $additional->quantity }}</td>
                <td style="border: 1px solid #ddd; padding: 8px;">{{ float_amount_with_currency_symbol($additional->price * $additional->quantity) }}</td>
                @php $extra_service += $additional->price * $additional->quantity @endphp
            </tr>
        @endforeach
        <tr>
            <td colspan="3" style="padding: 10px"><strong>{{ __('Additional Service Fee') }}</strong></td>
            <td style="padding: 10px"><strong>{{ float_amount_with_currency_symbol($extra_service) }}</strong></td>
        </tr>
        </tbody>
    </table>
@endif

@if($order_details->coupon_code !='')
    <h3 class="earning-title">{{ __('Coupon Details') }}</h3>
    <table class="table table-bordered" style="margin: 0 auto; border: 1px solid #ddd; border-collapse: collapse; width: 100%; margin-bottom: 30px;">
        <thead>
        <tr>
            <th style="background-color: #111d5c; color: #fff; padding: 10px; text-align: left;">{{ __('Coupon Code') }}</th>
            <th style="background-color: #111d5c; color: #fff; padding: 10px; text-align: left;">{{ __('Coupon Type') }}</th>
            <th style="background-color: #111d5c; color: #fff; padding: 10px; text-align: left;">{{ __('Coupon Amount') }}</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td style="border: 1px solid #ddd; padding: 8px;">{{ $order_details->coupon_code }}</td>
            <td style="border: 1px solid #ddd; padding: 8px;">{{ $order_details->coupon_type }}</td>
            <td style="border: 1px solid #ddd; padding: 8px;">
                @if($order_details->coupon_amount >0)
                    {{ float_amount_with_currency_symbol($order_details->coupon_amount) }}
                @endif
            </td>
        </tr>
        </tbody>
    </table>
@endif

@if($order_details->is_order_online !=1)
    <div class="earning-wrapper">
        <h3 class="earning-title">{{ __('Billing Details') }}</h3><hr>
        <p class="wrap-para"><strong>{{ __('Name:') }}</strong> {{ $order_details->name }}</p>
        <p class="wrap-para"><strong>{{ __('Email:') }}</strong> {{ $order_details->email }}</p>
        <p class="wrap-para"><strong>{{ __('Phone:') }}</strong> {{ $order_details->phone }}</p>
    </div>
    <div class="earning-wrapper">
        <h3 class="earning-title">{{ __('Shipping Details') }}</h3><hr>
        <p class="wrap-para"><strong>{{ __('Name:') }}</strong> {{ $order_details->name }}</p>
        <p class="wrap-para"><strong>{{ __('Email:') }}</strong> {{ $order_details->email }}</p>
        <p class="wrap-para"><strong>{{ __('Phone:') }}</strong> {{ $order_details->phone }}</p>
        <p class="wrap-para"><strong>{{ __('City:') }}</strong> {{ optional($order_details->service_city)->service_city }}</p>
        <p class="wrap-para"><strong>{{ __('Area:') }}</strong> {{ optional($order_details->service_area)->service_area }}</p>
        <p class="wrap-para"><strong>{{ __('Country:') }}</strong> {{ optional($order_details->service_country)->country }}</p>
        <p class="wrap-para"><strong>{{ __('Address:') }}</strong> {{ $order_details->address }}</p>
        <p class="wrap-para"><strong>{{ __('Date:') }}</strong> {{ __($order_details->date) }}</p>
        <p class="wrap-para"><strong>{{ __('Schedule:') }}</strong> {{ __($order_details->schedule) }}</p>
        <p class="wrap-para"><strong>{{ __('Order Create Date:') }}</strong> {{ optional($order_details->created_at)->toFormattedDateString() }}</p>
    </div>
@endif