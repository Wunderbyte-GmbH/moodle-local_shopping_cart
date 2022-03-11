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
    if (!addtocartbutton) {
        return;
    }

    // Make sure item is not yet in shopping cart. If so, add disabled class.
    const shoppingcart = document.querySelector('#nav-shopping_cart-popover-container');
    if (shoppingcart) {
        const cartitem = shoppingcart.querySelector('#item-' + component + '-' + id);
        if (cartitem) {
            addtocartbutton.classList.add('disabled');
        }
    }
    // Add click eventlistern to oneself.
    addtocartbutton.addEventListener('click', event => {
        // If we find the disabled class, the click event is aborted.
        if (addtocartbutton.classList.contains('disabled')) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        addItem(id, component);
    });
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
                let container = document.querySelector('#nav-shopping_cart-popover-container .popover-region-content-container');
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
            let total = document.querySelectorAll('#totalprice');
            total.forEach(total => {
                total.innerHTML = 0;
            });
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

            let item = document.querySelectorAll('#item-' + component + '-' + id);
            let itemprice = item[0].dataset.price;
            item.forEach(item => {
                if (item) {
                    item.remove();
                }
            });
            let total = document.querySelectorAll('#totalprice');
            total.forEach(total => {
                total = total == "undefined" ? total = 0 : total;
                total.innerHTML -= itemprice;
            });
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
                let total = document.getElementById('totalprice');
                total = total == "undefined" ? total = 0 : total;
                total.innerHTML -= itemprice;
            }
        },
    }]);
};

export const addItem = (id, component) => {

    Ajax.call([{
        methodname: "local_shopping_cart_add_item",
        args: {
            'component': component,
            'itemid': id
        },
        done: function(data) {
            data.component = component;
            data.id = id;
            data.userid = data.buyforuser; // For the mustache template, we need to obey structure.

            if (data.success == 0) {
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
                    let lastElem = document.querySelectorAll("[id^='litotalprice']");
                    lastElem.forEach(lastElem => {
                        // If we buy for a user, we only want to interact with the cashiers section.
                        if ((data.buyforuser == 0)
                            || (lastElem.id === "litotalprice_cashier")) {
                            lastElem.insertAdjacentHTML('beforeBegin', html);
                        }
                    });

                    // Make sure addtocartbutton is disabled once the item is in the shopping cart.
                    const addtocartbutton = document.querySelector('#btn-' + component + '-' + data.itemid);
                    if (addtocartbutton) {
                        addtocartbutton.classList.add('disabled');
                    }
                    // If we buy for a user, we don't have to do the navbar stuff below.
                    if (data.buyforuser != 0) {
                        return;
                    }
                    document.getElementById("countbadge").innerHTML++;
                    const badge = document.getElementById("itemcount");
                    badge.innerHTML = (parseInt(badge.innerHTML) || 0) + 1;
                    badge.classList.remove('hidden');
                    let total = document.querySelectorAll('#totalprice');
                    total.forEach(total => {
                        total.innerHTML = (parseInt(total.innerHTML) || 0) + parseInt(data.price);
                    });
                    let items = document.querySelectorAll('#item-' + component + '-' + data.itemid + ' .fa-trash-o');
                    items.forEach(item => {
                        addDeleteevent(item);
                    });
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
 * Delete Event.
 * @param {HTMLElement} item
 */
function addDeleteevent(item) {
    item.addEventListener('click', event => {
        event.preventDefault();
        event.stopPropagation();
        // Item comes as #item-booking-213123.
        const idarray = item.dataset.id.split('-');
        // First pop gets the id.
        const id = idarray.pop();
        // Second pop gets the component.
        const component = idarray.pop();
        const userid = item.dataset.userid;
        deleteItem(id, component, userid);
    });
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

