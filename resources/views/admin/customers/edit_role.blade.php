@extends('admin.template')
@section('main')
<div class="content-wrapper">
    <section class="content">
        <h3>Set User Role</h3>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        <form method="POST" action="{{ route('admin.users.updateRole', $user->id) }}">
            @csrf
            <div class="form-group">
              <label>User: </label>
              <strong>{{ $user->first_name }} {{ $user->last_name }} ({{ $user->email }})</strong>
            </div>
            <div class="form-group">
              <label>Assign Role</label>
              <select name="role" class="form-control" onchange="toggleBizId(this.value)">
                  @foreach ($roles as $val => $name)
                  <option value="{{ $val }}" @if($user->roles->first() && $user->roles->first()->name === $val) selected @endif>
                      {{ $name }}
                  </option>
                  @endforeach
              </select>
            </div>
            <div class="form-group" id="biz-id-row" style="display: {{ optional($user->roles->first())->name === 'admin' ? 'block' : 'none' }};">
                <label>Business ID (unique integer)</label>
                <input name="business_id" type="text" value="{{ $user->business_id ?? '' }}" class="form-control">
                <small>Change manually or leave blank for auto-generation. This ID will be used for all users under this admin.</small>
            </div>
            <button type="submit" class="btn btn-primary">Set Role</button>
        </form>
    </section>
</div>
<script>
function toggleBizId(role) {
    document.getElementById('biz-id-row').style.display = (role === 'admin') ? 'block' : 'none';
}
</script>
@stop