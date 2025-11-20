@php
  $theme = Auth::check() ? (Auth::user()->theme ?? Session::get('theme', 'light')) : Session::get('theme', 'light');
@endphp

<footer class="main-panel card border footer-bg p-4" id="footer">
  <div class="container-fluid container-fluid-90">
    <div class="row">
      <div class="col-6 col-sm-3 mt-4">
        <h2 class="font-weight-700">{{ trans('messages.static_pages.company') }}</h2>
        <div class="row">
          <div class="col p-0">
            <ul class="mt-1">
              @if(isset($footer_first))
                @foreach($footer_first as $ff)
                  <li class="pt-3 text-16"><a href="{{ url($ff->url) }}">{{ $ff->name }}</a></li>
                @endforeach
              @endif
            </ul>
          </div>
        </div>
      </div>

      <div class="col-6 col-sm-3 mt-4">
        <h2 class="font-weight-700">{{ trans('messages.static_pages.hosting') }}</h2>
        <div class="row">
          <div class="col p-0">
            <ul class="mt-1">
              @if(isset($footer_second))
                @foreach($footer_second as $fs)
                  <li class="pt-3 text-16"><a href="{{ url($fs->url) }}">{{ $fs->name }}</a></li>
                @endforeach
              @endif
            </ul>
          </div>
        </div>
      </div>

      <div class="col-6 col-sm-3 mt-4">
        <h2 class="font-weight-700">News and Media</h2>
        <div class="row">
          <div class="col p-0">
            <ul class="mt-1">
              @if(isset($footer_third))
                @foreach($footer_third as $ft)
                  <li class="pt-3 text-16"><a href="{{ url($ft->url) }}">{{ $ft->name }}</a></li>
                @endforeach
              @endif
            </ul>
          </div>
        </div>
      </div>

      <div class="col-6 col-sm-3 mt-5">
        <div class="row mt-5">
          <div class="col-md-12 text-center" style="margin-bottom: 10px;">
            <a href="{{ url('/') }}" style="display: inline-flex; align-items: center; gap: 11px;">
              <span style="display:inline-block;height:36px;width:36px;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96" width="36" height="36" aria-label="CyberSecAI.io logo">
                  <defs>
                    <linearGradient id="g1" x1="0" x2="1" y1="0" y2="1">
                      <stop offset="0%" stop-color="#1877c2"/>
                      <stop offset="100%" stop-color="#36d399"/>
                    </linearGradient>
                  </defs>
                  <path d="M48 8C67 17 84 20 84 37c0 38-27.5 50-36 54C39.5 87 12 75 12 37c0-17 17-20 36-29z"
                        fill="#fff" stroke="url(#g1)" stroke-width="5"/>
                  <g stroke="#1877c2" stroke-width="1.7">
                    <circle cx="48" cy="27" r="5" fill="#36d399"/>
                    <circle cx="33" cy="40" r="3.5" fill="#95dbfa"/>
                    <circle cx="63" cy="40" r="3.5" fill="#95dbfa"/>
                    <circle cx="41" cy="56" r="3.5" fill="#5ad7ba"/>
                    <circle cx="55" cy="56" r="3.5" fill="#5ad7ba"/>
                    <line x1="48" y1="27" x2="33" y2="40"/>
                    <line x1="48" y1="27" x2="63" y2="40"/>
                    <line x1="33" y1="40" x2="41" y2="56"/>
                    <line x1="63" y1="40" x2="55" y2="56"/>
                    <line x1="41" y1="56" x2="55" y2="56"/>
                  </g>
                  <title>mochanai Security + AI Logo</title>
                </svg>
              </span>
              <span style="font-size:1.7em;font-weight:700;color:#1877c2;letter-spacing:-0.04em;">
                mochan<strong style="color:#36d399;">ai.com</strong>
              </span>
            </a>
          </div>
        </div>

        <div class="col-md-12">
          <div class="social mt-4">
            <ul class="list-inline text-center">
              @if(isset($join_us))
                @for($i=0; $i<count($join_us); $i++)
                  @if ($join_us[$i]->value <> '#')
                    <li class="list-inline-item">
                      <a class="social-icon text-color text-18" target="_blank" href="{{ $join_us[$i]->value }}" aria-label="{{ $join_us[$i]->name }}"><i class="fab fa-{{ str_replace('_','-',$join_us[$i]->name) }}"></i></a>
                    </li>
                  @endif
                @endfor
              @endif
            </ul>
          </div>
        </div>

        <div class="row mt-3">
          <div class="col-md-12">
            <p class="text-center text-underline">
              <i class="fa fa-globe"></i> <strong>English</strong> <strong>$:<strong>USD</strong>
            </p>
          </div>
        </div>

        @if(Auth::check())
        <!-- Appearance toggle for logged-in users -->
        <div class="row mt-2">
          <div class="col-md-12">
            <div class="d-flex justify-content-center align-items-center">
              <i class="far fa-sun me-2" aria-hidden="true"></i>
              <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" id="themeSwitch" {{ ($theme ?? 'light') === 'dark' ? 'checked' : '' }} aria-label="Toggle dark mode">
                <label class="form-check-label ms-2" for="themeSwitch">Dark mode</label>
              </div>
              <i class="far fa-moon ms-2" aria-hidden="true"></i>
            </div>
          </div>
        </div>
        @endif

      </div>
    </div>

    <div class="border-top p-0 mt-4">
      <div class="row justify-content-between p-2">
        <p class="col-lg-12 col-sm-12 mb-0 mt-4 text-14 text-center">
          <strong>Copyright &copy; {{ date('Y') }} Telkin Corp.</strong> All rights reserved.
        </p>
      </div>
    </div>
  </div>

  <!-- Language Modal -->
  <div class="modal fade mt-5 z-index-high" id="languageModalCenter" tabindex="-1" aria-labelledby="languageModalCenterTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <div class="w-100 pt-3">
            <h5 class="modal-title text-20 text-center font-weight-700" id="languageModalLongTitle">{{ trans('messages.home.choose_language') }}</h5>
          </div>
          <div>
            <button type="button" class="btn-close filter-cancel" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
        </div>

        <div class="modal-body pb-5">
          <div class="row">
            @foreach($language as $key => $value)
              <div class="col-md-6 mt-4">
                <a href="javascript:void(0)" class="language_footer {{ (Session::get('language') == $key) ? 'text-success' : '' }}" data-lang="{{ $key }}">{{ $value }}</a>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Currency Modal -->
  <div class="modal fade mt-5 z-index-high" id="currencyModalCenter" tabindex="-1" aria-labelledby="languageModalCenterTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <div class="w-100 pt-3">
            <h5 class="modal-title text-20 text-center font-weight-700" id="languageModalLongTitle">{{ trans('messages.home.choose_currency') }}</h5>
          </div>
          <div>
            <button type="button" class="btn-close filter-cancel" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
        </div>

        <div class="modal-body pb-5">
          <div class="row">
            @foreach($currencies as $key => $value)
            <div class="col-6 col-sm-3 p-3">
              <div class="currency pl-3 pr-3 text-16 {{ (Session::get('currency') == $value->code) ? 'border border-success rounded-5 currency-active' : '' }}">
                <a href="javascript:void(0)" class="currency_footer" data-curr="{{ $value->code }}">
                  <p class="m-0 mt-2 text-16">{{ $value->name }}</p>
                  <p class="m-0 text-muted text-16">{{ $value->code }} - {!! $value->org_symbol !!}</p>
                </a>
              </div>
            </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>
  </div>
</footer>