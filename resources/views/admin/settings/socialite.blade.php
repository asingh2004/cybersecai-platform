@extends('admin.template')
@section('main')

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">

        <!-- Main content -->
        <section class="content">
            <div class="row">
                <div class="col-md-3 settings_bar_gap">
                    @include('admin.common.settings_bar')
                </div>
                <!-- right column -->
                <div class="col-md-9">
                    <!-- Horizontal Form -->
                    <div class="box box-info">
                        <div class="box-header with-border">
                            <h3 class="box-title">Social Logins</h3>
                        </div>
                        <!-- /.box-header -->
                        <!-- form start -->
                        <form id="socialiteform" method="post" action="{{ url('admin/settings/social-logins')}}" class="form-horizontal" enctype="multipart/form-data">
                            {{ csrf_field() }}
                            <div class="box-body">
                                <div class="form-group">
                                    <label for="exampleInputPassword1" class="control-label col-sm-3">Google</label>
                                    <div class="col-sm-6">
                                        <select name="google_login" class="form-control" >
                                            <option value="0" {{isset($social['google_login']) && $social['google_login'] == '0' ? 'selected':""}}>Inactive</option>
                                            <option value="1" {{isset($social['google_login']) && $social['google_login'] == '1' ? 'selected':""}}>Active</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="exampleInputPassword1" class="control-label col-sm-3">Facebook</label>
                                    <div class="col-sm-6">
                                        <select name="facebook_login" class="form-control" >
                                            <option value="0" {{isset($social['facebook_login']) && $social['facebook_login'] == '0' ? 'selected':""}}>Inactive</option>
                                            <option value="1" {{isset($social['facebook_login']) && $social['facebook_login'] == '1' ? 'selected':""}}>Active</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <!-- /.box-body -->
                            <div class="box-footer">
                                @if(Request::segment(3) == 'email' || Request::segment(3) == '' || Request::segment(3) == 'api_informations' || Request::segment(3) == 'payment_methods' || Request::segment(3) == 'social_links')
                                    <a class="btn btn-default" href="{{ url('admin/settings') }}">Cancel</a>
                                @else
                                    <button type="submit" class="btn btn-info ">Submit</button>
                                    <a class="btn btn-danger" href="{{ url('admin/settings') }}">Cancel</a>

                                @endif

                            </div>
                            <!-- /.box-footer -->
                        </form>
                    </div>
                    <!-- /.box -->

                    <!-- /.box -->
                </div>
                <!--/.col (right) -->
            </div>
            <!-- /.row -->
        </section>
        <!-- /.content -->
    </div>

@endsection
