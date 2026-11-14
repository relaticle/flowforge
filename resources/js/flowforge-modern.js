const FLOWFORGE_TOKENS = [
    '--ff-surface',
    '--ff-surface-sunken',
    '--ff-surface-raised',
    '--ff-border',
    '--ff-text',
    '--ff-text-muted',
    '--ff-accent',
    '--ff-accent-tint',
    '--ff-danger',
    '--ff-warning',
    '--ff-radius',
    '--ff-gap',
    '--ff-ease',
    '--ff-duration',
    '--ff-column-width',
    '--ff-tray-bg',
    '--ff-tray-radius',
    '--ff-card-bg',
    '--ff-card-ring',
    '--ff-card-radius',
    '--ff-card-shadow',
    '--ff-card-padding',
    '--ff-drag-tilt',
    '--ff-drag-shadow',
    '--ff-placeholder-border',
];

const HEADER_GESTURE_IGNORED = '.ff-column-actions, .fi-dropdown-panel, input, textarea, select, [contenteditable]:not([contenteditable="false"])';

const EMPTY_SPACE_IGNORED = `.ff-card, .ff-column-header, .ff-add-slot, .ff-add-row, .ff-cards-more, .ff-limit-hint, button, a, ${HEADER_GESTURE_IGNORED}`;

const MENU_DRAG_THRESHOLD = 4;

// A rail under collapse all waits this long, so a second click can claim the column alone.
const DOUBLE_CLICK_WINDOW = 300;

const focusMenuField = (panel) => {
    const field = panel.querySelector('[data-autofocus]');

    // A touch screen would raise its keyboard for a menu opened to collapse or hide.
    if (!field || panel.style.display !== 'block' || !window.matchMedia('(pointer: fine)').matches) {
        return;
    }

    field.focus({ preventScroll: true });
    field.setSelectionRange?.(field.value.length, field.value.length);
};

const menusIgnoringMousedown = new WeakSet();

const sortablesAwaitingDrag = new WeakSet();

const sortableIsBusy = () => window.Sortable.active || window.Sortable.dragged;

export default function flowforgeModern() {
    return {
        isLoading: {},
        fullyLoaded: {},
        dragOverColumn: null,

        railToggledAt: 0,
        railClickTimer: null,
        columnMenuPressAt: null,
        railPressAt: null,
        dragOverLane: null,
        dragSource: null,
        pendingCounts: {},
        pendingMoves: 0,
        collapsedLanes: {},
        addingColumn: false,
        newColumnName: '',
        newColumnColor: null,
        newColumnDotStyle: '',
        choosingNewColumnColor: false,
        newColumnTouched: false,
        addingColumnPending: false,
        placeholder: null,
        stopTrackingPointer: null,

        init() {
            this.$wire.$on('kanban-items-loaded', (event) => {
                const { columnId, isFullyLoaded } = event;

                if (isFullyLoaded) {
                    this.fullyLoaded[columnId] = true;
                }
            });
        },

        configureSortable(el) {
            if (!el.sortable || el.sortable.options.forceFallback) {
                return;
            }

            if (sortableIsBusy()) {
                this.configureSortableAfterDrag(el);

                return;
            }

            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            // SortableJS reads forceFallback only in its constructor, so the instance Filament built has to be replaced.
            el.sortable.destroy();

            el.sortable = window.Sortable.create(el, {
                draggable: '[x-sortable-item]',
                handle: '[x-sortable-handle]',
                dataIdAttr: 'x-sortable-item',
                forceFallback: true,
                // A click that wobbles a few pixels must stay a click; at 0 any pointermove starts a drag.
                fallbackTolerance: 5,
                fallbackOnBody: true,
                fallbackClass: '',
                ghostClass: '',
                delay: 150,
                delayOnTouchOnly: true,
                animation: reduceMotion ? 0 : 200,
                scrollSensitivity: 100,
                scrollSpeed: 15,
                forceAutoScrollFallback: true,
                group: {
                    name: el.getAttribute('x-sortable-group'),
                    pull: true,
                    put: (to, from) => this.canDrop(to.el, from.el),
                },
                onStart: (event) => this.startDrag(event),
                onEnd(event) {
                    const { item: draggedNode, to: parentNode, oldDraggableIndex, newDraggableIndex } = event;

                    if (oldDraggableIndex === newDraggableIndex) {
                        return;
                    }

                    const previousNode = parentNode.querySelectorAll(`:scope > ${this.options.draggable}`)[
                        newDraggableIndex - 1
                    ];

                    if (previousNode) {
                        parentNode.insertBefore(draggedNode, previousNode.nextSibling);
                    }
                },
            });
        },

        configureSortableAfterDrag(el) {
            if (sortablesAwaitingDrag.has(el)) {
                return;
            }

            sortablesAwaitingDrag.add(el);

            // destroy() runs _nulling() on Sortable's global state, which kills a live drag or a pending press.
            // dragged is set on pointerdown, active only a tick after the first move.
            const retry = () => {
                if (sortableIsBusy()) {
                    requestAnimationFrame(retry);

                    return;
                }

                sortablesAwaitingDrag.delete(el);

                if (el.isConnected) {
                    this.configureSortable(el);
                }
            };

            requestAnimationFrame(retry);
        },

        startDrag(event) {
            this.stopTrackingPointer?.();
            this.placeholder = event.item;
            this.placeholder.setAttribute('data-placeholder', '');
            this.markGhost();

            // WebKit turns a fallback-mode drag into a text selection across the board.
            window.getSelection()?.removeAllRanges();
            document.documentElement.classList.add('ff-dragging');

            this.dragSource = {
                column: event.from.dataset.columnId,
                lane: event.from.dataset.laneId ?? null,
            };

            const track = (pointerEvent) => this.trackPointer(pointerEvent);

            document.addEventListener('pointermove', track, { passive: true });
            document.addEventListener('touchmove', track, { passive: true });

            this.stopTrackingPointer = () => {
                document.removeEventListener('pointermove', track);
                document.removeEventListener('touchmove', track);
            };
        },

        trackPointer(pointerEvent) {
            if (!window.Sortable.active) {
                this.endDrag();

                return;
            }

            this.markGhost();

            const point = pointerEvent.touches?.[0] ?? pointerEvent;
            const target = document.elementFromPoint(point.clientX, point.clientY);
            const cell = target?.closest('[data-column-id]');
            const root = target?.closest('[data-column-root]');

            this.dragOverColumn = cell?.dataset.columnId ?? root?.dataset.columnRoot ?? null;
            this.dragOverLane = cell?.dataset.laneId ?? null;
        },

        markGhost() {
            const ghost = window.Sortable.ghost;

            if (!ghost || ghost.hasAttribute('data-dragging')) {
                return;
            }

            ghost.removeAttribute('data-placeholder');
            ghost.setAttribute('data-dragging', '');
            ghost.style.opacity = '1';

            // The fallback clone lives on document.body, so it inherits no token from the board or its column.
            const source = getComputedStyle(this.placeholder);

            for (const name of FLOWFORGE_TOKENS) {
                const value = source.getPropertyValue(name);

                if (value !== '') {
                    ghost.style.setProperty(name, value);
                }
            }
        },

        endDrag() {
            document.documentElement.classList.remove('ff-dragging');
            this.placeholder?.removeAttribute('data-placeholder');
            this.placeholder = null;
            this.stopTrackingPointer?.();
            this.stopTrackingPointer = null;
            this.dragOverColumn = null;
            this.dragOverLane = null;
            this.dragSource = null;
        },

        railPress(event) {
            this.railPressAt = { x: event.clientX, y: event.clientY };
        },

        toggleColumnCollapse(event, columnId, fromRail = false) {
            if (event.target.closest(HEADER_GESTURE_IGNORED) || (fromRail && event.detail > 1)) {
                return;
            }

            if (fromRail) {
                const press = this.railPressAt;

                this.railPressAt = null;

                if (press && Math.hypot(event.clientX - press.x, event.clientY - press.y) > MENU_DRAG_THRESHOLD) {
                    return;
                }
            }

            // The click that expands a rail must not let its double click collapse the header again.
            if (Date.now() - this.railToggledAt < 500) {
                return;
            }

            if (fromRail) {
                this.railToggledAt = Date.now();
            }

            this.$wire.toggleBoardColumnCollapse(columnId);
        },

        expandFromCollapseAll(event, columnId) {
            const press = this.railPressAt;

            this.railPressAt = null;

            if (event.target.closest(HEADER_GESTURE_IGNORED) || (press && Math.hypot(event.clientX - press.x, event.clientY - press.y) > MENU_DRAG_THRESHOLD)) {
                return;
            }

            const alone = event.detail > 1;

            clearTimeout(this.railClickTimer);
            this.railClickTimer = setTimeout(() => {
                this.railToggledAt = Date.now();
                this.$wire.toggleBoardColumnCollapse(columnId, alone);
            }, alone ? 0 : DOUBLE_CLICK_WINDOW);
        },

        collapseFromEmptySpace(event, columnId) {
            if (!event.target.closest(EMPTY_SPACE_IGNORED)) {
                this.toggleColumnCollapse(event, columnId);
            }
        },

        suppressEmptySpaceSelection(event) {
            if (event.detail > 1 && !event.target.closest(EMPTY_SPACE_IGNORED)) {
                event.preventDefault();
            }
        },

        openNewColumn() {
            this.addingColumn = true;
            this.newColumnTouched = true;
            // WebKit ignores a focus on the next tick, right after the ghost column shows. One frame later it takes it.
            this.$nextTick(() => requestAnimationFrame(() => {
                this.$refs.newColumnInput.focus({ preventScroll: true });
                this.$refs.newColumnInput.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            }));
        },

        cancelNewColumn() {
            this.addingColumn = false;
            this.newColumnTouched = true;
            this.newColumnName = '';
            this.newColumnColor = null;
            this.newColumnDotStyle = '';
            this.choosingNewColumnColor = false;
        },

        pickNewColumnColor(color, style) {
            this.newColumnColor = color;
            this.newColumnDotStyle = style;
            this.choosingNewColumnColor = false;
            this.$refs.newColumnInput.focus({ preventScroll: true });
        },

        async submitNewColumn() {
            if (this.addingColumnPending) {
                return;
            }

            this.addingColumnPending = true;
            this.newColumnTouched = true;

            try {
                await this.$wire.addBoardColumn(this.newColumnName, this.newColumnColor);
            } finally {
                this.addingColumnPending = false;
            }

            this.newColumnTouched = false;

            if (!this.$root.querySelector('.ff-add-column-error')) {
                this.cancelNewColumn();
            }
        },

        suppressDoubleClickSelection(event) {
            if (event.detail > 1 && !event.target.closest(HEADER_GESTURE_IGNORED)) {
                event.preventDefault();
            }
        },

        // Filament opens a dropdown on mousedown, which would open the menu at the start of a header drag.
        initColumnMenu(trigger) {
            const root = trigger.closest('.fi-dropdown');

            if (menusIgnoringMousedown.has(root)) {
                return;
            }

            const dropdown = window.Alpine.$data(root);
            const toggle = dropdown.toggle.bind(dropdown);

            menusIgnoringMousedown.add(root);
            dropdown.toggle = (event) => (event?.type === 'mousedown' ? undefined : toggle(event));
        },

        columnMenuPress(event) {
            this.columnMenuPressAt = { x: event.clientX, y: event.clientY };
        },

        columnMenuClick(event) {
            const press = this.columnMenuPressAt;

            this.columnMenuPressAt = null;

            if (event.detail > 1 || (press && Math.hypot(event.clientX - press.x, event.clientY - press.y) > MENU_DRAG_THRESHOLD)) {
                return;
            }

            const root = event.currentTarget.closest('.fi-dropdown');

            window.Alpine.$data(root).toggle(event);
            requestAnimationFrame(() => focusMenuField(root.querySelector(':scope > .fi-dropdown-panel')));
        },

        columnDrop(columnId) {
            if (this.dragOverColumn !== columnId) {
                return null;
            }

            return this.isColumnBlocked(columnId) ? 'blocked' : 'active';
        },

        cellDrop(columnId, laneId) {
            if (this.dragOverColumn !== columnId || this.dragOverLane !== laneId) {
                return null;
            }

            return this.isCellBlocked(columnId, laneId) ? 'blocked' : 'active';
        },

        canDrop(toEl, fromEl) {
            if (!this.$root.contains(toEl) || !this.$root.contains(fromEl)) {
                return false;
            }

            const toLane = toEl.dataset.laneId ?? null;
            const fromLane = fromEl.dataset.laneId ?? null;

            if (toLane !== fromLane && this.$root.dataset.lanesWritable !== 'true') {
                return false;
            }

            return !this.isColumnBlocked(toEl.dataset.columnId, fromEl.dataset.columnId);
        },

        isColumnBlocked(columnId, sourceColumnId = this.dragSource?.column) {
            if (!columnId || columnId === sourceColumnId) {
                return false;
            }

            const root = this.$root.querySelector(`[data-column-root="${CSS.escape(columnId)}"]`);
            const limit = root?.dataset.columnLimit;

            if (limit === undefined || limit === '') {
                return false;
            }

            return Number(root.dataset.columnTotal) >= Number(limit);
        },

        isCellBlocked(columnId, laneId) {
            if (!this.dragSource) {
                return false;
            }

            if (laneId !== this.dragSource.lane && this.$root.dataset.lanesWritable !== 'true') {
                return true;
            }

            return this.isColumnBlocked(columnId);
        },

        displayCount(columnId, total) {
            const count = this.pendingCounts[columnId] ?? total;
            const source = this.dragSource?.column;
            const target = this.dragOverColumn;

            if (!source || !target || source === target || this.isColumnBlocked(target)) {
                return count;
            }

            if (columnId === target) {
                return count + 1;
            }

            if (columnId === source) {
                return count - 1;
            }

            return count;
        },

        holdMovedCounts(sourceColumn, targetColumn) {
            if (sourceColumn === targetColumn) {
                return () => {};
            }

            const changes = { [sourceColumn]: -1, [targetColumn]: 1 };
            let released = false;

            for (const [columnId, change] of Object.entries(changes)) {
                this.pendingCounts[columnId] = this.renderedCount(columnId) + change;
            }

            this.pendingMoves++;

            return (refused) => {
                if (released) {
                    return;
                }

                released = true;

                if (refused) {
                    for (const [columnId, change] of Object.entries(changes)) {
                        this.pendingCounts[columnId] -= change;
                    }
                }

                if (--this.pendingMoves === 0) {
                    this.pendingCounts = {};
                }
            };
        },

        renderedCount(columnId) {
            const count = this.$root.querySelector(`[data-column-root="${CSS.escape(columnId)}"] .ff-count`);

            return this.pendingCounts[columnId] ?? Number(count?.dataset.total);
        },

        handleSortableEnd(event) {
            this.endDrag();

            const draggedNode = event.item;
            const parentNode = event.to;
            const dropIndex = event.newDraggableIndex ?? event.newIndex;
            const itemSelector = ':scope > [x-sortable-item], :scope > [data-card-id]';
            const readId = (el) => el.getAttribute('x-sortable-item') || el.getAttribute('data-card-id');

            if (event.from === parentNode && event.oldDraggableIndex === event.newDraggableIndex) {
                return;
            }

            // Filament's x-sortable re-inserts the node after items[dropIndex - 1] (filamentphp/filament#17402)
            // but skips index 0, leaving the DOM stale on a top drop.
            if (dropIndex === 0) {
                const firstItem = parentNode.querySelector(itemSelector);

                if (firstItem && firstItem !== draggedNode) {
                    parentNode.insertBefore(draggedNode, firstItem);
                }
            }

            const cardId = readId(draggedNode);

            if (!cardId) {
                console.error('Flowforge: Could not determine card ID for move operation');
                return;
            }

            const targetColumn = parentNode.getAttribute('data-column-id');

            if (!targetColumn) {
                console.error('Flowforge: Target column ID is missing');
                return;
            }

            const fromLane = event.from.dataset.laneId ?? null;
            const toLane = parentNode.dataset.laneId ?? null;
            const items = parentNode.querySelectorAll(itemSelector);
            const index = Array.prototype.indexOf.call(items, draggedNode);
            const afterCardId = index > 0 ? readId(items[index - 1]) : null;
            const beforeCardId = index >= 0 && index < items.length - 1 ? readId(items[index + 1]) : null;

            this.setCardState(draggedNode, true);

            const releaseCounts = this.holdMovedCounts(event.from.dataset.columnId, targetColumn);

            this.$wire.moveCard(cardId, targetColumn, afterCardId, beforeCardId, fromLane, toLane)
                .then((moved) => {
                    this.setCardState(draggedNode, false);

                    releaseCounts(moved === false);

                    if (moved === false) {
                        this.revertCard(event);
                    }
                })
                .catch(() => {
                    this.setCardState(draggedNode, false);
                    releaseCounts(true);
                    this.revertCard(event);
                });
        },

        revertCard({ item, from, oldDraggableIndex }) {
            item.remove();

            const siblings = from.querySelectorAll(':scope > [x-sortable-item]');
            const lastItem = siblings[siblings.length - 1];
            const anchor = siblings[oldDraggableIndex] ?? (lastItem ? lastItem.nextSibling : from.firstChild);

            from.insertBefore(item, anchor);
        },

        setCardState(cardElement, pending) {
            cardElement.toggleAttribute('data-pending', pending);
        },

        isLaneCollapsed(laneId) {
            return this.collapsedLanes[laneId] === true;
        },

        toggleLane(laneId) {
            this.collapsedLanes[laneId] = ! this.isLaneCollapsed(laneId);
        },

        isLoadingColumn(columnId) {
            return this.isLoading[columnId] || false;
        },

        isColumnFullyLoaded(columnId) {
            return this.fullyLoaded[columnId] || false;
        },

        handleSmoothScroll(columnId) {
            if (this.isLoadingColumn(columnId) || this.isColumnFullyLoaded(columnId)) {
                return;
            }

            this.isLoading[columnId] = true;

            this.$wire.loadMoreItems(columnId)
                .then(() => setTimeout(() => this.isLoading[columnId] = false, 100))
                .catch(() => this.isLoading[columnId] = false);
        },

        handleColumnScroll(event, columnId) {
            if (this.isColumnFullyLoaded(columnId)) {
                return;
            }

            const { scrollTop, scrollHeight, clientHeight } = event.target;

            if ((scrollTop + clientHeight) / scrollHeight >= 0.8 && !this.isLoadingColumn(columnId)) {
                this.handleSmoothScroll(columnId);
            }
        },
    };
}
