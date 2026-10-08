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

    // Auto-scroll message thread to bottom
    var threadBody = document.getElementById('threadBody');
    if (threadBody) {
        threadBody.scrollTop = threadBody.scrollHeight;

        var partnerId = threadBody.getAttribute('data-partner-id');
        var meId = parseInt(threadBody.getAttribute('data-me-id') || '0', 10);
        var pollUrl = threadBody.getAttribute('data-poll-url');
        var lastId = parseInt(threadBody.getAttribute('data-last-id') || '0', 10);
        if (partnerId && pollUrl && meId) {
            setInterval(function () {
                fetch(pollUrl + '?since=' + lastId, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data.messages || !data.messages.length) return;
                        data.messages.forEach(function (m) {
                            var bubble = document.createElement('div');
                            bubble.className = 'bubble ' + (parseInt(m.sender_id, 10) === meId ? 'out' : 'in');
                            var body = document.createElement('span');
                            body.innerHTML = (m.body || '').replace(/\n/g, '<br>');
                            var when = document.createElement('small');
                            when.textContent = m.created_at || '';
                            bubble.appendChild(body);
                            bubble.appendChild(when);
                            threadBody.appendChild(bubble);
                            lastId = Math.max(lastId, parseInt(m.id, 10));
                        });
                        threadBody.setAttribute('data-last-id', String(lastId));
                        threadBody.scrollTop = threadBody.scrollHeight;
                    })
                    .catch(function () {});
            }, 5000);
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
})();
