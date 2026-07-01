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
     * Global config. Set by PHP via Object.assign(FormIt, {...}).
     */
    window.FormIt = {
        actionUrl: '/assets/components/formit/action.php',
        recaptchaDefaultAction: 'submit'
    };

    /**
     * @param {HTMLFormElement} form
     * @constructor
     */
    function FormItForm(form) {
        if (!(form instanceof HTMLFormElement)) {
            console.error('[FormIt] First argument must be a form element.');
            return;
        }

        this.form = form;
        this._lastSubmitter = null;
        this.form.addEventListener('click', this._onClickSubmit.bind(this));
        this.form.addEventListener('submit', this._onSubmit.bind(this));
    }

    /**
     * Track which submit button was clicked.
     * @param {MouseEvent} e
     * @private
     */
    FormItForm.prototype._onClickSubmit = function (e) {
        var btn = e.target.closest('[type="submit"]');
        this._lastSubmitter = btn && btn.name ? btn : null;
    };

    /**
     * @param {SubmitEvent} e
     * @private
     */
    FormItForm.prototype._onSubmit = function (e) {
        this.ajaxToken = this.form.getAttribute('data-formit-ajax-token');

        if (!this.ajaxToken && !this.form.querySelector('[name="g-recaptcha-response"]')) {
            return;
        }

        e.preventDefault();

        // beforesubmit event (cancelable)
        var beforeEvent = this._dispatch('formit:beforesubmit', { form: this.form }, true);
        if (beforeEvent.defaultPrevented) return;

        this.submitter = e.submitter || this._lastSubmitter || this.form.querySelector('[type="submit"]');
        var formData  = new FormData(this.form);

        if (this.submitter && this.submitter.name) {
            formData.append(this.submitter.name, this.submitter.value || '');
        }

        var self = this;

        this._clearMessages();
        this._setLoading(true);

        // _resolveRecaptcha has its own guards — resolves immediately if not configured
        this._resolveRecaptcha(formData)
            .then(function () {
                self._submit(formData);
            })
            .catch(function (error) {
                console.error('[FormIt] reCAPTCHA failed:', error);
                self._setLoading(false);
                self._showMessage('[data-formit-error-message]', error.message || 'Request failed');
                self._dispatch('formit:error', { data: null, error: error });
            });
    };

    /**
     * Execute reCAPTCHA v3 and fill both formData and the response field.
     * Resolves immediately when reCAPTCHA is not configured.
     *
     * @param {FormData} formData
     * @returns {Promise<void>}
     * @private
     */
    FormItForm.prototype._resolveRecaptcha = function (formData) {
        var recaptchaResponseField = this.form.querySelector('[name="g-recaptcha-response"]');

        if (!recaptchaResponseField || typeof grecaptcha === 'undefined' || typeof window.hcaptcha === 'object') {
            return Promise.resolve();
        }

        var actionField     = this.form.querySelector('[name="g-recaptcha-action"]');
        var recaptchaAction = (actionField && actionField.value) || FormIt.recaptchaDefaultAction;

        return new Promise(function (resolve, reject) {
            grecaptcha.ready(function () {
                grecaptcha.execute(FormIt.recaptchaSiteKey, { action: recaptchaAction })
                    .then(function (token) {
                        recaptchaResponseField.value = token;
                        formData.set('g-recaptcha-response', token);
                        resolve();
                    })
                    .catch(reject);
            });
        });
    };

    /**
     * Submit the form: native submit or AJAX fetch depending on ajaxToken.
     *
     * @param {FormData} formData
     * @private
     */
    FormItForm.prototype._injectSubmitter = function () {
        if (!this.submitter || !this.submitter.name) return;
        var existing = this.form.querySelector('[name="' + this.submitter.name + '"]');
        if (existing && existing !== this.submitter) return;
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = this.submitter.name;
        hidden.value = this.submitter.value || '';
        this.form.appendChild(hidden);
    };

    FormItForm.prototype._submit = function (formData) {
        if (!this.ajaxToken) {
            this._injectSubmitter();
            this.form.submit();
            return;
        }

        var self = this;
        var form = this.form;

        fetch(FormIt.actionUrl, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-FormIt-Token': this.ajaxToken
            },
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
            // AJAX failed — fall back to native submit so the request still goes through.
            // reCAPTCHA token is already set in the response field value.
            console.warn('[FormIt] AJAX failed, falling back to native submit:', error);
            self._injectSubmitter();
            form.submit();
        })
        .finally(function () {
            self._setLoading(false);
            self._dispatch('formit:complete', {});
        });
    };

    /**
     * @param {Object} data - Response from processForm()
     * @private
     */
    FormItForm.prototype._handleResponse = function (data) {
        var placeholders   = data.placeholders || {};
        var hasFieldErrors = false;

        for (var key in placeholders) {
            if (placeholders.hasOwnProperty(key) && key.indexOf('error.') === 0) {
                hasFieldErrors = true;
                var fieldName = key.substring(6);
                var el = this.form.querySelector('[data-formit-error="' + fieldName + '"]');
                if (el) el.innerHTML = placeholders[key];
            }
        }

        this._showMessage('[data-formit-success-message]', placeholders.successMessage || '');
        this._showMessage('[data-formit-validation-error-message]', placeholders.validation_error_message || '');
        this._showMessage('[data-formit-error-message]', placeholders.error_message || '');

        var isError = !data.success || hasFieldErrors || placeholders.validation_error || placeholders.error_message;

        if (isError) {
            this._dispatch('formit:error', { data: data });
        } else {
            this._dispatch('formit:success', { data: data });

            this.form.reset();

            if (data.redirect_url) {
                var redirectEvent = this._dispatch('formit:redirect', { url: data.redirect_url }, true);
                if (redirectEvent.defaultPrevented) return;

                window.location.href = data.redirect_url;
            }
        }
    };

    /**
     * @param {string} selector
     * @param {string} message
     * @private
     */
    FormItForm.prototype._showMessage = function (selector, message) {
        if (!message) return;
        var el = this.form.querySelector(selector);
        if (el) {
            el.innerHTML = message;
        } else {
            var stripEl = document.createElement('div');
            stripEl.innerHTML = message;
            alert(stripEl.textContent || stripEl.innerText || message);
        }
    };

    /**
     * @private
     */
    FormItForm.prototype._clearMessages = function () {
        this.form.querySelectorAll('[data-formit-error]').forEach(function (el) {
            el.textContent = '';
        });
        this.form.querySelectorAll('[data-formit-success-message], [data-formit-validation-error-message], [data-formit-error-message]')
            .forEach(function (el) {
                el.textContent = '';
            });
    };

    /**
     * @param {boolean} loading
     * @private
     */
    FormItForm.prototype._setLoading = function (loading) {
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
     * @param {string} name
     * @param {Object} detail
     * @param {boolean} [cancelable]
     * @returns {CustomEvent}
     * @private
     */
    FormItForm.prototype._dispatch = function (name, detail, cancelable) {
        var event = new CustomEvent(name, {
            detail: detail,
            bubbles: true,
            cancelable: !!cancelable
        });
        this.form.dispatchEvent(event);
        return event;
    };

    // Auto-initialize all forms
    document.addEventListener('DOMContentLoaded', function () {
        var forms = document.querySelectorAll('form');
        for (var i = 0; i < forms.length; i++) {
            new FormItForm(forms[i]);
        }
    });

})(window, document);
