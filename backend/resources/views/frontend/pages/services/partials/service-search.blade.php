
<script>
  (function($){
    "use strict";

    $(document).ready(function(){
        $(document).on('change','#search_by_country,#search_by_city,#search_by_area,#search_by_category,#search_by_subcategory, #search_by_child_category, #search_by_rating,#search_by_sorting',function(e){
            e.preventDefault();
            // get price and set value
            let left_value = $('.input-min').val();
            let right_value = $('.input-max').val();
            $('#price_range_value').val(left_value + ',' + right_value);

            // google map km set
            let distance_km_value = $('#slider-value').text();
            $('#distance_kilometers_value').val(distance_km_value);
            let get_autocomplete_value = $('#autocomplete').val();
            $('#autocomplete_address').val(get_autocomplete_value);

            $('#search_service_list_form').trigger('submit');
        })

        // Service search by text
        var oldSearchQ = '';
        $(document).on('keyup','#search_by_query',function(e){
            e.preventDefault();

            // get price and set value
            let left_value = $('.input-min').val();
            let right_value = $('.input-max').val();
            $('#price_range_value').val(left_value + ',' + right_value);

            // google map km set
            let distance_km_value = $('#slider-value').text();
            $('#distance_kilometers_value').val(distance_km_value);
            let get_autocomplete_value = $('#autocomplete').val();
            $('#autocomplete_address').val(get_autocomplete_value);

            let qVal = $(this).val().trim();

            if(oldSearchQ !== qVal){
                setTimeout(function (){
                    oldSearchQ = qVal.trim();
                    if(qVal.length > 2){
                        $('#search_service_list_form').trigger('submit');
                    }
                },2000);
            }
        })

    });
})(jQuery);
</script>