<x-pulse::card :cols="$cols" :rows="$rows" :class="$class" wire:poll.5s="">
    <x-pulse::card-header name="Slow Commands">
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand">
        @if (!count($slowCommands))
            <x-pulse::no-results />
        @else
            <x-pulse::table>
                <colgroup>
                    <col width="100%" />
                    <col width="0%" />
                    <col width="0%" />
                </colgroup>
                <x-pulse::thead>
                    <tr>
                        <x-pulse::th>Command</x-pulse::th>
                        <x-pulse::th class="text-right">Count</x-pulse::th>
                        <x-pulse::th class="text-right">Average</x-pulse::th>
                        <x-pulse::th class="text-right">Slowest</x-pulse::th>
                    </tr>
                </x-pulse::thead>
                <tbody>
                @foreach($slowCommands as $command)
                    <tr class="h-2 first:h-0"></tr>
                    <tr wire:key="{{ $command->key }}">
                        <x-pulse::td class="max-w-[1px]">
                            <code class="block text-xs text-gray-900 dark:text-gray-100 truncate" title="">
                                {{ $command->key }}
                            </code>
                        </x-pulse::td>
                        <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold">
                            {{ round($command->count) }}
                        </x-pulse::td>
                        <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold">
                            {{ number_format($command->avg, 2, '.', ' ') }} <small>s</small>
                        </x-pulse::td>
                        <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold">
                            {{ number_format($command->max, 2, '.', ' ') }} <small>s</small>
                        </x-pulse::td>
                    </tr>
                @endforeach
                </tbody>
            </x-pulse::table>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
