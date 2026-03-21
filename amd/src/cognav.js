// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Cog navigation popover for the Simple course format.
 *
 * Replaces Moodle's secondary navigation bar with a fixed cog button
 * that opens a tile-grid popover. Loaded on ALL course pages so the
 * cog is available everywhere (course view, participants, grades, etc.).
 *
 * @module     format_simple/cognav
 * @copyright  2025 South African Theological Seminary <ict@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/str', 'core/templates'], function(Ajax, Str, Templates) {
    'use strict';

    /** @type {boolean} Whether the cog has already been initialised. */
    var initialised = false;

    /** @type {HTMLElement|null} The modal backdrop element. */
    var modalBackdrop = null;

    /** @type {HTMLElement|null} The modal panel element. */
    var modalPanel = null;

    /** @type {HTMLElement|null} The button that triggered the modal. */
    var modalTrigger = null;

    /** @type {Object} Pre-loaded language strings. */
    var langStrings = {};

    /**
     * Icon map for secondary nav items.
     *
     * Keys are matched against the nav item's data-key attribute or
     * lowercased text content.
     *
     * @type {Object<string, string>}
     */
    var COG_ICON_MAP = {
        'coursehome': 'fa-book',
        'course': 'fa-book',
        'settings': 'fa-sliders',
        'editsettings': 'fa-sliders',
        'participants': 'fa-users',
        'grades': 'fa-chart-bar',
        'reports': 'fa-chart-line',
        'competencies': 'fa-trophy',
        'badges': 'fa-certificate',
        'contentbank': 'fa-cubes',
        'content bank': 'fa-cubes',
        'coursereuse': 'fa-recycle',
        'course reuse': 'fa-recycle',
        'reuse': 'fa-recycle',
        'backup': 'fa-download',
        'restore': 'fa-upload',
        'questionbank': 'fa-circle-question',
        'question bank': 'fa-circle-question',
        'questions': 'fa-circle-question',
        'recyclebin': 'fa-trash',
        'import': 'fa-sign-in',
        'copy': 'fa-copy',
        'reset': 'fa-refresh',
        'filter': 'fa-filter',
        'filters': 'fa-filter',
        'activities': 'fa-person-walking',
        'penalties': 'fa-stopwatch',
        'gradepenalties': 'fa-stopwatch',
        'accessibility': 'fa-universal-access',
        'accessibilitytoolkit': 'fa-universal-access',
        'enrolment': 'fa-user-plus',
        'enrolments': 'fa-user-plus',
        'permissions': 'fa-shield',
        'roles': 'fa-id-badge',
        'calendar': 'fa-calendar',
        'outcomes': 'fa-bullseye',
        'logs': 'fa-list-alt',
        'livelog': 'fa-list-alt',
        'completion': 'fa-check-circle',
        'groups': 'fa-layer-group',
        'scales': 'fa-balance-scale',
        'letters': 'fa-font',
        'gradeletters': 'fa-font',
        'search': 'fa-search',
        'publish': 'fa-globe',
        'wiki': 'fa-file-text',
        'forum': 'fa-comments',
        'assignment': 'fa-pencil',
        'quiz': 'fa-pencil-square',
        'download': 'fa-download',
        'upload': 'fa-upload',
        'url': 'fa-link',
        'dashboard': 'fa-link',
        'usage': 'fa-chart-area',
        'statistics': 'fa-chart-area',
        'reminder': 'fa-user-clock',
        'reminders': 'fa-user-clock',
        'lti': 'fa-external-link-alt',
        'external tool': 'fa-external-link-alt',
        'recycle bin': 'fa-trash',
        'unified grader': 'img:/local/unifiedgrader/pix/icon.png',
        'submission': 'fa-list-alt',
        'submissions': 'fa-list-alt',
        'override': 'fa-redo-alt',
        'overrides': 'fa-redo-alt',
        'freeze': 'fa-ban',
        'syllabus': 'fa-info-circle'
    };

    /** @type {string} Default icon when no match is found. */
    var COG_ICON_DEFAULT = 'fa-ellipsis-h';

    /**
     * Resolve a Font Awesome icon class for a nav item.
     *
     * @param {string} key The data-key attribute value.
     * @param {string} text The visible text label.
     * @return {string} A Font Awesome icon class (e.g. "fa-users").
     */
    var resolveIcon = function(key, text) {
        if (key && COG_ICON_MAP[key.toLowerCase()]) {
            return COG_ICON_MAP[key.toLowerCase()];
        }
        var lowerText = (text || '').toLowerCase();
        var keys = Object.keys(COG_ICON_MAP);
        for (var i = 0; i < keys.length; i++) {
            if (lowerText.indexOf(keys[i]) !== -1) {
                return COG_ICON_MAP[keys[i]];
            }
        }
        return COG_ICON_DEFAULT;
    };

    /**
     * Extract navigation items from the secondary navigation DOM.
     *
     * @param {HTMLElement} secnav The .secondary-navigation element.
     * @return {Array<{text: string, url: string, active: boolean, icon: string}>}
     */
    var extractNavItems = function(secnav) {
        var items = [];
        var seen = new Set();

        // Primary visible nav links.
        var navLinks = secnav.querySelectorAll('ul.nav > li.nav-item > a.nav-link');
        navLinks.forEach(function(link) {
            if (link.classList.contains('dropdown-toggle')) {
                return;
            }
            var url = link.getAttribute('href');
            if (!url || url === '#' || seen.has(url)) {
                return;
            }
            seen.add(url);
            var li = link.closest('li');
            var key = li ? li.getAttribute('data-key') : '';
            items.push({
                text: (link.getAttribute('data-text') || link.textContent || '').trim(),
                url: url,
                active: link.classList.contains('active'),
                icon: resolveIcon(key, (link.getAttribute('data-text') || link.textContent || '').trim())
            });
        });

        // Items inside dropdown sub-menus.
        var dropdownItems = secnav.querySelectorAll('li.nav-item.dropdown .dropdown-menu .dropdown-item');
        dropdownItems.forEach(function(link) {
            var url = link.getAttribute('href');
            if (!url || url === '#' || seen.has(url)) {
                return;
            }
            seen.add(url);
            items.push({
                text: (link.textContent || '').trim(),
                url: url,
                active: link.classList.contains('active'),
                icon: resolveIcon('', (link.textContent || '').trim())
            });
        });

        // Items hidden in the moremenu overflow dropdown.
        var moreItems = secnav.querySelectorAll('[data-region="moredropdown"] .dropdown-item');
        moreItems.forEach(function(link) {
            var url = link.getAttribute('href');
            if (!url || url === '#' || seen.has(url)) {
                return;
            }
            seen.add(url);
            items.push({
                text: (link.textContent || '').trim(),
                url: url,
                active: link.classList.contains('active'),
                icon: resolveIcon('', (link.textContent || '').trim())
            });
        });

        return items;
    };

    /**
     * Build and inject the home button.
     *
     * @param {HTMLElement} container The cog container to append the home button to.
     * @param {string} courseUrl The course home URL.
     */
    /**
     * Check whether we are on the course main page.
     *
     * @param {string} courseUrl The course view URL.
     * @return {boolean}
     */
    var isOnCourseMainPage = function(courseUrl) {
        var viewUrl = new URL(courseUrl, window.location.origin);
        return window.location.pathname === viewUrl.pathname
            && window.location.search === viewUrl.search;
    };

    // ---------------------------------------------------------------
    // Section 0 Modal Overlay
    // ---------------------------------------------------------------

    /**
     * Create the modal DOM structure (once) using a Mustache template.
     *
     * @return {Promise} Resolved when the modal DOM is ready.
     */
    var createModalDom = function() {
        if (modalBackdrop) {
            return Promise.resolve();
        }

        return Templates.render('format_simple/local/modal', {
            courseinfo: langStrings.courseinfo,
            closelabel: langStrings.close
        }).then(function(html) {
            var temp = document.createElement('div');
            temp.innerHTML = html;

            modalBackdrop = temp.querySelector('.simple-s0-modal-backdrop');
            modalPanel = temp.querySelector('.simple-s0-modal');

            document.body.appendChild(modalBackdrop);
            document.body.appendChild(modalPanel);

            var closeBtn = modalPanel.querySelector('.simple-s0-modal-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', closeSection0Modal);
            }

            // Close on backdrop click.
            modalBackdrop.addEventListener('click', closeSection0Modal);

            // Close on Escape.
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modalPanel.classList.contains('is-open')) {
                    closeSection0Modal();
                }
            });

            return;
        });
    };

    /**
     * Hide activity cards inside a container when a module has injected inline content.
     *
     * @param {HTMLElement} container The container to scan for cmitems.
     */
    var hideInlineContentCards = function(container) {
        var cmitems = container.querySelectorAll('[data-for="cmitem"]');
        cmitems.forEach(function(cmitem) {
            var card = cmitem.querySelector('.simple-cm-card');
            if (!card) {
                return;
            }
            var children = cmitem.children;
            for (var i = 0; i < children.length; i++) {
                var child = children[i];
                if (child === card || child.classList.contains('simple-cm-edit')) {
                    continue;
                }
                card.style.display = 'none';
                return;
            }
        });
    };

    /**
     * Open the section 0 modal overlay.
     *
     * On the course view page, section 0 is rendered as a normal (hidden)
     * section in the page so Moodle's JS fully initialises its content.
     * On first open we physically move that live DOM element into the
     * modal, preserving all event listeners, menus, and widget state.
     *
     * On non-course-view pages we fall back to an AJAX fetch.
     *
     * @param {number} courseId The course ID.
     */
    var openSection0Modal = function(courseId) {
        createModalDom().then(function() {
            var body = modalPanel.querySelector('.simple-s0-modal-body');

            // If we already populated the body, just re-show.
            if (body.dataset.populated) {
                showModal();
                return;
            }

            // Look for the live section 0 element in the page (rendered as a
            // regular hidden section by the course format output class).
            var section0 = document.getElementById('simple-section-0');
            if (section0) {
                // Move the live DOM node — preserves all JS bindings.
                section0.removeAttribute('hidden');
                section0.classList.add('is-active');
                body.appendChild(section0);
                body.dataset.populated = '1';
                hideInlineContentCards(body);
                showModal();
                return;
            }

            // Fetch via AJAX on non-course-view pages.
            return Templates.render('format_simple/local/modal_loading', {
                loadingtext: langStrings.loading
            }).then(function(html) {
                body.innerHTML = html;
                showModal();

                return Ajax.call([{
                    methodname: 'format_simple_get_section0_content',
                    args: {courseid: courseId}
                }])[0];
            }).then(function(response) {
                body.innerHTML = response.html;
                body.dataset.populated = '1';
                hideInlineContentCards(body);
                return undefined;
            }).catch(function() {
                return Templates.render('format_simple/local/modal_error', {
                    message: langStrings.failedtoload
                }).then(function(html) {
                    body.innerHTML = html;
                    return undefined;
                }).catch(function() {
                    // Last resort fallback.
                });
            });
        }).catch(function() {
            // Modal DOM creation failed.
        });
    };

    /**
     * Show the modal with animation.
     */
    var showModal = function() {
        document.body.style.overflow = 'hidden';
        modalBackdrop.style.display = 'block';
        modalPanel.style.display = 'flex';
        modalBackdrop.setAttribute('aria-hidden', 'false');

        // Force reflow before adding the animation class.
        void modalPanel.offsetHeight;

        modalBackdrop.classList.add('is-open');
        modalPanel.classList.add('is-open');

        // Focus the close button.
        var closeBtn = modalPanel.querySelector('.simple-s0-modal-close');
        if (closeBtn) {
            closeBtn.focus();
        }
    };

    /**
     * Close the section 0 modal with animation.
     */
    var closeSection0Modal = function() {
        if (!modalPanel) {
            return;
        }

        modalBackdrop.classList.remove('is-open');
        modalPanel.classList.remove('is-open');

        var onEnd = function() {
            modalPanel.removeEventListener('transitionend', onEnd);
            modalBackdrop.style.display = 'none';
            modalPanel.style.display = 'none';
            modalBackdrop.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';

            if (modalTrigger) {
                modalTrigger.focus();
                modalTrigger = null;
            }
        };

        modalPanel.addEventListener('transitionend', onEnd);
    };

    // ---------------------------------------------------------------
    // Main setup
    // ---------------------------------------------------------------

    /**
     * Build and inject the cog popover.
     *
     * @param {string} courseUrl The course home URL.
     * @param {number} courseId The course ID.
     * @param {number} section0Modal Whether section 0 modal mode is enabled.
     * @param {number} unreadCount Number of unread forum posts in section 0.
     */
    var setup = function(courseUrl, courseId, section0Modal, unreadCount) {
        // Ensure the .format-simple body class is present.
        if (!document.body.classList.contains('format-simple')) {
            return;
        }

        // Skip on embedded/fullscreen layouts (e.g. grading interfaces).
        if (document.body.classList.contains('pagelayout-embedded')) {
            return;
        }

        // Extract nav items from secondary navigation.
        var secnav = document.querySelector('.secondary-navigation');
        var rawItems = secnav ? extractNavItems(secnav) : [];

        // Determine whether to show home button.
        var showhome = courseUrl && !isOnCourseMainPage(courseUrl);

        // Determine whether to show modal button.
        var showmodal = !!(section0Modal && courseId);

        // Nothing to render — bail out.
        if (!rawItems.length && !showhome && !showmodal) {
            return;
        }

        // Prepare template context with resolved image paths.
        var wwwroot = (window.M && M.cfg && M.cfg.wwwroot) || '';
        var templateItems = rawItems.map(function(item) {
            var isimg = item.icon.indexOf('img:') === 0;
            return {
                url: item.url,
                icon: isimg ? wwwroot + item.icon.substring(4) : item.icon,
                text: item.text,
                active: item.active,
                isimg: isimg
            };
        });

        var context = {
            hasitems: templateItems.length > 0,
            items: templateItems,
            coursetools: langStrings.coursetools,
            showhome: showhome,
            courseurl: courseUrl,
            backtocourse: langStrings.backtocourse,
            showmodal: showmodal,
            courseinfo: langStrings.courseinfo,
            unreadcount: unreadCount,
            hasunread: unreadCount > 0,
            unreadlabel: unreadCount + ' ' + langStrings.unreadposts
        };

        Templates.render('format_simple/local/cog_container', context).then(function(html) {
            var temp = document.createElement('div');
            temp.innerHTML = html;
            var container = temp.firstElementChild;
            document.body.appendChild(container);

            // Wire up cog popover toggle.
            var btn = container.querySelector('.simple-cog-btn');
            var popover = container.querySelector('.simple-cog-popover');
            if (btn && popover) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var open = popover.classList.toggle('is-open');
                    btn.classList.toggle('is-open', open);
                    btn.setAttribute('aria-expanded', open);
                });

                document.addEventListener('click', function(e) {
                    if (!container.contains(e.target)) {
                        popover.classList.remove('is-open');
                        btn.classList.remove('is-open');
                        btn.setAttribute('aria-expanded', 'false');
                    }
                });

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && popover.classList.contains('is-open')) {
                        popover.classList.remove('is-open');
                        btn.classList.remove('is-open');
                        btn.setAttribute('aria-expanded', 'false');
                        btn.focus();
                    }
                });

                // Hide the original secondary navigation.
                if (secnav) {
                    secnav.style.display = 'none';
                }
            }

            // Wire up section 0 modal button.
            var modalBtn = container.querySelector('.simple-s0-modal-btn');
            if (modalBtn && courseId) {
                modalBtn.addEventListener('click', function() {
                    modalTrigger = modalBtn;
                    openSection0Modal(courseId);
                });
            }

            return;
        }).catch(function() {
            // Cog container rendering failed — fall back silently.
        });
    };

    return {
        /**
         * Initialise the cog navigation.
         *
         * Safe to call multiple times — only runs once.
         *
         * @param {string} courseUrl The course home URL.
         * @param {number} courseId The course ID.
         * @param {number} section0Modal Whether section 0 modal mode is enabled.
         * @param {number} unreadCount Number of unread forum posts in section 0.
         */
        init: function(courseUrl, courseId, section0Modal, unreadCount) {
            if (initialised) {
                return;
            }
            initialised = true;

            // Load language strings before building the UI.
            Str.get_strings([
                {key: 'backtocourse', component: 'format_simple'},
                {key: 'coursetools', component: 'format_simple'},
                {key: 'courseinfo', component: 'format_simple'},
                {key: 'close', component: 'format_simple'},
                {key: 'loading', component: 'format_simple'},
                {key: 'failedtoload', component: 'format_simple'},
                {key: 'unreadposts', component: 'format_simple'},
            ]).then(function(strings) {
                langStrings.backtocourse = strings[0];
                langStrings.coursetools = strings[1];
                langStrings.courseinfo = strings[2];
                langStrings.close = strings[3];
                langStrings.loading = strings[4];
                langStrings.failedtoload = strings[5];
                langStrings.unreadposts = strings[6];

                setup(courseUrl, courseId, section0Modal, unreadCount);
                return;
            }).catch(function() {
                // Fallback: use English strings if loading fails.
                langStrings.backtocourse = 'Back to course';
                langStrings.coursetools = 'Course tools';
                langStrings.courseinfo = 'Course Info';
                langStrings.close = 'Close';
                langStrings.loading = 'Loading...';
                langStrings.failedtoload = 'Failed to load course info.';
                langStrings.unreadposts = 'Unread posts';

                setup(courseUrl, courseId, section0Modal, unreadCount);
            });
        }
    };
});
