@include('common.head')

@include('common.header')

@yield('main')

@auth
@include('components.chat-widget')
@endauth

@include('common.footer')

@include('common.foot')

@yield('validation_script')