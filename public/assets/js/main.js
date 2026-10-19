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
    }

    // Dismiss flash messages
    document.querySelectorAll('.flash-close').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var flash = btn.closest('.flash');
            if (flash) { flash.remove(); }
        });
    });
    document.querySelectorAll('.flash').forEach(function (flash) {
        setTimeout(function () { flash.style.transition = 'opacity .4s'; flash.style.opacity = '0';
            setTimeout(function () { flash.remove(); }, 400); }, 5000);
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
    // building blocks so we get motion site-wide without markup changes.
    var revealSelector = '.listing-card, .cat-card, .stat, .panel, .review, .card-soft, .cta-band, .section-head, .review-summary';
    var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var revealEls = Array.prototype.slice.call(document.querySelectorAll(revealSelector));

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
            var select = form.querySelector('.guide-status-select');
            if (!select || select.value !== 'cancelled') {
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
})();
