/**
 * FormIt
 *
 * Client-side functionality for FormIt forms.
 *
 * @package formit
 */
(function (window, document) {
    'use strict';

    /**
     * @param {HTMLFormElement} form
     * @param {Object} [options]
     * @constructor
     */
    function FormIt(form, options) {
        if (!(form instanceof HTMLFormElement)) {
            console.error('[FormIt] First argument must be a form element.');
            return;
        }

        this.form = form;
        this.options = {};

        for (var key in FormIt.defaults) {
            if (FormIt.defaults.hasOwnProperty(key)) {
                this.options[key] = FormIt.defaults[key];
            }
        }

        if (options) {
            for (var key in options) {
                if (options.hasOwnProperty(key)) {
                    this.options[key] = options[key];
                }
            }
        }

        this._lastSubmitter = null;
        this.form.addEventListener('click', this._onClickSubmit.bind(this));
        this.form.addEventListener('submit', this._onSubmit.bind(this));
    }

    /**
     * Track which submit button was clicked.
     * @param {MouseEvent} e
     * @private
     */
    FormIt.prototype._onClickSubmit = function (e) {
        var btn = e.target.closest('[type="submit"]');
        this._lastSubmitter = btn && btn.name ? btn : null;
    };

    /**
     * Global defaults. actionUrl is set by PHP via regClientScript.
     */
    FormIt.defaults = {
        actionUrl: '',
        clearOnSuccess: true,
        onBeforeSubmit: null,
        onSuccess: null,
        onError: null,
        onComplete: null,
        onRedirect: null
    };

    /**
     * @param {SubmitEvent} e
     * @private
     */
    FormIt.prototype._onSubmit = function (e) {
        e.preventDefault();

        // beforesubmit event (cancelable)
        var beforeEvent = this._dispatch('formit:beforesubmit', { form: this.form }, true);
        if (beforeEvent.defaultPrevented) return;

        // callback
        if (typeof this.options.onBeforeSubmit === 'function') {
            if (this.options.onBeforeSubmit(this.form) === false) return;
        }

        if (!this.options.actionUrl) {
            var msg = '[FormIt] actionUrl is not configured. Set the "formit.frontend_js" system setting or pass actionUrl when creating a FormIt instance.';
            console.error(msg);
            this._showMessage('[data-formit-error-message]', msg);
            return;
        }

        this._clearMessages();
        this._setLoading(true);

        var submitter = e.submitter || this._lastSubmitter;
        var formData = new FormData(this.form);

        // Include the clicked submit button so server-side submitVar check works
        if (submitter && submitter.name) {
            formData.append(submitter.name, submitter.value || '');
        }

        // Add ajaxToken from data-attribute
        var token = this.form.getAttribute('data-formit-ajax-token');
        if (token) {
            formData.append('ajaxToken', token);
        }

        var self = this;

        fetch(this.options.actionUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(function (response) {
            if (!response.ok) {
                return response.text().then(function (text) {
                    throw new Error('[FormIt] Server returned HTTP ' + response.status + ': ' + text.substring(0, 200));
                });
            }

            var contentType = response.headers.get('Content-Type') || '';
            if (contentType.indexOf('application/json') === -1) {
                return response.text().then(function (text) {
                    throw new Error('[FormIt] Expected JSON but received ' + contentType + ': ' + text.substring(0, 200));
                });
            }

            return response.json();
        })
        .then(function (data) {
            self._handleResponse(data);
        })
        .catch(function (error) {
            console.error('[FormIt] Request failed:', error);

            self._showMessage('[data-formit-error-message]', error.message || 'Request failed');

            self._dispatch('formit:error', { data: null, error: error });
            if (typeof self.options.onError === 'function') {
                self.options.onError(null, error);
            }
        })
        .finally(function () {
            self._setLoading(false);
            self._dispatch('formit:complete', {});
            if (typeof self.options.onComplete === 'function') {
                self.options.onComplete();
            }
        });
    };

    /**
     * @param {Object} data - Response from processForm()
     * @private
     */
    FormIt.prototype._handleResponse = function (data) {
        var placeholders = data.placeholders || {};
        var hasFieldErrors = false;

        // Fill field errors
        for (var key in placeholders) {
            if (placeholders.hasOwnProperty(key) && key.indexOf('error.') === 0) {
                hasFieldErrors = true;
                var fieldName = key.substring(6); // Remove prefix "error."
                var el = this.form.querySelector('[data-formit-error="' + fieldName + '"]');
                if (el) el.innerHTML = placeholders[key];
            }
        }

        // Fill messages with alert fallback
        this._showMessage('[data-formit-success-message]', placeholders.successMessage || '');
        this._showMessage('[data-formit-validation-error-message]', placeholders.validation_error_message || '');
        this._showMessage('[data-formit-error-message]', placeholders.error_message || '');

        // Determine if this is an error response
        var isError = !data.success || hasFieldErrors || placeholders.validation_error || placeholders.error_message;

        if (isError) {
            this._dispatch('formit:error', { data: data });
            if (typeof this.options.onError === 'function') {
                this.options.onError(data);
            }
        } else {
            this._dispatch('formit:success', { data: data });
            if (typeof this.options.onSuccess === 'function') {
                this.options.onSuccess(data);
            }

            if (this.options.clearOnSuccess) {
                this.form.reset();
            }

            // Handle redirect (cancelable)
            if (data.redirect_url) {
                var redirectEvent = this._dispatch('formit:redirect', { url: data.redirect_url }, true);
                if (redirectEvent.defaultPrevented) return;

                if (typeof this.options.onRedirect === 'function') {
                    if (this.options.onRedirect(data.redirect_url) === false) return;
                }

                window.location.href = data.redirect_url;
            }
        }
    };

    /**
     * Show a message in a container element or fall back to alert.
     * @param {string} selector
     * @param {string} message
     * @private
     */
    FormIt.prototype._showMessage = function (selector, message) {
        if (!message) return;
        var el = this.form.querySelector(selector);
        if (el) {
            el.innerHTML = message;
        } else {
            alert(message);
        }
    };

    /**
     * Clear all message and error elements in the form.
     * @private
     */
    FormIt.prototype._clearMessages = function () {
        this.form.querySelectorAll('[data-formit-error]').forEach(function (el) {
            el.textContent = '';
        });
        this.form.querySelectorAll('[data-formit-success-message], [data-formit-validation-error-message], [data-formit-error-message]')
            .forEach(function (el) {
                el.textContent = '';
            });
    };

    /**
     * Toggle loading state on the form.
     * @param {boolean} loading
     * @private
     */
    FormIt.prototype._setLoading = function (loading) {
        if (loading) {
            this.form.classList.add('formit-loading');
        } else {
            this.form.classList.remove('formit-loading');
        }

        var buttons = this.form.querySelectorAll('[type="submit"]');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].disabled = loading;
        }
    };

    /**
     * Dispatch a CustomEvent on the form element.
     * @param {string} name
     * @param {Object} detail
     * @param {boolean} [cancelable]
     * @returns {CustomEvent}
     * @private
     */
    FormIt.prototype._dispatch = function (name, detail, cancelable) {
        var event = new CustomEvent(name, {
            detail: detail,
            bubbles: true,
            cancelable: !!cancelable
        });
        this.form.dispatchEvent(event);
        return event;
    };

    // Auto-initialize
    document.addEventListener('DOMContentLoaded', function () {
        var forms = document.querySelectorAll('form[data-formit-ajax-token]');
        for (var i = 0; i < forms.length; i++) {
            new FormIt(forms[i]);
        }
    });

    // Expose globally
    window.FormIt = FormIt;

})(window, document);
