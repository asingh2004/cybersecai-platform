@extends('template')

@section('main')
<div class="container mb-4 margin-top-85 min-height">
    <div class="d-flex justify-content-center">
        <div class="p-5 mt-5 mb-5 border w-750">
            @if(Session::has('message'))
                <div class="alert {{ Session::get('alert-class') }} text-center">
                    {{ Session::get('message') }}
                </div>
            @endif

            <h2 class="mb-4">Users In Your Organisation</h2>

            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                    <tr>
                        <td>{{ $user->first_name }} {{ $user->last_name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->status }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <a href="{{ route('admin.users.create') }}" class="btn btn-success mt-3">Add New User</a>
        </div>
    </div>
</div>
@stop