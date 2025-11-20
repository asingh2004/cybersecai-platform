@extends('template')

@section('main')
<div class="container mb-4 margin-top-85 min-height">
    <div class="d-flex justify-content-center">
        <div class="p-5 mt-5 mb-5 border w-450">
            @if(Session::has('message'))
                <div class="alert {{ Session::get('alert-class') }} text-center">
                    {{ Session::get('message') }}
                </div>
            @endif

            <form method="post" action="{{ route('admin.users.store') }}">
                @csrf
                <div class="form-group col-sm-12 p-0">
                    <label>First Name</label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" class="form-control" required>
                    @error('first_name') <span class="text-danger">{{ $message }}</span>@enderror
                </div>
                <div class="form-group col-sm-12 p-0">
                    <label>Last Name</label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" class="form-control" required>
                    @error('last_name') <span class="text-danger">{{ $message }}</span>@enderror
                </div>
                <div class="form-group col-sm-12 p-0">
                    <label>Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control" required>
                    @error('email') <span class="text-danger">{{ $message }}</span>@enderror
                </div>
                <div class="form-group col-sm-12 p-0">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                    @error('password') <span class="text-danger">{{ $message }}</span>@enderror
                </div>
                <div class="form-group col-sm-12 p-0">
                    <label>Confirm Password</label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-success w-100 mt-3">Add User</button>
            </form>
        </div>
    </div>
</div>
@stop