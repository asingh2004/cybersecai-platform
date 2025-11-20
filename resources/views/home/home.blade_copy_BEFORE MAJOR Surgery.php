@extends('template')

@push('css')
    <link rel="stylesheet" type="text/css" href="{{ url('public/css/daterangepicker.min.css') }}" />
    <style>
        .vbtn-outline-success:hover {
            background: #1dbf73 !important;
        }

        .btn-outline-danger:hover {
            background: #dc3545 !important;
        }
    </style>
@endpush

@section('main')
    <input type="hidden" id="front_date_format_type" value="{{ Session::get('front_date_format_type') }}">

    <section class="hero-banner magic-ball">
        <div class="main-banner" style="background-image: url('{{ defined("BANNER_URL") ? BANNER_URL : '' }}');">
            <div class="container">
                <div class="row align-items-center text-center text-md-left">
                    <div class="col-md-6 col-lg-5 mb-5 mb-md-0">
                        <div class="main_formbg item animated zoomIn mt-80">
                            <h1 class="pt-4">{{ trans('messages.home.make_your_reservation') }}</h1>
                            <form id="front-search-form" method="post" action="{{ url('search') }}">
                                {{ csrf_field() }}
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="input-group pt-4">
                                            <input class="form-control p-3 text-14" id="front-search-field" placeholder="{{ trans('messages.home.where_want_to_go') }}" autocomplete="off" name="location" type="text" required>
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <div class="d-flex" id="daterange-btn">
                                            <div class="input-group mr-2 pt-4">
                                                <input class="form-control p-3 border-right-0 border text-14 checkinout" name="checkin" id="startDate" type="text" placeholder="{{ trans('messages.search.check_in') }}" autocomplete="off" required>
                                            </div>

                                            <div class="input-group ml-2 pt-4">
                                                <input class="form-control p-3 border-right-0 border text-14 checkinout" name="checkout" id="endDate" placeholder="{{ trans('messages.search.check_out') }}" type="text" required>
                                            </div>
                                        </div>
                                    </div>

                                    @if(!$respite_type->isEmpty())
                                        <div class="col-md-12">
                                            <div class="input-group pt-4 text-14">
                                                <select class="form-select form-control" name="respite_type" id="respite_type" required>
                                                    <option value="">Choose an AI Feature</option>
                                                    @foreach($respite_type as $respite)
                                                        <option value="{{$respite->name}}">{{$respite->name}}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <div class="col-md-12 front-search mt-5 pb-3 pt-4">
                                    <button type="submit" class="btn vbtn-default btn-block p-3 text-16">{{ trans('messages.home.search') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="recommandedbg bg-gray mt-4 magic-ball magic-ball-about pb-5">
        <div class="container-fluid container-fluid-90">
            <div class="row">
                <div class="recommandedhead section-intro text-center mt-70">
                    <p class="item animated fadeIn text-24 font-weight-700 m-0">AI Features</p>
                    <p class="mt-2">Discover the amazing capabilities of our AI models!</p>
                </div>
            </div>


          <div class="row mt-5">

    @foreach($aiFeatures as $feature) 
        <div class="col-md-6 col-lg-4 col-xl-3 pl-3 pr-3 pb-3 mt-4">
            <div class="card h-100 card-shadow card-1">
                <div class="grid">
                    <figure class="effect-milo">
                        <a href="{{ $feature->href }}"> <!-- Make the image clickable -->
                            <img src="{{ $feature->image }}" class="room-image-container200" alt="{{ $feature->name }}"/>
                        </a>
                    </figure>
                </div>
                <div class="card-body p-0 pl-1 pr-1">
                    <div class="d-flex">
                        <div class="p-2 text">
                            <p class="text-16 font-weight-700">{{ $feature->name }}</p>
                            <p class="text-13 mt-2 mb-0">{{ $feature->description }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
         
         
          
        </div>
    </section>

    @if(!$testimonials->isEmpty())
        <section class="testimonialbg pb-70">
            <div class="testimonials">
                <div class="container">
                    <div class="row">
                        <div class="recommandedhead section-intro text-center mt-70">
                            <p class="animated fadeIn text-24 text-color font-weight-700 m-0">{{ trans('messages.home.say_about_us') }}</p>
                            <p class="mt-2">{{ trans('messages.home.people_say') }}</p>
                        </div>
                    </div>

                    <div class="row mt-5">
                        @foreach($testimonials as $testimonial)
                        <div class="col-md-4 mt-4">
                            <div class="item h-100 card-1">
                                <img src="{{ $testimonial->image_url }}" alt="{{ $testimonial->name }}">
                                <div class="name">{{ $testimonial->name }}</div>
                                <small class="desig">{{ $testimonial->designation }}</small>
                                <p class="details">{{ substr($testimonial->description, 0, 200) }} </p>
                                <ul>
                                    @for ($i = 0; $i < 5; $i++)
                                        @if($testimonial->review > $i)
                                            <li><i class="fa fa-star secondary-text-color" aria-hidden="true"></i></li>
                                        @else
                                            <li><i class="fa fa-star rating" aria-hidden="true"></i></li>
                                        @endif
                                    @endfor
                                </ul>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endif

    <section class="bg-gray mt-70 pb-2">
        <div class="container-fluid container-fluid-90">
            <div class="row">
                <div class="section-intro text-center">
                    <p class="item animated fadeIn text-24 font-weight-700 m-0 text-capitalize">{{ trans('messages.home.top_destination') }}</p>
                    <p class="mt-3">{{ trans('messages.home.destination_slogan') }}</p>
                </div>
            </div>

            <div class="row mt-2">
                @foreach($starting_cities as $city)
                <div class="col-md-4 mt-5">
                    <a href="{{ url('/search?location=' . urlencode($city->name)) }}">
                        <div class="grid item animated zoomIn">
                            <figure class="effect-ming">
                                <img src="{{ $city->image_url }}" alt="city"/>
                                <figcaption>
                                    <p class="text-18 font-weight-700 position-center">{{ $city->name }}</p>
                                </figcaption>
                            </figure>
                        </div>
                    </a>
                </div>
                @endforeach
            </div>
        </div>
    </section>

@stop

@push('scripts')
    <script type="text/javascript" src='https://maps.googleapis.com/maps/api/js?key={{ @$map_key }}&callback=Function.prototype&libraries=places'></script>
    <script type="text/javascript" src="{{ url('public/js/moment.min.js') }}"></script>
    @auth
        <script src="{{ url('public/js/sweetalert.min.js') }}"></script>
    @endauth
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-daterangepicker/3.0.5/daterangepicker.min.js" integrity="sha512-mh+AjlD3nxImTUGisMpHXW03gE6F4WdQyvuFRkjecwuWLwD2yCijw4tKA3NsEFpA1C3neiKhGXPSIGSfCYPMlQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script type="text/javascript" src="{{ url('public/js/front.js') }}"></script>
    <script type="text/javascript" src="{{ url('public/js/daterangecustom.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            dateRangeBtn(moment(), moment(), null, '{{ $date_format }}');
        });
        @auth
        $(document).on('click', '.book_mark_change', function(event) {
            event.preventDefault();
            var property_id = $(this).data("id");
            var property_status = $(this).data("status");
            var user_id = "{{ Auth::id() }}";
            var dataURL = APP_URL + '/add-edit-book-mark';
            var that = this;
            var title = property_status == "1" ? "{{ trans('messages.favourite.remove') }}" : "{{ trans('messages.favourite.add') }}";

            swal({
                title: title,
                icon: "warning",
                buttons: {
                    cancel: {
                        text: "{{ trans('messages.general.no') }}",
                        value: null,
                        visible: true,
                        className: "btn btn-outline-danger text-16 font-weight-700  pt-3 pb-3 pl-5 pr-5",
                        closeModal: true,
                    },
                    confirm: {
                        text: "{{ trans('messages.general.yes') }}",
                        value: true,
                        visible: true,
                        className: "btn vbtn-outline-success text-16 font-weight-700 pl-5 pr-5 pt-3 pb-3 pl-5 pr-5",
                        closeModal: true
                    }
                },
                dangerMode: true,
            }).then((willDelete) => {
                if (willDelete) {
                    $.ajax({
                        url: dataURL,
                        data: {
                            "_token": "{{ csrf_token() }}",
                            'id': property_id,
                            'user_id': user_id,
                        },
                        type: 'post',
                        dataType: 'json',
                        success: function(data) {
                            $(that).removeData('status');
                            if (data.favourite.status == 'Active') {
                                $(that).css('color', '#1dbf73');
                                $(that).attr("data-status", 1);
                                swal('success', '{{ trans('messages.success.favourite_add_success') }}');
                            } else {
                                $(that).css('color', 'black');
                                $(that).attr("data-status", 0);
                                swal('success', '{{ trans('messages.success.favourite_remove_success') }}');
                            }
                        }
                    });
                }
            });
        });
        @endauth
    </script>
@endpush