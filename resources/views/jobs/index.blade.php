<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Background Jobs Dashboard</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 2em; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f4f4f4; }
        .success { color: green; }
        .error   { color: red; }
        form.inline { display: inline; }
        .controls { margin-bottom: 1em; }
    </style>
</head>
<body>

    <h1>Background Jobs Dashboard</h1>

    <div class="controls">
        <button onclick="location.reload()">Refresh</button>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <p class="success">{{ session('success') }}</p>
    @endif
    @if(session('error'))
        <p class="error">{{ session('error') }}</p>
    @endif

    {{-- Launch New Job --}}
    <section>
        <h2>Launch New Job</h2>
        <form action="{{ route('jobs.launch') }}" method="POST">
            @csrf
            <label>
                Class name:
                <input type="text" name="class" placeholder="SampleJob" required>
            </label>
            <label>
                Method:
                <input type="text" name="method" placeholder="handle" required>
            </label>
            <label>
                Params (comma-separated):
                <input type="text" name="params" placeholder="param1,param2">
            </label>
            <button type="submit">Launch</button>
        </form>
    </section>

    {{-- Running Jobs --}}
    <section>
        <h2>Running Jobs</h2>
        <table>
            <thead>
                <tr>
                    <th>Job ID</th>
                    <th>Class</th>
                    <th>Method</th>
                    <th>Params</th>
                    <th>Started At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($runningJobs as $job)
                    <tr>
                        <td>{{ $job['job_id'] }}</td>
                        <td>{{ $job['class'] }}</td>
                        <td>{{ $job['method'] }}</td>
                        <td>{{ implode(',', $job['params'] ?? []) }}</td>
                        <td>{{ $job['started_at'] }}</td>
                        <td>
                            <form class="inline" action="{{ route('jobs.cancel') }}" method="POST">
                                @csrf
                                <input type="hidden" name="job_id" value="{{ $job['job_id'] }}">
                                <button type="submit">Cancel</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">No running jobs.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    {{-- Job Status Logs --}}
    <section>
        <h2>Job Status Logs</h2>
        <table>
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Message</th>
                    <th>Retry Attempt</th>
                </tr>
            </thead>
            <tbody>
                @foreach($jobs as $line)
                    @php
                        // parse the timestamp and message
                        preg_match('/\[(.*?)\]\s+(.*)/', $line, $m);
                        $timestamp = $m[1] ?? '';
                        $message   = $m[2] ?? $line;
                        // capture 'attempt X' if present
                        preg_match('/attempt\s+(\d+)/i', $line, $r);
                        $retry     = $r[1] ?? '';
                    @endphp
                    <tr>
                        <td>{{ $timestamp }}</td>
                        <td>{{ $message }}</td>
                        <td>{{ $retry }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    {{-- Error Logs --}}
    <section>
        <h2>Error Logs</h2>
        <pre style="background:#f9f9f9; padding:10px; border:1px solid #ddd;">
@foreach($errors as $error)
{{ $error }}
@endforeach
        </pre>
    </section>

</body>
</html>
