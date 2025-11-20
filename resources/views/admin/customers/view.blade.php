@extends('admin.template')

@section('main')
<div class="content-wrapper">
    <section class="content-header">
        <h1>Customers<small>Control panel</small></h1>
        @include('admin.common.breadcrumb')
    </section>
    <section class="content">
        <div class="box">
            <div class="box-header">
                <h3 class="box-title">User Management</h3>
                <div class="pull-right">
                  <a class="btn btn-success" href="{{ url('admin/add-customer') }}">Add User</a>
                </div>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>First Name</th>
                                <th>Last Name</th>
                              	<th>User Role</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Business ID</th>
                                <th>ABN</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($customers as $user)
                            <tr>
                                <td>{{ $user->id }}</td>
                                <td>{{ $user->first_name }}</td>
                                <td>{{ $user->last_name }}</td>
                              	<td>
                                    {{ optional($user->roles->first())->display_name ?? 'N/A' }}
                                    <a href="{{ url('admin/edit-customer/'.$user->id) }}" class="btn btn-xs btn-primary ml-2">Edit</a>
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->phone }}</td>
                                <td>{{ $user->status }}</td>
                                <td>{{ $user->business_id }}</td>
                                <td>{{ $user->ABN }}</td>
                                <td>
                                  <a href="{{ url('admin/edit-customer/' . $user->id) }}" class="btn btn-xs btn-primary">Edit</a>
                                  <form method="POST" action="{{ url('admin/delete-customer') }}" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                      @csrf
                                      <input type="hidden" name="id" value="{{ $user->id }}">
                                      <button type="submit" class="btn btn-xs btn-danger ml-1">Delete</button>
                                  </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted">No users found.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
