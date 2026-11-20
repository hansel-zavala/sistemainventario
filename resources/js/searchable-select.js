const normalizeSearch = (text) => text.trim().normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();

document.addEventListener('alpine:init', () => {
    window.Alpine.data('searchableSelect', (model) => ({
        options: [],
        open: false,
        focused: false,
        query: null,
        activeIndex: -1,
        controlWidth: 0,

        init() {
            this.readOptions();
        },

        readOptions() {
            this.options = JSON.parse(this.$root.dataset.options);
        },

        get selectedValue() {
            return String(this.$wire.$get(model) ?? '');
        },

        get selectedLabel() {
            return this.options.find(({ value }) => value === this.selectedValue)?.label ?? '';
        },

        get inputValue() {
            return this.focused ? (this.query ?? '') : this.selectedLabel;
        },

        get matches() {
            const search = normalizeSearch(this.query ?? '');
            return search ? this.options.filter(({ label }) => normalizeSearch(label).includes(search)) : this.options;
        },

        get activeOptionId() {
            const option = this.matches[this.activeIndex];
            return this.open && option ? this.optionId(option) : null;
        },

        optionId(option) {
            return `${this.$refs.input.id}-option-${option.value}`;
        },

        openMenu() {
            if (this.open) return;

            this.readOptions();
            this.open = true;
            this.$nextTick(() => {
                const popup = this.$refs.popup;
                if (!this.open || !popup?.isConnected) return;

                popup.showPopover?.();
                popup.scrollTop = 0;
            });
        },

        toggleMenu() {
            this.$refs.input.focus({ preventScroll: true });
            if (this.open) {
                this.close();
                return;
            }

            this.openMenu();
        },

        close() {
            this.open = false;
            this.query = null;
            this.activeIndex = -1;
            this.$refs.popup.hidePopover?.();
        },

        search(text) {
            this.openMenu();
            this.query = text;
            this.activeIndex = this.matches.length === 1 ? 0 : -1;
            this.$refs.popup.scrollTop = 0;
        },

        choose(option) {
            if (option.value !== this.selectedValue) this.$wire.$set(model, option.value);
            this.close();
        },

        moveActive(direction) {
            this.openMenu();
            const lastIndex = this.matches.length - 1;
            if (lastIndex < 0) return;

            this.activeIndex = this.activeIndex === -1 && direction < 0
                ? lastIndex
                : Math.min(lastIndex, Math.max(0, this.activeIndex + direction));
            this.$nextTick(() => {
                const option = document.getElementById(this.activeOptionId);
                option?.scrollIntoView({ block: 'nearest' });
            });
        },

        handleKeydown(event) {
            if (event.isComposing || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;

            switch (event.key) {
                case 'ArrowDown':
                case 'ArrowUp':
                    event.preventDefault();
                    this.moveActive(event.key === 'ArrowDown' ? 1 : -1);
                    break;
                case 'Enter': {
                    event.preventDefault();
                    const option = this.matches[this.activeIndex];
                    if (option && this.open) this.choose(option);
                    else this.openMenu();
                    break;
                }
                case 'Escape':
                    if (!this.open) break;

                    event.preventDefault();
                    event.stopPropagation();
                    this.close();
                    break;
            }
        },
    }));
}, { once: true });
