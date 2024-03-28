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

import Ajax from 'core/ajax';
import Templates from 'core/templates';

import {updateTotalPrice, buttoninit} from 'local_shopping_cart/cart';

import {
    get_string as getString,
    get_strings as getStrings
        }
        from 'core/str';
import {showNotification} from 'local_shopping_cart/notifications';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import ModalForm from 'core_form/modalform';

const SELECTORS = {
    CANCELBUTTON: '.cashier-history-items .shopping_cart_history_cancel_button',
    PAIDBACKBUTTON: 'button.shopping_cart_history_paidback_button',
    CREDITSMANAGER: 'button.shopping_cart_history_creditsmanager',
    REBOOKBUTTON: '.shopping_cart_history_rebook_button',
};

// Little hack to get strings at top-level although getString is asynchronous.
let creditsmanagersuccess = 'success';
let notenoughcredits = 'notenoughcredits';
(async() => {
    creditsmanagersuccess = await getString('creditsmanagersuccess', 'local_shopping_cart');
    notenoughcredits = await getString('notenoughcredits', 'local_shopping_cart');
})();

export const init = (cancelationFee = null) => {

    const buttons = document.querySelectorAll(SELECTORS.CANCELBUTTON);

    buttons.forEach(button => {

        if (!button.dataset.initialized) {

            if (button.dataset.canceled == true) {
                setButtonToCanceled(button);
            } else {
                button.addEventListener('click', event => {

                    event.preventDefault();
                    event.stopPropagation();

                    if (button.dataset.canceled == false) {

                        // We find out if we are on the cashiers page. Only there, we set the cashiers modal.

                        if (window.location.href.includes('cashier.php')) {
                            confirmCancelAndSetCreditModal(button);
                        } else {
                            // We only add the functionality if we got a cancelation fee.
                            confirmCancelModal(button, cancelationFee);
                        }
                    }

                });
            }
            button.dataset.initialized = true;
        }
    });

    const elements = document.querySelectorAll(SELECTORS.PAIDBACKBUTTON);

    elements.forEach(element => {

        if (!element.dataset.initialized) {
            element.addEventListener('click', event => {

                event.preventDefault();
                event.stopPropagation();

                confirmPaidBackModal(element);
            });
            element.dataset.initialized = true;
        }
    });

    // Credits manager button.
    const creditsmanagerbtn = document.querySelectorAll(SELECTORS.CREDITSMANAGER);
    creditsmanagerbtn.forEach(btn => {
        if (!btn.dataset.initialized) {
            btn.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();
                openCreditsManagerModal(btn);
            });
            btn.dataset.initialized = true;
        }
    });

    // Mark for rebooking button.
    const rebookbuttons = document.querySelectorAll(SELECTORS.REBOOKBUTTON);
    rebookbuttons.forEach(btn => {
        if (!btn.dataset.initialized) {
            btn.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();
                markforrebooking(btn);
            });
            btn.dataset.initialized = true;
        }
    });
};

/**
 * This triggers the ajax call to acutally cancel the purchase.
 * @param {int} itemid
 * @param {string} area
 * @param {int} userid
 * @param {string} componentname
 * @param {int} historyid
 * @param {string} currency
 * @param {string} price
 * @param {string} credit
 * @param {type} button
 */
export function cancelPurchase(itemid, area, userid, componentname, historyid, currency, price, credit, button) {

    Ajax.call([{
        methodname: "local_shopping_cart_cancel_purchase",
        args: {
            'itemid': itemid,
            'componentname': componentname,
            'area': area,
            'userid': userid,
            'historyid': historyid,
            'credit': credit
        },
        done: function(data) {

            if (data.success == 1) {

                getString('cancelsuccess', 'local_shopping_cart').then(message => {

                    showNotification(message, "success");

                    return;
                }).catch(e => {
                    // eslint-disable-next-line no-console
                    console.log(e);
                });

                if (!button) {
                    import('local_wunderbyte_table/reload')
                    // eslint-disable-next-line promise/always-return
                    .then(wbt => {
                        wbt.reloadAllTables();
                    })
                    .catch(err => {
                            // Handle any errors, including if the module doesn't exist
                            // eslint-disable-next-line no-console
                            console.log(err);
                    });
                    return;
                }
                setButtonToCanceled(button);

                showCredit(data.credit, currency, userid);

                // Make sure addtocartbutton active againe once the item is removed from the shopping cart.
                const addtocartbutton = document.querySelector('#btn-' + componentname + '-' + itemid);

                // If there is not addtocartbutton, we have to add it anew.
                if (!addtocartbutton) {

                    data.itemid = itemid;
                    data.componentname = componentname;
                    data.price = Number(price).toFixed(2); // Creates a string with two decimals.

                    Templates.renderForPromise('local_shopping_cart/addtocartdb', data).then(({html}) => {

                        // Get parentelement.
                        let parent = document.querySelector('span.price_' + componentname + "_" + itemid);
                        parent.textContent = price + " " + currency;

                        if (parent) {
                            parent.insertAdjacentHTML('beforeend', html);
                        }

                        buttoninit(itemid, componentname);
                        return true;
                    }).catch((e) => {
                        // eslint-disable-next-line no-console
                        console.log(e);
                    });
                } else {

                     addtocartbutton.classList.remove('disabled');
                     addtocartbutton.dataset.initialized = false;
                     buttoninit(itemid, componentname);
                }

            } else {
                getString('canceldidntwork', 'local_shopping_cart').then(message => {

                    showNotification(message, "danger");

                    return;
                }).catch(e => {
                    // eslint-disable-next-line no-console
                    console.log(e);
                });
            }

        },
        fail: function(ex) {
            // eslint-disable-next-line no-console
            console.log("ex:" + ex);
        },
    }]);

}

/**
 * Function to change classes and text of button.
 * @param {*} button
 */
function setButtonToCanceled(button) {

    button.classList.add('disabled');
    button.classList.remove('btn-primary');
    button.classList.add('btn-danger');
    button.dataset.canceled = true;

    getString('canceled', 'local_shopping_cart').then(result => {

        button.innerText = result;
        return;
    }).catch(e => {
        // eslint-disable-next-line no-console
        console.log(e);
    });
}

/**
 *
 * @param {string} credit
 * @param {string} currency
 * @param {int} userid
 */
function showCredit(credit, currency, userid) {

    let creditelement = document.querySelector('li.shopping_cart_history_paidback');

    if (creditelement) {
        creditelement.classList.remove('hidden');

        let credittotalelement = creditelement.querySelector('span.credit_total');

        credittotalelement.textContent = credit;

    } else {

        let data = {
            'currency': currency,
            'credit': Number(credit).toFixed(2), // Creates a string with two decimals.
            'userid': userid
        };

        Templates.renderForPromise('local_shopping_cart/credit_item', data).then(({html}) => {

            // Get parentelement.
            let parent = document.querySelector('ul.cashier-history-items');

            parent.insertAdjacentHTML('afterbegin', html);

            // We rerun init after insert, to make sure we have the right value.
            init();
            return true;
        }).catch((e) => {
            // eslint-disable-next-line no-console
            console.log(e);
        });
    }
    // We also need to call the udpateTotalPrice function from this place to make sure everything is uptodate.
    updateTotalPrice();
}

/**
 *
 * @param {*} element
 */
function confirmPaidBack(element) {
    const userid = element.dataset.userid;
    const method = element.dataset.method;
    Ajax.call([{
        methodname: "local_shopping_cart_credit_paid_back",
        args: {
            userid,
            method
        },
        done: function(data) {

            // eslint-disable-next-line no-console
            console.log(data);

            let creditelement = document.querySelector('.credit_total');

            creditelement.textContent = 0;

            // We hide the creditelement once we have paid back everything.
            let licreditelements = document.querySelectorAll('.shopping_cart_history_paidback');

            licreditelements.forEach(licreditelement => licreditelement.classList.add('hidden'));

            getString('creditpaidback', 'local_shopping_cart').then(message => {

                showNotification(message, 'success');

                return;
            }).catch(e => {
                // eslint-disable-next-line no-console
                console.log(e);
            });

             // We also need to call the udpateTotalPrice function from this place to make sure everything is uptodate.
            updateTotalPrice();
            return;
        },
        fail: function(ex) {
        // eslint-disable-next-line no-console
        console.log("ex:" + ex);
        },
    }]);
}

/**
 *
 * @param {*} button
 * @param {*} cancelationFee
 */
export async function confirmCancelModal(button, cancelationFee) {

    // eslint-disable-next-line no-console
    console.log(button);

    // If we have no price, but there are all the other values on the button...
    // ... we first fetch the necessary data.
    if (!button.dataset.hasOwnProperty('price')) {
        await new Promise(function(resolve, reject) {
            Ajax.call([{
                methodname: 'local_shopping_cart_get_history_item',
                args: {
                    'itemid': button.dataset.itemid,
                    'componentname': button.dataset.componentname,
                    'area': button.dataset.area,
                    'userid': button.dataset.userid,
                },
                done: function(data) {

                    // eslint-disable-next-line no-console
                    console.log(data);

                    if (!data.success == 1) {
                        resolve(data);
                        return;
                    }

                    button.dataset.historyid = data.id;
                    button.dataset.price = data.price;
                    button.dataset.credit = 0;
                    button.dataset.currency = data.currency;
                    button.dataset.quotaconsumed = data.quotaconsumed;
                    button.dataset.round = data.round;
                    cancelationFee = data.cancelationfee;
                    button.dataset.buttontonull = true;

                    resolve(data);
                },
                fail: ex => {
                    // eslint-disable-next-line no-console
                    console.log("failed to load information for modal: " + JSON.stringify(ex));
                    reject(ex);
                }
            }]);
        });
    }
    if (!button.dataset.hasOwnProperty('price')) {
        getString('canceldidntwork', 'local_shopping_cart').then(message => {

            showNotification(message, "danger");

            return;
        }).catch(e => {
            // eslint-disable-next-line no-console
            console.log(e);
        });
        return;
    }

    // Before showing the cancel modal, we need to gather some information and pass it to the string.
    if (cancelationFee === null) {
        cancelationFee = 0;
    }

    const price = parseFloat(button.dataset.price);
    // Quota consumed is always on two deciamals.
    const quotaconsumed = parseFloat(button.dataset.quotaconsumed);

    const deducedvalue = price * quotaconsumed;
    const credit = price - deducedvalue - cancelationFee;
    const currency = button.dataset.currency;
    // We always round percentages.
    const percentage = Math.round(quotaconsumed * 100);

    const params = {
        quotaconsumed: quotaconsumed.toFixed(2),
        percentage: percentage + '%',
        currency: currency,
        deducedvalue: deducedvalue,
    };

    const roundvalues = button.dataset.round;
    if (roundvalues) {
        params.price = Math.round(price);
        params.credit = Math.round(credit);
        params.cancelationfee = Math.round(cancelationFee);
        params.deducedvalue = Math.round(deducedvalue);
    } else {
        params.price = price.toFixed(2);
        params.credit = credit.toFixed(2);
        params.cancelationfee = cancelationFee.toFixed(2);
        params.deducedvalue = deducedvalue.toFixed(2);
    }

    let bodystring = 'confirmcancelbodyuser';
    if (quotaconsumed > 0 && quotaconsumed < 1) {
        bodystring = 'confirmcancelbodyuserconsumption';
    } else if (quotaconsumed == 1) {
        bodystring = 'confirmcancelbodyusernocredit';
    }

    // Finally, make sure that we don't have negative values.
    if (params.credit < 0) {
        params.cancelationFee = 0 - params.credit; // Will be between 0 and cancelationfee.
        params.credit = 0;
    }

    // eslint-disable-next-line no-console
    console.log(params);

    getStrings([
            {key: 'confirmcanceltitle', component: 'local_shopping_cart'},
            {key: bodystring, component: 'local_shopping_cart', param: params},
            {key: 'cancelpurchase', component: 'local_shopping_cart'}
        ]
    ).then(strings => {
        // eslint-disable-next-line promise/no-nesting
        ModalFactory.create({type: ModalFactory.types.SAVE_CANCEL}).then(modal => {

            modal.setTitle(strings[0]);
            modal.setBody(strings[1]);
            modal.setSaveButtonText(strings[2]);
            modal.getRoot().on(ModalEvents.save, function() {

                const historyid = button.dataset.historyid;
                const itemid = button.dataset.itemid;
                const userid = button.dataset.userid;
                const currency = button.dataset.currency;
                const componentname = button.dataset.componentname;
                const area = button.dataset.area;
                const price = button.dataset.price;

                if (button.dataset.buttontonull) {
                    button = null;
                }

                cancelPurchase(itemid, area, userid, componentname, historyid, currency, price, 0, button);
            });

            modal.show();
            return modal;
        }).catch(e => {
            // eslint-disable-next-line no-console
            console.log(e);
        });
        return true;
    }).catch(e => {
        // eslint-disable-next-line no-console
        console.log(e);
    });
}

/**
 *
 * @param {*} button
 */
function confirmCancelAndSetCreditModal(button) {

    const price = button.dataset.price;
    const historyid = button.dataset.historyid;
    const itemid = button.dataset.itemid;
    const userid = button.dataset.userid;
    const currency = button.dataset.currency;
    const componentname = button.dataset.componentname;
    const area = button.dataset.area;

    const modalForm = new ModalForm({

        // Name of the class where form is defined (must extend \core_form\dynamic_form):
        formClass: "local_shopping_cart\\form\\modal_cancel_addcredit",
        // Add as many arguments as you need, they will be passed to the form:
        args: {'price': price,
               'historyid': historyid,
               'itemid': itemid,
               'userid': userid,
               'currency': currency,
               'componentname': componentname,
               'area': area},
        // Pass any configuration settings to the modal dialogue, for example, the title:
        modalConfig: {title: getString('confirmcanceltitle', 'local_shopping_cart')},
        // DOM element that should get the focus after the modal dialogue is closed:
        returnFocus: button
    });
    // Listen to events if you want to execute something on form submit.
    // Event detail will contain everything the process() function returned:
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
        const response = e.detail;
        // eslint-disable-next-line no-console
        console.log(response);

        const url = new URL(window.location.href);
        url.searchParams.append('userid', userid);
        window.location.replace(url.toString());
    });

    // Show the form.
    modalForm.show();
}

/**
 *
 * @param {*} element
 */
function confirmPaidBackModal(element) {

    getStrings([
        {key: 'confirmpaidbacktitle', component: 'local_shopping_cart'},
        {key: 'confirmpaidbackbody', component: 'local_shopping_cart'},
        {key: 'confirmpaidback', component: 'local_shopping_cart'}
    ]
    ).then(strings => {
        // eslint-disable-next-line promise/no-nesting
        ModalFactory.create({type: ModalFactory.types.SAVE_CANCEL}).then(modal => {

            modal.setTitle(strings[0]);
                modal.setBody(strings[1]);
                modal.setSaveButtonText(strings[2]);
                modal.getRoot().on(ModalEvents.save, function() {

                    confirmPaidBack(element);
                });

                modal.show();
                return modal;
        }).catch(e => {
            // eslint-disable-next-line no-console
            console.log(e);
        });
        return true;
    }).catch(e => {
        // eslint-disable-next-line no-console
        console.log(e);
    });
}

/**
 * Open the credits manager modal.
 * @param {htmlElement} button
 */
function openCreditsManagerModal(button) {
    // eslint-disable-next-line no-console
    console.log('credits-managermodal');

    const modalForm = new ModalForm({
        // Name of the class where form is defined (must extend \core_form\dynamic_form):
        formClass: "local_shopping_cart\\form\\modal_creditsmanager",
        // Add as many arguments as you need, they will be passed to the form:
        args: {
            userid: button.dataset.userid
        },
        // Pass any configuration settings to the modal dialogue, for example, the title:
        modalConfig: {title: getString('creditsmanager', 'local_shopping_cart')},
        // DOM element that should get the focus after the modal dialogue is closed:
        returnFocus: button
    });
    // Listen to events if you want to execute something on form submit.
    // Event detail will contain everything the process() function returned:
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
        const response = e.detail;

        if (response.error && response.error == 'notenoughcredits') {
            showNotification(notenoughcredits, 'danger');
        } else {
            // eslint-disable-next-line no-console
            console.log('credits-manager-modal response: ', response);
            showNotification(creditsmanagersuccess, 'info');
            setTimeout(function() {
                window.location.reload();
            }, 1500);
        }
    });

    // Show the form.
    modalForm.show();
}

/**
 * Mark booking options for rebooking.
 * @param {htmlElement} button
 */
function markforrebooking(button) {

    // eslint-disable-next-line no-console
    console.log(button);

    const historyid = button.dataset.historyid;
    const userid = button.dataset.userid;

    Ajax.call([{
        methodname: 'local_shopping_cart_mark_item_for_rebooking',
        args: {
            historyid,
            userid
        },
        done: function(data) {

            // eslint-disable-next-line no-console
            console.log(data);
            window.location.reload();

        },
        fail: ex => {
            // eslint-disable-next-line no-console
            console.log("local_shopping_cart_mark_item_for_rebooking failed: " + JSON.stringify(ex));
        },
    }]);
}