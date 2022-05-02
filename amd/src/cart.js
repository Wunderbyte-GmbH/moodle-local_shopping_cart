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
import Notification from 'core/notification';

import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';

import {confirmPayment} from 'local_shopping_cart/cachier';

import {
    get_strings as getStrings
        }
        from 'core/str';

export var countdownelement = null;
export var interval = null;
export var visbilityevent = false;

export const reloadAllButtons = () => {
    const addtocartbuttons = document.querySelectorAll('[id^=btn-]');
    addtocartbuttons.forEach(button => {
        button.classList.remove('disabled');
    });
};

export const buttoninit = (id, component) => {

    // If there is no id, we browse the whole document and init all buttons individually.
    if (!id) {

        const buttons = document.querySelectorAll("[id^='btn-" + component + "']");

        buttons.forEach(button => {
            // We have to get only the last part of the id, the number.
            const number = button.id.split(/[\s-]+/).pop();
            buttoninit(number, component);
        });
        return;
    }

    // First we get the button and delete the helper-span to secure js loading.
    const addtocartbutton = document.querySelector('#btn-' + component + '-' + id);

    // If we don't find the button, we abort.
    if (!addtocartbutton
        || addtocartbutton.dataset.initialized) {
        return;
    }

    // Make sure item is not yet in shopping cart. If so, add disabled class.


    let shoppingcart = document.querySelector('#shopping_cart-cashiers-cart');

    if (!shoppingcart) {
        shoppingcart = document.querySelector('#nav-shopping_cart-popover-container');
    }

    if (shoppingcart) {
        const cartitem = shoppingcart.querySelector('#item-' + component + '-' + id);
        if (cartitem) {
            addtocartbutton.classList.add('disabled');
        }
    }
    // Add click eventlistern to oneself.
    addtocartbutton.addEventListener('click', event => {

         // eslint-disable-next-line no-console
         console.log('button clicked', id);

        // If we find the disabled class, the click event is aborted.
        if (addtocartbutton.classList.contains('disabled')) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        addItem(id, component);
    });

    addtocartbutton.dataset.initialized = true;
};

/**
 *
 * @param {*} expirationdate
 */

 export const init = (expirationdate) => {
    countdownelement = document.querySelector('.expirationdate');
    initTimer(expirationdate);
    if (visbilityevent == false) {
        let items = document.querySelectorAll('[id^=item-] .fa-trash-o');
        items.forEach(item => {
            addDeleteevent(item);
        });
        document.addEventListener("visibilitychange", function() {
            visbilityevent = true;
            if (document.visibilityState === 'visible') {
                reinit();
            }
        });
    }
    updateTotalPrice();
};

export const reinit = () => {
    reloadAllButtons();
    Ajax.call([{
        methodname: "local_shopping_cart_get_shopping_cart_items",
        args: {
        },
        done: function(data) {
            Templates.renderForPromise('local_shopping_cart/shopping_cart_items', data).then(({html}) => {
                document.querySelector('.shopping-cart-items').remove();
                let container = document.querySelector('#nav-shopping_cart-popover-container .shopping-cart-items-container');
                container.insertAdjacentHTML('afterbegin', html);
                data.items.forEach(item => {
                    buttoninit(item.itemid, item.itemcomponent);
                });
                let deleteaction = document.querySelectorAll('.fa-trash-o');
                deleteaction.forEach(item => {
                    addDeleteevent(item);
                });
                let itemcount = document.getElementById("itemcount");
                itemcount.innerHTML = data.count;
                document.getElementById("countbadge").innerHTML = data.count;
                if (data.count > 0) {
                    itemcount.classList.remove("hidden");
                } else {
                    itemcount.classList.add("hidden");
                }
                initTimer(data.expirationdate);

                return true;
            }).catch((e) => {
                // eslint-disable-next-line no-console
                console.log(e);
            });
        },
        fail: function(ex) {
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
            let item = document.querySelectorAll('[id^=item-]');
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

export const deleteItem = (id, component, userid) => {

    Ajax.call([{
        methodname: "local_shopping_cart_delete_item",
        args: {
            'itemid': id,
            'component': component,
            'userid': userid
        },
        done: function() {

            // We might display the item more often than once.
            let items = document.querySelectorAll('#item-' + component + '-' + id);

            items.forEach(item => {
                if (item) {
                    item.remove();
                }
            });

            updateTotalPrice(userid);

            let itemcount1 = document.getElementById("countbadge");
            let itemcount2 = document.getElementById("itemcount");

            itemcount1.innerHTM = itemcount1.innerHTML > 0 ? itemcount1.innerHTML -= 1 : itemcount1.innerHTML;
            itemcount2.innerHTML = itemcount2.innerHTML > 0 ? itemcount2.innerHTML -= 1 : itemcount1.innerHTML;

            // If we have only one item left, we set back the expiration date.
            if (itemcount2.innerHTML == 0) {
                itemcount2.classList.add("hidden");

                // We clear the countdown and set back the timer.
                clearInterval(interval);
                initTimer();
            }

            // Make sure addtocartbutton active againe once the item is removed from the shopping cart.
            const addtocartbutton = document.querySelector('#btn-' + component + '-' + id);
            if (addtocartbutton) {
                addtocartbutton.classList.remove('disabled');
                buttoninit(id, component);
            }
        },
        fail: function(ex) {
            // eslint-disable-next-line no-console
            console.log(id, ex);
            let item = document.querySelector('#item-' + component + '-' + id);
            if (item) {
                item.remove();
                let itemcount1 = document.getElementById("countbadge");
                let itemcount2 = document.getElementById("itemcount");
                itemcount1.innerHTML = itemcount1.innerHTML > 0 ? itemcount1.innerHTML -= 1 : itemcount1.innerHTML;
                itemcount2.innerHTML = itemcount2.innerHTML > 0 ? itemcount2.innerHTML -= 1 : itemcount1.innerHTML;
                itemcount2.innerHTML = itemcount2.innerHTML == 0 ? itemcount2.classList.add("hidden") : itemcount2.innerHTML;
                let itemprice = item.dataset.price;

                // eslint-disable-next-line no-console
                console.log('itemprice', itemprice);

                updateTotalPrice(userid);
            }
        },
    }]);
};

export const addItem = (id, component) => {

    const oncashier = window.location.href.indexOf("cashier.php");

    let userid = 0;
    if (oncashier > 0) {
        userid = -1;
    }

    Ajax.call([{
        methodname: "local_shopping_cart_add_item",
        args: {
            'component': component,
            'itemid': id,
            'userid': userid
        },
        done: function(data) {
            data.component = component;
            data.id = id;
            data.userid = data.buyforuser; // For the mustache template, we need to obey structure.

            if (data.success != 1) {
                Notification.addNotification({
                    message: "Cart is full",
                    type: "danger"
                });
                setTimeout(() => {
                    let notificationslist = document.querySelectorAll('#user-notifications div.alert.alert-danger');
                    const notificatonelement = notificationslist[notificationslist.length - 1];
                    notificatonelement.remove();
                }, 5000);
                return;
            } else if (data.success == 1) {
                Notification.addNotification({
                    message: data.itemname + " added to cart",
                    type: "success"
                });

                setTimeout(() => {
                    let notificationslist = document.querySelectorAll('#user-notifications div.alert.alert-success');

                    const notificatonelement = notificationslist[notificationslist.length - 1];

                    notificatonelement.remove();
                }, 5000);

                Templates.renderForPromise('local_shopping_cart/shopping_cart_item', data).then(({html}) => {
                    let lastElements = document.querySelectorAll("[id^='liinitialtotal']");
                    lastElements.forEach(lastElem => {

                        // eslint-disable-next-line no-console
                        console.log('found li', lastElem);

                        // If we buy for a user, we only want to interact with the cashiers section.
                        if ((data.buyforuser == 0)
                            || (lastElem.id === "liinitialtotal_cashier")) {
                            lastElem.insertAdjacentHTML('beforeBegin', html);
                        }
                    });

                    // Make sure addtocartbutton is disabled once the item is in the shopping cart.
                    const addtocartbutton = document.querySelector('#btn-' + component + '-' + data.itemid);
                    if (addtocartbutton) {
                        addtocartbutton.classList.add('disabled');
                        addtocartbutton.removeEventListener('click', deleteEvent);
                    }

                    let items = document.querySelectorAll('#item-' + component + '-' + data.itemid + ' .fa-trash-o');
                    items.forEach(item => {
                        addDeleteevent(item, data.userid);
                    });

                    updateTotalPrice(data.userid);

                    // If we buy for a user, we don't have to do the navbar stuff below.
                    if (data.buyforuser != 0) {
                        return;
                    }
                    document.getElementById("countbadge").innerHTML++;
                    const badge = document.getElementById("itemcount");
                    badge.innerHTML = (parseInt(badge.innerHTML) || 0) + 1;
                    badge.classList.remove('hidden');
                    updateTotalPrice(data.userid);
                    clearInterval(interval);
                    initTimer(data.expirationdate);
                    return;
                }).catch((e) => {
                    // eslint-disable-next-line no-console
                    console.log(e);
                });
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

    // First, get the state of usecredit.
    const oncashier = window.location.href.indexOf("cashier.php");

    let labelareas = null;
    if (oncashier > 0) {
        labelareas = document.querySelectorAll('div.sc_price_label');
    } else {
        labelareas = document.querySelectorAll('li.sc_price_label');
    }

    labelareas.forEach(element => {

        // eslint-disable-next-line no-console
        console.log(element);

        // First we update the userid, if possible.
        userid = element.dataset.userid ? element.dataset.userid : userid;

        const checkbox = element.querySelector('input.usecredit-checkbox');

        if (checkbox) {
            if (checkbox.checked) {

                // eslint-disable-next-line no-console
                console.log('checked', checkbox.checked);
                usecredit = checkbox.checked;
            } else {
                usecredit = 0;
            }
        }
    });

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

            // eslint-disable-next-line no-console
            console.log(data);

            // We take the usecredit value we receive from the server.
            if (data.usecredit == 1) {
                data.usecreditclass = 'checked';
            } else {
                data.usecreditclass = '';
            }

            const initialtotal = data.initialtotal;

            let shoppingcart = document.querySelector('#shopping_cart-cashiers-cart');
            let cashierssection = null;
            let checkoutcart = null;
            let checkouttotals = null;

            if (!shoppingcart) {
                shoppingcart = document.querySelector('#nav-shopping_cart-popover-container');
                checkoutcart = document.querySelector('div.checkoutgrid.checkout');
                // eslint-disable-next-line no-console
                console.log('1', checkoutcart);
            } else {
                cashierssection = document.querySelector('#shopping_cart-cashiers-section');
            }

            let totals = [];
            if (cashierssection) {
                totals = cashierssection.querySelectorAll('.initialtotal');
            } else {
                // First we add the total price from navbar.
                totals = shoppingcart.querySelectorAll('.initialtotal');

                // If we are on the checkout site, we deal with the totals there separately.
                if (checkoutcart) {
                    checkouttotals = checkoutcart.querySelectorAll('.initialtotal');

                    if (checkouttotals) {
                        checkouttotals.forEach(total => {
                            total.innerHTML = initialtotal;
                        });
                    }
                }
            }

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

            // Run through the list of total prices and set them to the right one.
            totals.forEach(total => {
                total.innerHTML = initialtotal;
            });

            data.checkboxid = Math.random().toString(36).slice(2, 5);

            Templates.renderForPromise('local_shopping_cart/price_label', data).then(({html}) => {
                labelareas.forEach(element => {
                    // First, clean all children.
                    let child = element.lastElementChild;
                    while (child) {
                        element.removeChild(child);
                        child = element.lastElementChild;
                    }
                    element.insertAdjacentHTML("afterbegin", html);

                    const checkbox = element.querySelector('input.usecredit-checkbox');

                    if (checkbox) {
                        checkbox.addEventListener('change', event => {

                            // eslint-disable-next-line no-console
                            console.log(event);

                            updateTotalPrice(userid);
                        });
                    }

                });
                return true;
            }).catch((e => {
                // eslint-disable-next-line no-console
                console.log(e);
            }));

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
    if (userid != 0) {
        item.dataset.userid = userid;
    }
    item.addEventListener('click', deleteEvent);
}

/**
 * Function called in listener,
 * @param {HTMLElement} item
 */
function deleteEvent() {

        const item = this;
        // eslint-disable-next-line no-console
        console.log('item', item);
        // Item comes as #item-booking-213123.
        const idarray = item.dataset.id.split('-');

        // eslint-disable-next-line no-console
        console.log('idarray', idarray);
        // First pop gets the id.
        const id = idarray.pop();
        // Second pop gets the component.
        const component = idarray.pop();
        let userid = parseInt(item.dataset.userid);

        if (!userid) {
            userid = 0;
        }

        deleteItem(id, component, userid);
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