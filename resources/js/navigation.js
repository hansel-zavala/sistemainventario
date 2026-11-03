const root = document.querySelector('[data-app-navigation]');

if (root) {
    const sidebar = root.querySelector('#app-sidebar');
    const content = root.querySelector('[data-page-content]');
    const toggle = root.querySelector('[data-sidebar-toggle]');
    const backdrop = root.querySelector('[data-sidebar-backdrop]');
    const close = root.querySelector('[data-sidebar-close]');
    const search = root.querySelector('[data-module-search]');
    const empty = root.querySelector('[data-search-empty]');
    const tooltip = root.querySelector('[data-navigation-tooltip]');
    const desktop = window.matchMedia('(min-width: 64rem)');
    const storageKey = `inventory.sidebar.${root.dataset.userId}`;
    const groups = [...root.querySelectorAll('[data-navigation-group]')].map((element) => ({
        element,
        id: element.dataset.groupId,
        button: element.querySelector('[data-group-toggle]'),
        panel: element.querySelector('[data-group-panel]'),
        links: [...element.querySelectorAll('[data-navigation-link]')],
    }));
    let expanded = {};
    let flyout = null;
    let mobileOpen = false;
    let compact = document.documentElement.dataset.sidebarCompact === 'true';

    try {
        const stored = JSON.parse(localStorage.getItem(`${storageKey}.groups`) || '{}');
        if (stored && typeof stored === 'object' && !Array.isArray(stored)) expanded = stored;
    } catch {}

    groups.forEach((group) => {
        expanded[group.id] = group.element.dataset.active === 'true' || expanded[group.id] === true;
    });

    const persist = (key, value) => {
        try { localStorage.setItem(`${storageKey}.${key}`, JSON.stringify(value)); } catch {}
    };
    const isCompact = () => desktop.matches && compact;
    const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
    const hideTooltip = () => { tooltip.hidden = true; };

    const renderGroups = () => {
        const query = normalize(search.value);
        let matches = 0;
        groups.forEach((group) => {
            const matchesGroup = normalize(group.button.textContent).includes(query);
            group.links.forEach((link) => {
                link.hidden = Boolean(query) && !matchesGroup && !normalize(link.textContent).includes(query);
                if (!link.hidden) matches++;
            });
            group.element.hidden = group.links.every((link) => link.hidden);
            const open = isCompact() ? flyout === group : Boolean(query) || expanded[group.id];
            group.panel.hidden = !open || group.element.hidden;
            group.button.setAttribute('aria-expanded', String(open && !group.element.hidden));
        });
        const dashboard = root.querySelector('nav > [data-navigation-link]');
        dashboard.hidden = Boolean(query) && !normalize(dashboard.textContent).includes(query);
        empty.hidden = matches > 0 || !dashboard.hidden;
    };

    const closeFlyout = (restoreFocus = false) => {
        const previous = flyout;
        flyout = null;
        renderGroups();
        if (restoreFocus) previous?.button.focus();
    };

    const positionFlyout = (group) => {
        const height = group.panel.getBoundingClientRect().height;
        group.panel.style.top = `${Math.max(16, Math.min(group.button.getBoundingClientRect().top, window.innerHeight - height - 16))}px`;
    };

    const syncToggle = () => {
        toggle.setAttribute('aria-expanded', String(desktop.matches ? !compact : mobileOpen));
        toggle.setAttribute('aria-label', desktop.matches ? (compact ? 'Expandir menú' : 'Contraer menú') : (mobileOpen ? 'Cerrar menú' : 'Abrir menú'));
    };

    const setMobileOpen = (open, restoreFocus = true) => {
        mobileOpen = open;
        root.dataset.mobileOpen = String(open);
        backdrop.hidden = !open;
        content.inert = open;
        sidebar.inert = !desktop.matches && !open;
        document.body.style.overflow = open ? 'hidden' : '';
        if (open) {
            sidebar.setAttribute('role', 'dialog');
            sidebar.setAttribute('aria-modal', 'true');
            close.focus();
        } else {
            sidebar.removeAttribute('role');
            sidebar.removeAttribute('aria-modal');
            if (restoreFocus) toggle.focus();
        }
        syncToggle();
    };

    const setCompact = (value) => {
        compact = value;
        document.documentElement.dataset.sidebarCompact = String(value);
        persist('compact', value);
        search.value = '';
        closeFlyout();
        hideTooltip();
        syncToggle();
    };

    toggle.addEventListener('click', () => desktop.matches ? setCompact(!compact) : setMobileOpen(!mobileOpen));
    close.addEventListener('click', () => setMobileOpen(false));
    backdrop.addEventListener('click', () => setMobileOpen(false));
    root.querySelector('[data-expand-search]').addEventListener('click', () => {
        setCompact(false);
        search.focus();
    });
    search.addEventListener('input', renderGroups);

    groups.forEach((group) => {
        group.button.addEventListener('click', () => {
            hideTooltip();
            if (isCompact()) {
                flyout = flyout === group ? null : group;
                renderGroups();
                if (flyout) positionFlyout(group);
            } else {
                const wasOpen = group.button.getAttribute('aria-expanded') === 'true';
                if (search.value) search.value = '';
                expanded[group.id] = !wasOpen;
                persist('groups', expanded);
                renderGroups();
            }
        });
    });

    root.querySelectorAll('[data-tooltip]').forEach((element) => {
        const showTooltip = () => {
            if (!isCompact() || flyout?.button === element) return;
            tooltip.textContent = element.dataset.tooltip;
            tooltip.hidden = false;
            const rect = element.getBoundingClientRect();
            tooltip.style.left = '88px';
            tooltip.style.top = `${Math.max(8, Math.min(rect.top, window.innerHeight - tooltip.offsetHeight - 8))}px`;
        };
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('focus', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
        element.addEventListener('blur', hideTooltip);
        element.setAttribute('aria-label', element.dataset.tooltip);
    });

    const notifications = root.querySelector('[data-notifications]');
    const notificationButton = notifications.querySelector('[data-notifications-toggle]');
    const notificationPanel = notifications.querySelector('#notifications-panel');
    const closeNotifications = () => {
        notificationPanel.hidden = true;
        notificationButton.setAttribute('aria-expanded', 'false');
    };
    notificationButton.addEventListener('click', () => {
        notificationPanel.hidden = !notificationPanel.hidden;
        notificationButton.setAttribute('aria-expanded', String(!notificationPanel.hidden));
    });

    document.addEventListener('click', (event) => {
        if (flyout && !flyout.element.contains(event.target)) closeFlyout();
        if (!notifications.contains(event.target)) closeNotifications();
    });
    document.addEventListener('focusin', (event) => {
        if (flyout && !flyout.element.contains(event.target)) closeFlyout();
        if (!notifications.contains(event.target)) closeNotifications();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            hideTooltip();
            if (!notificationPanel.hidden) {
                closeNotifications();
                notificationButton.focus();
            } else if (flyout) {
                closeFlyout(true);
            } else if (mobileOpen) {
                setMobileOpen(false);
            }
        }
        if (event.key === 'Tab' && mobileOpen) {
            const focusable = [...sidebar.querySelectorAll('a, button, input')].filter((element) => !element.disabled && element.getClientRects().length);
            const first = focusable[0];
            const last = focusable.at(-1);
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    desktop.addEventListener('change', () => {
        setMobileOpen(false, false);
        search.value = '';
        closeFlyout();
        hideTooltip();
        if (sidebar.inert && sidebar.contains(document.activeElement)) toggle.focus();
    });
    window.addEventListener('resize', () => {
        hideTooltip();
        if (flyout) positionFlyout(flyout);
    });
    sidebar.querySelector('.sidebar-navigation').addEventListener('scroll', () => {
        hideTooltip();
        if (flyout) positionFlyout(flyout);
    });

    setMobileOpen(false, false);
    renderGroups();
}
