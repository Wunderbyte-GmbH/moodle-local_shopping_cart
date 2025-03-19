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
import ModalFactory from 'core/modal_factory';
import {get_string as getString} from 'core/str';

import {reinit} from 'local_shopping_cart/cart';

const SELECTORS = {
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
    ADDRESSREDRAWN: 'local_shopping_cart/addressesRedrawn',
};

const IDS = {
    VATNUMBER: 'shopping-cart-checkout-manager-vat-number',
    VERIFYVAT: 'shopping-cart-checkout-manager-verify-vat',
    COUNTRYSELECT: 'shopping-cart-checkout-manager-country-select',
};

/**
 * Initializes the checkout manager functionality.
 */
function init() {
    const formBody = document.querySelector(SELECTORS.CHECKBOXITEMBODY);
    initListeners(formBody);
    document.addEventListener(EVENTSLISTENING.ADDRESSREDRAWN, getNewAddress);
}

/**
 * Initializes the checkout manager functionality.
 * @param {HTMLElement} formBody - The name of the web service.
 */
function initListeners(formBody) {
    initControlListener();
    if (formBody != undefined) {
        initChangeListener();
        initVatNumberVerifyListener(formBody);
    }

}

/**
 * Initializes the change listener for the form body.
 */
function getNewAddress() {
    const formBody = document.querySelector(SELECTORS.CHECKBOXITEMBODY);
    const currentstep = getDatasetValue(formBody, 'currentstep');
    const identifier = getDatasetValue(formBody, 'identifier');

    if (currentstep !== null) {
        triggerButtonControlWebService(WEBSERVICE.CHECKOUTPROCESS, {
            action: '',
            currentstep: currentstep,
            identifier: identifier,
        });
    }
}

/**
 * Initializes the change listener for the form body.
 */
function initVatNumberVerifyListener() {
    const vatNumber = document.getElementById(IDS.VERIFYVAT);
    if (vatNumber) {
        vatNumber.addEventListener('click', vatNumberVerifyCallback);
    }
}

/**
 * Initializes the change listener for the form body.
 */
function vatNumberVerifyCallback() {
    const formBody = document.querySelector(SELECTORS.CHECKBOXITEMBODY);
    const countryCode = document.getElementById(IDS.COUNTRYSELECT)?.value;
    const vatNumber = document.getElementById(IDS.VATNUMBER)?.value;

    if (!countryCode || !vatNumber) {
        ModalFactory.create({type: ModalFactory.types.CANCEL}).then(modal => {
            modal.setTitle(getString('errorinvalidvatdatatitle', 'local_shopping_cart'));
            modal.setBody(getString('errorinvalidvatdatadescription', 'local_shopping_cart'));
            modal.show();
            return modal;
        }).catch(e => {
            // eslint-disable-next-line no-console
            console.log(e);
        });
        return;
    }
    triggerButtonControlWebService(WEBSERVICE.CHECKOUTPROCESS, {
        action: getDatasetValue(formBody, 'action'),
        currentstep: getDatasetValue(formBody, 'currentstep'),
        identifier: getDatasetValue(formBody, 'identifier'),
        changedinput: JSON.stringify({
            'vatCodeCountry': `${countryCode},${vatNumber}`,
        }),
    });
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

export {
    init,
    getDatasetValue,
    getChangedInputs
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