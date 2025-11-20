@extends('template')

@push('css')
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Raleway">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body, h1, h2, h3, h4, h5, h6 {
            font-family: "Raleway", sans-serif;
            color: #006400; /* Deep green color for text */
        }

        body, html {
            height: 100%;
            line-height: 1.8;
        }

        /* Full height image header */
        .bgimg-1 {
            background-position: center;
            background-size: cover;
            background-image: url("{{ asset('public/front/images/home/hero_image_1.png') }}");
            min-height: 100%;
        }

        .w3-bar .w3-button {
            padding: 16px;
        }

        .btn-primary {
            font-size: 2.5em; /* Enlarge button text */
            padding: 20px 40px; /* Enlarge button size */
            background-color: #006400; /* Deep green background for button */
            border-color: #006400; /* Deep green border for button */
            color: white; /* White text color for button */
        }

        .left-align-container {
            text-align: left; /* Align text to the left */
        }
    </style>
@endpush

@section('main')
<div class="margin-top-85">
    <div class="row m-0">
        {{-- sidebar start--}}
        @include('users.sidebar')
        {{-- sidebar end--}}

        <!--<div class="col-md-10 bgimg-1 w3-display-container w3-grayscale-min">-->
            <div class="col-lg-10">
                <div class="main-panel">
                    <div class="container-fluid container-fluid-90 margin-top-85 min-height d-flex flex-column left-align-container">
                        <h1 class="mb-4">Select Templates</h1>
                        
                        {{-- Display success message if available --}}
                        @if(session('success'))
                            <div class="alert alert-success">
                                {{ session('success') }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('user.updateConfigs') }}">
                            @csrf
                            <div class="form-group">
                                <!--<label for="configs">Available Configurations:</label>-->
                                @if($configs->isNotEmpty())
                                    <ul>
                                        @foreach($configs as $name)
                                            <li>
                                                <input type="checkbox" name="selected_names[]" id="config-{{ $loop->index }}" value="{{ $name }}">
                                                <label for="config-{{ $loop->index }}">{{ $name }}</label>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p>No configurations available.</p>
                                @endif
                            </div>
                            <button type="submit" class="btn btn-primary">Import Templates</button>
                          
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
<!--</div>-->
@endsection