<script>
    (function ($){

        $(document).ready(function (){

            //todo: if the wallet checkbox is checked need to show this value as current seleted payment gateway
            // load two
            $(document).on('click', '.wallet_selected_payment_gateway',function(){
                let wallet_value = $(this).val();
                $('.wallet-payment-gateway-wrapper .wallet_selected_payment_gateway').addClass('selected');
                if($('.wallet_selected_payment_gateway').is(':checked')){
                    $('.payment-gateway-wrapper #order_from_user_wallet').val('wallet');

                    // if select Order From Wallet
                    $('.custom_radio__single').removeClass('active');
                    $('.custom_radio__single__input').prop('checked', false);

                    // if wallet not select
                    $('.custom_radio__single__input').on('click', function (){
                        $('#wallet_selected_payment_gateway').prop('checked', false);
                    });

                }else {
                    $('.payment-gateway-wrapper #order_from_user_wallet').val('');
                }
            });

            $(document).on('click', '.current_balance_selected_gateway',function(){
                $('.payment-gateway-wrapper li').removeClass('active');
                $('.payment-gateway-wrapper li').removeClass('selected');
                $('.current-balance-wrapper .current_balance_selected_gateway').addClass('selected');
                $('.payment-gateway-wrapper #order_from_user_wallet').val('current_balance');
            });


        //new add start
            // select payment gateway
            $(document).on('click', '.paymentGateway_add__item',function(){
                let value = $(this).data('gateway');
                $('#order_from_user_wallet').val(value);

                // manual payment image option show/hide
                if(value == 'manual_payment'){
                    $('.manual_payment_gateway_extra_field').show();
                }else {
                    $('.manual_payment_gateway_extra_field').hide();
                }
            });

            // for wallet
            $(document).on('click', '#wallet_selected_payment_gateway',function(){
                $('.confirm-payment').find('#order_from_user_wallet').val('wallet');
            });


            // select manual payment gateway
            if($('#order_from_user_wallet').val() == 'manual_payment'){
                $('.manual_payment_gateway_extra_field').show();
            }else {
                $('.manual_payment_gateway_extra_field').hide();
            }


               // kinetic select bank name option show/hide
                @if(get_static_option('site_default_payment_gateway') === 'kineticpay')
                     $('.kinetic_payment_show_hide').show();
                @else
                    $('.kinetic_payment_show_hide').hide();
                @endif
            $(document).on('click', '.paymentGateway_add__item',function(){
                let value = $(this).data('gateway');
                $('#order_from_user_wallet').val(value);
                if(value == 'kineticpay'){
                    $('.kinetic_payment_show_hide').show();
                }else {
                    $('.kinetic_payment_show_hide').hide();
                }
            });
         //new add end


            // if payment gateway name value is null and (I agree with terms and conditions) is null
            $(document).on('click', '#check3', function(){
                if($('#order_from_user_wallet').val() !== null &&  $('#check3').is(":checked")){
                    $('.all_check_for_order').removeClass('active');
                    $('.all_check_for_order').addClass('completed');
                }else{
                    $('.all_check_for_order').removeClass('completed');
                    $('.all_check_for_order').addClass('active');
                }
            });

            // if (I agree with) is not null and (Order From Wallet) is null
            $(document).on('click', '#wallet_selected_payment_gateway', function (){
                if($('#wallet_selected_payment_gateway').is(":checked") === false){
                        $('.all_check_for_order').removeClass('completed');
                        $('.all_check_for_order').addClass('active');
                }else if($('#check3').is(":checked")){
                    $('.all_check_for_order').removeClass('active');
                    $('.all_check_for_order').addClass('completed');
                }
            });

        });

    })(jQuery);

</script>