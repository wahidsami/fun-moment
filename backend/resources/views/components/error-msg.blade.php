@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $error)
            <p class="mb-1">{{ $error }}</p>
        @endforeach
    </div>
@endif
