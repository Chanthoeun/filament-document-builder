<div class="table-block mb-4">
    @php
        $arrayVar = $block['array_variable'] ?? null;
        $items = $arrayVar ? data_get($data, $arrayVar, []) : [];
        $columns = $block['columns'] ?? [];
    @endphp

    @if(count($columns) > 0)
        <table class="w-full">
            <thead>
                <tr>
                    @foreach($columns as $column)
                        <th>{{ $column['header'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        @foreach($columns as $column)
                            <td>
                                {{ data_get($item, $column['variable'], '') }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" class="text-center">No data available</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif
</div>
