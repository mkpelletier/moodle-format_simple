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

define([], function() {
    'use strict';

    /** @type {boolean} Whether the cog has already been initialised. */
    var initialised = false;

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
        'freeze': 'fa-ban'
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
    var addHomeButton = function(container, courseUrl) {
        // Don't show the home button on the course main page itself.
        var viewUrl = new URL(courseUrl, window.location.origin);
        if (window.location.pathname === viewUrl.pathname
            && window.location.search === viewUrl.search) {
            return;
        }

        var homeBtn = document.createElement('a');
        homeBtn.className = 'simple-home-btn';
        homeBtn.href = courseUrl;
        homeBtn.setAttribute('aria-label', 'Back to course');
        homeBtn.setAttribute('title', 'Back to course');
        homeBtn.innerHTML = '<i class="fa fa-home" aria-hidden="true"></i>';
        container.appendChild(homeBtn);
    };

    /**
     * Build and inject the cog popover.
     *
     * @param {string} courseUrl The course home URL.
     */
    var setup = function(courseUrl) {
        var secnav = document.querySelector('.format-simple .secondary-navigation');
        if (!secnav) {
            return;
        }

        var items = extractNavItems(secnav);
        if (!items.length) {
            return;
        }

        // Build the cog container.
        var container = document.createElement('div');
        container.className = 'simple-cog-container';

        var btn = document.createElement('button');
        btn.className = 'simple-cog-btn';
        btn.type = 'button';
        btn.setAttribute('aria-label', 'Course tools');
        btn.setAttribute('aria-expanded', 'false');
        btn.innerHTML = '<i class="fa fa-cog" aria-hidden="true"></i>';

        var popover = document.createElement('div');
        popover.className = 'simple-cog-popover';
        popover.setAttribute('role', 'menu');

        var heading = document.createElement('div');
        heading.className = 'simple-cog-heading';
        heading.textContent = 'Course Tools';
        popover.appendChild(heading);

        var grid = document.createElement('div');
        grid.className = 'simple-cog-grid';

        items.forEach(function(item) {
            var tile = document.createElement('a');
            tile.className = 'simple-cog-tile';
            tile.href = item.url;
            tile.setAttribute('role', 'menuitem');
            if (item.active) {
                tile.classList.add('is-active');
            }

            var iconWrap = document.createElement('span');
            iconWrap.className = 'simple-cog-tile-icon';
            if (item.icon.indexOf('img:') === 0) {
                var imgPath = item.icon.substring(4);
                var wwwroot = (window.M && M.cfg && M.cfg.wwwroot) || '';
                iconWrap.innerHTML = '<img src="' + wwwroot + imgPath + '" alt="" width="20" height="20"'
                    + ' class="simple-cog-tile-img">';
            } else {
                iconWrap.innerHTML = '<i class="fa ' + item.icon + '" aria-hidden="true"></i>';
            }

            var label = document.createElement('span');
            label.textContent = item.text;

            tile.appendChild(iconWrap);
            tile.appendChild(label);
            grid.appendChild(tile);
        });

        popover.appendChild(grid);
        container.appendChild(btn);
        container.appendChild(popover);
        document.body.appendChild(container);

        // Add the home button for navigating back to the course.
        if (courseUrl) {
            addHomeButton(container, courseUrl);
        }

        // Hide the original secondary navigation.
        secnav.style.display = 'none';

        // Click toggle.
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var open = popover.classList.toggle('is-open');
            btn.classList.toggle('is-open', open);
            btn.setAttribute('aria-expanded', open);
        });

        // Close on click outside.
        document.addEventListener('click', function(e) {
            if (!container.contains(e.target)) {
                popover.classList.remove('is-open');
                btn.classList.remove('is-open');
                btn.setAttribute('aria-expanded', 'false');
            }
        });

        // Close on Escape.
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && popover.classList.contains('is-open')) {
                popover.classList.remove('is-open');
                btn.classList.remove('is-open');
                btn.setAttribute('aria-expanded', 'false');
                btn.focus();
            }
        });
    };

    return {
        /**
         * Initialise the cog navigation.
         *
         * Safe to call multiple times — only runs once.
         *
         * @param {string} courseUrl The course home URL.
         */
        init: function(courseUrl) {
            if (initialised) {
                return;
            }
            initialised = true;
            setup(courseUrl);
        }
    };
});
