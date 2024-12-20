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
};

const WEBSERVICE = {
    CHECKOUTPROCESS: 'local_shopping_cart_control_checkout_process',
};

const EVENTSLISTENING = {
    ADDRESSREDRAWN: 'local_shopping_cart/addressesRedrawn',
};
/**
 * Initializes the checkout manager functionality.
 */
function init() {
    initControlListener();
    initChangeListener();
    initVatNumberVerifyListener();
    document.addEventListener(EVENTSLISTENING.ADDRESSREDRAWN, function() {
        getNewAddress();
    });
}

/**
 * Initializes the change listener for the form body.
 */
function getNewAddress() {
    const formBody = document.querySelector(SELECTORS.CHECKBOXITEMBODY);
    const currentstep = formBody ? formBody.dataset.currentstep : null;
    if (currentstep !== null) {
        triggerButtonControlWebService(WEBSERVICE.CHECKOUTPROCESS, {
            action: '',
            currentstep: currentstep,
        });
    }
}

/**
 * Initializes the change listener for the form body.
 */
function initVatNumberVerifyListener() {
    const vatNumber = document.getElementById('shopping-cart-checkout-manager-verify-vat');
    if (vatNumber) {
        const formBody = document.querySelector(SELECTORS.CHECKBOXITEMBODY);
        vatNumber.addEventListener('click', function() {
            const countrySelect = document.getElementById('shopping-cart-checkout-manager-country-select');
            const countryCode = countrySelect.value;

            const vatNumberInput = document.getElementById('shopping-cart-checkout-manager-vat-number');
            const vatNumber = vatNumberInput.value;

            if (!countryCode || !vatNumber) {
                alert('Please select a country and enter a valid VAT number.');
                return;
            }
            const vatCountryCodeNumber = `${countryCode},${vatNumber}`;
            const changedInput = {
                'vatCodeCountry': vatCountryCodeNumber,
            };
            triggerButtonControlWebService(WEBSERVICE.CHECKOUTPROCESS, {
                action: formBody.dataset.action,
                currentstep: formBody.dataset.currentstep,
                identifier: formBody.dataset.identifier,
                changedinput: JSON.stringify(changedInput)
            });
        });
    }
}

/**
 * Initializes the change listener for the form body.
 */
function initControlListener() {
    const buttonSelectors = [SELECTORS.BUTTONS, SELECTORS.PROGRESSBUTTONS];
    buttonSelectors.forEach(selector => {
        const buttons = document.querySelectorAll(selector);
        buttons.forEach(button => {
            button.addEventListener('click', function() {
                const action = this.getAttribute('data-action');
                const currentstep = this.getAttribute('data-currentstep');
                triggerButtonControlWebService(WEBSERVICE.CHECKOUTPROCESS, {
                    action: action,
                    currentstep: currentstep,
                });
            });
        });
    });
}

/**
 * Initializes the change listener for the form body.
 */
function initChangeListener() {
    const formBody = document.querySelector(SELECTORS.CHECKBOXITEMBODY);
    if (formBody) {
        formBody.addEventListener('change', function(event) {
            const target = event.target;
            if (['INPUT', 'SELECT', 'TEXTAREA'].includes(target.tagName)) {
                if (target.type == 'checkbox') {
                    target.value = target.checked;
                }
                const changedInput = {
                    name: target.name || 'unnamed',
                    value: target.value,
                };
                triggerButtonControlWebService(WEBSERVICE.CHECKOUTPROCESS, {
                    action: formBody.dataset.action,
                    currentstep: formBody.dataset.currentstep,
                    identifier: formBody.dataset.identifier,
                    changedinput: JSON.stringify(changedInput)
                });
            }
        });
    }
}

/**
 * Handles button control for the checkout process.
 * @param {string} serviceName - The name of the web service.
 * @param {Object} params - The parameters for the web service call.
 */
function triggerButtonControlWebService(serviceName, params) {
    require(['core/ajax'], function (Ajax) {
        const requests = Ajax.call([{
            methodname: serviceName,
            args: params,
        }]);
        requests[0].done(function(response) {
            updateCheckoutManagerPartials(response);
        }).fail(function(err) {
            // eslint-disable-next-line no-console
            console.error('Failed to complete action. Error: ', err);
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
    if (data.reloadbody) {
        require(['core/templates'], function(templates) {
            templates.render(SELECTORS.CHECKOUTMANAGERFORMTEMPLATE, data.data)
                .then(function(html, js) {
                    return templates.replaceNodeContents(document.querySelector(SELECTORS.CHECKOUTMANAGERFORMID), html, js);
                })
                .then(function() {
                    if (data.jsscript) {
                        // Extract the raw JavaScript from the script tag
                        const scriptContent = data.jsscript.replace(/<script[^>]*>|<\/script>/gi, '');
                        // Execute the script safely
                        try {
                            templates.appendNodeContents(
                                document.querySelector(SELECTORS.CHECKOUTMANAGERFORMID),
                                '',
                                scriptContent
                        );

                        } catch (err) {
                            // eslint-disable-next-line no-console
                            console.error('Error executing script:', err);
                        }
                    }
                    return;
                })
                .catch(function(err) {
                    // eslint-disable-next-line no-console
                    console.error('Error rendering body: ', err);
                    return;
                });
        });
    } else {
        require(['core/templates'], function(templates) {
            // Target a specific div in the DOM
            const controlButtons = document.getElementById(SELECTORS.CHECKOUTMANAGERBUTTONSID);
            const progressBar = document.getElementById(SELECTORS.CHECKOUTMANAGERPROGRESSBARID);

            if (!controlButtons) {
                // eslint-disable-next-line no-console
                console.error('Target div not found in the DOM.');
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
                    console.error('Error updating the specific div:', err);
                });
            const progressBarTemplate = templates.render(SELECTORS.CHECKOUTMANAGERPROGRESSBARTEMPLATE, data.data)
                .then(function(html) {
                    progressBar.innerHTML = html;
                    return;
                })
                .catch(function(err) {
                    // eslint-disable-next-line no-console
                    console.error('Error updating the specific div:', err);
                });
            Promise.all([renderButtonTemplate, progressBarTemplate]).then(function() {
                // eslint-disable-next-line no-console
                console.log('Both templates have been updated.');
                initControlListener();
                return;
            }).catch(function(err) {
                // eslint-disable-next-line no-console
                console.error('Render problem:', err);
            });
        });

    }
}

export {init};