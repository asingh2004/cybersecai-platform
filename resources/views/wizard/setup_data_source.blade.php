@extends('template')

@push('styles')
<style>
  .wizard-tiles { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
  @media (min-width: 768px) { .wizard-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  .wizard-tile {
    border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; padding: 16px; cursor: pointer;
    transition: box-shadow .15s ease, border-color .15s ease, transform .1s ease; position: relative; min-height: 110px;
  }
  .wizard-tile:hover { box-shadow: 0 4px 18px rgba(0,0,0,.06); transform: translateY(-1px); }
  .wizard-tile.active { border-color: #1d4ed8; box-shadow: 0 6px 22px rgba(29,78,216,.12); }
  .wizard-tile h5 { font-size: 1rem; margin: 0 0 6px 0; font-weight: 700; color: #111827; }
  .wizard-tile p { margin: 0; color: #6b7280; font-size: .92rem; }
  .status-dot { position: absolute; top: 10px; right: 10px; width: 10px; height: 10px; border-radius: 999px; border: 2px solid #ffffff; box-shadow: 0 0 0 1px rgba(0,0,0,.08); }
  .status-green { background: #16a34a; }
  .status-red { background: #dc2626; }

  .content-panel { margin-top: 18px; }
  .content-card { border: 1px solid #e5e7eb; background: #fff; border-radius: 10px; padding: 18px; }
  .content-card h4 { margin: 0 0 12px; font-weight: 700; color: #111827; }

  .ds-grid { display: grid; grid-template-columns: repeat(1, minmax(0, 1fr)); gap: 12px; }
  @media (min-width: 768px) { .ds-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (min-width: 1200px) { .ds-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }

  .ds-card {
    border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; padding: 14px; display: flex; flex-direction: column; gap: 8px;
    transition: border-color .15s ease, box-shadow .15s ease;
  }
  .ds-card.selected { border-color: #1d4ed8; box-shadow: 0 6px 22px rgba(29,78,216,.12); }
  .ds-card .ds-name { font-weight: 700; color: #111827; margin: 0; font-size: 1rem; line-height: 1.3; }
  .ds-card .ds-desc { color: #6b7280; font-size: 0.92rem; }

  .btn-xxl { font-size: 1.1rem; padding: .75rem 1.25rem; }
  .readonly-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; }
</style>
@endpush

@section('main')
@php
  // derive active step from query param
  $activeStep = (int) request('step', 1);
  if ($activeStep !== 1 && $activeStep !== 2) $activeStep = 1;

  // Step 1 current selection
  $selectedValue = old('data_sources', $selected ?? (session('wizard.data_sources') ?? ''));
  if (is_array($selectedValue)) $selectedValue = $selectedValue[0] ?? '';

  // Step 1 status: green if something is selected
  $isStep1Complete = !empty($selectedValue);

  // Step 2 status: green if config->status is complete, else if at least half the required fields are non-empty
  $required = $config_fields ?? [];
  $existing = $m365 ?? [];
  $nonEmptyCount = 0;
  foreach ($required as $reqField) {
      $nonEmptyCount += (!empty($existing[$reqField])) ? 1 : 0;
  }
  $half = ceil(max(count($required), 1) / 2);
  $isStep2Complete = false;
  if (isset($config) && isset($config->status) && $config->status === 'complete') {
      $isStep2Complete = true;
  } else if (count($required) > 0) {
      $isStep2Complete = ($nonEmptyCount >= $half);
  }
@endphp

<div class="col-md-10">
  <div class="main-panel min-height mt-4">
    <div class="row">
      <div class="margin-top-85">
        <div class="row m-0">
          @include('users.sidebar')

          <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">

            <h1><strong>Setup Data Source</strong></h1>
            <p class="text-muted">Choose your data source and configure required credentials. You can switch between steps using the tiles below.</p>

            @if (session('success'))
              <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
              <div class="alert alert-danger">
                <ul class="mb-0">
                  @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
              </div>
            @endif

            <div class="wizard-tiles mb-3">
              <div class="wizard-tile {{ $activeStep === 1 ? 'active' : '' }}" data-step="1" role="button" tabindex="0">
                <span class="status-dot {{ $isStep1Complete ? 'status-green' : 'status-red' }}"></span>
                <h5>Step 1: Select Data Source</h5>
                <p>Pick from the available source types (M365, SMB, S3, DBs, etc.).</p>
              </div>
              <div class="wizard-tile {{ $activeStep === 2 ? 'active' : '' }}" data-step="2" role="button" tabindex="0">
                <span class="status-dot {{ $isStep2Complete ? 'status-green' : 'status-red' }}"></span>
                <h5>Step 2: Configure Connection</h5>
                <p>Provide credentials and required parameters.</p>
              </div>
            </div>

            <div class="content-panel">

              <!-- Step 1 content -->
              <div id="step-1" class="content-card" style="{{ $activeStep === 1 ? '' : 'display:none;' }}">
                <h4 class="mb-3">Step 1: Select Data Source</h4>
                <form method="POST" action="{{ route('wizard.step1.post') }}" id="dsSelectForm">
                  @csrf
                  <div class="ds-grid">
                    @foreach(($sources ?? collect()) as $source)
                      @php
                        $name = $source->data_source_name;
                        $isSelected = ($selectedValue === $name);
                      @endphp
                      <label class="ds-card {{ $isSelected ? 'selected' : '' }}" for="radio_{{ $loop->index }}">
                        <div class="d-flex align-items-center justify-content-between">
                          <div class="form-check">
                            <input type="radio" class="form-check-input"
                              id="radio_{{ $loop->index }}"
                              name="data_sources"
                              value="{{ $name }}"
                              {{ $isSelected ? 'checked' : '' }}>
                            <span class="ds-name ms-2">{{ $name }}</span>
                          </div>
                          <span class="status-dot {{ $isSelected ? 'status-green' : 'status-red' }}"></span>
                        </div>
                        @if(!empty($source->description))
                          <div class="ds-desc mt-1">{{ $source->description }}</div>
                        @endif
                      </label>
                    @endforeach
                  </div>
                  @error('data_sources') <div class="alert alert-danger mt-3">{{ $message }}</div> @enderror
                  <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn btn-primary btn-xxl">Save Selection & Continue</button>
                  </div>
                </form>
              </div>

              <!-- Step 2 content -->
              <div id="step-2" class="content-card" style="{{ $activeStep === 2 ? '' : 'display:none;' }}">
                <h4 class="mb-2">Step 2: Configure Your Data Source</h4>
                @php
                  use Illuminate\Support\Str;
                  $sourcesArray = is_array($sources ?? null) ? $sources : (empty($sources ?? null) ? [] : [$sources]);
                  $selected_source_name = $selected_source_name ?? ($sourcesArray[0] ?? $selectedValue ?? '');
                  $selected_source_name_clean = trim((string)$selected_source_name, "\"' \t\n\r\0\x0B");
                  $config_fields = $config_fields ?? [];
                  $m365 = $m365 ?? [];
                @endphp

                @if(empty($selected_source_name_clean))
                  <div class="alert alert-info">Please select a data source in Step 1 to continue.</div>
                @else
                  <div class="mb-3">
                    <div class="field-label mb-1">Selected data source</div>
                    <div class="readonly-box">{{ $selected_source_name_clean }}</div>
                  </div>

                  <form method="POST" action="{{ route('wizard.step5.post') }}" id="configForm">
                    @csrf

                    <h5 class="mt-1 mb-2">Required settings for {{ $selected_source_name_clean }}</h5>
                    @if(!empty($config_fields))
                      @php
                        $fieldDescriptions = [
                          'host'        => 'Database server hostname or IP address.',
                          'port'        => 'Database port (eg. 3306 for MySQL/MariaDB, 1433 for SQL Server/Fabric, 1521 for Oracle).',
                          'user'        => 'Database username (read access recommended).',
                          'db_password' => 'DB user password.',
                          'database'    => 'Database or schema name.',
                          'tenant_id'   => 'Azure AD tenant ID for Microsoft 365.',
                          'client_id'   => 'Azure AD Application (client) ID.',
                          'client_secret' => 'Azure AD client secret.',
                          'service_account_credentials_json' => 'Full JSON for your Google service account.',
                          'gdrive_folder_id' => 'Google Drive folder ID or "root".',
                          'aws_access_key_id'     => 'AWS IAM Access Key ID.',
                          'aws_secret_access_key' => 'AWS IAM Secret Access Key.',
                          'bucket_name'           => 'S3 bucket name.',
                          'region'                => 'AWS Region (e.g., us-east-1).',
                          'session_token'         => 'Optional STS session token.',
                          'developer_token'       => 'Box developer token.',
                          'smb_server'            => 'SMB server hostname or IP.',
                          'share_name'            => 'SMB share name (e.g., SharedDocs).',
                          'username'              => 'SMB username.',
                          'password'              => 'SMB password.',
                          'domain'                => 'Domain/Workgroup (optional).',
                          'base_path'             => 'Subfolder path (optional).',
                        ];
                      @endphp
                      <table class="table table-bordered">
                        <tbody>
                        @foreach($config_fields as $field)
                          @php
                            $inputName = strtolower(str_replace([' ', '-'], '_', $field));
                            $value = old($inputName, $m365[$field] ?? '');
                            $isPassword = Str::contains(strtolower($field), 'secret') || Str::contains(strtolower($field), 'password');
                            $inputType = $isPassword ? 'password' : 'text';
                          @endphp
                          <tr>
                            <td style="width:260px">
                              <label for="{{ $inputName }}">
                                {{ ucfirst(str_replace('_',' ', $inputName)) }}
                                @if($field === 'tenant_id' || $field === 'client_id') <span style="color:red">*</span> @endif
                                @if(isset($fieldDescriptions[$inputName]))
                                  <br><small class="text-muted">{{ $fieldDescriptions[$inputName] }}</small>
                                @endif
                              </label>
                            </td>
                            <td>
                              <input type="{{ $inputType }}" name="{{ $inputName }}" id="{{ $inputName }}" class="form-control"
                                value="{{ $value }}" @if($field === 'tenant_id' || $field === 'client_id') required @endif>
                            </td>
                          </tr>
                        @endforeach
                        </tbody>
                      </table>
                    @else
                      <div class="alert alert-info">No required fields found for this source. You may proceed.</div>
                    @endif

                    @if(Str::contains($selected_source_name_clean, 'M365') && !empty($m365['tenant_id']) && !empty($m365['client_id']) && !empty($m365['client_secret']))
                      <div class="mb-3">
                        <label>Your Webhook URL for OneDrive/SharePoint:</label>
                        <input type="text" class="form-control" value="{{ $m365['webhook_url'] ?? $webhook_url }}" readonly>
                        <input type="hidden" name="webhook_url" value="{{ $webhook_url }}">
                      </div>
                      <div class="mb-3">
                        <label>webhook_client_state:</label>
                        <input type="text" class="form-control" value="{{ $m365['webhook_client_state'] ?? $combo_id }}" readonly>
                      </div>
                    @endif

                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <div>
                        <a href="{{ request()->fullUrlWithQuery(['step' => 1]) }}" class="btn btn-primary btn-xxl">&#8592; Back</a>
                      </div>
                      <div>
                      
                        <button type="submit" name="save_type" value="complete" class="btn btn-success btn-xxl">Save All & Complete</button>
                      </div>
                    </div>
                  </form>

                  @php
                    $shouldShowBtn = (
                      $selected_source_name_clean === 'M365 - OneDrive, SharePoint & Teams Files'
                      && !empty($m365['tenant_id'])
                      && !empty($m365['client_id'])
                      && !empty($m365['client_secret'])
                      && isset($config->id)
                    );
                  @endphp
                  @if($shouldShowBtn)
                    <button type="button" id="establishBtn" class="btn btn-info mb-3">Establish link with your Tenancy</button>
                    <div id="linkStatus" class="alert alert-info mt-2" style="display:none;"></div>
                    <script>
                      document.getElementById('establishBtn')?.addEventListener('click', function() {
                        var btn = this;
                        btn.disabled = true;
                        var status = document.getElementById('linkStatus');
                        status.innerText = "Establishing link, please wait...";
                        status.style.display = 'block';
                        fetch("{{ route('wizard.establish_m365_link', [$config->id ?? 0]) }}", {
                          method: 'POST',
                          headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        })
                        .then(r => r.json())
                        .then(function(data) {
                          if (data.success) {
                            status.innerText = 'Link successfully established with your Tenancy!';
                            status.classList.remove('alert-info'); status.classList.add('alert-success');
                          } else {
                            status.innerText = 'Failed to link: ' + (data.err || 'Unknown error');
                            status.classList.remove('alert-info'); status.classList.add('alert-danger');
                            btn.disabled = false;
                          }
                        })
                        .catch(function(e) {
                          status.innerText = 'Error: ' + e;
                          status.classList.remove('alert-info'); status.classList.add('alert-danger');
                          btn.disabled = false;
                        });
                      });
                    </script>
                  @endif
                @endif
              </div>

            </div>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Top tiles switching
  document.querySelectorAll('.wizard-tile').forEach(function(tile) {
    tile.addEventListener('click', function() { activateStep(this.dataset.step); });
    tile.addEventListener('keypress', function(e) {
      if (e.key === 'Enter' || e.key === ' ') activateStep(this.dataset.step);
    });
  });

  function activateStep(step) {
    // Toggle tiles
    document.querySelectorAll('.wizard-tile').forEach(el => el.classList.toggle('active', el.dataset.step === String(step)));
    // Toggle sections
    ['1','2'].forEach(i => {
      const el = document.getElementById('step-' + i);
      if (el) el.style.display = (i === String(step)) ? '' : 'none';
    });
    // Update URL param
    const url = new URL(window.location.href);
    url.searchParams.set('step', String(step));
    window.history.replaceState({}, '', url.toString());
  }

  // Step 1: clicking a ds-card selects the radio
  document.querySelectorAll('.ds-card').forEach(function(card, idx) {
    card.addEventListener('click', function(e) {
      const input = card.querySelector('input[type="radio"]');
      if (input) {
        input.checked = true;
        // Update visual selection
        document.querySelectorAll('.ds-card').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
      }
    });
  });
});
</script>
@endpush