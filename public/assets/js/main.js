/* GuideMate — front-end interactions */
(function () {
    'use strict';

    // Mobile nav toggle
    var navToggle = document.getElementById('navToggle');
    var mainNav = document.getElementById('mainNav');
    if (navToggle && mainNav) {
        navToggle.addEventListener('click', function () {
            var open = mainNav.classList.toggle('open');
            navToggle.classList.toggle('open', open);
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // User dropdown menu
    var userBtn = document.getElementById('userMenuBtn');
    var userMenu = document.getElementById('userMenu');
    if (userBtn && userMenu) {
        userBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            userMenu.classList.toggle('open');
        });
        document.addEventListener('click', function (e) {
            if (!userMenu.contains(e.target) && e.target !== userBtn) {
                userMenu.classList.remove('open');
            }
        });
    }

    // Message thread options menu (⋯)
    var threadMenuBtn = document.getElementById('threadMenuBtn');
    var threadMenu = document.getElementById('threadMenu');
    if (threadMenuBtn && threadMenu) {
        threadMenuBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = threadMenu.classList.toggle('open');
            threadMenuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!threadMenu.contains(e.target) && e.target !== threadMenuBtn) {
                threadMenu.classList.remove('open');
                threadMenuBtn.setAttribute('aria-expanded', 'false');
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && threadMenu.classList.contains('open')) {
                threadMenu.classList.remove('open');
                threadMenuBtn.setAttribute('aria-expanded', 'false');
                threadMenuBtn.focus();
            }
        });
    }

    // Dismiss flash messages
    // Hotel Reservations Action Dropdown (vertical 3-dot menu)
    var activeResDropdown = null;
    var activeResBtn = null;
    var activeResRow = null;

    function closeResMenu() {
        if (activeResDropdown) {
            activeResDropdown.classList.remove('open', 'drop-up');
            activeResDropdown.style.top = '';
            activeResDropdown.style.bottom = '';
            activeResDropdown.style.left = '';
            activeResDropdown.style.right = '';
        }
        if (activeResBtn) {
            activeResBtn.setAttribute('aria-expanded', 'false');
        }
        if (activeResRow) {
            activeResRow.classList.remove('is-menu-open');
        }
        activeResDropdown = null;
        activeResBtn = null;
        activeResRow = null;
    }

    function positionResMenu(trigger, menu) {
        var btnRect = trigger.getBoundingClientRect();
        var menuWidth = menu.offsetWidth || 180;

        menu.style.visibility = 'hidden';
        menu.classList.add('open');
        var menuHeight = menu.offsetHeight || 170;
        menu.style.visibility = '';

        var spaceBelow = window.innerHeight - btnRect.bottom;
        var spaceAbove = btnRect.top;

        // Open upward if space below is less than menuHeight + 15 AND space above is greater than space below
        var openUpward = (spaceBelow < (menuHeight + 15)) && (spaceAbove > spaceBelow);

        // Position horizontally - align with right edge of button, keeping within window boundaries
        var rightEdge = window.innerWidth - btnRect.right;
        if (rightEdge < 10) {
            rightEdge = 10;
        }
        if ((window.innerWidth - rightEdge - menuWidth) < 10) {
            menu.style.right = 'auto';
            menu.style.left = '10px';
        } else {
            menu.style.left = 'auto';
            menu.style.right = rightEdge + 'px';
        }

        if (openUpward) {
            menu.classList.add('drop-up');
            menu.style.top = 'auto';
            menu.style.bottom = Math.max(10, (window.innerHeight - btnRect.top + 4)) + 'px';
        } else {
            menu.classList.remove('drop-up');
            menu.style.bottom = 'auto';
            menu.style.top = Math.max(10, (btnRect.bottom + 4)) + 'px';
        }
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest ? e.target.closest('.btn-res-action-trigger') : null;
        if (trigger) {
            e.stopPropagation();
            var menuId = trigger.getAttribute('aria-controls');
            var menu = menuId ? document.getElementById(menuId) : (trigger.parentElement ? trigger.parentElement.querySelector('.res-action-dropdown') : null);
            if (!menu) return;

            if (menu === activeResDropdown) {
                closeResMenu();
                return;
            }

            closeResMenu();

            positionResMenu(trigger, menu);

            menu.classList.add('open');
            trigger.setAttribute('aria-expanded', 'true');
            var row = trigger.closest ? trigger.closest('tr') : null;
            if (row) {
                row.classList.add('is-menu-open');
                activeResRow = row;
            }
            activeResDropdown = menu;
            activeResBtn = trigger;
            return;
        }

        if (activeResDropdown && !activeResDropdown.contains(e.target)) {
            closeResMenu();
        }
    });

    window.addEventListener('scroll', closeResMenu, true);
    window.addEventListener('resize', closeResMenu, true);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && activeResDropdown) {
            var b = activeResBtn;
            closeResMenu();
            if (b) { b.focus(); }
        }
    });

    // Dismiss flash messages & prevent duplicate display
    var seenFlashes = new Set();
    document.querySelectorAll('.flash').forEach(function (flash) {
        var text = (flash.textContent || '').trim();
        if (seenFlashes.has(text)) {
            flash.remove();
            return;
        }
        seenFlashes.add(text);

        var closeBtn = flash.querySelector('.flash-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                flash.remove();
            });
        }
        setTimeout(function () {
            flash.style.transition = 'opacity .4s ease, transform .4s ease';
            flash.style.opacity = '0';
            flash.style.transform = 'translateY(-6px)';
            setTimeout(function () { flash.remove(); }, 400);
        }, 5000);
    });

    // Prevent notifications from being captured in bfcache or replayed on back/forward
    window.addEventListener('pagehide', function () {
        document.querySelectorAll('.flash, #gm-dynamic-toast').forEach(function (el) {
            el.remove();
        });
    });
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            document.querySelectorAll('.flash, #gm-dynamic-toast').forEach(function (el) {
                el.remove();
            });
        }
    });

    // Favorite toggle (AJAX)
    document.querySelectorAll('.fav-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var id = btn.getAttribute('data-id');
            var token = btn.getAttribute('data-token');
            fetch(btn.getAttribute('data-url'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'listing_id=' + encodeURIComponent(id) + '&_token=' + encodeURIComponent(token)
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (data.authenticated === false) { window.location.href = data.redirect; return; }
                btn.classList.toggle('is-fav', data.favorited);
                btn.textContent = data.favorited ? '♥' : '♡';
            }).catch(function () {});
        });
    });

    // Booking price calculation
    var bookingForm = document.getElementById('bookingForm');
    if (bookingForm) {
        var unitPrice = parseFloat(bookingForm.getAttribute('data-price')) || 0;
        var guests = document.getElementById('guests');
        var totalOut = document.getElementById('bookingTotal');
        var update = function () {
            var n = Math.max(1, parseInt(guests.value, 10) || 1);
            var total = unitPrice * n;
            if (totalOut) {
                totalOut.textContent = '₱' + total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        };
        if (guests) { guests.addEventListener('input', update); update(); }
    }

    // ── Online Presence & Heartbeat (Multi-tab aware) ─────────────────────
    var authMeta = document.querySelector('meta[name="auth-user-id"]');
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var currentUserId = authMeta ? authMeta.getAttribute('content') : null;
    var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    if (currentUserId && csrfToken) {
        var TAB_STORAGE_KEY = 'guidemate_active_tabs_' + currentUserId;
        var TAB_EXPIRE_MS = 45000;
        var tabId = sessionStorage.getItem('guidemate_tab_id');
        if (!tabId) {
            tabId = 'tab_' + Math.random().toString(36).substring(2, 9) + '_' + Date.now();
            sessionStorage.setItem('guidemate_tab_id', tabId);
        }

        var getActiveTabs = function () {
            try {
                var raw = localStorage.getItem(TAB_STORAGE_KEY);
                var tabs = raw ? JSON.parse(raw) : {};
                var now = Date.now();
                var cleaned = {};
                for (var id in tabs) {
                    if (Object.prototype.hasOwnProperty.call(tabs, id)) {
                        if (now - tabs[id] < TAB_EXPIRE_MS) {
                            cleaned[id] = tabs[id];
                        }
                    }
                }
                return cleaned;
            } catch (e) {
                return {};
            }
        };

        var saveActiveTabs = function (tabs) {
            try {
                localStorage.setItem(TAB_STORAGE_KEY, JSON.stringify(tabs));
            } catch (e) {}
        };

        var touchTabPresence = function () {
            var tabs = getActiveTabs();
            tabs[tabId] = Date.now();
            saveActiveTabs(tabs);
        };

        var removeThisTab = function () {
            var tabs = getActiveTabs();
            delete tabs[tabId];
            saveActiveTabs(tabs);
            return Object.keys(tabs).length;
        };

        touchTabPresence();

        var sendPing = function () {
            touchTabPresence();
            fetch('/presence/ping', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: '_token=' + encodeURIComponent(csrfToken)
            }).catch(function () {});
        };

        setInterval(sendPing, 25000);

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                sendPing();
            }
        });

        var handleTabUnload = function () {
            var remaining = removeThisTab();
            if (remaining === 0) {
                var formData = new FormData();
                formData.append('_token', csrfToken);
                if (navigator.sendBeacon) {
                    navigator.sendBeacon('/presence/offline', formData);
                } else {
                    fetch('/presence/offline', {
                        method: 'POST',
                        credentials: 'same-origin',
                        keepalive: true,
                        body: formData
                    }).catch(function () {});
                }
            }
        };

        window.addEventListener('beforeunload', handleTabUnload);
        window.addEventListener('pagehide', handleTabUnload);
    }

    // ── Message Thread Polling, Presence & Translation ────────────────────
    var threadBody = document.getElementById('threadBody');
    if (threadBody) {
        threadBody.scrollTop = threadBody.scrollHeight;

        var partnerId = threadBody.getAttribute('data-partner-id');
        var meId = parseInt(threadBody.getAttribute('data-me-id') || '0', 10);
        var pollUrl = threadBody.getAttribute('data-poll-url');
        var targetLang = threadBody.getAttribute('data-target-lang') || 'en';
        var lastId = parseInt(threadBody.getAttribute('data-last-id') || '0', 10);

        var LANG_NAMES = {
            'tl': 'Tagalog', 'ceb': 'Cebuano', 'en': 'English', 'es': 'Spanish',
            'ko': 'Korean', 'ja': 'Japanese', 'zh': 'Chinese', 'fr': 'French', 'de': 'German'
        };

        var formatLangName = function (code) {
            if (!code) return '';
            var c = code.toLowerCase().split('-')[0];
            return LANG_NAMES[c] || code.toUpperCase();
        };

        var escapeHtml = function (str) {
            return (str || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        // Auto-translate toggle setup (Defaults to ON matching the mobile app)
        var btnAutoTranslate = document.getElementById('btnAutoTranslate');
        var autoTranslateBadge = document.getElementById('autoTranslateBadge');
        var isAutoTranslate = localStorage.getItem('guidemate_auto_translate') !== '0';

        var formatTime = function (dateStr) {
            if (!dateStr) return '';
            var d = new Date(dateStr.replace(/-/g, '/'));
            if (isNaN(d.getTime())) return dateStr;
            var h = d.getHours();
            var m = d.getMinutes();
            var ampm = h >= 12 ? 'pm' : 'am';
            h = h % 12;
            if (h === 0) h = 12;
            return h + ':' + (m < 10 ? '0' + m : m) + ' ' + ampm;
        };

        var syncAutoTranslateUI = function () {
            if (!btnAutoTranslate) return;
            if (isAutoTranslate) {
                btnAutoTranslate.classList.add('active');
                btnAutoTranslate.setAttribute('aria-pressed', 'true');
                if (autoTranslateBadge) autoTranslateBadge.textContent = 'ON';
            } else {
                btnAutoTranslate.classList.remove('active');
                btnAutoTranslate.setAttribute('aria-pressed', 'false');
                if (autoTranslateBadge) autoTranslateBadge.textContent = 'OFF';
            }
        };

        var showOriginal = function (bubble) {
            if (!bubble) return;
            var orig = bubble.getAttribute('data-original') || '';
            var textEl = bubble.querySelector('.bubble-text');
            if (textEl) textEl.innerHTML = escapeHtml(orig).replace(/\n/g, '<br>');
            bubble.setAttribute('data-viewing', 'original');
            var label = bubble.querySelector('.bubble-trans-label');
            if (label) label.textContent = 'Show translation';
            var row = bubble.querySelector('.bubble-trans-row');
            if (row) row.classList.remove('hidden');
            var btn = bubble.querySelector('.bubble-trans-btn');
            if (btn) btn.classList.remove('is-translated');
        };

        var showTranslated = function (bubble) {
            if (!bubble) return;
            var trans = bubble.getAttribute('data-translated') || '';
            var textEl = bubble.querySelector('.bubble-text');
            if (textEl) textEl.innerHTML = escapeHtml(trans).replace(/\n/g, '<br>');
            bubble.setAttribute('data-viewing', 'translated');
            var label = bubble.querySelector('.bubble-trans-label');
            if (label) label.textContent = 'Show original';
            var row = bubble.querySelector('.bubble-trans-row');
            if (row) row.classList.remove('hidden');
            var btn = bubble.querySelector('.bubble-trans-btn');
            if (btn) btn.classList.add('is-translated');
        };

        var fetchTranslation = function (bubble) {
            if (!bubble) return;
            if (bubble.getAttribute('data-loading') === '1') return; // Prevent duplicate requests
            var msgId = bubble.getAttribute('data-msg-id');
            if (!msgId) return;

            var btn = bubble.querySelector('.bubble-trans-btn');
            var row = bubble.querySelector('.bubble-trans-row');
            var statusEl = bubble.querySelector('.bubble-trans-status');

            bubble.setAttribute('data-loading', '1');
            if (btn) {
                btn.classList.add('is-loading');
                btn.disabled = true;
            }
            if (row) row.classList.remove('hidden');
            if (statusEl) statusEl.textContent = 'Translating...';

            fetch('/messages/' + msgId + '/translate', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: '_token=' + encodeURIComponent(csrfToken) + '&target_lang=' + encodeURIComponent(targetLang)
            })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) {
                bubble.removeAttribute('data-loading');
                if (btn) {
                    btn.classList.remove('is-loading');
                    btn.disabled = false;
                }
                if (statusEl) statusEl.textContent = '';

                if (data.translated && data.body) {
                    bubble.setAttribute('data-translated', data.body);
                    if (data.source_lang) bubble.setAttribute('data-source-lang', data.source_lang);
                    showTranslated(bubble);
                } else {
                    // Already in target language or translation not required
                    if (statusEl) {
                        statusEl.textContent = data.note || 'Already in preferred language.';
                        setTimeout(function () {
                            if (statusEl) statusEl.textContent = '';
                            if (bubble.getAttribute('data-viewing') === 'original' && !bubble.getAttribute('data-translated')) {
                                if (row) row.classList.add('hidden');
                            }
                        }, 3000);
                    }
                }
            })
            .catch(function () {
                bubble.removeAttribute('data-loading');
                if (btn) {
                    btn.classList.remove('is-loading');
                    btn.disabled = false;
                }
                if (statusEl) {
                    statusEl.textContent = 'Translation failed. Please retry.';
                    setTimeout(function () {
                        if (statusEl) statusEl.textContent = '';
                        if (bubble.getAttribute('data-viewing') === 'original' && !bubble.getAttribute('data-translated')) {
                            if (row) row.classList.add('hidden');
                        }
                    }, 4000);
                }
            });
        };

        if (btnAutoTranslate) {
            syncAutoTranslateUI();
            btnAutoTranslate.addEventListener('click', function () {
                isAutoTranslate = !isAutoTranslate;
                localStorage.setItem('guidemate_auto_translate', isAutoTranslate ? '1' : '0');
                syncAutoTranslateUI();

                if (isAutoTranslate) {
                    var untranslated = threadBody.querySelectorAll('.bubble.in:not([data-translated]), .bubble.in[data-translated=""]');
                    untranslated.forEach(function (b) {
                        fetchTranslation(b);
                    });
                    var cachedOriginals = threadBody.querySelectorAll('.bubble.in[data-viewing="original"]');
                    cachedOriginals.forEach(function (b) {
                        if (b.getAttribute('data-translated')) {
                            showTranslated(b);
                        }
                    });
                }
            });
        }

        // On initial page load with auto-translate, translate any incoming bubbles not yet translated
        if (isAutoTranslate) {
            var untranslatedInit = threadBody.querySelectorAll('.bubble.in:not([data-translated]), .bubble.in[data-translated=""]');
            untranslatedInit.forEach(function (b) {
                fetchTranslation(b);
            });
        }

        threadBody.addEventListener('click', function (e) {
            var topBtn = e.target.closest('.bubble-trans-btn');
            if (topBtn) {
                e.preventDefault();
                var bubble = topBtn.closest('.bubble');
                if (!bubble) return;
                var hasTrans = Boolean(bubble.getAttribute('data-translated'));
                if (hasTrans) {
                    var viewing = bubble.getAttribute('data-viewing') || 'original';
                    if (viewing === 'translated') {
                        showOriginal(bubble);
                    } else {
                        showTranslated(bubble);
                    }
                } else {
                    fetchTranslation(bubble);
                }
                return;
            }

            var toggleBtn = e.target.closest('.bubble-trans-toggle');
            if (toggleBtn) {
                e.preventDefault();
                var bubble = toggleBtn.closest('.bubble');
                if (!bubble) return;
                var viewing = bubble.getAttribute('data-viewing') || 'original';
                if (viewing === 'translated') {
                    showOriginal(bubble);
                } else {
                    var hasTrans = Boolean(bubble.getAttribute('data-translated'));
                    if (hasTrans) {
                        showTranslated(bubble);
                    } else {
                        fetchTranslation(bubble);
                    }
                }
                return;
            }
        });

        if (partnerId && pollUrl && meId) {
            setInterval(function () {
                var pollQuery = '?since=' + lastId +
                    '&lang=' + encodeURIComponent(targetLang) +
                    '&auto_translate=' + (isAutoTranslate ? '1' : '0');

                fetch(pollUrl + pollQuery, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (typeof data.partner_online === 'boolean') {
                        var badge = document.getElementById('partnerPresenceBadge');
                        if (badge) {
                            var isOnline = data.partner_online;
                            badge.className = 'presence-badge ' + (isOnline ? 'online' : 'offline');
                            var textSpan = badge.querySelector('.presence-text');
                            if (textSpan) textSpan.textContent = isOnline ? 'Online' : 'Offline';
                            badge.title = isOnline ? 'Online' : 'Offline';
                        }
                        var convDot = document.getElementById('convDot-' + partnerId);
                        if (convDot) {
                            convDot.className = 'presence-dot ' + (data.partner_online ? 'online' : 'offline');
                            convDot.title = data.partner_online ? 'Online' : 'Offline';
                        }
                    }

                    if (!data.messages || !data.messages.length) return;

                    data.messages.forEach(function (m) {
                        var isMine = parseInt(m.sender_id, 10) === meId;
                        var bubble = document.createElement('div');
                        bubble.className = 'bubble ' + (isMine ? 'out' : 'in');
                        bubble.setAttribute('data-msg-id', String(m.id));
                        bubble.setAttribute('data-original', m.body || '');

                        var hasTrans = Boolean(m.translated && m.translated_body);
                        bubble.setAttribute('data-translated', hasTrans ? m.translated_body : '');
                        bubble.setAttribute('data-source-lang', m.source_lang || '');
                        bubble.setAttribute('data-viewing', hasTrans ? 'translated' : 'original');

                        if (!isMine) {
                            var topBtn = document.createElement('button');
                            topBtn.type = 'button';
                            topBtn.className = 'bubble-trans-btn' + (hasTrans ? ' is-translated' : '');
                            topBtn.setAttribute('data-msg-id', String(m.id));
                            topBtn.title = hasTrans ? 'Toggle translation' : 'Translate message';
                            topBtn.setAttribute('aria-label', 'Translate message');
                            topBtn.innerHTML = '<svg class="trans-icon" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                                '<path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/></svg>';
                            bubble.appendChild(topBtn);
                        }

                        var textDiv = document.createElement('div');
                        textDiv.className = 'bubble-text';
                        textDiv.id = 'bubbleText-' + m.id;
                        var displayBody = hasTrans ? m.translated_body : (m.body || '');
                        textDiv.innerHTML = escapeHtml(displayBody).replace(/\n/g, '<br>');
                        bubble.appendChild(textDiv);

                        if (!isMine) {
                            var transRow = document.createElement('div');
                            transRow.className = 'bubble-trans-row' + (hasTrans ? '' : ' hidden');
                            transRow.id = 'transRow-' + m.id;
                            transRow.innerHTML = '<button type="button" class="bubble-trans-toggle" data-msg-id="' + m.id + '">' +
                                '<svg class="trans-toggle-icon" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                                '<path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/></svg>' +
                                '<span class="bubble-trans-label">' + (hasTrans ? 'Show original' : 'Show translation') + '</span>' +
                                '</button>' +
                                '<span class="bubble-trans-status" id="transStatus-' + m.id + '"></span>';
                            bubble.appendChild(transRow);
                        }

                        var meta = document.createElement('div');
                        meta.className = 'bubble-meta';
                        var timeEl = document.createElement('small');
                        timeEl.textContent = formatTime(m.created_at);
                        meta.appendChild(timeEl);
                        bubble.appendChild(meta);

                        threadBody.appendChild(bubble);
                        lastId = Math.max(lastId, parseInt(m.id, 10));

                        if (!isMine && isAutoTranslate && !hasTrans) {
                            fetchTranslation(bubble);
                        }
                    });

                    threadBody.setAttribute('data-last-id', String(lastId));
                    threadBody.scrollTop = threadBody.scrollHeight;
                })
                .catch(function () {});
            }, 4000);
        }
    }

    // Header shadow on scroll
    var header = document.getElementById('siteHeader');
    if (header) {
        var onScroll = function () { header.classList.toggle('scrolled', window.scrollY > 8); };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    // Scroll-reveal animations (staggered) — applied automatically to common
    // public browse and landing building blocks.
    // Operational workspaces (such as the hotel overview, room inventory,
    // reservations, and admin control panels) must always render immediately on initial
    // load, tab activation, or refresh without requiring any scrolling or intersection triggers.
    var revealSelector = '.listing-card, .cat-card, .stat, .panel, .review, .card-soft, .cta-band, .section-head, .review-summary';
    var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var isDashboardWorkspace = document.body && (
        document.body.classList.contains('page-dashboard') ||
        document.body.classList.contains('admin-body') ||
        document.querySelector('.dash-layout') !== null
    );

    var revealEls = isDashboardWorkspace ? [] : Array.prototype.slice.call(document.querySelectorAll(revealSelector)).filter(function (el) {
        return !el.closest('.dash-layout, .dash-main, .page-dashboard, .admin-body, #inventory, #reservations');
    });

    if (revealEls.length && 'IntersectionObserver' in window && !prefersReduced) {
        revealEls.forEach(function (el) { el.classList.add('reveal'); });
        var io = new IntersectionObserver(function (entries, obs) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    var el = entry.target;
                    // Stagger items that share the same parent (e.g. card grids).
                    var siblings = Array.prototype.slice.call(el.parentNode.children).filter(function (c) {
                        return c.classList.contains('reveal');
                    });
                    var idx = siblings.indexOf(el);
                    el.style.transitionDelay = Math.min(idx * 80, 400) + 'ms';
                    el.classList.add('is-in');
                    obs.unobserve(el);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
        revealEls.forEach(function (el) { io.observe(el); });
    }

    // Ensure all dashboard and overview content is immediately visible without scrolling
    var ensureOverviewVisible = function () {
        var dashEls = document.querySelectorAll('.dash-layout .panel, .dash-layout .stat, .dash-layout .section-head, #inventory, #reservations, .page-dashboard .panel, .page-dashboard .stat');
        dashEls.forEach(function (el) {
            el.classList.remove('reveal');
            el.classList.add('is-in');
            el.style.opacity = '1';
            el.style.transform = 'none';
            el.style.visibility = 'visible';
        });
    };
    ensureOverviewVisible();

    // Re-verify immediate visibility whenever the Overview tab or dashboard navigation is activated
    document.querySelectorAll('.dash-nav a').forEach(function (link) {
        link.addEventListener('click', function () {
            ensureOverviewVisible();
        });
    });
    window.addEventListener('hashchange', ensureOverviewVisible);

    // Profile photo: live preview of the selected image before saving
    var avatarInput = document.getElementById('avatar');
    var avatarPreview = document.getElementById('avatarPreview');
    if (avatarInput && avatarPreview) {
        avatarInput.addEventListener('change', function () {
            var file = avatarInput.files && avatarInput.files[0];
            if (file && file.type.indexOf('image/') === 0) {
                avatarPreview.src = URL.createObjectURL(file);
            }
        });
    }

    // Profile & about you: dynamically adjust textarea height to fit content
    var bioEl = document.getElementById('bio');
    if (bioEl) {
        var autoResize = function () {
            bioEl.style.height = 'auto';
            var borderOffset = (bioEl.offsetHeight - bioEl.clientHeight) || 4;
            bioEl.style.height = Math.max(120, bioEl.scrollHeight + borderOffset) + 'px';
        };
        bioEl.addEventListener('input', autoResize);
        bioEl.addEventListener('change', autoResize);
        window.addEventListener('resize', autoResize);
        autoResize();
    }

    // Booking: stop tourists from selecting a date the guide is already booked
    var bookingDate = document.getElementById('booking_date');
    if (bookingDate && bookingDate.dataset.booked) {
        var takenDates = bookingDate.dataset.booked.split(',').filter(Boolean);
        bookingDate.addEventListener('change', function () {
            if (takenDates.indexOf(bookingDate.value) !== -1) {
                bookingDate.setCustomValidity('That date is already booked. Please choose another date or another guide.');
                bookingDate.reportValidity();
                bookingDate.value = '';
            } else {
                bookingDate.setCustomValidity('');
            }
        });
    }

    // Listing form: live preview of the selected cover photo before saving
    var coverInput = document.getElementById('cover_file');
    var coverPreview = document.getElementById('coverPreview');
    if (coverInput && coverPreview) {
        coverInput.addEventListener('change', function () {
            var file = coverInput.files && coverInput.files[0];
            if (file && file.type.indexOf('image/') === 0) {
                coverPreview.src = URL.createObjectURL(file);
                coverPreview.style.display = '';
            }
        });
    }

    // Guide booking actions: confirm refund when cancelling a paid booking
    document.querySelectorAll('.guide-booking-action').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (document.body.classList.contains('guide-warned')) {
                e.preventDefault();
                alert('Your account has an active administrator warning. Booking actions are restricted until an admin clears it.');
                return;
            }
            var select = form.querySelector('.guide-status-select, [name="status"]');
            var statusVal = select ? select.value : (form.getAttribute('data-action') || '');
            if (statusVal !== 'cancelled') {
                return;
            }
            if (form.getAttribute('data-paid') !== '1') {
                if (!confirm('Cancel this booking?')) {
                    e.preventDefault();
                }
                return;
            }
            var amount = parseFloat(form.getAttribute('data-amount') || '0');
            var label = '₱' + amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (!confirm('Cancel this paid booking? ' + label + ' will be refunded to the tourist automatically.')) {
                e.preventDefault();
            }
        });
    });

    // Report form: show amount field when "extra payment" is selected
    var problemType = document.getElementById('problem_type');
    var amountField = document.getElementById('amountField');
    if (problemType && amountField) {
        var toggleAmount = function () {
            amountField.style.display = problemType.value === 'extra_payment' ? '' : 'none';
        };
        problemType.addEventListener('change', toggleAmount);
        toggleAmount();
    }

    // Registration: reveal guide verification uploads only for guides
    var guideDocs = document.getElementById('guideDocs');
    var roleRadios = document.querySelectorAll('input[type="radio"][name="role"]');
    if (guideDocs && roleRadios.length) {
        var syncGuideDocs = function () {
            var selected = document.querySelector('input[name="role"]:checked');
            guideDocs.style.display = (selected && selected.value === 'guide') ? '' : 'none';
        };
        roleRadios.forEach(function (r) { r.addEventListener('change', syncGuideDocs); });
        syncGuideDocs();
    }

    // Interactive star rating picker
    document.querySelectorAll('.star-picker').forEach(function (picker) {
        var input = picker.querySelector('input[name="rating"]');
        var stars = Array.prototype.slice.call(picker.querySelectorAll('.star'));
        var paint = function (val) {
            stars.forEach(function (s, i) { s.classList.toggle('on', i < val); });
        };
        stars.forEach(function (star, i) {
            star.addEventListener('mouseenter', function () { paint(i + 1); });
            star.addEventListener('click', function () { if (input) { input.value = i + 1; } paint(i + 1); });
        });
        picker.addEventListener('mouseleave', function () { paint(input ? parseInt(input.value, 10) || 0 : 0); });
        paint(input ? parseInt(input.value, 10) || 0 : 0);
    });

    // Preferences modal
    var prefOpen = document.getElementById('prefOpen');
    var prefModal = document.getElementById('prefModal');
    var prefClose = document.getElementById('prefClose');
    if (prefOpen && prefModal) {
        var prefTabs = prefModal.querySelectorAll('.pref-tab');
        var prefPanels = prefModal.querySelectorAll('.pref-panel');

        var openPref = function () {
            prefModal.hidden = false;
            prefModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            if (prefClose) prefClose.focus();
        };
        var closePref = function () {
            prefModal.hidden = true;
            prefModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            prefOpen.focus();
        };

        prefOpen.addEventListener('click', openPref);
        if (prefClose) prefClose.addEventListener('click', closePref);
        prefModal.addEventListener('click', function (e) {
            if (e.target === prefModal) closePref();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !prefModal.hidden) closePref();
        });

        prefTabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var name = tab.getAttribute('data-tab');
                prefTabs.forEach(function (t) {
                    var active = t === tab;
                    t.classList.toggle('is-active', active);
                    t.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                prefPanels.forEach(function (panel) {
                    var show = panel.getAttribute('data-panel') === name;
                    panel.classList.toggle('is-active', show);
                    panel.hidden = !show;
                });
            });
        });
    }

    // My bookings: tap QR to reveal booking code
    document.querySelectorAll('.booking-qr-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var wrap = btn.closest('.booking-qr-wrap');
            if (!wrap) return;
            var panel = wrap.querySelector('.booking-code-panel');
            var hint = wrap.querySelector('.booking-qr-hint');
            if (!panel) return;
            var open = panel.hidden;
            panel.hidden = !open;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (hint) {
                hint.textContent = open ? 'Tap QR to hide code' : 'Tap QR to show code';
            }
        });
    });
    document.querySelectorAll('.booking-code-copy').forEach(function (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var code = copyBtn.getAttribute('data-code') || '';
            if (!code || !navigator.clipboard) return;
            navigator.clipboard.writeText(code).then(function () {
                var prev = copyBtn.textContent;
                copyBtn.textContent = 'Copied!';
                setTimeout(function () { copyBtn.textContent = prev; }, 1600);
            });
        });
    });

    // Hero background video — smooth loop, auto-resume if browser pauses
    var heroSection = document.querySelector('.hero');
    var heroVideo = document.getElementById('heroVideo');
    if (heroSection && heroVideo) {
        var poster = heroVideo.getAttribute('poster') || '';
        if (poster) {
            heroSection.style.setProperty('--hero-poster', 'url("' + poster + '")');
        }

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            heroSection.classList.add('is-poster-only');
        } else {
            heroVideo.muted = true;
            heroVideo.defaultMuted = true;
            heroVideo.playsInline = true;

            var keepPlaying = function () {
                if (document.hidden || heroSection.classList.contains('is-poster-only')) {
                    return;
                }
                if (heroVideo.paused && heroVideo.readyState >= 2) {
                    heroVideo.play().catch(function () {});
                }
            };

            var onReady = function () {
                keepPlaying();
            };

            heroVideo.addEventListener('loadeddata', onReady);
            heroVideo.addEventListener('canplay', onReady);
            heroVideo.addEventListener('canplaythrough', onReady);

            heroVideo.addEventListener('pause', function () {
                window.setTimeout(keepPlaying, 80);
            });

            heroVideo.addEventListener('ended', function () {
                heroVideo.currentTime = 0;
                keepPlaying();
            });

            // Seamless loop — some browsers freeze on native loop with large files
            heroVideo.addEventListener('timeupdate', function () {
                var dur = heroVideo.duration;
                if (dur && dur > 0 && heroVideo.currentTime >= dur - 0.35) {
                    heroVideo.currentTime = 0.05;
                }
            });

            heroVideo.addEventListener('waiting', function () {
                window.setTimeout(keepPlaying, 400);
            });

            heroVideo.addEventListener('stalled', function () {
                window.setTimeout(keepPlaying, 600);
            });

            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    keepPlaying();
                }
            });

            window.setInterval(keepPlaying, 2500);

            keepPlaying();
        }
    }

    document.querySelectorAll('form.js-unrestrict').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var name = form.getAttribute('data-name') || 'this partner';
            var ok = window.confirm(
                'Unrestrict ' + name + '?\n\nConfirm that this partner is not harming tourists. Booking actions (Confirm, Complete, Cancel) will be restored.'
            );
            if (!ok) {
                e.preventDefault();
            }
        });
    });

    // Password visibility toggle (eye icon: switch between dots and plain text)
    function initPasswordToggles(root) {
        var container = root || document;
        container.querySelectorAll('.toggle-password').forEach(function (btn) {
            if (btn.dataset.toggleBound === '1') {
                return;
            }
            btn.dataset.toggleBound = '1';

            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                var wrap = btn.closest('.input-wrap') || btn.parentElement;
                if (!wrap) { return; }

                var input = wrap.querySelector('input');
                if (!input) { return; }

                var wasPassword = (input.type === 'password' || input.getAttribute('type') === 'password');
                var nextType = wasPassword ? 'text' : 'password';

                input.type = nextType;
                input.setAttribute('type', nextType);

                btn.setAttribute('aria-label', wasPassword ? 'Hide password' : 'Show password');
                btn.setAttribute('title', wasPassword ? 'Hide password' : 'Show password');

                var eyeOpen = btn.querySelector('.eye-open');
                var eyeClosed = btn.querySelector('.eye-closed');
                if (eyeOpen && eyeClosed) {
                    eyeOpen.style.display = wasPassword ? 'none' : 'block';
                    eyeClosed.style.display = wasPassword ? 'block' : 'none';
                }

                try { input.focus(); } catch (err) {}
            });
        });
    }

    window.initPasswordToggles = initPasswordToggles;
    initPasswordToggles();

    // Admin Note "Read more" / "Show less" toggle
    document.addEventListener('click', function (e) {
        var toggleBtn = e.target.closest('.admin-note-toggle');
        if (!toggleBtn) return;
        e.preventDefault();
        var card = toggleBtn.closest('.admin-note-card');
        if (!card) return;
        var isExpanded = card.classList.toggle('is-expanded');
        toggleBtn.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        var textSpan = toggleBtn.querySelector('.toggle-text');
        if (textSpan) {
            textSpan.textContent = isExpanded ? 'Show less' : 'Read more';
        }
    });

    // -------------------------------------------------------------
    // Account Verification Gating for Listing-Creation Flow
    // -------------------------------------------------------------
    function initVerificationGating() {
        var modal = document.getElementById('verificationGatingModal');
        if (!modal) {
            return;
        }

        var loadingState = document.getElementById('verifLoadingState');
        var errorState = document.getElementById('verifErrorState');
        var approvedState = document.getElementById('verifApprovedState');
        var defaultState = document.getElementById('verifDefaultState');
        var statusBanner = document.getElementById('verifStatusBanner');
        var statusTitle = document.getElementById('verifStatusTitle');
        var statusText = document.getElementById('verifStatusText');
        var statusIcon = document.getElementById('verifStatusIcon');
        var cancelBtn = document.getElementById('verifCancelBtn');
        var actionBtn = document.getElementById('verifActionBtn');
        var retryBtn = document.getElementById('verifRetryBtn');
        var proceedBtn = document.getElementById('verifProceedBtn');
        var closeBtns = modal.querySelectorAll('[data-close-verif-modal]');

        var endpoint = modal.dataset.statusEndpoint || '/dashboard/verification/status';
        var lastTrigger = null;
        var isChecking = false;

        function isUserVerified() {
            var metaVerified = document.querySelector('meta[name="auth-user-verified"]');
            if (metaVerified && metaVerified.content === '1') {
                return true;
            }
            return modal.dataset.userVerified === '1';
        }

        function setUserVerified(verified, status) {
            modal.dataset.userVerified = verified ? '1' : '0';
            if (status) {
                modal.dataset.userStatus = status;
            }
            var metaVerified = document.querySelector('meta[name="auth-user-verified"]');
            if (metaVerified) {
                metaVerified.content = verified ? '1' : '0';
            }
            var metaStatus = document.querySelector('meta[name="auth-user-status"]');
            if (metaStatus && status) {
                metaStatus.content = status;
            }
        }

        function showState(state) {
            if (loadingState) loadingState.style.display = (state === 'loading') ? 'block' : 'none';
            if (errorState) errorState.style.display = (state === 'error') ? 'block' : 'none';
            if (approvedState) approvedState.style.display = (state === 'approved') ? 'block' : 'none';
            if (defaultState) defaultState.style.display = (state === 'default') ? 'block' : 'none';
        }

        function openModal() {
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';

            // Show default view or check live status
            checkStatus();
        }

        function closeModal() {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            if (lastTrigger && typeof lastTrigger.focus === 'function') {
                try { lastTrigger.focus(); } catch (err) {}
            }
        }

        function checkStatus() {
            if (isChecking) return;
            isChecking = true;
            showState('loading');

            fetch(endpoint, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('Server returned ' + res.status);
                }
                return res.json();
            })
            .then(function (data) {
                isChecking = false;
                if (data.is_verified || data.guide_status === 'approved') {
                    setUserVerified(true, 'approved');
                    showState('approved');
                    if (proceedBtn) {
                        var isRoomTrigger = lastTrigger && (
                            lastTrigger.dataset.action === 'add-room' ||
                            lastTrigger.dataset.openRoomModal !== undefined ||
                            lastTrigger.id === 'btnAddRoomType' ||
                            (lastTrigger.textContent || '').toLowerCase().indexOf('room') !== -1
                        );
                        var isHotelTrigger = lastTrigger && (
                            lastTrigger.dataset.action === 'new-hotel' ||
                            lastTrigger.dataset.openNewHotel !== undefined ||
                            (lastTrigger.href && lastTrigger.href.indexOf('/dashboard/hotels') !== -1) ||
                            (lastTrigger.textContent || '').toLowerCase().indexOf('hotel') !== -1
                        );
                        if (isRoomTrigger) {
                            proceedBtn.textContent = 'Continue to Add Room Type →';
                        } else if (isHotelTrigger) {
                            proceedBtn.textContent = 'Continue to My Hotels →';
                        } else {
                            proceedBtn.textContent = 'Continue to New Listing →';
                        }
                        proceedBtn.focus();
                    }
                } else {
                    setUserVerified(false, data.guide_status || 'none');
                    updateUnverifiedContent(data);
                    showState('default');
                    if (actionBtn) actionBtn.focus();
                }
            })
            .catch(function (err) {
                isChecking = false;
                var errMessage = document.getElementById('verifErrorMessage');
                if (errMessage) {
                    errMessage.textContent = 'Unable to verify your account status. Please check your network connection and try again.';
                }
                showState('error');
                if (retryBtn) retryBtn.focus();
            });
        }

        function updateUnverifiedContent(data) {
            var status = data.guide_status || 'pending';
            if (status === 'rejected') {
                if (statusBanner) {
                    statusBanner.className = 'status-banner banner-red';
                }
                if (statusIcon) statusIcon.textContent = '⚠️';
                if (statusTitle) statusTitle.textContent = 'Application Needs Update';
                if (statusText) {
                    statusText.textContent = data.admin_note
                        ? ('Admin note: ' + data.admin_note)
                        : 'Your submitted verification documents were not approved. Please review the note and re-submit your documents.';
                }
            } else if (status === 'none') {
                if (statusBanner) {
                    statusBanner.className = 'status-banner banner-amber';
                }
                if (statusIcon) statusIcon.textContent = '📋';
                if (statusTitle) statusTitle.textContent = 'Verification Required';
                if (statusText) statusText.textContent = 'Please submit your valid government ID and credentials for administrator review.';
            } else {
                if (statusBanner) {
                    statusBanner.className = 'status-banner banner-amber';
                }
                if (statusIcon) statusIcon.textContent = '⏳';
                if (statusTitle) statusTitle.textContent = 'Verification in Progress';
                if (statusText) statusText.textContent = 'An administrator is currently reviewing your account information and documents.';
            }
        }

        // Close button handlers
        closeBtns.forEach(function (btn) {
            btn.addEventListener('click', closeModal);
        });
        if (cancelBtn) {
            cancelBtn.addEventListener('click', closeModal);
        }

        // Retry handler
        if (retryBtn) {
            retryBtn.addEventListener('click', function (e) {
                e.preventDefault();
                checkStatus();
            });
        }

        // Proceed handler when verified
        if (proceedBtn) {
            proceedBtn.addEventListener('click', function (e) {
                e.preventDefault();
                closeModal();
                var isRoomTrigger = lastTrigger && (
                    lastTrigger.dataset.action === 'add-room' ||
                    lastTrigger.dataset.openRoomModal !== undefined ||
                    lastTrigger.id === 'btnAddRoomType' ||
                    (lastTrigger.textContent || '').toLowerCase().indexOf('room') !== -1
                );
                if (isRoomTrigger) {
                    var roomModal = document.getElementById('modal-add-room');
                    if (roomModal) {
                        roomModal.style.display = 'flex';
                        var firstRoomInput = roomModal.querySelector('input, select, textarea');
                        if (firstRoomInput) {
                            try { firstRoomInput.focus(); } catch (err) {}
                        }
                        return;
                    }
                }
                var isHotelTrigger = lastTrigger && (
                    lastTrigger.dataset.action === 'new-hotel' ||
                    lastTrigger.dataset.openNewHotel !== undefined ||
                    (lastTrigger.href && lastTrigger.href.indexOf('/dashboard/hotels') !== -1) ||
                    (lastTrigger.textContent || '').toLowerCase().indexOf('new hotel') !== -1
                );
                if (isHotelTrigger) {
                    window.location.href = '/dashboard/hotels?create=1';
                    return;
                }
                var newListingModal = document.getElementById('newListingModal');
                if (newListingModal) {
                    newListingModal.hidden = false;
                    newListingModal.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';
                    var firstInput = newListingModal.querySelector('input, select, textarea');
                    if (firstInput) firstInput.focus();
                } else if (lastTrigger && lastTrigger.href) {
                    window.location.href = lastTrigger.href;
                } else {
                    window.location.href = '/dashboard/listings?create=1';
                }
            });
        }

        // Escape key to close
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.hidden) {
                closeModal();
            }
        });

        // Accessible focus trap inside modal dialog
        modal.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab' || modal.hidden) return;
            var focusable = modal.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
            var visibleFocusable = Array.prototype.filter.call(focusable, function (el) {
                return el.offsetWidth > 0 || el.offsetHeight > 0 || el.getClientRects().length > 0;
            });
            if (visibleFocusable.length === 0) return;

            var first = visibleFocusable[0];
            var last = visibleFocusable[visibleFocusable.length - 1];

            if (e.shiftKey) {
                if (document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                }
            } else {
                if (document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        });

        // Global click interceptor for listing and hotel inventory entry points
        document.addEventListener('click', function (e) {
            var target = e.target;
            var trigger = target.closest('[data-open-new-listing], [data-action="new-listing"], [data-open-new-hotel], [data-action="new-hotel"], [data-action="add-room"], [data-open-room-modal], #btnAddRoomType, a[href*="/dashboard/listings/create"], a[href*="/dashboard/vehicles/create"], a[href*="/dashboard/hotels?create=1"], a[href*="/dashboard/listings?create=1"]');

            if (!trigger) {
                var btn = target.closest('a.btn, button.btn');
                if (btn && btn.closest('.dash-layout, .section-head, .empty-state, .panel, .panel-head')) {
                    var txt = (btn.textContent || '').trim().toLowerCase();
                    if (txt.indexOf('new listing') !== -1 || txt.indexOf('add listing') !== -1 || txt.indexOf('create listing') !== -1 || txt.indexOf('list a vehicle') !== -1 || txt.indexOf('new hotel') !== -1 || txt.indexOf('add room') !== -1) {
                        trigger = btn;
                    }
                }
            }

            if (!trigger) {
                return;
            }

            // If user is already verified, do not show gating modal!
            if (isUserVerified()) {
                return;
            }

            // Gated! Prevent accessing listing form / room modal and display pop-up modal
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }
            lastTrigger = trigger;
            openModal();
        }, true);
    }

    window.initVerificationGating = initVerificationGating;
    initVerificationGating();
})();


// ==========================================================================
// Document Preview Modal & Lightbox
// Opens attached verification documents (images, PDFs) directly in an
// accessible interactive lightbox without full-page navigation.
// ==========================================================================
(function () {
    'use strict';

    function initDocPreviewModal() {
        var modal = document.getElementById('docPreviewModal');
        if (!modal) return;

        var titleEl = document.getElementById('docModalTitle');
        var typeBadgeEl = document.getElementById('docModalTypeBadge');
        var statusBadgeEl = document.getElementById('docModalStatusBadge');
        var ownerEl = document.getElementById('docModalOwner');
        var fileInfoEl = document.getElementById('docModalFileInfo');
        var dateEl = document.getElementById('docModalDate');
        var zoomToolbar = document.getElementById('docModalZoomControls');
        var zoomLevelEl = document.getElementById('docZoomLevel');
        var zoomInBtn = document.getElementById('docZoomInBtn');
        var zoomOutBtn = document.getElementById('docZoomOutBtn');
        var zoomResetBtn = document.getElementById('docZoomResetBtn');
        var externalLink = document.getElementById('docModalExternalLink');
        var downloadBtn = document.getElementById('docModalDownloadBtn');
        var closeBtn = document.getElementById('docModalCloseBtn');

        var loader = document.getElementById('docModalLoader');
        var imageWrap = document.getElementById('docModalImageWrap');
        var imageStage = document.getElementById('docModalImageStage');
        var imgEl = document.getElementById('docModalImg');
        var pdfWrap = document.getElementById('docModalPdfWrap');
        var pdfFrame = document.getElementById('docModalPdfFrame');
        var unsupportedWrap = document.getElementById('docModalUnsupported');
        var errorWrap = document.getElementById('docModalError');
        var errorRetryBtn = document.getElementById('docErrorRetryBtn');
        var errorDownloadBtn = document.getElementById('docErrorDownloadBtn');
        var unsupportedDownload = document.getElementById('docUnsupportedDownload');

        var currentZoom = 1.0;
        var lastTrigger = null;
        var currentData = null;

        function updateZoom(newZoom) {
            currentZoom = Math.min(Math.max(0.3, newZoom), 3.5);
            if (imageStage) {
                imageStage.style.transform = 'scale(' + currentZoom + ')';
            }
            if (zoomLevelEl) {
                zoomLevelEl.textContent = Math.round(currentZoom * 100) + '%';
            }
        }

        function resetZoom() {
            updateZoom(1.0);
        }

        function showState(state) {
            if (loader) loader.style.display = state === 'loading' ? 'flex' : 'none';
            if (imageWrap) imageWrap.style.display = state === 'image' ? 'flex' : 'none';
            if (pdfWrap) pdfWrap.style.display = state === 'pdf' ? 'block' : 'none';
            if (unsupportedWrap) unsupportedWrap.style.display = state === 'unsupported' ? 'flex' : 'none';
            if (errorWrap) errorWrap.style.display = state === 'error' ? 'flex' : 'none';
            if (zoomToolbar) zoomToolbar.style.display = state === 'image' ? 'inline-flex' : 'none';
        }

        function setStatusBadge(status) {
            if (!statusBadgeEl) return;
            var s = (status || 'pending').toLowerCase();
            statusBadgeEl.className = 'doc-status-pill';
            if (s === 'approved' || s === 'verified') {
                statusBadgeEl.classList.add('pill-approved');
                statusBadgeEl.textContent = '✓ Verified';
            } else if (s === 'rejected') {
                statusBadgeEl.classList.add('pill-cancelled');
                statusBadgeEl.textContent = '⚠️ Rejected';
            } else {
                statusBadgeEl.classList.add('pill-pending');
                statusBadgeEl.textContent = '⏳ Under review';
            }
        }

        function openModal() {
            modal.style.display = 'flex';
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            resetZoom();
            if (closeBtn) {
                closeBtn.focus();
            }
        }

        function closeModal() {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            if (imgEl) imgEl.src = '';
            if (pdfFrame) pdfFrame.src = 'about:blank';
            if (lastTrigger && typeof lastTrigger.focus === 'function') {
                lastTrigger.focus();
            }
            lastTrigger = null;
            currentData = null;
        }

        // Close handlers
        modal.addEventListener('click', function (e) {
            if (e.target.hasAttribute('data-doc-close')) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (modal.style.display === 'flex' && e.key === 'Escape') {
                closeModal();
            }
        });

        // Zoom controls
        if (zoomInBtn) {
            zoomInBtn.addEventListener('click', function () {
                updateZoom(currentZoom + 0.25);
            });
        }
        if (zoomOutBtn) {
            zoomOutBtn.addEventListener('click', function () {
                updateZoom(currentZoom - 0.25);
            });
        }
        if (zoomResetBtn) {
            zoomResetBtn.addEventListener('click', resetZoom);
        }

        // Mouse wheel zoom over image stage
        if (imageWrap) {
            imageWrap.addEventListener('wheel', function (e) {
                if (imageWrap.style.display !== 'none') {
                    e.preventDefault();
                    var delta = e.deltaY < 0 ? 0.15 : -0.15;
                    updateZoom(currentZoom + delta);
                }
            }, { passive: false });
        }

        // Image load & error events
        if (imgEl) {
            imgEl.addEventListener('load', function () {
                showState('image');
                resetZoom();
            });
            imgEl.addEventListener('error', function () {
                showState('error');
            });
        }

        // PDF load & error events
        if (pdfFrame) {
            pdfFrame.addEventListener('load', function () {
                if (pdfFrame.src && pdfFrame.src !== 'about:blank') {
                    showState('pdf');
                }
            });
        }

        // Retry handler
        if (errorRetryBtn) {
            errorRetryBtn.addEventListener('click', function () {
                if (currentData) {
                    loadDocumentData(currentData);
                }
            });
        }

        function loadDocumentData(data) {
            currentData = data;
            showState('loading');
            resetZoom();

            var label = data.label || 'Document';
            if (titleEl) titleEl.textContent = label;
            if (typeBadgeEl) typeBadgeEl.textContent = data.doc_type_label || 'Verification Document';
            setStatusBadge(data.status);

            if (ownerEl) {
                ownerEl.textContent = data.owner_name ? 'Submitted by ' + data.owner_name : '';
                ownerEl.style.display = data.owner_name ? 'inline' : 'none';
            }
            if (fileInfoEl) {
                var info = (data.ext ? data.ext.toUpperCase() : '') + (data.file_size_formatted ? ' · ' + data.file_size_formatted : '');
                fileInfoEl.textContent = info || 'Attached file';
            }
            if (dateEl) {
                dateEl.textContent = data.created_at_formatted ? 'Uploaded ' + data.created_at_formatted : '';
                dateEl.style.display = data.created_at_formatted ? 'inline' : 'none';
            }

            var fileUrl = data.file_url || data.raw_url;
            var dlUrl = data.download_url || fileUrl;

            if (externalLink) externalLink.href = fileUrl;
            if (downloadBtn) downloadBtn.href = dlUrl;
            if (errorDownloadBtn) errorDownloadBtn.href = dlUrl;
            if (unsupportedDownload) unsupportedDownload.href = dlUrl;

            if (!data.file_exists && data.file_exists !== undefined) {
                var msgEl = document.getElementById('docErrorMessage');
                if (msgEl) msgEl.textContent = 'The document record exists, but the file was not found on the server disk.';
                showState('error');
                return;
            }

            if (data.is_image) {
                imgEl.src = fileUrl;
            } else if (data.is_pdf) {
                pdfFrame.src = fileUrl;
            } else {
                showState('unsupported');
            }
        }

        function previewDocument(trigger) {
            lastTrigger = trigger;
            openModal();
            showState('loading');

            var docId = trigger.getAttribute('data-doc-id');
            var docUrl = trigger.getAttribute('data-doc-url') || trigger.getAttribute('href');
            var docLabel = trigger.getAttribute('data-doc-label') || trigger.innerText.replace(/^[📎\s]+/, '').replace(/[\s↗]+$/, '').trim();
            var docType = trigger.getAttribute('data-doc-type') || 'Document';
            var docStatus = trigger.getAttribute('data-doc-status') || 'pending';
            var docOwner = trigger.getAttribute('data-doc-owner') || '';

            // Set preliminary display while fetching
            if (titleEl) titleEl.textContent = docLabel || 'Document Preview';
            if (typeBadgeEl) typeBadgeEl.textContent = docType;
            setStatusBadge(docStatus);
            if (ownerEl) {
                ownerEl.textContent = docOwner ? 'Submitted by ' + docOwner : '';
                ownerEl.style.display = docOwner ? 'inline' : 'none';
            }

            if (externalLink) externalLink.href = docUrl;
            if (downloadBtn) downloadBtn.href = docId ? '/documents/download/' + docId : docUrl;

            // If we have a docId, fetch authoritative metadata from controller
            if (docId && parseInt(docId, 10) > 0) {
                fetch('/documents/' + docId + '?json=1', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function (res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(function (json) {
                    loadDocumentData(json);
                })
                .catch(function () {
                    // Fallback to client-side heuristics if JSON endpoint unreachable
                    fallbackClientPreview(docUrl, docLabel, docType, docStatus, docOwner, docId);
                });
            } else {
                fallbackClientPreview(docUrl, docLabel, docType, docStatus, docOwner, docId);
            }
        }

        function fallbackClientPreview(docUrl, docLabel, docType, docStatus, docOwner, docId) {
            var extMatch = (docUrl || '').split('?')[0].match(/\.([a-zA-Z0-9]+)$/);
            var ext = extMatch ? extMatch[1].toLowerCase() : '';
            var isImage = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'].indexOf(ext) !== -1;
            var isPdf = ext === 'pdf';

            loadDocumentData({
                id: docId || 0,
                label: docLabel || 'Document',
                doc_type_label: docType || 'Verification Document',
                status: docStatus || 'pending',
                owner_name: docOwner || '',
                ext: ext,
                is_image: isImage,
                is_pdf: isPdf,
                file_exists: true,
                file_url: docUrl,
                download_url: docId ? '/documents/download/' + docId : docUrl
            });
        }

        // Global click delegator for document links
        document.addEventListener('click', function (e) {
            var target = e.target;
            var trigger = target.closest('[data-doc-preview="1"], a.doc-link, a.admin-doc-link');

            if (!trigger) {
                // Also check if an anchor points to /documents/file/ or uploads/guide-docs/
                var a = target.closest('a');
                if (a && a.href) {
                    var h = a.href;
                    if (h.indexOf('/documents/file/') !== -1 || (h.indexOf('/uploads/guide-docs/') !== -1 && h.indexOf('/download') === -1)) {
                        trigger = a;
                    }
                }
            }

            if (!trigger) return;

            // Don't intercept explicit download triggers
            if (trigger.getAttribute('download') !== null || (trigger.href && trigger.href.indexOf('/download') !== -1)) {
                return;
            }

            // Don't intercept if user held modifier keys (Ctrl/Cmd/Shift)
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();
            previewDocument(trigger);
        }, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDocPreviewModal);
    } else {
        initDocPreviewModal();
    }
})();
