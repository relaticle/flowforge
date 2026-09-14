export default function flowforge({state}) {
    return {
        state,
        isLoading: {},
        fullyLoaded: {},
        collapsedSwimlanes: {},

        init() {
            this.$wire.$on('kanban-items-loaded', (event) => {
                const { columnId, isFullyLoaded } = event;
                if (isFullyLoaded) {
                    this.fullyLoaded[columnId] = true;
                }
            });

            // Restore collapsed swimlane state from localStorage
            if (this.state.swimlanes) {
                this._restoreSwimlaneState();
            }

            this.$nextTick(() => this.enhanceSortableScroll());
        },

        enhanceSortableScroll() {
            this.$el.querySelectorAll('[x-sortable]').forEach(el => {
                if (el.sortable) {
                    el.sortable.option('scrollSensitivity', 100);
                    el.sortable.option('scrollSpeed', 15);
                    el.sortable.option('forceAutoScrollFallback', true);
                }
            });
        },

        handleSortableEnd(event) {
            const draggedNode = event.item;
            const parentNode = event.to;
            // SortableJS exposes both newIndex (across all children) and
            // newDraggableIndex (draggable children only). Prefer the draggable
            // index where available and fall back to newIndex for older or
            // alternative SortableJS builds.
            const dropIndex = event.newDraggableIndex ?? event.newIndex;

            const itemSelector = ':scope > [x-sortable-item], :scope > [data-card-id]';
            const readId = (el) => el.getAttribute('x-sortable-item') || el.getAttribute('data-card-id');

            // Filament's shared x-sortable directive re-inserts the dragged
            // node after items[dropIndex - 1] to work around
            // filamentphp/filament#17402, but that branch is skipped when
            // dropIndex === 0 — leaving the DOM stale on top drops. Extend
            // the workaround so the top position is normalized before we
            // read neighbors.
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

            // Derive neighbors from the normalized DOM rather than
            // sortable.toArray(), which can return stale order when SortableJS
            // leaves the DOM out of sync.
            const items = parentNode.querySelectorAll(itemSelector);
            const index = Array.prototype.indexOf.call(items, draggedNode);
            const afterCardId = index > 0 ? readId(items[index - 1]) : null;
            const beforeCardId = index >= 0 && index < items.length - 1
                ? readId(items[index + 1])
                : null;

            this.setCardState(draggedNode, true);

            this.$wire.moveCard(cardId, targetColumn, afterCardId, beforeCardId)
                .then(() => this.setCardState(draggedNode, false))
                .catch(() => this.setCardState(draggedNode, false));
        },

        setCardState(cardElement, disabled) {
            cardElement.style.opacity = disabled ? '0.7' : '';
            cardElement.style.pointerEvents = disabled ? 'none' : '';
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
            if (this.isColumnFullyLoaded(columnId)) return;

            const { scrollTop, scrollHeight, clientHeight } = event.target;
            const scrollPercentage = (scrollTop + clientHeight) / scrollHeight;

            if (scrollPercentage >= 0.8 && !this.isLoadingColumn(columnId)) {
                this.handleSmoothScroll(columnId);
            }
        },

        // --- Swimlane collapse/expand ---

        toggleSwimlane(swimlaneId) {
            this.collapsedSwimlanes[swimlaneId] = !this.collapsedSwimlanes[swimlaneId];
            this._saveSwimlaneState();
        },

        isSwimlaneCollapsed(swimlaneId) {
            return this.collapsedSwimlanes[swimlaneId] || false;
        },

        _getSwimlaneStorageKey() {
            // Use the page URL path as a board-specific key
            return 'flowforge:swimlanes:' + window.location.pathname;
        },

        _saveSwimlaneState() {
            try {
                localStorage.setItem(
                    this._getSwimlaneStorageKey(),
                    JSON.stringify(this.collapsedSwimlanes)
                );
            } catch (e) {
                // localStorage may be unavailable; ignore silently
            }
        },

        _restoreSwimlaneState() {
            try {
                const stored = localStorage.getItem(this._getSwimlaneStorageKey());
                if (stored) {
                    this.collapsedSwimlanes = JSON.parse(stored);
                }
            } catch (e) {
                this.collapsedSwimlanes = {};
            }
        },
    }
}
