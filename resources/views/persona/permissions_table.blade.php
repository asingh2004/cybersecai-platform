<table class="table table-sm table-bordered">
    <thead class="thead-light">
        <tr>
            <th>Role</th>
            <th>Granted To</th>
            <th>Email</th>
            <th>Login</th>
        </tr>
    </thead>
    <tbody>
    @foreach($permissions as $perm)
        <tr>
            <td>{{ $perm['role'] }}</td>
            <td>{{ $perm['granted_to'] }}</td>
            <td>{{ $perm['email'] ?? '-' }}</td>
            <td>{{ $perm['login'] ?? '-' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>