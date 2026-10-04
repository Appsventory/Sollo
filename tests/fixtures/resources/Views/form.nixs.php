<form method="POST" action="/submit">
@csrf
@method('PUT')
<input name="x" value="{{ old('x') }}">
</form>
