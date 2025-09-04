<?php

namespace AstraChild\Core\Hooks;

class JQuery
{

    public static function disableJquery(): void
    {
        if (!is_admin() && !is_user_logged_in()) {
            global $wp_scripts;
            if ($wp_scripts instanceof \WP_Scripts) {
                foreach ($wp_scripts->registered as $handle => $script) {
                    if (strpos($handle, 'jquery') === 0) {
                        try {
                            wp_dequeue_script($handle);
                            wp_deregister_script($handle);
                        } catch (\Exception $e) {
                            error_log('JQuery::disableJquery error for handle ' . $handle . ': ' . $e->getMessage());
                        }
                    }
                }
            }
        }
    }

    public static function suppressJqueryErrors(): void
    {
        ?>
        <script>
            (function (window, document) {
                if (window.jQuery) return;

                console.warn('jQuery is not loaded. Providing a minimal shim to avoid errors.');

                // Preserve any existing globals to support noConflict
                var _prev$ = window.$;
                var _prevjQuery = window.jQuery;

                function JQ(selector, context) {
                    if (!selector) { this.nodes = []; this.length = 0; return; }
                    if (typeof selector === 'function') {
                        // ready(fn)
                        if (document.readyState === 'complete' || document.readyState === 'interactive') {
                            try { selector(); } catch (e) { }
                        } else {
                            document.addEventListener('DOMContentLoaded', function () {
                                try { selector(); } catch (e) { }
                            }, { once: true });
                        }
                        this.nodes = [];
                        this.length = 0;
                        return;
                    }
                    if (selector.nodeType) {
                        this.nodes = [selector];
                    } else if (selector instanceof NodeList || Array.isArray(selector)) {
                        this.nodes = Array.prototype.slice.call(selector);
                    } else if (typeof selector === 'string') {
                        var ctx = context && context.querySelectorAll ? context : document;
                        try {
                            this.nodes = Array.prototype.slice.call(ctx.querySelectorAll(selector));
                        } catch (e) {
                            this.nodes = [];
                        }
                    } else {
                        this.nodes = [];
                    }
                    this.length = this.nodes.length;
                }

                var proto = JQ.prototype;

                proto.each = function (cb) {
                    this.nodes.forEach(function (node, i) {
                        try { cb.call(node, i, node); } catch (e) { }
                    });
                    return this;
                };

                proto.get = function (index) {
                    return this.nodes[index];
                };

                proto.eq = function (index) {
                    var node = this.nodes[index < 0 ? this.nodes.length + index : index];
                    return new JQ(node || []);
                };

                proto.map = function (fn) {
                    return this.nodes.map(function (n, i) {
                        try { return fn.call(n, i, n); } catch (e) { return null; }
                    }).filter(function (v) { return v !== null; });
                };

                proto.find = function (selector) {
                    var found = [];
                    this.each(function () {
                        try {
                            var res = this.querySelectorAll(selector);
                            if (res && res.length) Array.prototype.push.apply(found, res);
                        } catch (e) { }
                    });
                    return new JQ(found);
                };

                proto.parent = function () {
                    var parents = [];
                    this.each(function () {
                        var p = this.parentNode;
                        if (p && parents.indexOf(p) === -1) parents.push(p);
                    });
                    return new JQ(parents);
                };

                proto.children = function () {
                    var kids = [];
                    this.each(function () {
                        try {
                            var ch = this.children;
                            if (ch && ch.length) Array.prototype.push.apply(kids, ch);
                        } catch (e) { }
                    });
                    return new JQ(kids);
                };

                proto.on = function (evt, selectorOrHandler, handler) {
                    // on(event, handler) or on(event, selector, handler)
                    if (typeof selectorOrHandler === 'function') {
                        this.each(function () {
                            this.addEventListener(evt, selectorOrHandler);
                        });
                    } else {
                        var sel = selectorOrHandler;
                        var fn = handler;
                        this.each(function () {
                            this.addEventListener(evt, function (e) {
                                try {
                                    var target = e.target;
                                    // delegate
                                    if (target && target.matches && target.matches(sel)) {
                                        fn.call(target, e);
                                    } else if (target && target.closest) {
                                        var match = target.closest(sel);
                                        if (match) fn.call(match, e);
                                    }
                                } catch (err) { }
                            });
                        });
                    }
                    return this;
                };

                proto.off = function (evt, handler) {
                    this.each(function () {
                        try { this.removeEventListener(evt, handler); } catch (e) { }
                    });
                    return this;
                };

                proto.trigger = function (evtName, detail) {
                    var ev;
                    try {
                        ev = new CustomEvent(evtName, { detail: detail || null, bubbles: true, cancelable: true });
                    } catch (e) {
                        ev = document.createEvent('CustomEvent');
                        ev.initCustomEvent(evtName, true, true, detail || null);
                    }
                    this.each(function () {
                        try { this.dispatchEvent(ev); } catch (e) { }
                    });
                    return this;
                };

                proto.addClass = function (name) {
                    if (!name) return this;
                    this.each(function () { if (this.classList) this.classList.add(name); });
                    return this;
                };

                proto.removeClass = function (name) {
                    if (!name) return this;
                    this.each(function () { if (this.classList) this.classList.remove(name); });
                    return this;
                };

                proto.hasClass = function (name) {
                    if (!this.nodes[0]) return false;
                    try { return this.nodes[0].classList ? this.nodes[0].classList.contains(name) : ((' ' + this.nodes[0].className + ' ').indexOf(' ' + name + ' ') > -1); } catch (e) { return false; }
                };

                proto.css = function (prop, value) {
                    if (typeof prop === 'string' && typeof value === 'undefined') {
                        var el = this.nodes[0];
                        if (!el) return undefined;
                        try { return window.getComputedStyle(el)[prop]; } catch (e) { return undefined; }
                    }
                    if (typeof prop === 'string') {
                        this.each(function () { try { this.style[prop] = value; } catch (e) { } });
                    } else if (typeof prop === 'object') {
                        var obj = prop;
                        this.each(function () {
                            for (var k in obj) {
                                if (Object.prototype.hasOwnProperty.call(obj, k)) {
                                    try { this.style[k] = obj[k]; } catch (e) { }
                                }
                            }
                        });
                    }
                    return this;
                };

                proto.attr = function (name, value) {
                    if (typeof value === 'undefined') {
                        return this.nodes[0] ? this.nodes[0].getAttribute && this.nodes[0].getAttribute(name) : undefined;
                    }
                    this.each(function () { try { this.setAttribute && this.setAttribute(name, value); } catch (e) { } });
                    return this;
                };

                proto.data = function (name, value) {
                    if (typeof name === 'undefined') return undefined;
                    if (typeof value === 'undefined') {
                        var el = this.nodes[0];
                        return el && el.dataset ? el.dataset[name] : (el && el.getAttribute ? el.getAttribute('data-' + name) : undefined);
                    }
                    this.each(function () {
                        try {
                            if (this.dataset) this.dataset[name] = value;
                            else this.setAttribute && this.setAttribute('data-' + name, value);
                        } catch (e) { }
                    });
                    return this;
                };

                proto.append = function (content) {
                    this.each(function () {
                        try {
                            if (typeof content === 'string') {
                                var tmp = document.createElement('div');
                                tmp.innerHTML = content;
                                while (tmp.firstChild) this.appendChild(tmp.firstChild);
                            } else if (content instanceof Node) {
                                this.appendChild(content);
                            } else if (content && content.nodes) {
                                content.each(function () { this && this.parentNode && this.parentNode.appendChild(this); }.bind(this));
                            }
                        } catch (e) { }
                    });
                    return this;
                };

                proto.prepend = function (content) {
                    this.each(function () {
                        try {
                            if (typeof content === 'string') {
                                var tmp = document.createElement('div');
                                tmp.innerHTML = content;
                                var first = this.firstChild;
                                while (tmp.lastChild) this.insertBefore(tmp.lastChild, first);
                            } else if (content instanceof Node) {
                                this.insertBefore(content, this.firstChild);
                            }
                        } catch (e) { }
                    });
                    return this;
                };

                proto.remove = function () {
                    this.each(function () { if (this.parentNode) try { this.parentNode.removeChild(this); } catch (e) { } });
                    return this;
                };

                proto.hide = function () {
                    this.each(function () {
                        try {
                            if (!this.dataset) this.dataset = {};
                            if (!this.dataset._prevDisplay) this.dataset._prevDisplay = this.style && this.style.display ? this.style.display : '';
                            this.style.display = 'none';
                        } catch (e) { }
                    });
                    return this;
                };

                proto.show = function () {
                    this.each(function () {
                        try {
                            var prev = this.dataset && this.dataset._prevDisplay ? this.dataset._prevDisplay : '';
                            this.style.display = prev || '';
                            if (prev === '') this.style.removeProperty('display');
                        } catch (e) { }
                    });
                    return this;
                };

                proto.val = function (value) {
                    if (typeof value === 'undefined') {
                        var el = this.nodes[0];
                        return el ? el.value : undefined;
                    }
                    this.each(function () { try { this.value = value; } catch (e) { } });
                    return this;
                };

                proto.html = function (value) {
                    if (typeof value === 'undefined') {
                        var el = this.nodes[0];
                        return el ? el.innerHTML : undefined;
                    }
                    this.each(function () { try { this.innerHTML = value; } catch (e) { } });
                    return this;
                };

                proto.text = function (value) {
                    if (typeof value === 'undefined') {
                        var el = this.nodes[0];
                        return el ? el.textContent : undefined;
                    }
                    this.each(function () { try { this.textContent = value; } catch (e) { } });
                    return this;
                };

                // Simple fade (uses CSS transitions when possible)
                proto.fadeOut = function (duration) {
                    duration = typeof duration === 'number' ? duration : 200;
                    this.each(function () {
                        try {
                            var el = this;
                            el.style.transition = 'opacity ' + duration + 'ms';
                            el.style.opacity = '1';
                            requestAnimationFrame(function () {
                                el.style.opacity = '0';
                            });
                            setTimeout(function () {
                                try { el.style.display = 'none'; el.style.opacity = ''; el.style.transition = ''; } catch (e) { }
                            }, duration + 20);
                        } catch (e) { }
                    });
                    return this;
                };

                proto.fadeIn = function (duration) {
                    duration = typeof duration === 'number' ? duration : 200;
                    this.each(function () {
                        try {
                            var el = this;
                            el.style.display = el.dataset && el.dataset._prevDisplay ? el.dataset._prevDisplay : (getComputedStyle(el).display === 'none' ? 'block' : getComputedStyle(el).display);
                            el.style.opacity = '0';
                            el.style.transition = 'opacity ' + duration + 'ms';
                            requestAnimationFrame(function () { el.style.opacity = '1'; });
                            setTimeout(function () { try { el.style.opacity = ''; el.style.transition = ''; } catch (e) { } }, duration + 20);
                        } catch (e) { }
                    });
                    return this;
                };

                // Basic ajax using fetch with jQuery-like interface for common usage
                function ajax(opts) {
                    if (!opts || !opts.url) return Promise.reject(new Error('No url'));
                    var method = (opts.type || opts.method || 'GET').toUpperCase();
                    var body = opts.data || null;
                    var headers = opts.headers || {};
                    if (method === 'GET' && body && typeof body === 'object') {
                        var params = new URLSearchParams(body).toString();
                        opts.url += (opts.url.indexOf('?') === -1 ? '?' : '&') + params;
                        body = null;
                    } else if (body && typeof body === 'object' && !(body instanceof FormData)) {
                        headers['Content-Type'] = headers['Content-Type'] || 'application/json; charset=utf-8';
                        body = JSON.stringify(body);
                    }
                    return fetch(opts.url, { method: method, headers: headers, body: body, credentials: opts.xhrFields && opts.xhrFields.withCredentials ? 'include' : 'same-origin' })
                        .then(function (res) {
                            var ct = res.headers.get('content-type') || '';
                            if (opts.dataType === 'json' || ct.indexOf('application/json') !== -1) return res.json();
                            return res.text();
                        })
                        .then(function (data) {
                            if (typeof opts.success === 'function') opts.success(data);
                            return data;
                        })
                        .catch(function (err) {
                            if (typeof opts.error === 'function') opts.error(err);
                            throw err;
                        });
                }

                // Utility functions
                function extend(target) {
                    for (var i = 1; i < arguments.length; i++) {
                        var src = arguments[i];
                        if (!src) continue;
                        for (var k in src) {
                            if (Object.prototype.hasOwnProperty.call(src, k)) {
                                target[k] = src[k];
                            }
                        }
                    }
                    return target;
                }

                // Expose $
                var $fn = JQ.prototype;
                var $ = function (selector, context) {
                    return new JQ(selector, context);
                };

                // attach static helpers
                $.ajax = ajax;
                $.extend = extend;
                $.isFunction = function (v) { return typeof v === 'function'; };
                $.noop = function () { };
                $.trim = function (s) { return typeof s === 'string' ? s.trim() : s; };

                // Usage logging: detect when other scripts try to read/use jQuery
                (function () {
                    var usageCount = 0;
                    var MAX_USAGE_LOGS = 12; // avoid spamming the console

                    function safeStack() {
                        try {
                            var err = new Error();
                            if (!err.stack) throw err;
                            return err.stack.split('\n').slice(2).join('\n');
                        } catch (e) {
                            return '(stack unavailable)';
                        }
                    }

                    function logUsage(kind, detail) {
                        try {
                            if (usageCount++ >= MAX_USAGE_LOGS) return;
                            var msg = '[jQuery-shim] ' + kind + (detail ? (': ' + detail) : '');
                            if (console && console.warn) {
                                console.warn(msg, '\nCalled from:', safeStack());
                            }
                        } catch (e) { }
                    }

                    // For modern browsers use Proxy to trap calls and property access
                    var wrapper = $;
                    try {
                        var proxied = new Proxy(wrapper, {
                            apply: function (target, thisArg, args) {
                                logUsage('function call', JSON.stringify(args && args.length ? args.slice(0, 2) : []));
                                return Reflect.apply(target, thisArg, args);
                            },
                            get: function (target, prop) {
                                // common jQuery properties we attach - don't log internal accesses
                                if (prop === 'fn' || prop === 'prototype' || prop === 'ajax' || prop === 'extend' || prop === 'noConflict' || prop === 'isFunction' || prop === 'noop' || prop === 'trim') {
                                    return Reflect.get(target, prop);
                                }
                                logUsage('property access', String(prop));
                                return Reflect.get(target, prop);
                            }
                        });

                        // Provide a basic noConflict similar behavior
                        proxied.noConflict = function (deep) {
                            if (window.$ === proxied) window.$ = _prev$;
                            if (deep && window.jQuery === proxied) window.jQuery = _prevjQuery;
                            return proxied;
                        };

                        // forward static helpers onto proxy (some environments need explicit copy)
                        proxied.ajax = $.ajax;
                        proxied.extend = $.extend;
                        proxied.isFunction = $.isFunction;
                        proxied.noop = $.noop;
                        proxied.trim = $.trim;

                        // set globals to proxied function
                        window.jQuery = window.$ = proxied;
                        window.jQuery.fn = window.jQuery.prototype = $fn;
                    } catch (e) {
                        // Proxy not available: fallback to simple wrapper that logs on call/access
                        var orig = $;
                        var fallback = function (selector, context) {
                            logUsage('function call (fallback)', String(selector));
                            return orig(selector, context);
                        };
                        fallback.ajax = $.ajax;
                        fallback.extend = $.extend;
                        fallback.isFunction = $.isFunction;
                        fallback.noop = $.noop;
                        fallback.trim = $.trim;
                        fallback.noConflict = function (deep) {
                            if (window.$ === fallback) window.$ = _prev$;
                            if (deep && window.jQuery === fallback) window.jQuery = _prevjQuery;
                            return fallback;
                        };

                        window.jQuery = window.$ = fallback;
                        window.jQuery.fn = window.jQuery.prototype = $fn;
                    }

                    // Also listen to global errors that mention jQuery – helpful if some code throws before using the shim
                    try {
                        window.addEventListener && window.addEventListener('error', function (ev) {
                            try {
                                var msg = ev && ev.message ? ev.message : (ev && ev.error && ev.error.message ? ev.error.message : '');
                                if (msg && msg.toLowerCase && msg.toLowerCase().indexOf('jquery') !== -1) {
                                    logUsage('global error mentioning jQuery', msg || String(ev));
                                }
                            } catch (e) { }
                        }, true);

                        window.addEventListener && window.addEventListener('unhandledrejection', function (ev) {
                            try {
                                var reason = ev && ev.reason ? (ev.reason.message || String(ev.reason)) : '';
                                if (reason && reason.toLowerCase && reason.toLowerCase().indexOf('jquery') !== -1) {
                                    logUsage('unhandledrejection mentioning jQuery', reason);
                                }
                            } catch (e) { }
                        }, true);
                    } catch (e) { }
                })();
            })(window, document);
        </script>
        <?php
    }
}
