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
import {addDiscountEvent} from 'local_shopping_cart/cashier';
import {showNotification} from 'local_shopping_cart/notifications';

import {
    get_strings as getStrings
        }
        from 'core/str';

export var interval = null;
export var visbilityevent = false;



// We need click listener on cart
// Disabled should not be done by a check on cart, but either it's loaded via php, OR it is added right away with the click.
// We should load the whole cart, including the price, with one function which always stays the same. (less css)




const SELECTORS = {
    SHOPPING_CART_ITEM: '[data-item="shopping_cart_item"]',
    NAVBARCONTAINER: '#nav-shopping_cart-popover-container .shopping-cart-items-container',
    TRASHCLASS: 'fa-trash-o',
    BADGECOUNT: '#nav-shopping_cart-popover-container div.count-container',
    COUNTDOWN: '#nav-shopping_cart-popover-container span.expirationdate',
    CASHIERSCART: '#shopping_cart-cashiers-cart',
    CHECKOUTCART: 'div.shopping-cart-checkout-items-container',
    PRICELABELCHECKBOX: '.sc_price_label input.usecredit-checkbox',
    PRICELABELAREA: '.sc_price_label',
};

export const buttoninit = (itemid, component) => {

    // Return all buttons with the add to cart functionality.
    const buttons =
    document.querySelectorAll(
        'button'
        + '[data-itemid="' + itemid + '"]'
        + '[data-component="' + component + '"]'
        + '[data-objecttable="local_shopping_cart"');

    buttons.forEach(addtocartbutton => {

        // We need to check all the buttons.
        toggleActiveButtonState(addtocartbutton);

        // We only ever initialize the button once.
        if (!addtocartbutton || addtocartbutton.dataset.initialized === 'true') {
            return;
        }
        addtocartbutton.dataset.initialized = 'true';

        // Add click eventlistern to oneself.
        addtocartbutton.addEventListener('click', event => {

        // If we find the disabled class, the click event is aborted.
        if (addtocartbutton.classList.contains('disabled')) {
            return;
        }
            event.preventDefault();
            event.stopPropagation();
            addItem(itemid, component);
        });
    });

    return;

    // If there is no itemid, we browse the whole document and init all buttons individually.
    if (!itemid) {
        const buttons = document.querySelectorAll(SELECTORS.SHOPPING_CART_ITEM);
        buttons.forEach(button => {
            const number = button.itemid;
            buttoninit(number, component);
        });
        return;
    }

    // We don't know how many instances of this peticular button are on the site. So we need to be agnostic.

    // First we get the button and delete the helper-span to secure js loading.
    const addtocartbutton = document.querySelector('#btn-' + component + '-' + itemid);

    // If we don't find the button, we abort.
    if (!addtocartbutton || addtocartbutton.dataset.initialized === 'true') {
        return;
    }
    addtocartbutton.dataset.initialized = 'true';

    // Make sure item is not yet in shopping cart. If so, add disabled class.
    let shoppingcart = document.querySelector('#shopping_cart-cashiers-cart');

    if (!shoppingcart) {
        shoppingcart = document.querySelector('#nav-shopping_cart-popover-container');
    }

    if (shoppingcart) {
        const cartitem = shoppingcart.querySelector('[id^="item-' + component + '-' + itemid + ']');
        if (cartitem) {
            addtocartbutton.classList.add('disabled');
        }
    }


};

/**
 *
 * @param {*} expirationdate
 */

 export const init = (expirationdate) => {

    // We might have more than one container.
    let containers = [];
    containers.push(document.querySelector(SELECTORS.NAVBARCONTAINER));
    containers.push(document.querySelector(SELECTORS.CHECKOUTCART));

    containers.filter(x => x !== null).forEach(container => {

        container.addEventListener('click', event => {

            // Decide the target of the click.
            const element = event.target;

            if (element.classList.contains(SELECTORS.TRASHCLASS))  {

                const userid = element.dataset.userid ? element.dataset.userid : 0;
                const component = element.dataset.component;
                const itemid = element.dataset.itemid;

                deleteItem(itemid, component, userid);
            }
        });
    });

    if (visbilityevent == false) {
        document.addEventListener("visibilitychange", function() {
            visbilityevent = true;
            if (document.visibilityState === 'visible') {
                reinit();
            }
        });
    }

    return;

    // countdownelement = document.querySelector('.expirationdate');
    initTimer(expirationdate);
    if (visbilityevent == false) {
        let items = document.querySelectorAll(SELECTORS.SHOPPING_CART_ITEM + ' .fa-trash-o');
        items.forEach(item => {
            addDeleteevent(item);
        });

        items = document.querySelectorAll(SELECTORS.SHOPPING_CART_ITEM + ' .fa-eur');
        items.forEach(item => {
            addDiscountEvent(item);
        });

    }
    updateTotalPrice();
};

/**
 * Function to reload the cart. We can pass on the certain component if we need to make sure that not only the cart is reloaded.
 * This is the case when adding or deleting a certain item and a special button has to be reset.
 * @param {*} userid
 */
export const reinit = (userid = 0) => {

    // eslint-disable-next-line no-console
    console.log('reinit', userid);

    Ajax.call([{
        methodname: "local_shopping_cart_get_shopping_cart_items",
        args: {
            userid
        },
        done: function(data) {
            let promises = [];
            promises.push(Templates.renderForPromise('local_shopping_cart/shopping_cart_items', data).then(({html, js}) => {

                // We know we will always find the Navbar, so we can do this right away.
                Templates.replaceNodeContents(SELECTORS.NAVBARCONTAINER, html, js);

                return true;
            }).catch((e) => {
                // eslint-disable-next-line no-console
                console.log(e);
            }));

            promises.push(Templates.renderForPromise('local_shopping_cart/shopping_cart_items', data).then(({html, js}) => {

                // We try to replace for Checkout page.
                Templates.replaceNodeContents(SELECTORS.CHECKOUTCART, html, js);

                return true;
            }).catch((e) => {
                // eslint-disable-next-line no-console
                console.log(e);
            }));
            Promise.all(promises).then(() => {

                clearInterval(interval);
                initTimer(data.expirationdate);

                updateBadge(data.count);

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

export const deleteAllItems = () => {
    Ajax.call([{
        methodname: "local_shopping_cart_delete_all_items_from_cart",
        args: {
        },
        done: function() {
            let item = document.querySelectorAll(SELECTORS.SHOPPING_CART_ITEM);
            item.forEach(item => {
                if (item) {
                    item.remove();
                }
            });

            updateTotalPrice();

            let itemcount1 = document.getElementById("countbadge");
            let itemcount2 = document.getElementById("itemcount");

            itemcount1.innerHTML = 0;
            itemcount2.innerHTML = 0;
            itemcount2.classList.add("hidden");

            // Make sure addtocartbutton active againe once the item is removed from the shopping cart.
            const addtocartbutton = document.querySelectorAll('[id^=btn-].disabled');
            addtocartbutton.forEach(btn => {
                if (btn) {
                    btn.classList.remove('disabled');
                }
            });
        },
        fail: function(ex) {
            // eslint-disable-next-line no-console
            console.log(ex);
        },
    }]);
};

export const deleteItem = (itemid, component, userid) => {

    Ajax.call([{
        methodname: "local_shopping_cart_delete_item",
        args: {
            'itemid': itemid,
            'component': component,
            'userid': userid
        },
        done: function() {

            reinit(itemid, component);
        },
        fail: function() {

            reinit();
        },
    }]);
};

export const addItem = (itemid, component) => {

    const oncashier = window.location.href.indexOf("cashier.php");

    let userid = 0;
    if (oncashier > 0) {
        userid = -1;
    }

    Ajax.call([{
        methodname: "local_shopping_cart_add_item",
        args: {
            'component': component,
            'itemid': itemid,
            'userid': userid
        },
        done: function(data) {
            data.component = component;
            data.itemid = itemid;
            data.userid = data.buyforuser; // For the mustache template, we need to obey structure.

            if (data.success != 1) {

                showNotification("Cart is full", 'danger');

                return;
            } else if (data.success == 1) {

                showNotification(data.itemname + " added to cart", 'success');

                // If we are on the cashier page, we add the possiblity to add a discount to the cart items.
                const oncashier = window.location.href.indexOf("cashier.php");
                if (oncashier) {
                    data.iscashier = true;
                }

                reinit(itemid, component);
            }

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
 */
export const updateTotalPrice = (userid = 0, usecredit = true) => {

    // eslint-disable-next-line no-console
    console.log('updatetotalprice');

    // We must make sure the checkbox is only once visible on the site.
    const checkbox = document.querySelector(SELECTORS.PRICELABELCHECKBOX);

    // eslint-disable-next-line no-console
    console.log('checked', checkbox.checked, usecredit);

    usecredit = usecredit ? 1 : 0;

    // eslint-disable-next-line no-console
    console.log('usecredit before ajax', usecredit);

    Ajax.call([{
        methodname: "local_shopping_cart_get_price",
        args: {
            userid,
            usecredit
        },
        done: function(data) {

             // We take the usecredit value we receive from the server.
             if (data.usecredit == 1) {
                data.usecreditvalue = 'checked';
            } else {
                data.usecreditvalue = '';
            }

            // eslint-disable-next-line no-console
            console.log(data, data.usecreditvalue);

            data.checkboxid = Math.random().toString(36).slice(2, 5);

            const labelareas = document.querySelectorAll(SELECTORS.PRICELABELAREA);

            // eslint-disable-next-line no-console
            console.log(labelareas);

            Templates.renderForPromise('local_shopping_cart/price_label', data).then(({html, js}) => {

                Templates.replaceNodeContents(SELECTORS.PRICELABELAREA, html, js);

                return true;
            }).catch((e => {
                // eslint-disable-next-line no-console
                console.log(e);
            }));

            let paymentbutton = document.querySelector(".shopping_cart_payment_region button");

            if (paymentbutton) {

                const price = data.price;
                const currency = data.currency;

                // eslint-disable-next-line no-console
                console.log("paymentbutton", price, currency);

                paymentbutton.dataset.cost = price + " " + currency;

                if (price == 0) {
                    // eslint-disable-next-line no-console
                    console.log('price is 0');
                    paymentbutton.addEventListener('click', dealWithZeroPrice);
                } else {
                    // eslint-disable-next-line no-console
                    console.log('price is not 0');
                    paymentbutton.removeEventListener('click', dealWithZeroPrice);
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
 *
 * @param {*} event
 */
function dealWithZeroPrice(event) {

    // eslint-disable-next-line no-console
    console.log('onlymyclickcounts');

        event.stopPropagation();
        event.preventDefault();
        // eslint-disable-next-line no-console
        console.log('onlymyclickcounts', event.target);

        confirmZeroPriceCheckoutModal(event.target);
}

/**
 * Delete Event.
 * @param {HTMLElement} item
 * @param {int} userid
 */
function addDeleteevent(item, userid = 0) {
    if (userid !== 0) {
        item.dataset.userid = '' + userid;
    }
    item.addEventListener('click', deleteEvent);
}

/**
 * Function called in listener.
 */
function deleteEvent() {
        const item = this;
        // eslint-disable-next-line no-console
        console.log('item', item);
        // Item comes as #item-booking-213123.
        const itemid = item.dataset.itemid;
        const component = item.dataset.component;
        let userid = item.dataset.userid;
        if (!userid) {
            userid = 0;
        }
        deleteItem(itemid, component, userid);
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
            deleteAllItems();

            clearInterval(interval);
        }
    }, 1000);
}


/**
 * Initialize Timer.
 *
 * @param {integer} expirationdate
 *
 */
function initTimer(expirationdate = null) {

    const countdownelement = document.querySelector(SELECTORS.COUNTDOWN);
    if (interval) {
        clearInterval(interval);
    }
    let delta = 0;
    let now = Date.now('UTC');
    now = (new Date()).getTime() / 1000;
    if (expirationdate) {
        delta = (expirationdate - now);
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

        ModalFactory.create({type: ModalFactory.types.SAVE_CANCEL}).then(modal => {

            modal.setTitle(strings[0]);
                modal.setBody(strings[1]);
                modal.setSaveButtonText(strings[2]);
                modal.getRoot().on(ModalEvents.save, function() {

                    const userid = element.dataset.userid;

                    // eslint-disable-next-line no-console
                    console.log(userid);

                    if (userid) {
                        confirmPayment(userid);
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

    // eslint-disable-next-line no-console
    console.log('toggleActiveButtonState');

    let selector = '';
    let component = null;
    let itemid = null;

    // If we have a button, we only look for this particular itemid.
    if (button) {

        // We'll find the right variables in the DOM.
        itemid = button.dataset.itemid;
        component = button.dataset.component;

        selector =
            'button'
            + '[data-itemid="' + itemid + '"]'
            + '[data-component="' + component + '"]'
            + '[data-objecttable="local_shopping_cart"';
    } else {
        // As we might have more than one of these buttons, we always need to look for all of them in the document.
        // We will update for all the buttons we find.
        selector =
        'button'
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
        itemid = addtocartbutton.dataset.itemid;

        const cartitem = shoppingcart.querySelector('[id^="item-' + component + '-' + itemid + '"]');

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

    if (userid < 1) {
        userid = 0;
    }

    const checkbox = document.querySelector(SELECTORS.PRICELABELCHECKBOX);

    if (checkbox) {
        checkbox.addEventListener('change', event => {

            // eslint-disable-next-line no-console
            console.log(event);

            if (event.currentTarget.checked) {
                updateTotalPrice(userid, true);
            } else {
                updateTotalPrice(userid, false);
            }
        });
    }
}