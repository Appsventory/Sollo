@foreach($list as $i)<li>{{ $i }}</li>@empty<p>none</p>@endforeach|@if($flag)yes@else no@endif|@unless($flag)U@endunless
