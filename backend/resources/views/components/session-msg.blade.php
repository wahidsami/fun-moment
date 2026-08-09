@if(session()->has('msg'))
    <div class="alert alert-{{session('type')}} alert-dismissible fade show" role="alert">
        <ul>
            <li> {!! session('msg') !!}</li>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
