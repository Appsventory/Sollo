@extends('layouts.app')

@section('content')
<p>before</p>
@nixscomponent('Badge', ['text' => 'SLOT'])inner body@endnixscomponent
@include('partials.part', ['x' => 5])
<p>after</p>
@endsection
