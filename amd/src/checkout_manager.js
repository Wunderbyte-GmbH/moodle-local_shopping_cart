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

/*
 * @package    local_shopping_cart
 * @copyright  Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {reinit} from 'local_shopping_cart/cart';
import {get_string as getString} from 'core/str';

const SELECTORS = {
    STEPFORMCONTAINER: '[data-shopping-cart-step-form]',
    CHECKOUTMANAGERFORMID: '#shopping-cart-checkout-manager-form',
    CHECKOUTMANAGERFORMTEMPLATE: 'local_shopping_cart/checkout_manager_form',
    CHECKOUTMANAGERBUTTONSTEMPLATE: 'local_shopping_cart/checkout_manager_form_buttons',
    CHECKOUTMANAGERBUTTONSID: 'shopping-cart-checkout-manager-buttons',
    CHECKOUTMANAGERPROGRESSBARTEMPLATE: 'local_shopping_cart/checkout_manager_form_progress_bar',
    CHECKOUTMANAGERPROGRESSBARID: 'shopping-cart-checkout-manager-status-bar',
    BUTTONS: '.shopping-cart-checkout-manager-buttons button',
    PROGRESSBUTTONS: '.shopping-cart-checkout-manager-status-bar button',
    CHECKBOXITEMBODY: '#shopping-cart-checkout-manager-form-body',
    NEWADDRESSBUTTON: '.shopping-cart-new-address',
    FEEDBACKMESSAGE: '.shopping-cart-checkout-manager-alert-container',
    PAYMENTREGIONBUTTON: 'div.shopping_cart_payment_region button'
};

const WEBSERVICE = {
    CHECKOUTPROCESS: 'local_shopping_cart_control_checkout_process',
};

const EVENTSLISTENING = {
    RELOADADDRESSSTEP: 'local_shopping_cart/reloadAddressStep',
};

// Mounted dynamic step forms by step key.
const stepForms = {};

/**
 * Initializes the checkout manager functionality.
 */
function init() {
    const formBody = document.querySelector(SELECTORS.CHECKBOXITEMBODY);
    initListeners(formBody);
    initAddressStepReloadListener();
}

/**
 * After an address was created/edited/deleted via the inline address form,
 * reload the addresses step form and select the given address.
 */
function initAddressStepReloadListener() {
    if (document.body.dataset.scAddressStepReload === 'true') {
        return;
    }
    document.body.dataset.scAddressStepReload = 'true';
    document.addEventListener(EVENTSLISTENING.RELOADADDRESSSTEP, e => {
        const form = stepForms.addresses;
        if (!form) {
            return;
        }
        form.load().then(() => {
            const newaddressid = e.detail?.newaddressid;
            const addresskey = e.detail?.addresskey || 'billing';
            if (newaddressid) {
                const radio = document.querySelector(
                    `input[name="selectedaddress_${addresskey}"][value="${newaddressid}"]`
                );
                if (radio) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change', {bubbles: true}));
                }
            }
            return null;
        }).catch(err => {
            // eslint-disable-next-line no-console
            console.error('fail address step reload', err);
        });
    });
}

/**
 * Initializes the checkout manager functionality.
 * @param {HTMLElement} formBody - The name of the web service.
 */
function initListeners(formBody) {
    initControlListener();
    if (formBody != undefined) {
        initChangeListener();
    }

}

/**
 * Initializes the change listener for the form body.
 */
function initControlListener() {
    const selectors = `${SELECTORS.BUTTONS}, ${SELECTORS.PROGRESSBUTTONS}`;
    document.querySelectorAll(selectors).forEach(button => {
        button.addEventListener('click', controlCallback);
    });
}

/**
 * Initializes the change listener for the form body.
 */
function controlCallback() {
    const action = this.getAttribute('data-action');
    const currentstep = this.getAttribute('data-currentstep');

    const paymentbutton = document.querySelector(SELECTORS.PAYMENTREGIONBUTTON);
    const identifier = paymentbutton ? paymentbutton.getAttribute('data-identifier') ?? '' : '';

    if (action && currentstep) {
        triggerButtonControlWebService(WEBSERVICE.CHECKOUTPROCESS, {
            action: action,
            currentstep: currentstep,
            identifier: identifier,
        });
    }
}

/**
 * Initializes the change listener for the form body.
 */
function initChangeListener() {
    const formBody = document.querySelector(SELECTORS.CHECKBOXITEMBODY);
    formBody.addEventListener('change', event => changeCallback(event, formBody));
}

/**
 * Initializes the change listener for the form body.
 * @param {Object} event - The name of the web service.
 * @param {HTMLElement} formBody - The parameters for the web service call.
 */
function changeCallback(event, formBody) {
    const target = event.target;
    // Steps implemented as dynamic forms handle their own submission -
    // the legacy input scraping must not touch them.
    if (target.closest(SELECTORS.STEPFORMCONTAINER)) {
        return;
    }
    if (['INPUT', 'SELECT', 'TEXTAREA'].includes(target.tagName)) {
        const changedInputs = getChangedInputs();
        if (
            target.hasAttribute('data-skip-webservice')
        ) {
            return;
        }
        if (target.type == 'checkbox') {
            target.value = target.checked;
        }

        triggerButtonControlWebService(WEBSERVICE.CHECKOUTPROCESS, {
            action: getDatasetValue(formBody, 'action'),
            currentstep: getDatasetValue(formBody, 'currentstep'),
            identifier: getDatasetValue(formBody, 'identifier'),
            changedinput: JSON.stringify(changedInputs)
        });
    }
}

/**
 * Handles button control for the checkout process.
 */
function getChangedInputs() {
    const processElements = document.querySelectorAll('[data-shopping-cart-process-data="true"]');
    return Array.from(processElements).map(element => {
        const value = element.type === 'checkbox' ? element.checked : element.value;
        if (element.type === 'radio') {
            return element.checked
                ? {name: element.name || 'unnamed', value: value}
                : [];
        }

        return {
            name: element.name || 'unnamed',
            value: value,
        };
    })
    .filter(item => item !== null);
}

/**
 * Handles button control for the checkout process.
 * @param {string} serviceName - The name of the web service.
 * @param {Object} params - The parameters for the web service call.
 */
function triggerButtonControlWebService(serviceName, params) {
    require(['core/ajax'], function(Ajax) {
        const requests = Ajax.call([{
            methodname: serviceName,
            args: params,
        }]);
        requests[0].done(function(response) {
            updateCheckoutManagerPartials(response);
        }).fail(function(err) {
            // eslint-disable-next-line no-console
            console.error('fail button trigger', err);
            return;
        });
    });
}

/**
 * Updates specific Mustache partials dynamically based on the web service response.
 * @param {Object} data - The data returned from the web service.
 */
function updateCheckoutManagerPartials(data) {
    data.data = JSON.parse(data.data);
    require(['core/templates'], function(templates) {
        if (data.reloadbody) {
            staticReloadBody(templates, data);
        } else {
            newReloadBody(templates, data);
        }
    });
}

/**
 * Utility to get dataset values safely.
 * @param {Object} templates - The element with the dataset.
 * @param {Array} data - The element with the dataset.
 */
function newReloadBody(templates, data) {
    const controlButtons = document.getElementById(SELECTORS.CHECKOUTMANAGERBUTTONSID);
    const progressBar = document.getElementById(SELECTORS.CHECKOUTMANAGERPROGRESSBARID);
    const feedbackMessageContainer = document.querySelector(SELECTORS.FEEDBACKMESSAGE);

    if (!controlButtons) {
        // eslint-disable-next-line no-console
        console.error('controlButtons');
        return;
    }
    // Render new content for the div
    const renderButtonTemplate = templates.render(SELECTORS.CHECKOUTMANAGERBUTTONSTEMPLATE, data.data)
        .then(function(html) {
            controlButtons.innerHTML = html;
            return;
        })
        .catch(function(err) {
            // eslint-disable-next-line no-console
            console.error('fail render button', err);
        });
    const progressBarTemplate = templates.render(SELECTORS.CHECKOUTMANAGERPROGRESSBARTEMPLATE, data.data)
        .then(function(html) {
            progressBar.innerHTML = html;
            return;
        })
        .catch(function(err) {
            // eslint-disable-next-line no-console
            console.error('fail render button', err);
        });
    if (feedbackMessageContainer) {
        const datafeedback = JSON.parse(data.managerdata);
        if (
            feedbackMessageContainer &&
            datafeedback.feedback != undefined
        ) {
            templates.render('local_shopping_cart/checkout_manager_feedback', datafeedback.feedback)
                .then(function(html) {
                    feedbackMessageContainer.innerHTML = html;
                    return;
                })
                .catch(function(err) {
                    // eslint-disable-next-line no-console
                    console.error('fail render feedback', err);
                });
        }
    }
    Promise.all([renderButtonTemplate, progressBarTemplate]).then(function() {
        initControlListener();
        callZeroPriceListener();
        return;
    }).catch(function(err) {
        // eslint-disable-next-line no-console
        console.error('fail init listener', err);
    });
}

/**
 * Utility to get dataset values safely.
 * @param {Object} templates - The element with the dataset.
 * @param {Array} data - The element with the dataset.
 */
function staticReloadBody(templates, data) {
    templates.render(SELECTORS.CHECKOUTMANAGERFORMTEMPLATE, data.data)
        .then(function(html, js) {
            return templates.replaceNodeContents(document.querySelector(SELECTORS.CHECKOUTMANAGERFORMID), html, js);
        })
        .then(function() {
            if (data.jsscript) {
                const scriptContent = data.jsscript.replace(/<script[^>]*>|<\/script>/gi, '');
                try {
                    templates.appendNodeContents(
                        document.querySelector(SELECTORS.CHECKOUTMANAGERFORMID),
                        '',
                        scriptContent
                );

                } catch (err) {
                    // eslint-disable-next-line no-console
                    console.error('fail script', err);
                }

                callZeroPriceListener();
            }
            return;
        })
        .catch(function(err) {
            // eslint-disable-next-line no-console
            console.error('fail script', err);
            return;
        });
}

/**
 * Utility to get dataset values safely.
 * @param {HTMLElement} element - The element with the dataset.
 * @param {string} key - The dataset key.
 * @returns {string|null} - The value or null if not found.
 */
function getDatasetValue(element, key) {
    return element?.dataset[key] || '';
}

/**
 * Mount dynamic forms for checkout steps that use the forms contract.
 *
 * Called from the step-form container template, both on initial page load
 * and after a client-side body re-render. Auto-submits on change to keep
 * the live update behaviour of the legacy steps.
 */
function initStepForms() {
    document.querySelectorAll(SELECTORS.STEPFORMCONTAINER).forEach(container => {
        if (container.dataset.stepFormInitialized === 'true') {
            return;
        }
        container.dataset.stepFormInitialized = 'true';
        require(['core_form/dynamicform'], function(DynamicForm) {
            const form = new DynamicForm(container, container.dataset.shoppingCartStepForm);
            stepForms[container.dataset.stepkey] = form;
            // The VAT step verifies against an external service (VIES) which can
            // be slow, so show a spinner and lock the UI while it runs.
            const showsspinner = container.dataset.stepkey === 'vatnrchecker';
            if (showsspinner) {
                form.addEventListener(form.events.SUBMIT_BUTTON_PRESSED, () => {
                    setStepVerificationState(container, true);
                });
                form.addEventListener(form.events.SERVER_VALIDATION_ERROR, () => {
                    setStepVerificationState(container, false);
                });
                form.addEventListener(form.events.CLIENT_VALIDATION_ERROR, () => {
                    setStepVerificationState(container, false);
                });
            }
            form.addEventListener(form.events.FORM_SUBMITTED, e => {
                // Keep the form mounted - the default handler would empty the container.
                e.preventDefault();
                if (showsspinner) {
                    setStepVerificationState(container, false);
                }
                updateCheckoutManagerPartials(e.detail);
                // A step submission (VAT validation, address selection) can change
                // the applied tax and therefore the visible price. Reload the cart
                // and price labels, matching the legacy standalone VAT checker
                // behaviour where submitting always triggered reinit().
                reinit();
            });
            if (container.dataset.autosubmit === '1') {
                container.addEventListener('change', () => {
                    form.submitFormAjax();
                });
            }
            // The form is already rendered server-side (Moodle dynamic-forms
            // pattern), so we hydrate it without an extra load() roundtrip.
        });
    });
}

// Cached localized "Checking VAT number..." text.
let vatVerificationLoadingText = null;
let vatVerificationLoadingTextPromise = null;

/**
 * Toggle the verifying state of a step form: spinner on its submit button,
 * a status text, and the surrounding navigation/checkout buttons disabled.
 *
 * @param {HTMLElement} container - The step form container.
 * @param {boolean} loading - Whether verification is in progress.
 */
function setStepVerificationState(container, loading) {
    const submitButton = container.querySelector('form [type="submit"]');
    if (submitButton) {
        if (loading) {
            if (!submitButton.dataset.originalLabel) {
                submitButton.dataset.originalLabel = submitButton.innerHTML;
            }
            submitButton.setAttribute('aria-busy', 'true');
            submitButton.disabled = true;
            submitButton.innerHTML =
                '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' +
                submitButton.dataset.originalLabel;
            getVatVerificationLoadingText().then(text => {
                showStepStatus(container, text);
                return null;
            }).catch(() => {
                return null;
            });
        } else {
            submitButton.setAttribute('aria-busy', 'false');
            submitButton.disabled = false;
            if (submitButton.dataset.originalLabel) {
                submitButton.innerHTML = submitButton.dataset.originalLabel;
            }
            clearStepStatus(container);
        }
    }
    // Lock the surrounding navigation and checkout buttons while verifying.
    const lockselectors = `${SELECTORS.BUTTONS}, ${SELECTORS.PROGRESSBUTTONS}, ${SELECTORS.PAYMENTREGIONBUTTON}`;
    document.querySelectorAll(lockselectors).forEach(element => {
        element.disabled = loading;
    });
}

/**
 * Loads and caches the localized "Checking VAT number..." text.
 *
 * @returns {Promise} resolving to the localized string.
 */
function getVatVerificationLoadingText() {
    if (vatVerificationLoadingText !== null) {
        return Promise.resolve(vatVerificationLoadingText);
    }
    if (!vatVerificationLoadingTextPromise) {
        vatVerificationLoadingTextPromise = getString('vatnrverificationinprogress', 'local_shopping_cart')
            .then(text => {
                vatVerificationLoadingText = text;
                return text;
            });
    }
    return vatVerificationLoadingTextPromise;
}

/**
 * Shows a status message inside a step form container.
 *
 * @param {HTMLElement} container - The step form container.
 * @param {string} text - The message to show.
 */
function showStepStatus(container, text) {
    let status = container.querySelector('.shopping-cart-step-verify-status');
    if (!status) {
        status = document.createElement('div');
        status.className = 'shopping-cart-step-verify-status text-muted small mt-2';
        container.appendChild(status);
    }
    status.textContent = text;
}

/**
 * Removes the status message from a step form container.
 *
 * @param {HTMLElement} container - The step form container.
 */
function clearStepStatus(container) {
    const status = container.querySelector('.shopping-cart-step-verify-status');
    if (status) {
        status.remove();
    }
}

export {
    init,
    getDatasetValue,
    getChangedInputs,
    initStepForms
};

/**
 * Call the zero price listener.
 *
 * @return [type]
 *
 */
function callZeroPriceListener() {
    // Initially, we need to add the zeroPriceListener once.
    const paymentbutton = document.querySelector(SELECTORS.PAYMENTREGIONBUTTON);
    if (paymentbutton) {
        reinit();
    }
}

/**
 * Prevents browsers from serving the checkout page from the back/forward cache (bfcache).
 * This is important to avoid users pressing "back" after a successful checkout and re-triggering
 * the payment with stale form data, which would lead to double bookings.
 * See https://web.dev/articles/bfcache for details.
 */
export function preventBFCache() {
    window.addEventListener('pageshow', function(event) {
        // True only when loaded from back/forward cache memory.
        if (event.persisted) {
            window.location.reload();
        }
    });
}
