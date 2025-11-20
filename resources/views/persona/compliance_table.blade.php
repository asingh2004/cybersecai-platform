<table class="table table-bordered table-hover" id="file-compliance-table">
    <thead>
        <tr>
            <th>File</th>
            <th>Type</th>
            <th>Last Modified</th>
            <th>Size</th>
            <th>Risk</th>
            <th>Compliance</th>
            <th>Permissions</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($files as $file)
        <tr
    style="cursor:pointer"
    onclick="window.location='{{ route('persona.file_detail', ['hash'=>$file['fullpath_hash'], 'fileName'=>rawurlencode($file['file_name'])]) }}'"
    data-filename="{{ $file['file_name'] }}"
    data-risk="{{ strtoupper($file['compliance']['overall_risk']) }}"
>
    <td>
        <a href="{{ route('persona.file_detail', ['hash'=>$file['fullpath_hash'], 'fileName'=>rawurlencode($file['file_name'])]) }}">
            {{ $file['file_name'] }}
        </a>
    </td>
            <td>{{ \Str::afterLast($file['file_type'] ?? '', '/') }}</td>
            <td>{{ \Carbon\Carbon::parse($file['last_modified'])->toDayDateTimeString() }}</td>
            <td>{{ number_format($file['size_bytes']/1024) }} KB</td>
            <td>
                @php
                $risk = strtolower($file['compliance']['overall_risk']);
                $badge = 'badge-' . ($risk === 'high' ? 'high' : ($risk==='medium' ? 'medium' : ($risk==='low' ? 'low' : 'na')));
                @endphp
                <span class="badge {{ $badge }}">{{ $file['compliance']['overall_risk'] }}</span>
            </td>
            <td>
                <ul class="mb-0">
                @foreach ($file['compliance']['summary_table'] as $ct)
                    <li>
                        <b>{{ $ct['standard'] }}:</b>
                        {{ $ct['risk_rating'] }}, <span style="font-size:90%;">{{ $ct['data_types'] }}</span>
                    </li>
                @endforeach
                </ul>
            </td>
            <td>
                @foreach ($file['permissions'] as $p)
                    <span class="badge badge-secondary">{{ ucfirst($p['role']) }} </span>
                    <span style="font-size:95%;">{{ $p['granted_to'] }}</span><br>
                @endforeach
            </td>
        </tr>
    @endforeach
    </tbody>
</table>