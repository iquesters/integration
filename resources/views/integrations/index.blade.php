@extends('integration::layouts.app')

@section('content')
<div class="container">
    <h1>Integrations</h1>

    @if ($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>UID</th>
                <th>Name</th>
                <th>Small Name</th>
                <th>Nature</th>
                <th>Status</th>
                <th>Metas</th>
            </tr>
        </thead>
        <tbody>
            @forelse($integrations as $integration)
                <tr>
                    <td>{{ $integration->id }}</td>
                    <td>{{ $integration->uid }}</td>
                    <td>{{ $integration->name }}</td>
                    <td>{{ $integration->small_name }}</td>
                    <td>{{ $integration->nature }}</td>
                    <td>{{ $integration->status }}</td>
                    <td>
                        @if($integration->metas->isNotEmpty())
                            <ul>
                                @foreach($integration->metas as $meta)
                                    <li><strong>{{ $meta->meta_key }}:</strong> {{ $meta->meta_value }}</li>
                                @endforeach
                            </ul>
                        @else
                            <em>No meta data</em>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">No integrations found</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection