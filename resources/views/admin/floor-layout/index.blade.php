@extends('layouts.admin')

@section('title', 'Floor Layout')
@section('page-title', 'Floor Layout')
@section('page-subtitle', 'Arrange tables on a floor grid')

@section('content')
    <nav class="mb-4 text-sm text-[#898989]" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('admin.dashboard') }}" class="font-semibold text-[#04346a] hover:underline">Dashboard</a></li>
            <li aria-hidden="true">/</li>
            <li class="font-semibold text-[#1f2f3e]">Floor Layout</li>
        </ol>
    </nav>

    <section class="mb-5 rounded-xl bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-end gap-3">
            <label class="block min-w-[16rem] flex-1 text-sm font-semibold">
                Floor
                <select
                    class="mt-1 w-full rounded-lg border border-[#d5dde7] px-3 py-2 font-medium"
                    aria-label="Floor"
                    onchange="if (this.value) window.location = this.value"
                >
                    @forelse ($floors as $floor)
                        <option value="{{ route('admin.floor-layout.index', ['floor' => $floor->id]) }}" @selected($selected && $selected->id === $floor->id)>
                            {{ $floor->name }} · {{ $floor->layout_rows }} rows × {{ $floor->layout_columns }} columns
                        </option>
                    @empty
                        <option value="">No floors yet</option>
                    @endforelse
                </select>
            </label>
            <button type="button" id="add-floor" class="rounded-lg bg-[#04346a] px-4 py-2 text-sm font-semibold text-white">Add Floor</button>
            @if ($selected)
                <button type="button" id="edit-floor" class="rounded-lg border border-[#d5dde7] px-4 py-2 text-sm font-semibold">Edit Floor</button>
            @endif
        </div>
        @if ($selected?->description)
            <p class="mt-3 text-sm text-[#898989]">{{ $selected->description }}</p>
        @endif
    </section>

    <dialog id="floor-dialog" class="w-[min(36rem,calc(100%-2rem))] rounded-xl p-0 backdrop:bg-black/40">
        <form id="floor-details-form" method="POST" action="{{ route('admin.floor-layout.floors.store') }}" class="p-5">
            @csrf
            <input type="hidden" name="_method" id="floor-form-method" value="POST" disabled>
            <input type="hidden" name="code" id="floor-form-code" value="">
            <input type="hidden" name="sort_order" id="floor-form-sort" value="">
            <input type="hidden" name="is_active" id="floor-form-active" value="1">
            <h2 id="floor-form-title" class="text-lg font-bold">Add Floor</h2>
            <p class="mt-1 text-sm text-[#898989]">Each floor keeps its own rows and columns. Saving a name or description does not move tables.</p>
            @if ($errors->any())
                <ul class="mt-3 list-disc pl-5 text-sm font-semibold text-red-700">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <label class="block text-sm font-semibold sm:col-span-2">
                    Floor name
                    <input id="floor-form-name" name="name" required maxlength="100" class="mt-1 w-full rounded-lg border border-[#d5dde7] px-3 py-2 font-medium">
                </label>
                <label class="block text-sm font-semibold sm:col-span-2">
                    Description
                    <input id="floor-form-description" name="description" maxlength="255" class="mt-1 w-full rounded-lg border border-[#d5dde7] px-3 py-2 font-medium">
                </label>
                <label class="block text-sm font-semibold">
                    Rows
                    <input id="floor-form-rows" name="layout_rows" type="number" min="1" max="16" required value="4" class="mt-1 w-full rounded-lg border border-[#d5dde7] px-3 py-2 font-medium">
                </label>
                <label class="block text-sm font-semibold">
                    Columns
                    <input id="floor-form-columns" name="layout_columns" type="number" min="1" max="24" required value="5" class="mt-1 w-full rounded-lg border border-[#d5dde7] px-3 py-2 font-medium">
                </label>
            </div>
            <p class="mt-4 text-sm font-semibold">Grid preview</p>
            <div id="floor-preview" class="mt-2 overflow-x-auto"></div>
            <p id="floor-preview-warning" class="mt-2 hidden text-sm font-semibold text-red-700"></p>
            <div class="mt-4 flex gap-2">
                <button id="floor-form-save" type="submit" class="rounded-lg bg-[#04346a] px-4 py-2 text-sm font-semibold text-white">Save</button>
                <button id="floor-form-cancel" type="button" class="rounded-lg border border-[#d5dde7] px-4 py-2 text-sm font-semibold">Cancel</button>
            </div>
        </form>
    </dialog>

    @if ($selected)
        @php
            $columns = (int) $selected->layout_columns;
            $rows = (int) $selected->layout_rows;
        @endphp

        <section class="floor-plan-panel mb-5 rounded-xl bg-white p-5">
            <div class="floor-plan-toolbar">
                <div>
                    <h2 class="text-lg font-bold">{{ $selected->name }}</h2>
                    <p class="mt-1 text-sm text-[#5c6b7a]">Each table occupies one cell. Seating capacity changes the drawing inside that cell. Drag a table onto a cell, or set its column and row. The space between cells is the walkway. Save stores the layout. Reset discards unsaved moves.</p>
                </div>
                <div class="floor-plan-actions">
                    <a href="{{ route('admin.floor-layout.index', ['floor' => $selected->id]) }}" class="rounded-lg border border-[#d5dde7] bg-white px-4 py-2 text-sm font-semibold">Reset</a>
                </div>
            </div>
            <ul class="floor-plan-legend" aria-label="Table status">
                <li><span class="floor-plan-swatch floor-plan-status-available"></span>Available</li>
                <li><span class="floor-plan-swatch floor-plan-status-occupied"></span>Occupied</li>
                <li><span class="floor-plan-swatch floor-plan-status-cleaning"></span>Cleaning</li>
                <li><span class="floor-plan-swatch floor-plan-status-merged"></span>Merged</li>
            </ul>

            <form id="floor-layout-form" method="POST" action="{{ route('admin.floor-layout.save', $selected) }}" class="mt-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="layout_version" value="{{ $selected->layout_version }}">
                <input type="hidden" name="ensure_placements" value="1">
                <div class="overflow-x-auto">
                    <div
                        id="floor-grid"
                        class="floor-plan-canvas"
                        style="grid-template-columns: repeat({{ $columns }}, minmax(10.5rem, 1fr)); grid-template-rows: repeat({{ $rows }}, 11.5rem); min-width: {{ $columns * 180 }}px;"
                        data-columns="{{ $columns }}"
                        data-rows="{{ $rows }}"
                    >
                        @for ($row = 0; $row < $rows; $row++)
                            @for ($column = 0; $column < $columns; $column++)
                                <div
                                    class="floor-plan-cell {{ ($row + $column) % 2 === 0 ? 'is-alt' : '' }}"
                                    data-cell
                                    data-x="{{ $column }}"
                                    data-y="{{ $row }}"
                                    style="grid-column: {{ $column + 1 }}; grid-row: {{ $row + 1 }};"
                                ></div>
                            @endfor
                        @endfor
                        @foreach ($placed->values() as $index => $table)
                            @php
                                $statusLabel = $statuses[$table->id] ?? 'Available';
                                $statusClass = match ($statusLabel) {
                                    'Occupied' => 'floor-plan-status-occupied',
                                    'Cleaning' => 'floor-plan-status-cleaning',
                                    'Merged' => 'floor-plan-status-merged',
                                    default => 'floor-plan-status-available',
                                };
                                $chairCount = min(max((int) $table->capacity, 1), 8);
                                $chairBucket = $chairCount <= 2 ? 2 : ($chairCount <= 4 ? 4 : ($chairCount <= 6 ? 6 : 8));
                                $scaleClass = \App\Services\Table\TableLayout::illustrationClass((int) $table->capacity);
                            @endphp
                            <article
                                draggable="true"
                                data-layout-card
                                data-number="{{ $table->number }}"
                                class="floor-plan-table {{ $statusClass }}"
                                style="grid-column: {{ ((int) $table->layout_x) + 1 }}; grid-row: {{ ((int) $table->layout_y) + 1 }};"
                            >
                                <input type="hidden" name="placements[{{ $index }}][table_id]" value="{{ $table->id }}">
                                <div class="floor-plan-figure {{ $scaleClass }} floor-plan-chairs-{{ $chairBucket }}">
                                    @for ($chair = 0; $chair < $chairCount; $chair++)
                                        <span class="floor-plan-chair" aria-hidden="true"></span>
                                    @endfor
                                    <div class="floor-plan-top">
                                        <p class="floor-plan-number">{{ $table->number }}</p>
                                        <p class="floor-plan-seats">{{ $table->capacity }} {{ $table->capacity === 1 ? 'seat' : 'seats' }} · {{ $statusLabel }}</p>
                                    </div>
                                </div>
                                <div class="floor-plan-meta">
                                    <label>
                                        <span class="sr-only">Column</span>
                                        <select name="placements[{{ $index }}][x]" data-axis="x" aria-label="Column for {{ $table->number }}">
                                            @for ($column = 0; $column < $columns; $column++)
                                                <option value="{{ $column }}" @selected((int) $table->layout_x === $column)>C{{ $column + 1 }}</option>
                                            @endfor
                                        </select>
                                    </label>
                                    <label>
                                        <span class="sr-only">Row</span>
                                        <select name="placements[{{ $index }}][y]" data-axis="y" aria-label="Row for {{ $table->number }}">
                                            @for ($row = 0; $row < $rows; $row++)
                                                <option value="{{ $row }}" @selected((int) $table->layout_y === $row)>R{{ $row + 1 }}</option>
                                            @endfor
                                        </select>
                                    </label>
                                    <button type="button" data-unplace>Remove</button>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
                <p id="layout-warning" class="mt-3 hidden text-sm font-semibold text-red-700"></p>
                <div class="mt-3">
                    <button id="layout-save" type="submit" class="rounded-lg bg-[#04346a] px-4 py-2 text-sm font-semibold text-white">Save layout</button>
                </div>
            </form>
        </section>

        <section class="rounded-xl bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold">Assign an existing table</h2>
            <p class="mt-1 text-sm text-[#898989]">Choose a table that is not currently placed on this floor. Tables already on another floor stay listed with that floor. Assigning moves the table record only. Orders, bills, and status stay on the same table.</p>
            <form method="POST" action="{{ route('admin.floor-layout.assign', $selected) }}" class="mt-4 grid gap-3 sm:grid-cols-4">
                @csrf
                <input type="hidden" name="layout_version" value="{{ $selected->layout_version }}">
                <label class="block text-sm font-semibold sm:col-span-2">
                    Table
                    <select name="table_id" required class="mt-1 w-full rounded-lg border border-[#d5dde7] px-3 py-2 font-medium">
                        <option value="">Select a table</option>
                        @foreach ($others as $table)
                            <option value="{{ $table->id }}">
                                {{ $table->number }} · {{ $table->capacity }} seats · {{ $statuses[$table->id] ?? '' }}
                                · {{ $table->floor?->name ?? 'No floor' }}{{ $table->floor_id === $selected->id ? ' · not positioned' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-semibold">
                    Column
                    <select name="x" class="mt-1 w-full rounded-lg border border-[#d5dde7] px-3 py-2 font-medium">
                        @for ($column = 0; $column < $columns; $column++)
                            <option value="{{ $column }}">{{ $column + 1 }}</option>
                        @endfor
                    </select>
                </label>
                <label class="block text-sm font-semibold">
                    Row
                    <select name="y" class="mt-1 w-full rounded-lg border border-[#d5dde7] px-3 py-2 font-medium">
                        @for ($row = 0; $row < $rows; $row++)
                            <option value="{{ $row }}">{{ $row + 1 }}</option>
                        @endfor
                    </select>
                </label>
                <div class="sm:col-span-4">
                    <button type="submit" class="rounded-lg bg-[#04346a] px-4 py-2 text-sm font-semibold text-white">Assign table</button>
                </div>
            </form>
            <div id="removed-tables" class="mt-4 text-sm text-[#898989]"></div>
        </section>
    @endif
@endsection

@push('scripts')
    <script>
        (() => {
            const dialog = document.getElementById('floor-dialog')
            const form = document.getElementById('floor-details-form')
            const method = document.getElementById('floor-form-method')
            const preview = document.getElementById('floor-preview')
            const warning = document.getElementById('floor-preview-warning')
            const save = document.getElementById('floor-form-save')
            if (!dialog || !form || !method || !preview || !warning || !save) return

            const storeUrl = @json(route('admin.floor-layout.floors.store'));
            const updateUrl = @json($selected ? route('admin.floor-layout.floors.update', $selected) : null);
            const placed = @json($previewTables);
            const current = @json($floorForm);

            const draw = (usePlaced) => {
                const rows = Math.max(1, Number(document.getElementById('floor-form-rows').value) || 1)
                const columns = Math.max(1, Number(document.getElementById('floor-form-columns').value) || 1)
                const tables = usePlaced ? placed : []
                const outside = tables.filter((table) => table.x >= columns || table.y >= rows)
                preview.innerHTML = ''
                const grid = document.createElement('div')
                grid.className = 'grid gap-1 rounded-lg border border-[#d5dde7] bg-[#f8fafc] p-2'
                grid.style.gridTemplateColumns = `repeat(${columns}, minmax(1.5rem, 1fr))`
                grid.style.gridTemplateRows = `repeat(${rows}, 1.5rem)`
                grid.style.minWidth = `${columns * 28}px`
                tables.forEach((table) => {
                    if (outside.includes(table)) return
                    const cell = document.createElement('div')
                    cell.className = 'rounded bg-[#04346a] text-[10px] font-bold text-white'
                    cell.style.gridColumn = `${table.x + 1}`
                    cell.style.gridRow = `${table.y + 1}`
                    cell.textContent = table.number
                    grid.appendChild(cell)
                })
                preview.appendChild(grid)
                if (outside.length) {
                    warning.textContent = `These tables must be moved before this grid can be saved: ${outside.map((table) => table.number).join(', ')}.`
                    warning.classList.remove('hidden')
                    save.disabled = true
                } else {
                    warning.textContent = ''
                    warning.classList.add('hidden')
                    save.disabled = false
                }
            }

            const open = (editing) => {
                form.action = editing ? updateUrl : storeUrl
                method.disabled = !editing
                method.value = editing ? 'PUT' : 'POST'
                document.getElementById('floor-form-title').textContent = editing ? 'Edit Floor' : 'Add Floor'
                document.getElementById('floor-form-name').value = editing ? current.name : ''
                document.getElementById('floor-form-description').value = editing ? (current.description || '') : ''
                document.getElementById('floor-form-rows').value = editing ? current.rows : 4
                document.getElementById('floor-form-columns').value = editing ? current.columns : 5
                document.getElementById('floor-form-code').value = editing ? current.code : ''
                document.getElementById('floor-form-code').disabled = !editing
                document.getElementById('floor-form-sort').value = editing ? current.sort_order : ''
                document.getElementById('floor-form-sort').disabled = !editing
                document.getElementById('floor-form-active').value = editing ? String(current.is_active) : '1'
                draw(editing)
                dialog.showModal()
            }

            document.getElementById('add-floor')?.addEventListener('click', () => open(false))
            document.getElementById('edit-floor')?.addEventListener('click', () => open(true))
            document.getElementById('floor-form-cancel')?.addEventListener('click', () => dialog.close())
            document.getElementById('floor-form-rows')?.addEventListener('input', () => draw(method.value === 'PUT' && !method.disabled))
            document.getElementById('floor-form-columns')?.addEventListener('input', () => draw(method.value === 'PUT' && !method.disabled))

            if (@json($errors->any())) {
                const editing = @json(old('_method')) === 'PUT' && Boolean(updateUrl)
                form.action = editing ? updateUrl : storeUrl
                method.disabled = !editing
                method.value = editing ? 'PUT' : 'POST'
                document.getElementById('floor-form-title').textContent = editing ? 'Edit Floor' : 'Add Floor'
                document.getElementById('floor-form-name').value = @json(old('name', ''));
                document.getElementById('floor-form-description').value = @json(old('description', ''));
                document.getElementById('floor-form-rows').value = @json(old('layout_rows', 4));
                document.getElementById('floor-form-columns').value = @json(old('layout_columns', 5));
                document.getElementById('floor-form-code').disabled = !editing
                document.getElementById('floor-form-sort').disabled = !editing
                draw(editing)
                dialog.showModal()
            }
        })()
    </script>
    <script>
        (() => {
            const grid = document.getElementById('floor-grid')
            const form = document.getElementById('floor-layout-form')
            const warning = document.getElementById('layout-warning')
            const save = document.getElementById('layout-save')
            if (!grid || !form || !warning || !save) return

            const columns = Number(grid.dataset.columns)
            const rows = Number(grid.dataset.rows)
            let dragging = null

            const cards = () => [...grid.querySelectorAll('[data-layout-card]')]
            const cells = () => [...grid.querySelectorAll('[data-cell]')]

            const clearTargets = () => {
                cells().forEach((cell) => cell.classList.remove('is-drop', 'is-drop-invalid'))
            }

            const cellOccupied = (x, y, card) => cards().some((other) => {
                if (other === card) return false
                return Number(other.querySelector('[data-axis="x"]').value) === x
                    && Number(other.querySelector('[data-axis="y"]').value) === y
            })

            const cellAt = (clientX, clientY) => {
                const hit = cells().find((cell) => {
                    const rect = cell.getBoundingClientRect()
                    return clientX >= rect.left && clientX <= rect.right && clientY >= rect.top && clientY <= rect.bottom
                })
                if (hit) return hit
                return cells().reduce((nearest, cell) => {
                    const rect = cell.getBoundingClientRect()
                    const distance = Math.hypot(clientX - (rect.left + rect.width / 2), clientY - (rect.top + rect.height / 2))
                    return !nearest || distance < nearest.distance ? { cell, distance } : nearest
                }, null)?.cell ?? null
            }

            const highlight = (card, x, y) => {
                clearTargets()
                if (!card) return
                const target = cells().find((cell) => Number(cell.dataset.x) === x && Number(cell.dataset.y) === y)
                target?.classList.add(cellOccupied(x, y, card) ? 'is-drop-invalid' : 'is-drop')
            }

            const place = (card) => {
                const x = Number(card.querySelector('[data-axis="x"]').value)
                const y = Number(card.querySelector('[data-axis="y"]').value)
                card.style.gridColumn = `${x + 1}`
                card.style.gridRow = `${y + 1}`
            }

            const overlaps = () => {
                const boxes = cards().map((card) => ({
                    card,
                    x: Number(card.querySelector('[data-axis="x"]').value),
                    y: Number(card.querySelector('[data-axis="y"]').value),
                }))
                let invalid = false
                boxes.forEach((box) => box.card.classList.remove('ring-2', 'ring-red-600'))
                boxes.forEach((left, index) => {
                    boxes.slice(index + 1).forEach((right) => {
                        if (left.x === right.x && left.y === right.y) {
                            invalid = true
                            left.card.classList.add('ring-2', 'ring-red-600')
                            right.card.classList.add('ring-2', 'ring-red-600')
                        }
                    })
                })
                warning.textContent = invalid ? 'Two tables cannot share the same cell.' : ''
                warning.classList.toggle('hidden', !invalid)
                save.disabled = invalid
            }

            const selectCard = (card) => {
                cards().forEach((item) => item.classList.toggle('is-selected', item === card))
            }

            cards().forEach((card) => {
                card.addEventListener('click', () => selectCard(card))
                card.addEventListener('dragstart', (event) => {
                    dragging = card
                    card.classList.add('is-dragging')
                    selectCard(card)
                    event.dataTransfer?.setData('text/plain', card.dataset.number || '')
                    event.dataTransfer.effectAllowed = 'move'
                })
                card.addEventListener('dragend', () => {
                    dragging = null
                    card.classList.remove('is-dragging')
                    clearTargets()
                })
                card.querySelectorAll('select').forEach((select) => {
                    select.addEventListener('change', () => {
                        place(card)
                        overlaps()
                    })
                    select.addEventListener('mousedown', (event) => event.stopPropagation())
                })
                const remove = card.querySelector('[data-unplace]')
                remove.addEventListener('click', () => {
                    const list = document.getElementById('removed-tables')
                    const note = document.createElement('p')
                    note.textContent = `${card.dataset.number} will be unplaced when you save.`
                    list.appendChild(note)
                    card.remove()
                    overlaps()
                })
            })

            grid.addEventListener('dragover', (event) => {
                event.preventDefault()
                if (!dragging) return
                const cell = cellAt(event.clientX, event.clientY)
                if (!cell) return
                highlight(dragging, Number(cell.dataset.x), Number(cell.dataset.y))
            })
            grid.addEventListener('drop', (event) => {
                event.preventDefault()
                if (!dragging) return
                const cell = cellAt(event.clientX, event.clientY)
                clearTargets()
                if (!cell) return
                const x = Number(cell.dataset.x)
                const y = Number(cell.dataset.y)
                if (cellOccupied(x, y, dragging)) {
                    overlaps()
                    return
                }
                dragging.querySelector('[data-axis="x"]').value = String(x)
                dragging.querySelector('[data-axis="y"]').value = String(y)
                place(dragging)
                overlaps()
            })

            overlaps()
        })()
    </script>
@endpush
