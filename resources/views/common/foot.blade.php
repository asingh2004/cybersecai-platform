<!-- Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=AW-10890525205"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'AW-10890525205');
  gtag('config', 'G-3K7RC2C8D9');
</script>

<!-- jQuery (required by jquery-validate and daterangepicker) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<!-- jQuery Validate (if used) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.5/jquery.validate.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<!-- Bootstrap 5.3.x bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Site main JS -->
<script src="{{ asset('public/js/main.js') }}"></script>

<!-- Moment + daterangepicker (if used) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="{{ URL::to('/') }}/public/js/daterangepicker.js"></script>

{!! @$head_code !!}

<script type="text/javascript">
  var APP_URL = "{{ url('/') }}";
  var USER_ID = "{{ isset(Auth::user()->id) ? Auth::user()->id : '' }}";
  var sessionDate = '{!! Session::get('date_format_type') !!}';

  // Currency change
  $(document).on('click', '.currency_footer', function() {
    var currency = $(this).data('curr');
    $.ajax({
      type: "POST",
      url: APP_URL + "/set_session",
      data: {
        "_token": "{{ csrf_token() }}",
        'currency': currency
      },
      success: function() {
        location.reload();
      }
    });
  });

  // Language change
  $(document).on('click', '.language_footer', function() {
    var language = $(this).data('lang');
    $.ajax({
      type: "POST",
      url: APP_URL + "/set_session",
      data: {
        "_token": "{{ csrf_token() }}",
        'language': language
      },
      success: function() {
        location.reload();
      }
    });
  });

  // Theme toggle (light/dark)
  $(document).on('change', '#themeSwitch', function() {
    var theme = this.checked ? 'dark' : 'light';
    // Apply immediately
    document.documentElement.setAttribute('data-bs-theme', theme);
    // Save locally (guest fallback)
    try { localStorage.setItem('theme', theme); } catch (e) {}

    // Persist for logged-in users
    @if(Auth::check())
    $.ajax({
      type: "POST",
      url: APP_URL + "/set_session",
      data: {
        "_token": "{{ csrf_token() }}",
        'theme': theme
      }
    });
    @endif
  });

  // Optional: enable Bootstrap popovers where present
  var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
  popoverTriggerList.map(function (el) { return new bootstrap.Popover(el); });
</script>

@stack('scripts')
</body>
</html>