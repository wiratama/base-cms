@extends ('main::master')

@section ('content')

<h3>{!! $title !!}</h3>

<div class="padd">
	<form action="">
		<select name="active_log" class="form-control select2" onchange="this.form.submit()">
			<option value="">- Choose Log Filename -</option>
			@foreach($available_log as $logs)
			<option value="{{ $logs }}" {{ $logs == $active_log ? 'selected' : '' }}>{{ $logs }}</option>
			@endforeach
		</select>
	</form>
</div>

@if($file_log)

<div class="panel">
	<div style="padding:1em 1.5em">
		<a href="{{ url()->route('admin.log.export') }}?active_log={{ $active_log }}" class="btn btn-primary">Export Log</a>
	</div>
	<div class="panel-body">
		@if(strlen($file_log) > (100 * 1024))
		<div class="alert alert-warning">Log is too large to display. Just export it with the button above.</div>
		@else
		<textarea style="width:100%; height:500px;">{!! $file_log !!}</textarea>
		@endif
	</div>
	<div style="padding:1em 1.5em">
		<a href="{{ url()->route('admin.log.export') }}?active_log={{ $active_log }}" class="btn btn-primary">Export Log</a>
	</div>
</div>
@endif

@stop