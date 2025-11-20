@extends('template')
@push('styles')
<style>
    .btn-xxl { font-size: 1.6rem; padding: 1rem 2.5rem; }
</style>
@endpush

@section('main')

<div class="col-md-10">
  <div class="main-panel min-height mt-4">
    <div class="row">
      <div class="margin-top-85">
        <div class="row m-0">
          @include('users.sidebar')
          <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
            <div><h2>Step 4: Enter Configuration Details For Your Data Source</h2></div>
            @php
                use Illuminate\Support\Str;
                $sourcesArray = is_array($sources) ? $sources : (empty($sources) ? [] : [$sources]);
                $selected_source_name = $selected_source_name ?? ($sourcesArray[0] ?? '');
                // Strip leading/trailing quotes and whitespace for all downstream use:
                $selected_source_name_clean = trim($selected_source_name, "\"' \t\n\r\0\x0B");
                $config_fields = $config_fields ?? [];
                $m365 = $m365 ?? [];
            @endphp
            <form method="POST" action="{{ route('wizard.step5.post') }}" id="tenancyForm">
              @csrf

              @if(!empty($selected_source_name_clean))
                <hr>
                <h5>Integration for {{ $selected_source_name_clean }}</h5>
                <table class="table table-bordered">
                  <tbody>
                    
                    
                    
                    @php
$fieldDescriptions = [
    // 1. M365 - OneDrive, SharePoint & Teams Files
    'tenant_id'   => 'Azure AD tenant ID for your Microsoft 365 environment.',
    'client_id'   => 'Azure AD Application (client) ID for Microsoft Graph integration.',
    'client_secret' => 'Azure AD client secret for Microsoft Graph authentication.',
    // 2. Google Drive (Google Workspace)
    'service_account_credentials_json' => 'Full JSON for your Google service account, downloaded from Google Cloud Console.',
    'gdrive_folder_id' => 'Google Drive folder ID or "root" for top-level scan.',
    // 3. AWS S3
    'aws_access_key_id'     => 'AWS IAM Access Key ID (with s3:ListBucket, s3:GetObject permissions).',
    'aws_secret_access_key' => 'AWS IAM Secret Access Key.',
    'bucket_name'           => 'Your S3 bucket name (e.g., company-data-backups).',
    'region'                => 'AWS Region (e.g., us-east-1, eu-central-1) where the bucket is located.',
    'session_token'         => 'Optional temporary session token for federated/STSing users.',
    // 4. Box Drive
    'developer_token'   => 'Developer token from your Box app integration.',
    // 5. Dropbox Business (not shown, add if needed)
    'access_token'      => 'Dropbox Business API Access Token.',
    // 6. EMC, etc (not shown)
    'host'              => 'Hostname or IP address of your EMC/Isilon device.',
    'share_path'        => 'UNC path or share location for the storage (e.g., /ifs/data/company_docs).',
    // 7. SMB Fileshare
    'smb_server'        => 'SMB server hostname or IP (e.g., fileserver.company.com).',
    'share_name'        => 'The SMB share name (e.g., SharedDocs in \\\\server\\SharedDocs).',
    'username'          => 'Username for SMB share (domain-qualified if needed).',
    'password'          => 'Password for SMB share (keep secure).',
    'domain'            => 'Domain/Workgroup for authentication (optional for AD/NTLM).',
    'base_path'         => 'Subfolder path within the share (optional; e.g., year2024/).',
    // 8. NFS Fileshare
    // (use base_path as above)
];
@endphp
                    
                    
                    
                  @foreach($config_fields as $field)
                    @php
                      $inputName = strtolower(str_replace([' ', '-'], '_', $field));
                      $value = old($inputName, $m365[$field] ?? '');
                      $isPassword = Str::contains(strtolower($field), 'secret') || Str::contains(strtolower($field), 'password');
                      $inputType = $isPassword ? 'password' : 'text';
                    @endphp
                    <tr>
                      <td style="width:200px">
                            <label for="{{ $inputName }}">
      {{ ucfirst(str_replace('_',' ', $inputName)) }}
      @if($field === 'tenant_id' || $field === 'client_id') <span style="color:red;">*</span> @endif
      @if(isset($fieldDescriptions[$inputName]))
        <br>
        <small class="text-muted">{{ $fieldDescriptions[$inputName] }}</small>
      @endif
    </label>
                      </td>
                      <td>
                        <input
                          type="{{ $inputType }}"
                          name="{{ $inputName }}"
                          id="{{ $inputName }}"
                          class="form-control"
                          value="{{ $value }}"
                          @if($field === 'tenant_id' || $field === 'client_id') required @endif
                        >
                      </td>
                    </tr>
                  @endforeach
                  </tbody>
                </table>
              @endif

              {{-- Show webhook details if M365 --}}
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
                     <a href="{{ route('wizard.step3') }}" class="btn btn-xxl btn-primary">
                        &#8592; Back
                     </a>
                 </div>
                 <div>
 
                   	<button type="submit" name="save_type" value="in_progress" class="btn btn-xxl btn-primary">
            			Save All & Continue
        			</button>
        			<button type="submit" name="save_type" value="complete" class="btn btn-xxl btn-success">
            			Save All & Complete
        			</button>
                 </div>
              </div>
            </form>

            {{-- "Establish link" button only becomes enabled after saving form --}}
            @php
                $shouldShowBtn = (
                    $selected_source_name_clean === 'M365 - OneDrive, SharePoint & Teams Files'
                    && !empty($m365['tenant_id']) 
                    && !empty($m365['client_id']) 
                    && !empty($m365['client_secret'])
                );
            @endphp
            @if($shouldShowBtn)
              <button type="button" id="establishBtn" class="btn btn-info mb-3">Establish link with your Tenancy</button>
            @endif
            <div id="linkStatus" class="alert alert-info mt-2" style="display:none;"></div>
            <script>
              @if($shouldShowBtn)
              document.getElementById('establishBtn').addEventListener('click', function() {
                  var btn = this;
                  btn.disabled = true;
                  var status = document.getElementById('linkStatus');
                  status.innerText = "Establishing link, please wait...";
                  status.style.display = 'block';
                  fetch("{{ route('wizard.establish_m365_link', [$config->id]) }}", {
                      method: 'POST',
                      headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                  })
                  .then(r => r.json())
                  .then(function(data) {
                      if (data.success) {
                          status.innerText = 'Link successfully established with your Tenancy!';
                          status.classList.remove('alert-info');
                          status.classList.add('alert-success');
                      } else {
                          status.innerText = 'Failed to link: ' + (data.err || 'Unknown error');
                          status.classList.remove('alert-info');
                          status.classList.add('alert-danger');
                          btn.disabled = false;
                      }
                  })
                  .catch(function(e) {
                      status.innerText = 'Error: ' + e;
                      status.classList.remove('alert-info');
                      status.classList.add('alert-danger');
                      btn.disabled = false;
                  });
              });
              @endif
            </script>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection