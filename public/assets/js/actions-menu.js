/* ROOTINS shared More Actions enhancement.
 * Presentation and keyboard behavior only; existing links, permissions,
 * delegated handlers, forms, and AJAX contracts remain untouched.
 */
(function (window, document, $) {
    'use strict';

    var toggleSelector = [
        '.pharmacy-more-actions-toggle',
        '.admin-row-actions-more > .dropdown-toggle',
        '.dropdown-toggle'
    ].join(',');
    var viewportGap = 8;

    function isMoreActions(toggle) {
        var text = String(toggle.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
        return text.indexOf('more action') !== -1 || toggle.classList.contains('pharmacy-more-actions-toggle') || !!toggle.closest('.admin-row-actions-more');
    }

    function enhance(root) {
        var toggles = [];
        if (root && root.matches && root.matches(toggleSelector)) toggles.push(root);
        if (root && root.querySelectorAll) toggles = toggles.concat(Array.prototype.slice.call(root.querySelectorAll(toggleSelector)));
        toggles.forEach(function (toggle) {
            if (!isMoreActions(toggle)) return;
            toggle.classList.add('ri-more-actions-toggle');
            var icon = toggle.querySelector('i, svg, .ri-actions-icon');
            if (icon) icon.classList.add('ri-actions-icon');
            else {
                icon = document.createElement('i');
                icon.className = 'fa-solid fa-ellipsis-vertical ri-actions-icon mr-1';
                icon.setAttribute('aria-hidden', 'true');
                toggle.insertBefore(icon, toggle.firstChild);
            }
            var wrapper = toggle.closest('.dropdown');
            var menu = wrapper && wrapper.querySelector('.dropdown-menu');
            if (wrapper) {
                wrapper.classList.add('ri-actions-wrapper');
                wrapper._riActionsMenu = menu || null;
                wrapper._riActionsPlaceholder = wrapper._riActionsPlaceholder || null;
                wrapper._riActionsInModal = !!wrapper.closest('.modal');
            }
            if (menu) {
                menu.classList.add('ri-actions-menu');
                menu._riActionsWrapper = wrapper;
            }
        });
    }

    function positionMenu(wrapper) {
        var toggle = wrapper.querySelector('.ri-more-actions-toggle');
        var menu = wrapper._riActionsMenu || wrapper.querySelector('.ri-actions-menu');
        if (!toggle || !menu || wrapper.classList.contains('pharmacy-more-actions')) return;
        var rect = toggle.getBoundingClientRect();
        menu.style.setProperty('position', 'fixed', 'important');
        menu.style.setProperty('display', 'block', 'important');
        menu.style.setProperty('visibility', 'hidden', 'important');
        menu.style.setProperty('transform', 'none', 'important');
        var width = menu.offsetWidth;
        var height = menu.offsetHeight;
        var left = Math.min(Math.max(viewportGap, rect.right - width), Math.max(viewportGap, window.innerWidth - width - viewportGap));
        var below = rect.bottom + viewportGap;
        var above = rect.top - height - viewportGap;
        var preferAbove = !!wrapper.closest('.admin-appointment-page');
        var top = preferAbove && above >= viewportGap
            ? above
            : below + height <= window.innerHeight - viewportGap || above < viewportGap
            ? Math.min(below, window.innerHeight - height - viewportGap)
            : above;
        menu.style.setProperty('top', Math.max(viewportGap, top) + 'px', 'important');
        menu.style.setProperty('left', left + 'px', 'important');
        menu.style.setProperty('right', 'auto', 'important');
        menu.style.setProperty('visibility', 'visible', 'important');
    }

    function portalMenu(wrapper) {
        var menu = wrapper._riActionsMenu || wrapper.querySelector('.ri-actions-menu');
        if (!menu || menu.parentNode === document.body || wrapper._riActionsPlaceholder) return;
        var placeholder = document.createComment('rootins-actions-menu');
        menu.parentNode.insertBefore(placeholder, menu);
        document.body.appendChild(menu);
        wrapper._riActionsPlaceholder = placeholder;
        menu._riActionsWrapper = wrapper;
        if (wrapper._riActionsInModal) menu.classList.add('ri-actions-menu--in-modal');
    }

    function restoreMenu(wrapper, menu) {
        var placeholder = wrapper._riActionsPlaceholder;
        if (placeholder && placeholder.parentNode) {
            placeholder.parentNode.insertBefore(menu, placeholder.nextSibling);
            placeholder.parentNode.removeChild(placeholder);
        }
        wrapper._riActionsPlaceholder = null;
        menu.classList.remove('ri-actions-menu--in-modal');
    }

    function resetMenu(menu) {
        ['display', 'left', 'right', 'top', 'transform', 'visibility'].forEach(function (property) {
            menu.style.removeProperty(property);
        });
    }

    function menuItems(menu) {
        return $(menu).find('.dropdown-item:not([disabled]):visible');
    }

    function bind() {
        enhance(document);
        $(document)
            .off('shown.bs.dropdown.rootinsActions', '.ri-actions-wrapper:not(.pharmacy-more-actions)')
            .on('shown.bs.dropdown.rootinsActions', '.ri-actions-wrapper:not(.pharmacy-more-actions)', function () {
                portalMenu(this);
                positionMenu(this);
                var wrapper = this;
                window.setTimeout(function () {
                    var menu = wrapper._riActionsMenu;
                    if (menu && menu.classList.contains('show')) positionMenu(wrapper);
                }, 0);
                var toggle = $(this).find('.ri-more-actions-toggle');
                var menu = $(this._riActionsMenu || this.querySelector('.ri-actions-menu'));
                toggle.attr('aria-expanded', 'true');
                menu.find('.dropdown-item').attr('role', 'menuitem');
                menuItems(menu).first().trigger('focus');
            })
            .off('hidden.bs.dropdown.rootinsActions', '.ri-actions-wrapper:not(.pharmacy-more-actions)')
            .on('hidden.bs.dropdown.rootinsActions', '.ri-actions-wrapper:not(.pharmacy-more-actions)', function () {
                var menu = this._riActionsMenu || this.querySelector('.ri-actions-menu');
                if (menu) {
                    resetMenu(menu);
                    restoreMenu(this, menu);
                }
                $(this).find('.ri-more-actions-toggle').attr('aria-expanded', 'false').trigger('blur');
            })
            .off('keydown.rootinsActions', '.ri-actions-menu:not(.pharmacy-more-actions-menu)')
            .on('keydown.rootinsActions', '.ri-actions-menu:not(.pharmacy-more-actions-menu)', function (event) {
                var items = menuItems(this);
                if (!items.length) return;
                if (event.key === 'Escape') {
                    event.preventDefault();
                    var wrapper = this._riActionsWrapper;
                    if (wrapper) $(wrapper).find('.ri-more-actions-toggle').dropdown('hide').trigger('focus');
                    return;
                }
                if (['ArrowDown', 'ArrowUp', 'Home', 'End'].indexOf(event.key) === -1) return;
                event.preventDefault();
                var current = items.index(document.activeElement);
                var next = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : event.key === 'ArrowDown' ? (current + 1) % items.length : (current - 1 + items.length) % items.length;
                items.eq(next).trigger('focus');
            });
        $(window).off('resize.rootinsActions scroll.rootinsActions').on('resize.rootinsActions scroll.rootinsActions', function () {
            document.querySelectorAll('.ri-actions-wrapper:not(.pharmacy-more-actions)').forEach(function (wrapper) {
                var menu = wrapper._riActionsMenu;
                if (menu && menu.classList.contains('show')) positionMenu(wrapper);
            });
        });
        if (window.MutationObserver) new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                Array.prototype.forEach.call(mutation.addedNodes, function (node) {
                    if (node.nodeType === 1) enhance(node);
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
    else bind();
}(window, document, window.jQuery));
