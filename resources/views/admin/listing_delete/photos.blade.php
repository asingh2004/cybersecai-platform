@extends('admin.template')
@section('main')
    <div class="content-wrapper">
        <section class="content-header">
            <h1>
                Photos
                <small>Photos</small>
            </h1>
            <ol class="breadcrumb">
                <li><a href="{{url('/')}}/admin/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
            </ol>
        </section>
        <section class="content">
            <div class="row">
                <div class="col-md-3 settings_bar_gap">
                    @include('admin.common.property_bar')
                </div>

                <div class="col-md-9">
                    <div class="box box-info">
                        <div class="box-body">

                            <form id="img_form" enctype='multipart/form-data' method="post"
                                  action="{{url('admin/listing/'.$result->id.'/'.$step)}}"
                                  class='signup-form login-form' accept-charset='UTF-8'>
                                {{ csrf_field() }}
                                <div class="col-md-12">


                                    <div class="panel panel-default">
                                        <div class="panel-body">
                                            <div class="row">

                                                <div class="col-md-6">
                                                    @if(session('success'))
                                                        <span
                                                            class="text-center text-success">{{ session('success') }}</span>
                                                    @endif
                                                    <input class="form-control text-16" name="file" id="photo_file"
                                                           type="file" value="">
                                                    <input type="hidden" id="photo" type="text" name="photos">
                                                    <input type="hidden" name="img_name" id="img_name">
                                                    <input type="hidden" name="crop" id="type" value="crop">
                                                    <p class="text-13">(Width 640px and Height 360px)</p>
                                                    <div id="result" class="hide">
                                                        <img src="#" alt="">
                                                    </div>
                                                    @if (!$errors->any('url'))
                                                        <span
                                                            class="text-center text-danger">{{$errors->first()}}</span>
                                                    @endif
                                                </div>
                                                <div class="col-md-6">
                                                    <button type="submit"
                                                            class="btn btn-large btn-primary next-section-button"
                                                            id="submit">
                                                        {{trans('messages.listing_description.upload')}}
                                                    </button>
                                                </div>

                                            </div>
                                        </div>
                                    </div>

                                </div>
                                <br>
                                <br>
                            </form>

                            <div class="row">
                                <div id="photo-list-div" class="col-md-12 l-pad-none min-height-div">

                                    <?php
                                    $serial = 0;
                                    ?>

                                    @foreach($photos as $photo)

                                        <?php
                                        $serial++;
                                        ?>

                                        <div class="col-md-4 margin-top10" id="photo-div-{{$photo->id}}">
                                            <div class="room-image-container200"
                                                 style="background-image:url({{url('public/images/property/'.$photo->property_id.'/'.$photo->photo)}});">
                                                @if($photo->cover_photo == 0)
                                                    <a class="photo-delete" href="javascript:void(0)"
                                                       data-rel="{{$photo->id}}"><p class="photo-delete-icon"><i
                                                                class="fa fa-trash-o"></i></p></a>
                                                @endif
                                            </div>
                                            <div class="margin-top5">
                                                <textarea data-rel="{{$photo->id}}"
                                                          class="form-control photo-highlights"
                                                          placeholder="What are the highlights of this photo?">{{$photo->message}}</textarea>
                                            </div>


                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label for="sel1">Serial</label>
                                                    <input type="text" image_id="{{$photo->id}}"
                                                           property_id="{{$result->id}}" id="serial-{{$photo->id}}"
                                                           class="form-control serial" name="serial"
                                                           value="{{$photo->serial}}">
                                                </div>
                                                <div class="col-md-6">
                                                    @if($photo->cover_photo == 0)
                                                        <label for="sel1">Cover Photo</label>
                                                        <select class="form-control photoId" id="photoId">
                                                            <option value="Yes"
                                                                    <?= ($photo->cover_photo == 1) ? 'selected' : '' ?> image_id="{{$photo->id}}"
                                                                    property_id="{{$result->id}}">Yes
                                                            </option>
                                                            <option value="No"
                                                                    <?= ($photo->cover_photo == 0) ? 'selected' : '' ?> image_id="{{$photo->id}}"
                                                                    property_id="{{$result->id}}">No
                                                            </option>
                                                        </select>
                                                    @endif
                                                </div>
                                            </div>

                                            @if($serial % 3 == 0)
                                                <div style="clear:both;">&nbsp;</div>
                                            @endif

                                        </div>
                                    @endforeach
                                </div>
                                <div class="col-md-12">
              <span class="text-danger display-off" id='photo'>This field is required
                                </div>
                            </div>
                            <div class="row">
                                <br>

                                <!-- <div class="col-md-12"> -->
                                 <div class="d-grid gap-2 d-md-block">
                                    <!-- <div class="col-md-10 col-sm-6 col-xs-6 l-pad-none text-left"> -->
                                        <a data-prevent-default=""
                                           href="{{ url('admin/listing/'.$result->id.'/amenities') }}"
                                           class="btn btn-large btn-primary">{{trans('messages.listing_description.back')}}</a>
                                    <!-- </div> -->
                                    <!-- <div class="col-md-2 col-sm-6 col-xs-6 text-right"> -->
                                        <a href="{{url('admin/listing/'.$result->id.'/pricing')}}"
                                           class="btn btn-large btn-primary next-section-button">
                                            {{trans('messages.listing_basic.next')}}
                                        </a>
                                    <!-- </div> -->
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>
    </div>

    <div class="modal fade d-none z-index-high" id="crop-modal" role="dialog">
        <div class="modal-dialog" style="width: 80% !important;">
            <!-- Modal content-->
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close cls-reload" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Edit Image</h4>
                </div>
                <div>
                    <canvas id="canvas">
                        Your browser does not HTML5 canvas element.
                    </canvas>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-info" id="crop" style="margin-left: 20px" type="submit" name="submit">Crop
                    </button>
                    <button type="button" id="restore" class="btn btn-default pull-right">Skip</button>
                </div>
            </div>
        </div>
    </div>

    @push('css')
        <link rel="stylesheet" href="{{ url('public/css/cropper.css') }}"/>
        <style>
            .modal-content {
                border-radius: 0 !important;
            }

            /* Limit image width to avoid overflow the container */
            img {
                max-width: 100% !important;
            }

            #canvas {
                max-height: 550px;
                width: 100%;
                background-color: #ffffff;
                cursor: default;
                border: 1px solid black;
            }

            div#result {
                height: 200px;
                width: 100% !important;
                border: 1px solid #1dbf73;
            }

            div#result img {
                width: 100%;
                height: 100%;
                object-fit: contain;
            }

            .hide {
                display: none;
            }
        </style>
    @endpush
    @push('scripts')
        <script src="{{ url('public/js/cropper.min.js') }}"></script>
        <script type="text/javascript">
            var gl_photo_id = 0;
            $(document).on('submit', '#photo-form', function (e) {
                e.preventDefault();
                $('#photo').hide();
                var dataURL = '{{url("add_photos/$result->id")}}';
                var form_data = new FormData(this);
                var photo_file = $('#photo_file').val();
                if (photo_file != '') {
                    // page_loader_start();
                    $.ajax({
                        url: dataURL,
                        data: {
                            form_data,
                            '_token': '{{ csrf_token() }}'
                        },
                        type: 'post',
                        dataType: 'json',
                        processData: false,
                        contentType: false,
                        success: function (result) {
                            if (result.status) {
                                var photo_url = '{{url("images/rooms/$result->id")}}' + '/' + result.photo_name;
                                var photo_div = '<div class="col-md-4 margin-top10" id="photo-div-' + result.photo_id + '">'
                                    + '<div class="room-image-container200" style="background-image:url(' + photo_url + ');">'
                                    + '<a class="photo-delete" href="#" data-rel="' + result.photo_id + '"><p class="photo-delete-icon"><i class="fa fa-trash-o"></i></p></a>'
                                    + '</div>'
                                    + '<div class="margin-top5">'
                                    + '<textarea data-rel="' + result.photo_id + '" class="form-control photo-highlights" placeholder="' + "{{ trans('messages.lys.highlights_photo') }}" + '"></textarea>'
                                    + '</div>'
                                    + '</div>';
                                $('#photo-list-div').append(photo_div);
                            } else
                                $('#photo').show();

                        },
                        error: function (request, error) {
                            // This callback function will trigger on unsuccessful action
                            show_error_message('Det har oppstått nettverksfeil vennligst prøv igjen');
                        }
                    });
                    $('#photo_file').val('');
                    page_loader_stop();
                }
            });
            $(document).on('click', '.photo-delete', function (e) {
                e.preventDefault();
                gl_photo_id = $(this).attr('data-rel');
                var con = bootbox.confirm('Are you sure you want to delete this?', location_image_upload);
            });
            $(document).on('focusout', '.photo-highlights', function (e) {
                var dataURL = '{{url("admin/listing/$result->id/photo_message")}}';
                var photo_id = $(this).attr('data-rel');
                var messages = $(this).val();
                $.ajax({
                    url: dataURL,
                    data: {'photo_id': photo_id, 'messages': messages, '_token': '{{ csrf_token() }}'},
                    type: 'post',
                    dataType: 'json',
                    success: function (result) {

                    },
                    error: function (request, error) {
                        // This callback function will trigger on unsuccessful action
                        show_error_message('Det har oppstått nettverksfeil vennligst prøv igjen');
                    }
                });
            })

            function location_image_upload(result) {
                if (result) {
                    var dataURL = '{{url("admin/listing/$result->id/photo_delete")}}';
                    var photo_id = gl_photo_id;

                    //page_loader_start();
                    $.ajax({
                        url: dataURL,
                        data: {'photo_id': photo_id, '_token': '{{ csrf_token() }}'},
                        type: 'post',
                        dataType: 'json',
                        success: function (result) {
                            if (result.success) {
                                $('#photo-div-' + photo_id).remove();
                            }
                        },
                        error: function (request, error) {
                            // This callback function will trigger on unsuccessful action
                            console.log(error);
                        }
                    });
                    //page_loader_stop();
                }
            }


            $(document).on('change', '.photoId', function (ev) {
                // alert('ok');
                var dataURL = '{{url("admin/listing/photo/make_default_photo")}}';
                var option_value = $(this).val();
                var photo_id = $('option:selected', this).attr('image_id');
                var property_id = $('option:selected', this).attr('property_id');

                $.ajax({
                    url: dataURL,
                    data: {
                        'photo_id': photo_id,
                        'property_id': property_id,
                        'option_value': option_value,
                        '_token': '{{ csrf_token() }}'
                    },
                    type: 'post',
                    dataType: 'json',
                    success: function (result) {
                        location.reload();
                    }
                });


            });

            $(document).on('change', '.serial', function (ev) {
                var dataURL = '{{url("admin/listing/photo/make_photo_serial")}}';
                var serial = $(this).val();
                var id = $(this).attr('image_id');

                $.ajax({
                    url: dataURL,
                    data: {'id': id, 'serial': serial, '_token': '{{ csrf_token() }}'},
                    type: 'post',
                    dataType: 'json',
                    success: function (result) {
                        location.reload();
                    }
                });

            });

        </script>
        <script>
            $('#photo_file').on('change', function () {
                $("#crop-modal").modal();
                var canvas = $("#canvas"),
                    context = canvas.get(0).getContext("2d"),
                    result = $('#result img');
                let name = this.files[0].name;
                if (this.files && this.files[0]) {
                    if (this.files[0].type.match(/^image\//)) {
                        var reader = new FileReader();
                        reader.onload = function (evt) {
                            var img = new Image();
                            img.onload = function () {
                                context.canvas.height = img.height;
                                context.canvas.width = img.width;
                                context.drawImage(img, 0, 0);
                                var cropper = canvas.cropper({});
                                $('#crop').click(function () {
                                    // Get a string base 64 data url
                                    var croppedImageDataURL = canvas.cropper('getCroppedCanvas').toDataURL("image/png");
                                    result.attr('src', croppedImageDataURL);
                                    $('#result').toggleClass('hide');
                                    $('#photo').val(croppedImageDataURL);
                                    $('#img_name').val(name);
                                    $('#type').val('crop');
                                    canvas.cropper('destroy');
                                    $("#crop-modal").modal('toggle');
                                    $("#submit").click();

                                });

                                $('#restore').click(function () {
                                    canvas.cropper('destroy');
                                    result.empty();
                                    $('#type').val('original');
                                    $("#submit").click();
                                });
                            };
                            img.src = evt.target.result;
                        };
                        reader.readAsDataURL(this.files[0]);
                    } else {
                        alert("Invalid file type! Please select an image file.");
                    }
                } else {
                    alert('No file(s) selected.');
                }
            });

        </script>
    @endpush
@stop

@section('validate_script')
    <script src="{{ asset('public/backend/js/additional-method.min.js') }}"></script>
    <script type="text/javascript">
        $(document).ready(function () {

            $('#img_form').validate({
                rules: {
                    'photos[]': {
                        required: true,
                        accept: "image/jpg,image/jpeg,image/png,image/gif"
                    }
                },
                messages: {
                    'photos[]': {
                        accept: 'The file must be an image (jpg, jpeg, png or gif)'
                    }
                }
            });

        });
    </script>
@endsection
