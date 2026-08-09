@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show mt-4">
        <ul class="list-none">
            <button type="button btn-sm" class="btn-close" data-bs-dismiss="alert" aria-label="Close">×</button>
            @foreach($errors->all() as $error)
                <li> {{$error}}</li> 
            @endforeach
        </ul>
    </div>
@endif