@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    @foreach($errors->all() as $error)
        <li> {{$error}}</li>
    @endforeach
</div>
@endif

