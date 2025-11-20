
@extends('template')

@section('main')

@if(session('success'))
    <div class="alert alert-success mt-3">{{ session('success') }}</div>
@endif
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
                    
            <div class="margin-top-85">
                <div class="row m-0">
                <!-- sidebar start-->
                    @include('users.sidebar')
                <!--sidebar end-->
          
	<div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
<h2>Configuration complete!</h2>
<p>Your settings have been saved. Here is a summary:</p>
<pre>{{ print_r($all->toArray(), true) }}</pre>
      @if(!empty($all->m365_config_json))
    	<h4>Your M365 Config JSON:</h4>
    	<pre>{{ json_encode($all->m365_config_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
	@endif

      
<a href="{{ route('wizard.dashboard') }}" class="btn btn-primary mt-3">Back to Dashboard</a>
                  </div></div></div></div>
@endsection