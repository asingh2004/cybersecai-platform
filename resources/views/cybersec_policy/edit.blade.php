@extends('template')

@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
  <h2>Edit Policy for "{{ implode(', ', $sources) }}"</h2>
  <form method="POST" action="{{ route('cybersec_policy.update', $dataConfig->id) }}">
    @csrf
    @method('PUT')
    @foreach($policySchema as $key => $rule)
      <div class="mb-3">
        <label><strong>{{ ucwords(str_replace('_',' ', $key)) }}</strong><br>
        <small>{{ $rule['description'] }}</small></label>
        @if($rule['type'] == 'boolean')
          <select name="restriction_policy[{{ $key }}]" class="form-control">
            <option value="1" {{ isset($currentPolicy[$key]) && $currentPolicy[$key] ? 'selected' : '' }}>Yes</option>
            <option value="0" {{ isset($currentPolicy[$key]) && !$currentPolicy[$key] ? 'selected' : ''}}>No</option>
          </select>
        @elseif($rule['type'] == 'enum')
          <select name="restriction_policy[{{ $key }}]" class="form-control">
            @foreach($rule['values'] as $val)
              <option value="{{ $val }}" {{ (isset($currentPolicy[$key]) && $currentPolicy[$key]==$val)?'selected':'' }}>{{ ucfirst($val) }}</option>
            @endforeach
          </select>
        @elseif($rule['type'] == 'array')
          <input
            type="text"
            class="form-control"
            name="restriction_policy[{{ $key }}]"
            value="{{ isset($currentPolicy[$key]) ? implode(',',(array)$currentPolicy[$key]) : '' }}"
            placeholder="Comma separated values"
          >
        @endif
      </div>
    @endforeach
    <button type="submit" class="btn btn-primary">Save Policy</button>
    <a href="{{ route('wizard.dashboard') }}" class="btn btn-secondary">Cancel</a>
  </form>
</div></div></div></div></div></div>
@endsection