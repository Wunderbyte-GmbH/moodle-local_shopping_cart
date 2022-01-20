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

export var interval = null;
export const buttoninit = (id, component) => {

    // eslint-disable-next-line no-console
    console.log('initialized', id, component);

    // First we get the button and delete the helper-span to secure js loading.
    const addtocartbutton = document.querySelector('#btn-' + component + '-' + id);

    // If we don't find the button, we abort.
    if (!addtocartbutton) {
        return;
    }

    // We remove the backup span element, as we don't need it anymore.
    addtocartbutton.querySelector('.loadJavascript').remove();
    // We show the potentially hidden second span element with the button text.
    const spanlabel = addtocartbutton.querySelector('span');
    if (spanlabel) {
        spanlabel.classList.remove('hidden');
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
 * Gets called from mustache template.
 *
 */
 export const init = () => {
    initTimer();
    document.querySelectorAll('.fa-trash-o').forEach(item => {
        addDeleteevent(item);
    });

};

export const deleteAllItems = () => {
    Ajax.call([{
        methodname: "delete_all_items_from_cart",
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
        fail: function() {
        },
    }]);
};

export const deleteItem = (id, component) => {
    Ajax.call([{
        methodname: "local_shopping_cart_delete_item",
        args: {
            'itemid': id,
            'component': component
        },
        done: function() {
            // eslint-disable-next-line no-console
            console.log(id);
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
            itemcount2.innerHTML = itemcount2.innerHTML == 0 ? itemcount2.classList.add("hidden") : itemcount2.innerHTML;
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
            Templates.renderForPromise('local_shopping_cart/shopping_cart_item', data).then(({html}) => {
                let lastElem = document.getElementById('litotalprice');
                lastElem.insertAdjacentHTML('beforeBegin', html);
                document.getElementById("countbadge").innerHTML++;
                const badge = document.getElementById("itemcount");
                badge.innerHTML = (parseInt(badge.innerHTML) || 0) + 1;
                badge.classList.remove('hidden');
                let total = document.getElementById('totalprice');
                total.innerHTML = (parseInt(total.innerHTML) || 0) + parseInt(data.price);
                let item = document.querySelector('#item-' + component + '-' + data.itemid + ' .fa-trash-o');
                addDeleteevent(item);
                clearInterval(interval);
                setExpirationDate(data.expirationdate);
                initTimer();

                // Make sure addtocartbutton is disabled once the item is in the shopping cart.
                const addtocartbutton = document.querySelector('#btn-' + component + '-' + data.itemid);
                if (addtocartbutton) {
                    addtocartbutton.classList.add('disabled');
                }
            });
        },
        fail: function(ex) {
            // eslint-disable-next-line no-console
            console.log('error', ex);
        }
    }], true);
};

/**
 * Delete Event.
 * @param {*} item
 */
function addDeleteevent(item) {
    item.addEventListener('click', event => {
        event.preventDefault();
        event.stopPropagation();

        // eslint-disable-next-line no-console
        console.log('delete clicked', item.dataset.id);

        let idarray = item.dataset.id.split('-');

        let id = idarray.pop();
        let component = idarray.pop();
        deleteItem(id, component);
    });
}



/**
 * Start the timer.
 * @param {bool} flag
 * @param {int} duration
 * @param {int} display
 */
function startTimer(flag, duration, display) {

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
            console.log("delete");
            clearInterval(interval);
            timer = 3000;
            display.classList.add("hidden");
        }
        if (flag) {
            display.classList.remove("hidden");
        }
        if (!flag) {
            timer = 0;
            clearInterval(interval);
        }
    }, 1000);
}


/**
 * Set the timer.
 */
function initTimer() {
    document.querySelectorAll('.expirationdate').forEach(timer => {
        let delta = 0;
        let now = Date.now('UTC');
        now = (new Date()).getTime() / 1000;
        delta = (timer.dataset.expirationdate - now);
        if (delta < 0) {
            delta = 0;
        }
        startTimer(1, delta, timer);
    });
}

/**a
 * Set dataset from timer.
 * @param {int} expirationdate
 */
function setExpirationDate(expirationdate) {
    document.querySelectorAll('.expirationdate').forEach(timer => {
        timer.dataset.expirationdate = expirationdate;
    });
}
