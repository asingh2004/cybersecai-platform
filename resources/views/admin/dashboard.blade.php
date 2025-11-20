@extends('admin.template')
@section('main')
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
     <!-- Main content -->
    <section class="content">


<div class="row">
      <!-- Small boxes (Stat box) -->
      <div class="row">
        <!-- ./col -->
        <div class="col-lg-4 col-xs-6">
          <!-- small box -->
          <div class="small-box bg-yellow">
            <div class="inner">
              <h3>{{ $total_users_count }}</h3>

              <p>Total Users</p>
            </div>
            <div class="icon">
              <i class="ion ion-person-add"></i>
            </div>
            <a href="{{ url('admin/customers') }}" class="small-box-footer">More info <i class="fa fa-arrow-circle-right"></i></a>
          </div>
        </div>
        <!-- ./col -->
        

        <!-- ./col -->
        <div class="col-lg-4 col-xs-6">
          <!-- small box -->
          <div class="small-box bg-purple">
            <div class="inner">
              <h3>{{ $today_users_count }}</h3>

              <p>User's Request Pending your Action</p>
            </div>
            <div class="icon">
              <i class="ion ion-person-add"></i>
            </div>
            <a href="{{ url('admin/customers') }}" class="small-box-footer">More info <i class="fa fa-arrow-circle-right"></i></a>
          </div>
        </div>
        
        <!-- ./col -->
      </div>
      <!-- /.row -->
 

      
      <div class="box box-info">
  <div class="box-header with-border">
    <h3 class="box-title">Pending User Approvals</h3>
  </div>
  <div class="box-body">
    <div class="table-responsive">
      <table class="table table-bordered">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($pendingUsers as $user)
            <tr>
              <td>{{ $user->first_name }} {{ $user->last_name }}</td>
              <td>{{ $user->email }}</td>
              <td>{{ $user->status }}</td>
              <td>
                <form action="{{ route('admin.users.approve', $user->id) }}" method="POST" style="display:inline">
                  @csrf
                  <select name="role" style="width:auto;display:inline-block">
                      <option value="user" selected>User</option>
                      <option value="admin">Admin</option>
                  </select>
                  <input type="text" name="business_id" value="{{ $user->business_id ?: '' }}" placeholder="Business ID (admin only)" style="width:120px" 
                      onfocus="if(this.form.role.value!='admin'){this.value='';this.blur();}">
                  <button type="submit" class="btn btn-success btn-sm">Approve</button>
              </form>
              </td>
            </tr>
          @endforeach
          @if ($pendingUsers->isEmpty())
            <tr><td colspan="4" class="text-center text-muted">No users awaiting approval</td></tr>
          @endif
        </tbody>
      </table>
    </div>
  </div>
</div>
      
 

    </section>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->
@stop
