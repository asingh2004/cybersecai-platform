@extends('template')

@section('main')

<div class="margin-top-85">
    <div class="row m-0">
        <!-- sidebar start-->
        @include('users.sidebar')
        <!--sidebar end-->
        <div class="col-lg-10 p-0">
            <div class="container-fluid min-height">
                <div class="col-md-12 mt-5">
                    <div class="main-panel">
                        @include('users.profile_nav')

                        <!--Success Message -->
                        @if(Session::has('message'))
                            <div class="row pl-5 pr-5 mt-5">
                                <div class="col-md-12  alert {{ Session::get('alert-class') }} alert-dismissable fade in top-message-text opacity-1">
                                    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
                                    {{ Session::get('message') }}
                                </div>
                            </div>
                        @endif

                        <div class="row mt-5 border pt-4 pb-4 rounded">
                            <div class="col-md-3">
                                @if($result->profile_image)
                                    <img width="200" height="200" title="{{ Auth::user()->first_name }}" src="{{  url('public/images/profile').'/'.Auth::user()->id.'/'.$result->profile_image }}" alt="{{ $result->first_name }}">
                                @else
                                    <img width="225" height="225" title="{{ Auth::user()->first_name }}" src="{{  \Auth::user()->profile_src }}" alt="{{ $result->first_name }}">
                                @endif

                            </div>
                            <div class="col-md-9 align-self-center">
                                <p class="text-16 mt-2">{{trans('messages.users_media.photo_data')}}</p>
                                <form name="ajax_upload" method="post" id="ajax_upload" enctype="multipart/form-data" action="{{ url('/') }}/users/profile/media" accept-charset="UTF-8" >
                                    {{ csrf_field() }}
                                    <div class="row">
                                        <div class="col-md-6 p-0">
                                            <input type="file" name="photos[]" id="profile_image" class="form-control">
                                        </div>
                                        <div class="col-md-3">
                                            <input type="submit" class="btn form-control btn-success" value="upload">
                                        </div>
                                    </div>

                                    <iframe class="d-none" name="upload_frame" id="upload_frame"></iframe>
                                </form>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<!-- This js is already in foot.blade.php -->
<!-- <script  type="text/javascript" src="{{ url('public/js/jquery.validate.min.js') }}"></script> -->
<script src="{{ url('public/js/additional-method.min.js') }}"></script>
<script type="text/javascript">
    $(document).ready(function () {
        $('#ajax_upload').validate({
            rules: {
                'photos[]': {
                    accept: "image/jpg,image/jpeg,image/png,image/gif"
                }
            },
            messages: {
            'photos[]': {
                    accept: "{{ __('messages.jquery_validation.image_accept') }}",
                    }
            },
            errorElement : 'div',
            errorLabelContainer: '.errorTxt_p'
        });
    });
</script>
@endpush
@stop
