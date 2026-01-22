@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Generate Customs Declaration Form</h1>
    <!-- Form preview and generation interface -->
    <form action="{{ route('declaration-forms.store') }}" method="POST">
        @csrf
        <!-- Display parsed invoice details and matched customs codes -->
        <!-- Allow manual code selection if needed -->
        <button type="submit" class="btn btn-primary">Generate Form</button>
    </form>
</div>
@endsection
