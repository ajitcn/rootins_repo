(function ($) {
    'use strict';

    const namespace = '.pharmacyMoreActions';
    const viewportGap = 8;

    function positionMenu(wrapper) {
        const toggle = wrapper.find('.pharmacy-more-actions-toggle')[0];
        const menu = wrapper.data('pharmacy-menu') || wrapper.find('.pharmacy-more-actions-menu')[0];

        if (!toggle || !menu) {
            return;
        }

        const toggleRect = toggle.getBoundingClientRect();

        menu.style.setProperty('position', 'fixed', 'important');
        menu.style.setProperty('display', 'block', 'important');
        menu.style.setProperty('visibility', 'hidden', 'important');
        menu.style.setProperty('transform', 'none', 'important');

        const menuWidth = menu.offsetWidth;
        const menuHeight = menu.offsetHeight;
        const maxLeft = Math.max(viewportGap, window.innerWidth - menuWidth - viewportGap);
        const left = Math.min(
            Math.max(viewportGap, toggleRect.right - menuWidth),
            maxLeft
        );
        const belowTop = toggleRect.bottom + viewportGap;
        const aboveTop = toggleRect.top - menuHeight - viewportGap;
        const top = belowTop + menuHeight <= window.innerHeight - viewportGap || aboveTop < viewportGap
            ? Math.min(belowTop, window.innerHeight - menuHeight - viewportGap)
            : aboveTop;

        menu.style.setProperty('top', `${Math.max(viewportGap, top)}px`, 'important');
        menu.style.setProperty('left', `${left}px`, 'important');
        menu.style.setProperty('right', 'auto', 'important');
        menu.style.setProperty('visibility', 'visible', 'important');
    }

    function portalMenu(wrapper) {
        const menu = wrapper.find('.pharmacy-more-actions-menu')[0];

        if (!menu || menu.parentNode === document.body || wrapper.data('pharmacy-menu-placeholder')) {
            return;
        }

        const placeholder = document.createComment('rootins-pharmacy-actions-menu');
        menu.parentNode.insertBefore(placeholder, menu);
        document.body.appendChild(menu);
        wrapper.data('pharmacy-menu', menu).data('pharmacy-menu-placeholder', placeholder);
        menu._pharmacyMoreActionsWrapper = wrapper[0];
    }

    function restoreMenu(wrapper, menu) {
        const placeholder = wrapper.data('pharmacy-menu-placeholder');

        if (placeholder && placeholder.parentNode) {
            placeholder.parentNode.insertBefore(menu, placeholder.nextSibling);
            placeholder.parentNode.removeChild(placeholder);
        }

        wrapper.removeData('pharmacy-menu-placeholder').removeData('pharmacy-menu');
    }

    function resetMenu(menu) {
        [
            'display',
            'left',
            'right',
            'top',
            'transform',
            'visibility',
        ].forEach((property) => menu.style.removeProperty(property));
    }

    function menuItems(menu) {
        return menu.find('.dropdown-item:not([disabled]):visible');
    }

    $(document)
        .off('shown.bs.dropdown' + namespace)
        .on('shown.bs.dropdown' + namespace, '.pharmacy-more-actions', function () {
            $('.pharmacy-more-actions.show')
                .not(this)
                .find('.pharmacy-more-actions-toggle')
                .dropdown('hide');

            const wrapper = $(this);
            portalMenu(wrapper);
            positionMenu(wrapper);
            window.setTimeout(function () {
                const menu = wrapper.data('pharmacy-menu');
                if (menu && menu.classList.contains('show')) {
                    positionMenu(wrapper);
                }
            }, 0);

            wrapper.find('.pharmacy-more-actions-toggle').attr('aria-expanded', 'true');
            const menu = $(wrapper.data('pharmacy-menu') || wrapper.find('.pharmacy-more-actions-menu'));

            menu.find('.dropdown-item').attr('role', 'menuitem');
            menuItems(menu).first().trigger('focus');
        })
        .off('hidden.bs.dropdown' + namespace)
        .on('hidden.bs.dropdown' + namespace, '.pharmacy-more-actions', function () {
            const wrapper = $(this);
            const menu = wrapper.data('pharmacy-menu') || wrapper.find('.pharmacy-more-actions-menu')[0];
            resetMenu(menu);
            restoreMenu(wrapper, menu);
            wrapper.find('.pharmacy-more-actions-toggle').attr('aria-expanded', 'false');
            wrapper.find('.pharmacy-more-actions-toggle').trigger('blur');
        })
        .off('keydown' + namespace)
        .on('keydown' + namespace, '.pharmacy-more-actions-menu', function (event) {
            const items = menuItems($(this));
            const current = items.index(document.activeElement);

            if (!items.length) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                const wrapper = this._pharmacyMoreActionsWrapper;
                if (wrapper) $(wrapper).find('.pharmacy-more-actions-toggle').dropdown('hide').trigger('focus');
                return;
            }

            if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp' && event.key !== 'Home' && event.key !== 'End') {
                return;
            }

            event.preventDefault();

            if (event.key === 'Home') {
                items.first().trigger('focus');
                return;
            }

            if (event.key === 'End') {
                items.last().trigger('focus');
                return;
            }

            const next = event.key === 'ArrowDown'
                ? (current + 1) % items.length
                : (current - 1 + items.length) % items.length;

            items.eq(next).trigger('focus');
        })
        .off('click' + namespace)
        .on('click' + namespace, function (event) {
            if (!$(event.target).closest('.pharmacy-more-actions, .pharmacy-more-actions-menu').length) {
                $('.pharmacy-more-actions.show .pharmacy-more-actions-toggle').each(function () {
                    $(this).dropdown('hide');
                });
            }
        })
        .off('click' + namespace, '.pharmacy-more-actions-menu .dropdown-item')
        .on('click' + namespace, '.pharmacy-more-actions-menu .dropdown-item', function () {
            const menu = this.closest('.pharmacy-more-actions-menu');
            const wrapper = menu && menu._pharmacyMoreActionsWrapper;
            const toggle = wrapper
                ? $(wrapper).find('.pharmacy-more-actions-toggle')
                : $(this).closest('.pharmacy-more-actions').find('.pharmacy-more-actions-toggle');

            window.setTimeout(function () {
                toggle.dropdown('hide');
            }, 0);
        });

    $(window)
        .off('resize' + namespace + ' scroll' + namespace)
        .on('resize' + namespace + ' scroll' + namespace, function () {
            $('.pharmacy-more-actions').each(function () {
                const wrapper = $(this);
                const menu = wrapper.data('pharmacy-menu');
                if (menu && menu.classList.contains('show')) positionMenu(wrapper);
            });
        });
})(jQuery);
