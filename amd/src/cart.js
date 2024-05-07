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

import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';

import {confirmPayment} from 'local_shopping_cart/cashier';
import {discountModal} from 'local_shopping_cart/cashier';
import {showNotification} from 'local_shopping_cart/notifications';

import {
    get_strings as getStrings,
    get_string as getString
        }
        from 'core/str';

export var interval = null;
export var visbilityevent = false;

// This file inits the cart on every page, on checkout and cashier.
// The cart is always loaed entirely and replaced via css.
// The cashiers cart are identified in the DOM via userid -1 (CASHIERUSER).
// The translation to real userids is done in the PHP only.

const CASHIERUSER = -1;

const SELECTORS = {
    SHOPPING_CART_ITEM: '[data-item="shopping_cart_item"]',
    NAVBARCONTAINER: '#nav-shopping_cart-popover-container .shopping-cart-items-container',
    TRASHCLASS: 'fa-trash-o',
    DISCOUNTCLASS: 'shoppingcart-discount-icon',
    BADGECOUNT: '#nav-shopping_cart-popover-container div.count-container',
    COUNTDOWN: '#nav-shopping_cart-popover-container span.expirationtime',
    CASHIERSCART: 'div.shopping-cart-cashier-items-container',
    CHECKOUTCART: 'div.shopping-cart-checkout-items-container',
    PRICELABELCHECKBOX: '.sc_price_label input.usecredit-checkbox',
    INSTALLMENTSCHECKBOX: '.sc_price_label input.useinstallments-checkbox',
    PRICELABELAREA: '.sc_price_label',
    CHECKOUTBUTTON: '#nav-shopping_cart-popover-container #shopping-cart-checkout-button',
    PAYMENTREGIONBUTTON: 'div.shopping_cart_payment_region button',
    ACCEPTTERMS: '#accepttermsnandconditions',
};
/**
 *
 * @param {*} expirationtime
 */

 export const init = (expirationtime, nowdate) => {

    // eslint-disable-next-line no-console
    console.log(expirationtime, nowdate);

    initTimer(expirationtime, nowdate);

    // We might have more than one container.
    let containers = [];
    containers = document.querySelectorAll(SELECTORS.NAVBARCONTAINER
        + "," + SELECTORS.CASHIERSCART
        + "," + SELECTORS.CHECKOUTCART);

    containers.forEach(container => {

        container.addEventListener('click', event => {

            // Decide the target of the click.
            const element = event.target;

            if (element.classList.contains(SELECTORS.TRASHCLASS)) {

                const userid = element.dataset.userid ? element.dataset.userid : 0;
                const component = element.dataset.component;
                const area = element.dataset.area;
                const itemid = element.dataset.itemid;

                deleteItem(itemid, component, area, userid);
            } else if (element.classList.contains(SELECTORS.DISCOUNTCLASS)) {

                discountModal(event);
            }
        });
    });

    // Re-init cart on page reload or navigation to another page - required for 2-digit price precision visibility.
    document.addEventListener("readystatechange", () => {
        if (document.readyState !== 'loading') {
            reinit();
        }
    });

    if (visbilityevent == false) {
        document.addEventListener("visibilitychange", function() {
            visbilityevent = true;
            if (document.visibilityState === 'visible') {
                reinit();
            }
        });
    }

    // Initially, we need to add the zeroPriceListener once.
    const paymentbutton = document.querySelector(SELECTORS.PAYMENTREGIONBUTTON);
    if (paymentbutton) {
        const data = {
            price: paymentbutton.dataset.price,
            currency: paymentbutton.dataset.currency,
        };
        addZeroPriceListener(data);
    }

    const accepttermsbutton = document.querySelector(SELECTORS.ACCEPTTERMS);
    if (accepttermsbutton && paymentbutton) {
        addAcceptTermsListener(accepttermsbutton, paymentbutton);
    }
};

export const buttoninit = (itemid, component, area) => {

    // If we don't have an itemid, we need to look for all the buttons.
    if (!itemid || !component || !area) {
        const selector = '[data-objecttable="local_shopping_cart"';
        const allbuttons = document.querySelectorAll(
            selector);

        allbuttons.forEach(button => {
            const itemid = button.dataset.itemid;
            const area = button.dataset.area;
            const component = button.dataset.component;
            buttoninit(itemid, component, area);
        });
        return;
    }

    // Return all buttons with the add to cart functionality.
    const buttons =
    document.querySelectorAll(
        'div'
        + '[data-itemid="' + itemid + '"]'
        + '[data-component="' + component + '"]'
        + '[data-area="' + area + '"]'
        + '[data-objecttable="local_shopping_cart"');

    buttons.forEach(addtocartbutton => {

        // We need to check all the buttons.
        toggleActiveButtonState(addtocartbutton);

        // We only ever initialize the button once.
        if (!addtocartbutton || addtocartbutton.dataset.initialized === 'true') {
            return;
        }
        addtocartbutton.dataset.initialized = 'true';

        // If the button has the nojs flag, we don't add the listener at all.

        if (addtocartbutton.dataset.nojs) {
            return;
        }

        // Add click eventlistern to oneself.
        addtocartbutton.addEventListener('click', event => {

            if (addtocartbutton.dataset.blocked == 'true') {
                return;
            }

            // If we find the disabled class, the click event is aborted.
            if (addtocartbutton.classList.contains('disabled')) {
                event.preventDefault();
                event.stopPropagation();
                // DeleteItem(itemid, component, area);
            } else {
                // Event.preventDefault();
                // Event.stopPropagation();
                addItem(itemid, component, area);
            }
        });
    });

    return;
};

/**
 * Function to reload the cart. We can pass on the certain component if we need to make sure that not only the cart is reloaded.
 * This is the case when adding or deleting a certain item and a special button has to be reset.
 * @param {*} userid
 */
export const reinit = (userid = 0) => {

    userid = transformUserIdForCashier(userid);

    Ajax.call([{
        methodname: "local_shopping_cart_get_shopping_cart_items",
        args: {
            userid
        },
        done: function(data) {

            // If we are on the cashier page, we add the possibility to add a discount to the cart items.
            const oncashier = window.location.href.indexOf("cashier.php");

            if (oncashier > 0) {
                data.iscashier = true;
            } else {
                data.iscashier = false;
            }

            let containers = [];

            if (userid != 0 && data.iscashier) {
                containers = document.querySelectorAll(SELECTORS.CASHIERSCART);
            } else {
                containers = document.querySelectorAll(SELECTORS.NAVBARCONTAINER
                    + "," + SELECTORS.CHECKOUTCART);
            }

            let promises = [];

            // Before rendering, we convert all prices to strings with 2 fixed decimals.
            convertPricesToNumberFormat(data);

            // We render for promise for all the containers.
            promises.push(Templates.renderForPromise('local_shopping_cart/shopping_cart_items', data).then(({html, js}) => {
                containers.forEach(container => {
                // We know we will always find the Navbar, so we can do this right away.
                    Templates.replaceNodeContents(container, html, js);
                });
                return true;
            }).catch((e) => {
                // eslint-disable-next-line no-console
                console.log(e);
            }));

            Promise.all(promises).then(() => {

                // If we are on the cashier page, we add the possibility to add a discount to the cart items.
                if (!(userid != 0 && data.iscashier)) {
                    clearInterval(interval);
                    initTimer(data.expirationtime, data.nowdate);

                    updateBadge(data.count);
                }

                toggleActiveButtonState();

                updateTotalPrice(userid);

                return;
            }).catch(e => {
                // eslint-disable-next-line no-console
                console.log(e);
            });

        },
        fail: ex => {
            // eslint-disable-next-line no-console
            console.log("ex:" + ex);
        },
    }]);
};

/**
 * This function is only called when the timer invalidates the cart.
 * If no userid is provided the logged in USER will be used.
 * The USER-user is chosen with the userid 0, we just reinit everything after sending.
 * @param {*} userid
 */
export const deleteAllItems = (userid = 0) => {
    Ajax.call([{
        methodname: "local_shopping_cart_delete_all_items_from_cart",
        args: {
            'userid': userid
        },
        done: function() {
            reinit(0);
        },
        fail: function(ex) {
            // eslint-disable-next-line no-console
            console.log(ex);
        },
    }]);
};

export const deleteItem = (itemid, component, area, userid) => {

    userid = transformUserIdForCashier(userid);

    Ajax.call([{
        methodname: "local_shopping_cart_delete_item",
        args: {
            'itemid': itemid,
            'component': component,
            'area': area,
            'userid': userid
        },
        done: function(data) {

            getString('item_deleted', 'local_shopping_cart', data.itemname).then(message => {
                showNotification(message, 'success');
                return;
            }).catch(e => {
                // eslint-disable-next-line no-console
                console.log(e);
            });

            reinit(userid);

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

        },
        fail: function() {

            reinit(userid);
        },
    }]);
};

export const addItem = (itemid, component, area) => {

    let userid = transformUserIdForCashier();

    if (!Number.isInteger(userid)) {
        userid = parseInt(userid);
    }

    Ajax.call([{
        methodname: "local_shopping_cart_add_item",
        args: {
            'area': area,
            'component': component,
            'itemid': itemid,
            'userid': userid
        },
        done: function(data) {
            data.component = component;
            data.area = area;
            data.itemid = itemid;
            data.userid = userid; // For the mustache template, we need to obey structure.
            addItemShowNotification(data);
        },
        fail: function(ex) {
            // eslint-disable-next-line no-console
            console.log('error', ex);
        }
    }], true);
};

/**
 *
 * @param {*} userid
 * @param {*} usecredit
 * @param {*} useinstallments
 */
export const updateTotalPrice = (userid = 0, usecredit = true, useinstallments = false) => {

    // On cashier, update price must always be for cashier user.
    const oncashier = window.location.href.indexOf("cashier.php");

    if (oncashier > 0) {
        userid = CASHIERUSER;
    }

    if (!Number.isInteger(userid)) {
        userid = parseInt(userid);
    }

    // We must make sure the checkbox is only once visible on the site.
    // const checkbox = document.querySelector(SELECTORS.PRICELABELCHECKBOX);
    usecredit = usecredit ? 1 : 0;
    useinstallments = useinstallments ? 1 : 0;

    Ajax.call([{
        methodname: "local_shopping_cart_get_price",
        args: {
            userid,
            usecredit,
            useinstallments
        },
        done: function(data) {

            // We take the usecredit value we receive from the server.
            if (data.usecredit == 1) {
                data.usecreditvalue = 'checked';
            } else {
                data.usecreditvalue = '';
            }

            data.checkboxid = Math.random().toString(36).slice(2, 5);

            if (data.installments.length > 0) {
                data.installmentscheckboxid = 'i' + data.checkboxid;
            }

            data.userid = userid;

            const labelareas = document.querySelectorAll(SELECTORS.PRICELABELAREA);

            // Before rendering, we convert all prices to strings with 2 fixed decimals.
            convertPricesToNumberFormat(data);

            Templates.renderForPromise('local_shopping_cart/price_label', data).then(({html, js}) => {

                labelareas.forEach(labelarea => {
                    Templates.replaceNodeContents(labelarea, html, js);

                    addZeroPriceListener(data);
                });

                return true;
            }).catch((e => {
                // eslint-disable-next-line no-console
                console.log(e);
            }));

            const checkoutButton = document.querySelector(SELECTORS.CHECKOUTBUTTON);
            const paymentbutton = document.querySelector(SELECTORS.PAYMENTREGIONBUTTON);
            if (data.count == 0) {
                if (checkoutButton) {
                    checkoutButton.classList.add("disabled");
                }
                if (paymentbutton) {
                    paymentbutton.style.display = "none";
                }
            } else {
                if (checkoutButton) {
                    checkoutButton.classList.remove("disabled");
                }
                if (paymentbutton) {
                    paymentbutton.style.display = "inline";
                }
            }
        },
        fail: function(ex) {
            // eslint-disable-next-line no-console
            console.log('error', ex);
        }
    }], true);
};

/**
 * Looks for the payment buttun, updates cost and adds the listener.
 * @param {*} data
 */
function addZeroPriceListener(data) {

    let paymentbutton = document.querySelector(".shopping_cart_payment_region button");

    if (paymentbutton) {

        if (paymentbutton.classList.contains('disabled')) {
            return;
        }

        const price = data.price;
        const currency = data.currency;

        paymentbutton.dataset.cost = price + " " + currency;

        if (price == 0) {

            paymentbutton.addEventListener('click', dealWithZeroPrice);
        } else {

            paymentbutton.removeEventListener('click', dealWithZeroPrice);
        }
    }
}

/**
 * Function to show notifications when items are added.
 * @param {*} data
 */
export function addItemShowNotification(data) {
    const CARTPARAM_ALREADYINCART = 0; // Already in cart.
    const CARTPARAM_SUCCESS = 1; // Item added to cart successfully.
    const CARTPARAM_CARTISFULL = 2; // Item added to cart successfully.
    const CARTPARAM_COSTCENTER = 3; // Item added to cart successfully.
    const CARTPARAM_FULLYBOOKED = 4; // Item not added because item is already fully booked.
    const CARTPARAM_ALREADYBOOKED = 5; // Item not added because item was already booked before.

    switch (data.success) {
        case CARTPARAM_ALREADYINCART:
            reinit();
            return;
        case CARTPARAM_SUCCESS:
            getString('addedtocart', 'local_shopping_cart', data.itemname).then(message => {
                showNotification(message, 'success');
                return;
            }).catch(e => {
                // eslint-disable-next-line no-console
                console.log(e);
            });
            reinit(data.userid);
            return;
        case CARTPARAM_CARTISFULL:
            getStrings([
                {key: 'cartisfull', component: 'local_shopping_cart'},
                {key: 'ok', component: 'core'},
            ]).then(strings => {
                // eslint-disable-next-line promise/no-nesting
                ModalFactory.create({type: ModalFactory.types.SAVE_CANCEL}).then(modal => {
                    modal.setBody(strings[0]);
                    modal.setSaveButtonText(strings[1]);
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
            return;
        case CARTPARAM_COSTCENTER:
            getStrings([
                {key: 'error:costcentertitle', component: 'local_shopping_cart'},
                {key: 'error:costcentersdonotmatch', component: 'local_shopping_cart'},
                {key: 'ok', component: 'core'},
            ]).then(strings => {
                // eslint-disable-next-line promise/no-nesting
                ModalFactory.create({type: ModalFactory.types.SAVE_CANCEL}).then(modal => {
                    modal.setTitle(strings[0]);
                    modal.setBody(strings[1]);
                    modal.setSaveButtonText(strings[2]);
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
            return;
        case CARTPARAM_FULLYBOOKED:
            getStrings([
                {key: 'error:fullybookedtitle', component: 'local_shopping_cart'},
                {key: 'error:fullybooked', component: 'local_shopping_cart'},
                {key: 'ok', component: 'core'},
            ]).then(strings => {
                // eslint-disable-next-line promise/no-nesting
                ModalFactory.create({type: ModalFactory.types.SAVE_CANCEL}).then(modal => {
                    modal.setTitle(strings[0]);
                    modal.setBody(strings[1]);
                    modal.setSaveButtonText(strings[2]);

                    // Reload when OK button is clicked.
                    modal.getRoot().on(ModalEvents.save, function() {
                        window.location.reload();
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
            return;
        case CARTPARAM_ALREADYBOOKED:
            getStrings([
                {key: 'error:alreadybookedtitle', component: 'local_shopping_cart'},
                {key: 'error:alreadybooked', component: 'local_shopping_cart'},
                {key: 'ok', component: 'core'},
            ]).then(strings => {
                // eslint-disable-next-line promise/no-nesting
                ModalFactory.create({type: ModalFactory.types.SAVE_CANCEL}).then(modal => {
                    modal.setTitle(strings[0]);
                    modal.setBody(strings[1]);
                    modal.setSaveButtonText(strings[2]);

                    // Reload when OK button is clicked.
                    modal.getRoot().on(ModalEvents.save, function() {
                        window.location.reload();
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
            return;
        default:
            getStrings([
                {key: 'error:generalcarterror', component: 'local_shopping_cart'},
                {key: 'ok', component: 'core'},
            ]).then(strings => {
                // eslint-disable-next-line promise/no-nesting
                ModalFactory.create({type: ModalFactory.types.SAVE_CANCEL}).then(modal => {
                    modal.setBody(strings[0]);
                    modal.setSaveButtonText(strings[1]);
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
            return;
    }

}

/**
 *
 * @param {*} event
 */
function dealWithZeroPrice(event) {

    event.stopPropagation();
    event.preventDefault();

    confirmZeroPriceCheckoutModal(event.target);
}

/**
 * Start the timer.
 *
 * @param {integer} duration
 * @param {integer} display
 */
function startTimer(duration, display) {

    var timer = duration,
                minutes,
                seconds;
    interval = setInterval(function() {

        minutes = parseInt(timer / 60, 10);
        seconds = parseInt(timer % 60, 10);

        minutes = minutes < 10 ? "0" + minutes : minutes;
        seconds = seconds < 10 ? "0" + seconds : seconds;

        display.textContent = minutes + ":" + seconds;

        if (--timer < 0) {

            // We so the expiration time has already kicked in on the server.
            setTimeout(() => {
                reinit(0);
            }, 2000);

            // We don't actually need to make this call.
            // deleteAllItems();

            clearInterval(interval);
        }
    }, 1000);
}


/**
 * Initialize Timer.
 *
 * @param {integer} expirationtime
 * @param {integer} nowdate
 *
 */
function initTimer(expirationtime = null, nowdate = null) {

    const countdownelement = document.querySelector(SELECTORS.COUNTDOWN);

    if (!countdownelement || !nowdate) {
        return;
    }

    if (interval) {
        clearInterval(interval);
    }
    let delta = 0;
    let now = nowdate;
    if (expirationtime) {
        delta = (expirationtime - now);
    }
    if (delta <= 0) {
        delta = 0;
        countdownelement.classList.add("hidden");
    } else if (delta > 0) {
        countdownelement.classList.remove("hidden");
        startTimer(delta, countdownelement);
    }
}

/**
 *
 * @param {*} element
 */
function confirmZeroPriceCheckoutModal(element) {

    getStrings([
        {key: 'confirmzeropricecheckouttitle', component: 'local_shopping_cart'},
        {key: 'confirmzeropricecheckoutbody', component: 'local_shopping_cart'},
        {key: 'confirmzeropricecheckout', component: 'local_shopping_cart'}
    ]
    ).then(strings => {
        // eslint-disable-next-line promise/no-nesting
        ModalFactory.create({type: ModalFactory.types.SAVE_CANCEL}).then(modal => {

            modal.setTitle(strings[0]);
                modal.setBody(strings[1]);
                modal.setSaveButtonText(strings[2]);
                modal.getRoot().on(ModalEvents.save, function() {

                    const userid = element.dataset.userid;

                    if (userid) {
                        // The second parameter designs the payment method.
                        // In the cart, the constant PAYMENT_METHOD_CREDITS translates to 2.
                        confirmPayment(userid, 2);
                    }
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
 * Function to update the page in the nav bar.
 * @param {*} count
 */
function updateBadge(count) {

    const badge = document.querySelector(SELECTORS.BADGECOUNT);

    if (count > 0) {
        badge.innerHTML = count;
        badge.classList.remove('hidden');
    } else {
        badge.innerHTML = count;
        badge.classList.add('hidden');
    }
}

/**
 * Function to toggle the active state.
 * @param {*} button
 */
function toggleActiveButtonState(button = null) {

    let selector = '';
    let component = null;
    let area = null;
    let itemid = null;

    // If we have a button, we only look for this particular itemid.
    if (button) {

        // We'll find the right variables in the DOM.
        itemid = button.dataset.itemid;
        component = button.dataset.component;
        area = button.dataset.area;

        selector =
            'div'
            + '[data-itemid="' + itemid + '"]'
            + '[data-component="' + component + '"]'
            + '[data-area="' + area + '"]'
            + '[data-objecttable="local_shopping_cart"';
    } else {
        // As we might have more than one of these buttons, we always need to look for all of them in the document.
        // We will update for all the buttons we find.
        selector =
        'div'
        + '[data-objecttable="local_shopping_cart"';
    }

    const buttons = document.querySelectorAll(selector);

    // Make sure item is not yet in shopping cart. If so, add disabled class.
    let shoppingcart = document.querySelector(SELECTORS.CASHIERSCART);

    if (!shoppingcart) {
        shoppingcart = document.querySelector(SELECTORS.NAVBARCONTAINER);
    }

    buttons.forEach(addtocartbutton => {

        component = addtocartbutton.dataset.component;
        area = addtocartbutton.dataset.area;
        itemid = addtocartbutton.dataset.itemid;

        const cartitem = shoppingcart.querySelector('[id="item-' + component + '-' + area + '-' + itemid + '"]');

        if (cartitem) {

            addtocartbutton.classList.add('disabled');
        } else {

            addtocartbutton.classList.remove('disabled');
        }
    });
}

/**
 * Function to init Price Label and add Listener.
 * @param {*} userid
 */
export function initPriceLabel(userid) {

    // eslint-disable-next-line no-console
    console.log('initpricelabel');

    if (userid < 1) {
        userid = 0;
    }

    const checkbox = document.querySelector(SELECTORS.PRICELABELCHECKBOX);
    const installmentscheckbox = document.querySelector(SELECTORS.INSTALLMENTSCHECKBOX);

    if (checkbox && !checkbox.initialized) {
        checkbox.initialized = true;
        checkbox.addEventListener('change', event => {

            var installementsvalue = false;
            if (installmentscheckbox) {
                installementsvalue = installmentscheckbox.checked;
            }

            if (event.currentTarget.checked) {
                updateTotalPrice(userid, true, installementsvalue);
            } else {
                updateTotalPrice(userid, false, installementsvalue);
            }
        });
    }

    if (installmentscheckbox && !installmentscheckbox.initialized) {
        installmentscheckbox.initialized = true;

        // eslint-disable-next-line no-console
        console.log('add event listener to installment');
        installmentscheckbox.addEventListener('change', event => {

            // eslint-disable-next-line no-console
            console.log(event.currentTarget, event.currentTarget.checked);

            if (event.currentTarget.checked) {
                updateTotalPrice(userid, checkbox.checked, true);
            } else {
                updateTotalPrice(userid, checkbox.checked, false);
            }
        });
    }
}

/**
 * We need to know if we are on the cashier page to transform userid if necessary.
 * @param {integer} userid
 * @returns {integer}
 */
function transformUserIdForCashier(userid = null) {

    const oncashier = window.location.href.indexOf("cashier.php");

    if ((userid == CASHIERUSER || !(userid === 0 || userid === "0")) && oncashier > 0) {
        userid = CASHIERUSER;
    } else if (userid === null) {
        userid = 0;
    }

    if (!Number.isInteger(userid)) {
        userid = parseInt(userid);
    }

    return userid;
}

/**
 * Helper function to convert prices to number format before rendering.
 * @param {Object} data the data containing the price values
 */
function convertPricesToNumberFormat(data) {
    // Render all prices to 2 fixed decimals.
    if (data.price) {
        data.price = Number(data.price).toFixed(2);
    }
    if (data.initialtotal) {
        data.initialtotal = Number(data.initialtotal).toFixed(2);
    }
    if (data.initialtotal_net) {
        // eslint-disable-next-line camelcase
        data.initialtotal_net = Number(data.initialtotal_net).toFixed(2);
    }
    if (data.discount) {
        data.discount = Number(data.discount).toFixed(2);
    }
    if (data.deductible) {
        data.deductible = Number(data.deductible).toFixed(2);
    }
    if (data.credit) {
        data.credit = Number(data.credit).toFixed(2);
    }
    if (data.remainingcredit) {
        data.remainingcredit = Number(data.remainingcredit).toFixed(2);
    }
    if (data.price_net) {
        // eslint-disable-next-line camelcase
        data.price_net = Number(data.price_net).toFixed(2);
    }
    if (data.price_gross) {
        // eslint-disable-next-line camelcase
        data.price_gross = Number(data.price_gross).toFixed(2);
    }
    if (data.items) {
        for (var i = 0; i < data.items.length; i++) {
            if (data.items[i].price) {
                data.items[i].price = Number(data.items[i].price).toFixed(2);
            }
            if (data.items[i].price_gross) {
                // eslint-disable-next-line camelcase
                data.items[i].price_gross = Number(data.items[i].price_gross).toFixed(2);
            }
            if (data.items[i].price_net) {
                // eslint-disable-next-line camelcase
                data.items[i].price_net = Number(data.items[i].price_net).toFixed(2);
            }
        }
    }
}

/**
 * Add Accept terms listener to set the right class to payment region button.
 * @param {element} accepttermsbutton
 * @param {element} paymentbutton
 */
function addAcceptTermsListener(accepttermsbutton, paymentbutton) {

    accepttermsbutton.addEventListener('change', event => {
        if (event.currentTarget.checked) {
            paymentbutton.disabled = false;
        } else {
            paymentbutton.disabled = true;
        }
    });
}