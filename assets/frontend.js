/**
 * Custom Comments — frontend interaction layer.
 *
 * Vanilla JS (no WP script dependencies):
 *  - submit main comment form + inline reply forms (REST)
 *  - refresh the comment list through the REST refresh endpoint
 *  - sort dropdown (persisted via ?cc_order=)
 *  - auto-growing textareas, loading/error messages
 *
 * Localized as `jankxCustomComments` (restUrl, nonce, i18n).
 */
(function () {
    'use strict';

    var cfg = window.jankxCustomComments || {};
    var restUrl = cfg.restUrl || '';
    var nonce = cfg.nonce || '';
    var i18n = cfg.i18n || {};

    if (!restUrl) {
        return;
    }

    function t(key, fallback) {
        return i18n[key] || fallback || '';
    }

    function request(method, params) {
        var url = restUrl + '/comments';
        var init = {
            method: method,
            headers: {
                'X-WP-Nonce': nonce,
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
        };

        if (method === 'GET') {
            url += '?' + new URLSearchParams(params).toString();
        } else {
            init.body = JSON.stringify(params);
        }

        return fetch(url, init).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok && data && data.success) {
                    data.success = false;
                }
                return data;
            });
        });
    }

    function getList() {
        return document.querySelector('[data-jcc-list]');
    }

    function getListInner(list) {
        return list ? list.querySelector('.jankx-comment-list__inner') : null;
    }

    function refreshList() {
        var list = getList();
        var inner = getListInner(list);
        if (!list || !inner) {
            return Promise.resolve();
        }

        var attrs = {};
        try {
            attrs = JSON.parse(list.getAttribute('data-attrs') || '{}');
        } catch (e) {
            attrs = {};
        }

        list.classList.add('is-loading');

        return request('GET', {
            post_id: parseInt(list.getAttribute('data-post-id'), 10) || 0,
            order: list.getAttribute('data-order') || 'newest',
            attrs: JSON.stringify(attrs),
        })
            .then(function (data) {
                if (data && data.success) {
                    inner.innerHTML =
                        data.html ||
                        '<p class="jankx-comment-list__empty">' +
                            t('empty', 'Chưa có bình luận nào.') +
                            '</p>';
                    if (data.order) {
                        list.setAttribute('data-order', data.order);
                    }
                }
            })
            .catch(function () {
                /* keep current markup on network failure */
            })
            .then(function () {
                list.classList.remove('is-loading');
            });
    }

    function showMessage(scope, text, type) {
        var message = scope.querySelector('[data-jcc-message]');
        if (!message) {
            return;
        }
        ['info', 'success', 'error', 'pending'].forEach(function (name) {
            message.classList.remove('jcc-message--' + name);
        });
        message.classList.add('jcc-message--' + (type || 'info'));
        message.hidden = !text;
        message.textContent = text || '';
    }

    function setBusy(form, busy) {
        var button = form.querySelector('[data-jcc-submit]');
        if (!button) {
            return;
        }
        if (busy) {
            button.setAttribute('data-jcc-label', button.textContent);
            button.textContent = t('submitting', 'Đang gửi...');
            button.disabled = true;
        } else {
            var label = button.getAttribute('data-jcc-label');
            if (label) {
                button.textContent = label;
            }
            button.disabled = false;
        }
    }

    function readFields(form) {
        var content = '';
        var textarea = form.querySelector(
            'textarea[name="jcc-content"], textarea'
        );
        content = textarea ? textarea.value.trim() : '';

        var fields = { content: content };
        var author = form.querySelector('[name="jcc-author"]');
        var email = form.querySelector('[name="jcc-email"]');

        if (author) {
            fields.author = author.value.trim();
        }
        if (email) {
            fields.email = email.value.trim();
        }
        return fields;
    }

    function validate(form, fields) {
        if (!fields.content) {
            showMessage(form, t('required', 'Vui lòng viết bình luận.'), 'error');
            return false;
        }
        var author = form.querySelector('[name="jcc-author"]');
        var email = form.querySelector('[name="jcc-email"]');
        if (author && !fields.author) {
            showMessage(form, t('loginName', 'Vui lòng nhập tên của bạn.'), 'error');
            return false;
        }
        if (email && (!fields.email || fields.email.indexOf('@') === -1)) {
            showMessage(
                form,
                t('loginEmail', 'Vui lòng nhập email hợp lệ.'),
                'error'
            );
            return false;
        }
        showMessage(form, '', 'info');
        return true;
    }

    function submitForm(form) {
        var list = getList();
        var isReply = form.hasAttribute('data-jcc-reply-form');
        var fields = readFields(form);

        if (!validate(form, fields)) {
            return;
        }

        var postId = parseInt(
            form.getAttribute('data-post-id') ||
                (list && list.getAttribute('data-post-id')) ||
                '0',
            10
        );
        var parent = isReply
            ? parseInt(form.getAttribute('data-parent') || '0', 10)
            : 0;

        setBusy(form, true);

        request('POST', {
            post_id: postId,
            content: fields.content,
            parent: parent,
            author: fields.author || '',
            email: fields.email || '',
        })
            .then(function (data) {
                if (!data || !data.success) {
                    showMessage(
                        form,
                        (data && data.message) ||
                            t('error', 'Có lỗi xảy ra, vui lòng thử lại.'),
                        'error'
                    );
                    return;
                }

                showMessage(
                    form,
                    data.pending
                        ? t('pending', 'Bình luận của bạn đang chờ kiểm duyệt.')
                        : t('success', 'Bình luận của bạn đã được đăng.'),
                    data.pending ? 'pending' : 'success'
                );

                var textarea = form.querySelector('textarea');
                if (textarea) {
                    textarea.value = '';
                    textarea.style.height = '';
                }

                return refreshList().then(function () {
                    if (isReply) {
                        hideReplyForm(form);
                    }
                });
            })
            .catch(function () {
                showMessage(
                    form,
                    t('error', 'Có lỗi xảy ra, vui lòng thử lại.'),
                    'error'
                );
            })
            .then(function () {
                setBusy(form, false);
            });
    }

    function hideReplyForm(form) {
        form.hidden = true;
        var item = form.closest('.jankx-comment-item');
        var button = item
            ? item.querySelector('[data-jcc-toggle-reply]')
            : null;
        if (button) {
            button.setAttribute('aria-expanded', 'false');
        }
    }

    function toggleReply(button) {
        var item = button.closest('.jankx-comment-item');
        if (!item) {
            return;
        }
        var form = item.querySelector('[data-jcc-reply-form]');
        if (!form) {
            return;
        }
        var opening = form.hidden;
        form.hidden = !opening;
        button.setAttribute('aria-expanded', opening ? 'true' : 'false');
        if (opening) {
            var textarea = form.querySelector('textarea');
            if (textarea) {
                textarea.focus();
            }
        }
    }

    function applyOrder(select) {
        var list = getList();
        if (!list) {
            return;
        }
        var order = select.value === 'oldest' ? 'oldest' : 'newest';
        list.setAttribute('data-order', order);

        try {
            var url = new URL(window.location.href);
            url.searchParams.set('cc_order', order);
            window.history.replaceState({}, '', url.toString());
        } catch (e) {
            /* older browsers: sort still works for this page view */
        }

        refreshList();
    }

    function autoGrow(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight + 2, 240) + 'px';
    }

    function bind() {
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (
                form.matches('[data-jcc-form]') ||
                form.matches('[data-jcc-reply-form]')
            ) {
                event.preventDefault();
                submitForm(form);
            }
        });

        document.addEventListener('click', function (event) {
            var toggle = event.target.closest('[data-jcc-toggle-reply]');
            if (toggle) {
                event.preventDefault();
                toggleReply(toggle);
                return;
            }
            var cancel = event.target.closest('[data-jcc-cancel-reply]');
            if (cancel) {
                event.preventDefault();
                var form = cancel.closest('[data-jcc-reply-form]');
                if (form) {
                    hideReplyForm(form);
                }
            }
        });

        document.addEventListener('change', function (event) {
            if (event.target.matches('[data-jcc-order]')) {
                applyOrder(event.target);
            }
        });

        document.addEventListener(
            'input',
            function (event) {
                if (
                    event.target.tagName === 'TEXTAREA' &&
                    event.target.matches(
                        '[data-jcc-form] textarea, [data-jcc-reply-form] textarea'
                    )
                ) {
                    autoGrow(event.target);
                }
            },
            true
        );
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();
