
<script type="text/javascript">
    (function($){
        "use strict"
        $(document).ready(function(){
            $('#search_by_city').select2({
                placeholder: `{{__('search city')}}`,
                ajax: {
                    type: 'get',
                    url: "{{route('user.country.city.ajax.search')}}",
                    dataType: 'json',
                    data: function (params) {
                        let country_id = $("#search_by_country").val();
                        return {
                            q: params.term, // search term
                            country_id: country_id,
                        };
                    },
                    delay: 250,
                    processResults: function (response) {
                        console.log(response.data);
                        return {
                            results:  $.map(response, function (item) {
                                return {
                                    text: item.service_city,
                                    id: item.id
                                }
                            })
                        };
                    },
                    cache: true
                }
            });

        });
    }(jQuery));
</script>
